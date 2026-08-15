<?php

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';
require_once dol_buildpath('/exportlistes/lib/exportlistes.lib.php');

/**
 * Hook implementation for ExportListes.
 *
 * Strategy: WYSIWYG client-side scraping.
 *
 * The export button is injected once on every Dolibarr list page via the
 * printFieldPreListTitle hook (which fires after print_barre_liste, so the
 * pagination select is already in the DOM). On click, embedded JavaScript
 * parses the rendered <table class="liste"> in place, collects exactly the
 * visible columns and the data rows currently on screen, then POSTs the
 * dataset to public/export.php which streams it back as CSV or XLSX.
 *
 * No SQL adapters, no session token, no contextpage mapping. Works on any
 * Dolibarr list (core or third-party module) without configuration.
 */
class ActionsExportlistes extends CommonHookActions
{
    /** @var DoliDB */
    public $db;

    /** @var array<string,mixed> */
    public $results = array();

    /** @var bool Set to true once the button HTML has been emitted on the page. */
    private $buttonInjected = false;

    /**
     * @param DoliDB $db Database handler.
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Inject the export button on every list page.
     *
     * Fires after print_barre_liste(), so the pagination .selectlimit control is
     * already in the DOM and the synchronous placement script works immediately.
     *
     * @param array        $parameters  Hook parameters.
     * @param CommonObject $object      Current object (unused).
     * @param string       $action      Current action (unused).
     * @param HookManager  $hookmanager Hook manager (unused).
     * @return int 0 to continue, never error.
     */
    public function printFieldPreListTitle($parameters, &$object, &$action, $hookmanager)
    {
        if ($this->buttonInjected) {
            return 0;
        }

        global $user, $langs;

        if (!exportlistes_is_module_enabled()) {
            return 0;
        }
        if (!exportlistes_user_can_export($user)) {
            return 0;
        }
        if (!exportlistes_is_list_context($parameters)) {
            return 0;
        }

        $showCsv  = (int) getDolGlobalInt('EXPORTLISTES_ENABLE_CSV', 1);
        $showXlsx = (int) getDolGlobalInt('EXPORTLISTES_ENABLE_XLSX', 1);
        if (!$showCsv && !$showXlsx) {
            return 0;
        }

        $langs->loadLangs(array('exportlistes@exportlistes', 'main'));

        $contextpage = exportlistes_detect_contextpage($parameters);
        if ($contextpage === '') {
            $contextpage = 'list';
        }

        $this->buttonInjected = true;
        $this->resprints      = $this->renderButton($contextpage, $showCsv, $showXlsx, $langs);

        return 0;
    }

