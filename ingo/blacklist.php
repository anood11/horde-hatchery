<?php
/**
 * Blacklist script.
 *
 * Copyright 2002-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author Mike Cochrane <mike@graftonhall.co.nz>
 * @author Michael Slusarz <slusarz@horde.org>
 */

require_once dirname(__FILE__) . '/lib/base.php';

/* Redirect if blacklist is not available. */
if (!in_array(Ingo_Storage::ACTION_BLACKLIST, $_SESSION['ingo']['script_categories'])) {
    $notification->push(_("Blacklist is not supported in the current filtering driver."), 'horde.error');
    header('Location: ' . Horde::applicationUrl('filters.php', true));
    exit;
}

/* Get the backend. */
$scriptor = Ingo::loadIngoScript();

/* Determine if this scriptor supports mark-as-deleted. */
$have_mark = $scriptor && in_array(Ingo_Storage::ACTION_FLAGONLY, $scriptor->availableActions());

/* Get the blacklist object. */
$blacklist = &$ingo_storage->retrieve(Ingo_Storage::ACTION_BLACKLIST);
if (is_a($blacklist, 'PEAR_Error')) {
    $notification->push($blacklist);
    $blacklist = new Ingo_Storage_Blacklist();
}
$folder = $blacklist_folder = null;

/* Perform requested actions. */
$actionID = Horde_Util::getFormData('actionID');
switch ($actionID) {
case 'create_folder':
    $blacklist_folder = Ingo::createFolder(Horde_Util::getFormData('new_folder_name'));
    break;

case 'rule_update':
    switch (Horde_Util::getFormData('action')) {
    case 'delete':
        $folder = '';
        break;

    case 'mark':
        $folder = Ingo::BLACKLIST_MARKER;
        break;

    case 'folder':
        $folder = Horde_Util::getFormData('actionvalue');
        break;
    }

    if (($folder == Ingo::BLACKLIST_MARKER) && !$have_mark) {
        $notification->push("Not supported by this script generator.", 'horde.error');
    } else {
        $ret = $blacklist->setBlacklist(Horde_Util::getFormData('blacklist'));
        if (is_a($ret, 'PEAR_Error')) {
            $notification->push($ret, $ret->getCode());
        } else {
            $blacklist->setBlacklistFolder($folder);
            if (!$ingo_storage->store($blacklist)) {
                $notification->push(_("Error saving changes."), 'horde.error');
            } else {
                $notification->push(_("Changes saved."), 'horde.success');
            }

            if ($prefs->getValue('auto_update')) {
                /* This does its own $notification->push() on error: */
                Ingo::updateScript();
            }
        }

        /* Update the timestamp for the rules. */
        $_SESSION['ingo']['change'] = time();
    }

    break;
}

/* Create the folder listing. */
if (!isset($blacklist_folder)) {
    $blacklist_folder = $blacklist->getBlacklistFolder();
}
$field_num = $have_mark ? 2 : 1;
$folder_list = Ingo::flistSelect($blacklist_folder, 'filters', 'actionvalue',
                                 'document.filters.action[' . $field_num .
                                 '].checked=true');

/* Get the blacklist rule. */
$filters = &$ingo_storage->retrieve(Ingo_Storage::ACTION_FILTERS);
$bl_rule = $filters->findRule(Ingo_Storage::ACTION_BLACKLIST);

Ingo::prepareMenu();
Ingo::addNewFolderJs();
$title = _("Blacklist Edit");
require INGO_TEMPLATES . '/common-header.inc';
require INGO_TEMPLATES . '/menu.inc';
require INGO_TEMPLATES . '/blacklist/blacklist.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';
