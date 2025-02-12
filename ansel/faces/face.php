<?php
/**
 * Shows all images that the supplied, named face appears on?
 *
 * TODO: Maybe incorporate this into some kind of generic "result" view?
 * At least, we need to rename this to something other that image.php to
 * reflect what it's used for.
 *
 * Copyright 2008-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Duck <duck@obala.net>
 */
require_once dirname(__FILE__) . '/../lib/base.php';
$faces = Ansel_Faces::factory();
$face_id = Horde_Util::getFormData('face');
try {
    $face = $faces->getFaceById($face_id);
} catch (Horde_Exception $e) {
    $notification->push($face->getMessage());
    header('Location: ' . Horde::applicationUrl('faces/index.php'));
    exit;
}

$title = _("Face") . ' :: ' . $face['face_name'];

require ANSEL_TEMPLATES . '/common-header.inc';
require ANSEL_TEMPLATES . '/menu.inc';
require_once ANSEL_TEMPLATES . '/faces/face.inc';
require $registry->get('templates', 'horde') . '/common-footer.inc';