    /**
     * Build the button HTML + the scraping/POST JavaScript.
     *
     * @param string    $contextpage Used only as filename prefix.
     * @param int       $showCsv
     * @param int       $showXlsx
     * @param Translate $langs
     * @return string
     */
    private function renderButton($contextpage, $showCsv, $showXlsx, $langs)
    {
        $exportUrl  = DOL_URL_ROOT.'/custom/exportlistes/public/export.php';
        $csrf       = function_exists('currentToken') ? currentToken() : (function_exists('newToken') ? newToken() : '');
        $btnLabel   = dol_escape_htmltag($langs->trans('ExportListButton'));
        $csvLabel   = dol_escape_htmltag($langs->trans('ExportCSV'));
        $xlsxLabel  = dol_escape_htmltag($langs->trans('ExportXLSX'));
        // For JS string literals we use json_encode with JSON_HEX_TAG so that
        // any embedded "</script>" sequences are rendered safe inside <script>.
        $jsFlags    = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;
        $emptyMsgJs = json_encode((string) $langs->trans('ExportListEmpty'), $jsFlags);
        $errorMsgJs = json_encode((string) $langs->trans('ExportListError'), $jsFlags);
        $contextJs  = json_encode((string) $contextpage, $jsFlags);
        $exportJs   = json_encode($exportUrl, $jsFlags);
        $csrfJs     = json_encode((string) $csrf, $jsFlags);

        $uid = 'exp_'.bin2hex(random_bytes(6));

        $out  = '<span id="'.$uid.'_wrap" class="exportlistes-wrap" style="display:none;position:relative;white-space:nowrap;vertical-align:middle">';

        if ($showCsv && $showXlsx) {
            $out .= '<button type="button" id="'.$uid.'_btn"';
            $out .= ' class="btn btnTitle" title="'.$btnLabel.'">';
            $out .= '<span class="fa fa-file-export btnTitle-icon"></span>';
            $out .= '<span class="fa fa-caret-down" style="font-size:.7em;margin-left:3px"></span>';
            $out .= '</button>';
            $out .= '<div id="'.$uid.'_menu"';
            $out .= ' style="display:none;position:absolute;right:0;top:calc(100% + 4px);z-index:9999;';
            $out .= 'background:#fff;border:1px solid rgba(0,0,0,.15);border-radius:6px;';
            $out .= 'box-shadow:0 4px 18px rgba(0,0,0,.13);min-width:170px;overflow:hidden">';
            $out .= '<a href="javascript:void(0)" data-fmt="csv" class="exportlistes-item"';
            $out .= ' style="display:flex;align-items:center;gap:9px;padding:11px 15px;';
            $out .= 'text-decoration:none;color:#222;border-bottom:1px solid #f0f0f0">';
            $out .= '<span class="fa fa-file-csv fa-fw" style="color:#16a34a;font-size:1.05em"></span>'.$csvLabel;
            $out .= '</a>';
            $out .= '<a href="javascript:void(0)" data-fmt="xlsx" class="exportlistes-item"';
            $out .= ' style="display:flex;align-items:center;gap:9px;padding:11px 15px;';
            $out .= 'text-decoration:none;color:#222">';
            $out .= '<span class="fa fa-file-excel fa-fw" style="color:#1d6f42;font-size:1.05em"></span>'.$xlsxLabel;
            $out .= '</a>';
            $out .= '</div>';
        } elseif ($showCsv) {
            $out .= '<a href="javascript:void(0)" id="'.$uid.'_btn" data-fmt="csv"';
            $out .= ' class="btn btnTitle exportlistes-single" title="'.$btnLabel.'">';
            $out .= '<span class="fa fa-file-csv btnTitle-icon" style="color:#16a34a"></span>';
            $out .= '</a>';
        } else {
            $out .= '<a href="javascript:void(0)" id="'.$uid.'_btn" data-fmt="xlsx"';
            $out .= ' class="btn btnTitle exportlistes-single" title="'.$btnLabel.'">';
            $out .= '<span class="fa fa-file-excel btnTitle-icon" style="color:#1d6f42"></span>';
            $out .= '</a>';
        }

        $out .= '</span>';

        // ------------------------------------------------------------------
        // Inline script (synchronous): place button + bind click + scrape DOM
        // ------------------------------------------------------------------
        $out .= '<script type="text/javascript">';
        $out .= '(function(){';
        $out .= 'var WRAP=document.getElementById("'.$uid.'_wrap");';
        $out .= 'if(!WRAP) return;';
        $out .= 'var EXPORT_URL='.$exportJs.';';
        $out .= 'var CSRF='.$csrfJs.';';
        $out .= 'var CTX='.$contextJs.';';
        $out .= 'var EMPTY_MSG='.$emptyMsgJs.';';
        $out .= 'var ERROR_MSG='.$errorMsgJs.';';

        // Place wrap before the pagination limit control, in the page header bar.
        $out .= 'function place(){';
        $out .= 'var sel=document.querySelector("select.selectlimit,input.selectlimit");';
        $out .= 'if(sel&&sel.parentNode){';
        $out .= 'sel.parentNode.insertBefore(WRAP,sel);';
        $out .= 'WRAP.style.display="inline-block";';
        $out .= 'WRAP.style.marginRight="6px";';
        $out .= 'return true;';
        $out .= '}';
        $out .= 'return false;';
        $out .= '}';
        $out .= 'if(!place()){';
        $out .= 'if(document.readyState==="loading"){';
        $out .= 'document.addEventListener("DOMContentLoaded",place);';
        $out .= '}else{setTimeout(place,50);}';
        $out .= '}';

        // Toggle dropdown menu (when both formats enabled).
        $out .= 'var MENU=document.getElementById("'.$uid.'_menu");';
        $out .= 'var TOGGLE=document.getElementById("'.$uid.'_btn");';
        $out .= 'if(MENU&&TOGGLE){';
        $out .= 'TOGGLE.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();MENU.style.display=(MENU.style.display==="block"?"none":"block");});';
        $out .= 'document.addEventListener("click",function(e){if(MENU.style.display==="block"&&!WRAP.contains(e.target))MENU.style.display="none";});';
        $out .= '}';

        // Bind format triggers.
        $out .= 'var items=WRAP.querySelectorAll("[data-fmt]");';
        $out .= 'for(var i=0;i<items.length;i++){';
        $out .= '(function(el){el.addEventListener("click",function(e){e.preventDefault();if(MENU)MENU.style.display="none";doExport(el.getAttribute("data-fmt"));});})(items[i]);';
        $out .= '}';

        // ------- Scrape: locate target table ----------------------------
        // Strategy: find any zebra-striped data row (tr.oddeven is Dolibarr's
        // standard data-row class) and walk up to its containing <table>.
        // Fallback: any table.liste in the document.
        $out .= 'function findTable(){';
        $out .= 'var dataRow=document.querySelector("table tr.oddeven, table tr.impair, table tr.pair");';
        $out .= 'if(dataRow){var t=dataRow;while(t&&t.tagName!=="TABLE") t=t.parentNode;if(t) return t;}';
        $out .= 'var f=document.querySelector("form#searchFormList,form[name=formfilteraction]");';
        $out .= 'if(f){var t=f.querySelector("table.liste,table.noborder,table.tagtable");if(t) return t;}';
        $out .= 'return document.querySelector("table.liste,table.noborder.liste,table.tagtable.liste,div.div-table-responsive table");';
        $out .= '}';

        // ------- Scrape: extract headers + rows -------------------------
        $out .= 'function gather(){';
        $out .= 'var t=findTable();';
        $out .= 'if(!t) return null;';
        $out .= 'var allTr=Array.prototype.slice.call(t.querySelectorAll("tr"));';

        // Locate first data row (Dolibarr standard: oddeven / impair / pair).
        $out .= 'var firstDataIdx=-1;';
        $out .= 'for(var i=0;i<allTr.length;i++){';
        $out .= 'var c=" "+(allTr[i].className||"")+" ";';
        $out .= 'if(c.indexOf(" oddeven ")>=0||c.indexOf(" impair ")>=0||c.indexOf(" pair ")>=0){firstDataIdx=i;break;}';
        $out .= '}';

        // Header row detection (1st pass): closest row above the data block
        // with visible label text and no search/filter inputs.
        $out .= 'var headerRow=null,headerIdx=-1;';
        $out .= 'if(firstDataIdx>0){';
        $out .= 'for(var i=firstDataIdx-1;i>=0;i--){';
        $out .= 'var tr=allTr[i];';
        $out .= 'var cls=" "+(tr.className||"")+" ";';
        $out .= 'if(cls.indexOf(" liste_titre_filter ")>=0||cls.indexOf(" liste_titre_search ")>=0||cls.indexOf(" liste_titre_add ")>=0) continue;';
        $out .= 'if(tr.querySelector("input[type=text],input[type=search],input[type=number],input[type=date],input[type=datetime-local],textarea")) continue;';
        $out .= 'var cells=tr.children;';
        $out .= 'if(!cells||cells.length===0) continue;';
        $out .= 'var hasLabel=false;';
        $out .= 'for(var k=0;k<cells.length;k++){if((cells[k].textContent||"").trim().length>0){hasLabel=true;break;}}';
        $out .= 'if(hasLabel){headerRow=tr;headerIdx=i;break;}';
        $out .= '}';
        $out .= '}';

        // Header row detection (2nd pass): any row marked liste_titre that is
        // not a filter/search/add row.
        $out .= 'if(!headerRow){';
        $out .= 'for(var i=0;i<allTr.length;i++){';
        $out .= 'var tr=allTr[i];';
        $out .= 'var cls=" "+(tr.className||"")+" ";';
        $out .= 'if(cls.indexOf(" liste_titre_filter ")>=0||cls.indexOf(" liste_titre_search ")>=0||cls.indexOf(" liste_titre_add ")>=0) continue;';
        $out .= 'if(tr.querySelectorAll("th").length>0){headerRow=tr;headerIdx=i;break;}';
        $out .= 'if(cls.indexOf(" liste_titre ")>=0){headerRow=tr;headerIdx=i;break;}';
        $out .= '}';
        $out .= '}';

        // Fallback: if a data row was found, build synthetic headers from cell
        // count so the export still works (user gets generic Col 1, Col 2... headers).
        $out .= 'if(!headerRow&&firstDataIdx>=0){';
        $out .= 'var dataCells=allTr[firstDataIdx].children;';
        $out .= 'var headers=[];var keep=[];';
        $out .= 'for(var c=0;c<dataCells.length;c++){';
        $out .= 'var dc=dataCells[c];';
        $out .= 'if(dc.querySelector("input[type=checkbox]")){keep.push(false);continue;}';
        $out .= 'var st=window.getComputedStyle(dc);';
        $out .= 'if(st.display==="none"||st.visibility==="hidden"){keep.push(false);continue;}';
        $out .= 'keep.push(true);headers.push("Col "+(headers.length+1));';
        $out .= '}';
        $out .= 'return collectRows(allTr,firstDataIdx-1,headers,keep);';
        $out .= '}';

        $out .= 'if(!headerRow) return null;';

        $out .= 'var headers=[];var keep=[];';
        $out .= 'var headerCells=headerRow.children;';
        $out .= 'for(var c=0;c<headerCells.length;c++){';
        $out .= 'var th=headerCells[c];';
        // Skip mass-action checkbox column.
        $out .= 'if(th.querySelector("input[type=checkbox]")){keep.push(false);continue;}';
        // Skip hidden columns (display:none = unchecked column in Dolibarr).
        $out .= 'var st=window.getComputedStyle(th);';
        $out .= 'if(st.display==="none"||st.visibility==="hidden"){keep.push(false);continue;}';
        // Skip columns with empty header (typically the trailing action column).
        $out .= 'var label=cleanText(th);';
        $out .= 'if(label===""){keep.push(false);continue;}';
        $out .= 'keep.push(true);headers.push(label);';
        $out .= '}';

        $out .= 'return collectRows(allTr,headerIdx,headers,keep);';
        $out .= '}';

        // ------- Scrape: collect data rows after a given start index ----
        $out .= 'function collectRows(allTr,startIdx,headers,keep){';
        $out .= 'var rows=[];';
        $out .= 'for(var r=startIdx+1;r<allTr.length;r++){';
        $out .= 'var tr=allTr[r];';
        $out .= 'var cls=" "+(tr.className||"")+" ";';
        $out .= 'if(cls.indexOf(" liste_titre ")>=0) continue;';
        $out .= 'if(cls.indexOf(" liste_total ")>=0||cls.indexOf(" liste_sub_total ")>=0) continue;';
        $out .= 'if(tr.querySelector("input[type=text],input[type=search],input[type=number],input[type=date],input[type=datetime-local],textarea")) continue;';
        $out .= 'var cells=tr.children;';
        $out .= 'if(cells.length===0) continue;';
        // Skip pagination/footer single-cell rows.
        $out .= 'if(cells.length===1&&cells[0].getAttribute&&cells[0].getAttribute("colspan")) continue;';
        $out .= 'var row=[];var idx=0;var hasContent=false;';
        $out .= 'for(var c=0;c<cells.length;c++){';
        $out .= 'if(idx>=keep.length) break;';
        $out .= 'if(!keep[idx]){idx++;continue;}';
        $out .= 'var v=cleanText(cells[c]);';
        $out .= 'if(v!=="") hasContent=true;';
        $out .= 'row.push(v);idx++;';
        $out .= '}';
        $out .= 'while(row.length<headers.length) row.push("");';
        $out .= 'if(row.length>headers.length) row=row.slice(0,headers.length);';
        $out .= 'if(hasContent) rows.push(row);';
        $out .= '}';
        $out .= 'return {headers:headers,rows:rows};';
        $out .= '}';

        // ------- Scrape: clean cell text --------------------------------
        $out .= 'function cleanText(node){';
        $out .= 'var clone=node.cloneNode(true);';
        // Strip form controls.
        $out .= 'var bad=clone.querySelectorAll("input,select,textarea,button,script,style");';
        $out .= 'for(var i=0;i<bad.length;i++){if(bad[i].parentNode) bad[i].parentNode.removeChild(bad[i]);}';
        // Replace <br> with newlines before reading textContent.
        $out .= 'var brs=clone.querySelectorAll("br");';
        $out .= 'for(var i=0;i<brs.length;i++){brs[i].parentNode.replaceChild(document.createTextNode(" "),brs[i]);}';
        // For <img>, fall back to alt/title text.
        $out .= 'var imgs=clone.querySelectorAll("img");';
        $out .= 'for(var i=0;i<imgs.length;i++){var alt=imgs[i].getAttribute("alt")||imgs[i].getAttribute("title")||"";if(alt) imgs[i].parentNode.replaceChild(document.createTextNode(alt),imgs[i]);else if(imgs[i].parentNode) imgs[i].parentNode.removeChild(imgs[i]);}';
        $out .= 'var s=clone.textContent||clone.innerText||"";';
        $out .= 'return s.replace(/\\s+/g," ").trim();';
        $out .= '}';

        // ------- POST dataset to export.php -----------------------------
        $out .= 'function doExport(fmt){';
        $out .= 'var data=null;try{data=gather();}catch(e){alert(ERROR_MSG);return;}';
        $out .= 'if(!data||!data.headers.length||!data.rows.length){alert(EMPTY_MSG);return;}';
        $out .= 'var f=document.createElement("form");';
        $out .= 'f.method="POST";f.action=EXPORT_URL;f.style.display="none";';
        $out .= 'function add(n,v){var i=document.createElement("input");i.type="hidden";i.name=n;i.value=v;f.appendChild(i);}';
        $out .= 'add("token",CSRF);add("format",fmt);add("contextpage",CTX);add("payload",JSON.stringify(data));';
        $out .= 'document.body.appendChild(f);f.submit();';
        $out .= 'setTimeout(function(){if(f.parentNode) f.parentNode.removeChild(f);},2000);';
        $out .= '}';

        $out .= '})();';
        $out .= '</script>';

        return $out;
    }
}
