<?php
/* Copyright (C) 2026 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation file for module ExportListes.
 */
class modExportlistes extends DolibarrModules
{
    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct($db)
    {
        $this->db = $db;
        $this->numero = 106500;
        $this->rights_class = 'exportlistes';
        $this->family = 'tools';
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'Export filtered list results to CSV/XLSX without pagination limit';
        $this->version = '0.1.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'generic';

        $this->module_parts = array(
            'hooks' => array('all')
        );

        $this->dirs = array();
        $this->config_page_url = array('setup.php@exportlistes');
        $this->hidden = false;
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array('exportlistes@exportlistes');
        $this->phpmin = array(7, 4);
        $this->need_dolibarr_version = array(22, 0);

        $this->const = array(
            array('EXPORTLISTES_ENABLE_CSV', 'yesno', '1', 'Enable CSV export', 0, 'current'),
            array('EXPORTLISTES_ENABLE_XLSX', 'yesno', '1', 'Enable XLSX export', 0, 'current'),
            array('EXPORTLISTES_MAX_ROWS', 'integer', '100000', 'Maximum rows per export (0 = unlimited)', 0, 'current'),
            array('EXPORTLISTES_MAX_PAYLOAD_BYTES', 'integer', (string) (16 * 1024 * 1024), 'Maximum POST payload size in bytes (0 = unlimited)', 0, 'current'),
            array('EXPORTLISTES_CSV_DELIMITER', 'chaine', ';', 'CSV delimiter', 0, 'current'),
            array('EXPORTLISTES_CSV_BOM', 'yesno', '1', 'Add UTF-8 BOM to CSV', 0, 'current')
        );

        $this->rights = array();
        $r = 0;

        $this->rights[$r][0] = 1065001;
        $this->rights[$r][1] = 'Use list export button';
        $this->rights[$r][4] = 'use';
        $this->rights[$r][5] = '';
        $r++;

        $this->rights[$r][0] = 1065002;
        $this->rights[$r][1] = 'Configure export module';
        $this->rights[$r][4] = 'admin';
        $this->rights[$r][5] = '';
    }
}
