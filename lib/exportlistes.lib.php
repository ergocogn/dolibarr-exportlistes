<?php
/* Copyright (C) 2026       ergoCogn sàrl
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 */

/**
 * Return true if ExportListes module is enabled.
 *
 * @return bool
 */
function exportlistes_is_module_enabled()
{
    global $conf;

    if (function_exists('isModEnabled')) {
        return (bool) isModEnabled('exportlistes');
    }

    return !empty($conf->exportlistes->enabled);
}

/**
 * Detect the Dolibarr list context page identifier (used as filename prefix).
 *
 * @param array $parameters Hook parameters.
 * @return string
 */
function exportlistes_detect_contextpage($parameters = array())
{
    $contextpage = GETPOST('contextpage', 'aZ');
    if ($contextpage) {
        return $contextpage;
    }

    if (!empty($parameters['context'])) {
        $contexts = explode(':', $parameters['context']);
        foreach ($contexts as $ctx) {
            if (substr($ctx, -4) === 'list') {
                return $ctx;
            }
        }
    }

    return '';
}

/**
 * True if this request is a list page suitable for the export button.
 *
 * @param array $parameters Hook parameters.
 * @return bool
 */
function exportlistes_is_list_context($parameters = array())
{
    $self = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
    if (strpos($self, '/list.php') === false && strpos($self, '_list.php') === false) {
        return false;
    }

    $contextpage = exportlistes_detect_contextpage($parameters);
    if (!$contextpage) {
        return true;
    }

    if (substr($contextpage, -4) === 'list') {
        return true;
    }

    return false;
}

/**
 * Return true if module export right is granted.
 *
 * @param User $user Current user.
 * @return bool
 */
function exportlistes_user_can_export($user)
{
    if (!empty($user->admin)) {
        return true;
    }

    if (is_object($user) && method_exists($user, 'hasRight')) {
        return (bool) $user->hasRight('exportlistes', 'use');
    }

    if (!empty($user->rights->exportlistes->use)) {
        return true;
    }

    return false;
}

/**
 * Return true if module admin right is granted.
 *
 * @param User $user Current user.
 * @return bool
 */
function exportlistes_user_can_admin($user)
{
    if (!empty($user->admin)) {
        return true;
    }

    if (is_object($user) && method_exists($user, 'hasRight')) {
        return (bool) $user->hasRight('exportlistes', 'admin');
    }

    if (!empty($user->rights->exportlistes->admin)) {
        return true;
    }

    return false;
}

/**
 * Validate a token submitted to the public export endpoint.
 *
 * @param string $submittedToken Token sent by the form.
 * @return bool
 */
function exportlistes_check_csrf_token($submittedToken)
{
    $submittedToken = (string) $submittedToken;
    if ($submittedToken === '') {
        return false;
    }

    $sessionTokens = array();
    if (function_exists('currentToken')) {
        $sessionTokens[] = (string) currentToken();
    }
    if (function_exists('newToken')) {
        $sessionTokens[] = (string) newToken();
    }
    if (!empty($_SESSION['newtoken'])) {
        $sessionTokens[] = (string) $_SESSION['newtoken'];
    }
    if (!empty($_SESSION['token'])) {
        $sessionTokens[] = (string) $_SESSION['token'];
    }

    $uniqueTokens = array();
    foreach ($sessionTokens as $known) {
        if ($known !== '' && !in_array($known, $uniqueTokens, true)) {
            $uniqueTokens[] = $known;
        }
    }

    foreach ($uniqueTokens as $known) {
        if (function_exists('hash_equals')) {
            if (hash_equals($known, $submittedToken)) {
                return true;
            }
        } elseif ($known === $submittedToken) {
            return true;
        }
    }

    return false;
}

/**
 * Normalize CSV delimiter to one usable byte for fputcsv.
 *
 * @param string $delimiter Configured delimiter.
 * @return string
 */
function exportlistes_normalize_csv_delimiter($delimiter)
{
    $delimiter = (string) $delimiter;
    if ($delimiter === '') {
        return ';';
    }

    return substr($delimiter, 0, 1);
}
