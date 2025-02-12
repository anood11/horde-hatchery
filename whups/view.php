<?php
/**
 * Copyright 2003-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author Jan Schneider <jan@horde.org>
 */

define('WHUPS_BASE', dirname(__FILE__));
require_once WHUPS_BASE . '/lib/base.php';

$actionID = Horde_Util::getFormData('actionID');
$id = Horde_Util::getFormData('ticket');
$filename = Horde_Util::getFormData('file');
$type = Horde_Util::getFormData('type');

// Get the ticket details first.
if (empty($id)) {
    exit;
}
$details = $whups_driver->getTicketDetails($id);
if (is_a($details, 'PEAR_Error')) {
    if ($details->code === 0) {
        // No permissions to this ticket.
        $url = Horde::url($registry->get('webroot', 'horde') . '/login.php', true);
        $url = Horde_Util::addParameter($url, 'url', Horde::selfUrl(true));
        header('Location: ' . $url);
        exit;
    } else {
        Horde::fatal($details->getMessage(), __FILE__, __LINE__);
    }
}

// Check permissions on this ticket.
if (!count(Whups::permissionsFilter($whups_driver->getHistory($id), 'comment', Horde_Perms::READ))) {
    Horde::fatal(sprintf(_("You are not allowed to view ticket %d."), $id), __FILE__, __LINE__);
}

if (empty($conf['vfs']['type'])) {
    Horde::fatal(_("The VFS backend needs to be configured to enable attachment uploads."), __FILE__, __LINE__);
}

require_once 'VFS.php';
$vfs = VFS::factory($conf['vfs']['type'], Horde::getDriverConfig('vfs'));
if (is_a($vfs, 'PEAR_Error')) {
    Horde::fatal($vfs, __FILE__, __LINE__);
} else {
    $data = $vfs->read(WHUPS_VFS_ATTACH_PATH . '/' . $id, $filename);
}
if (is_a($data, 'PEAR_Error')) {
    Horde::fatal(sprintf(_("Access denied to %s"), $filename), __FILE__, __LINE__);
}

/* Run through action handlers */
switch ($actionID) {
case 'download_file':
     $browser->downloadHeaders($filename, null, false, strlen($data));
     echo $data;
     exit;

case 'view_file':
    $mime_part = new Horde_Mime_Part();
    $mime_part->setType(Horde_Mime_Magic::extToMime($type));
    $mime_part->setContents($data);
    $mime_part->setName($filename);

    $viewer = Horde_Mime_Viewer::factory($mime_part);

    $ret = $viewer->render('full');
    reset($ret);
    $key = key($ret);

    if (strpos($ret[$key]['type'], 'text/html') !== false) {
        require WHUPS_BASE . '/templates/common-header.inc';
        echo $ret[$key]['data'];
        require $registry->get('templates', 'horde') . '/common-footer.inc';
    } else {
        $browser->downloadHeaders($ret[$key]['name'], $ret[$key]['type'], true, strlen($ret[$key]['data']));
        echo $ret[$key]['data'];
    }
    exit;
}
