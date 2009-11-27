<?php
/**
 * Copyright 2003-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Chuck Hagenbuch <chuck@horde.org>
 */

require_once dirname(__FILE__) . '/../lib/base.php';

$id = Horde_Util::getFormData('image');
$image = &$ansel_storage->getImage($id);
if (is_a($image, 'PEAR_Error')) {
    Horde::fatal($image, __FILE__, __LINE__);
}
$gallery = $ansel_storage->getGallery(abs($image->gallery));
if (is_a($gallery, 'PEAR_Error')) {
    Horde::fatal($gallery, __FILE__, __LINE__);
}
if (!$gallery->hasPermission(Horde_Auth::getAuth(), Horde_Perms::READ)) {
    Horde::fatal(_("Access denied viewing this photo."), __FILE__, __LINE__);
}

/* Sendfile support. Lighttpd < 1.5 only understands the X-LIGHTTPD-send-file header */
if ($conf['vfs']['src'] == 'sendfile') {
    /* Need to ensure the file exists */
    try {
        $image->createView('mini', 'ansel_default');
    } catch (Horde_Exception $e) {
        Horde::logMessage($e->getMessage(), __FILE__, __LINE__, PEAR_LOG_ERR);
        exit;
    }
    $filename = $ansel_vfs->readFile($image->getVFSPath('mini'), $image->getVFSName('mini'));
    header('Content-Type: ' . $image->getType('mini'));
    header('X-LIGHTTPD-send-file: ' . $filename);
    header('X-Sendfile: ' . $filename);
    exit;
}

if (is_a($result = $image->display('mini'), 'PEAR_Error')) {
    Horde::fatal($result, __FILE__, __LINE__);
}
