<?php
/**
 * Process an single image (to be called by ajax)
 *
 * Copyright 2008-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Duck <duck@obala.net>
 */
require_once dirname(__FILE__) . '/../lib/base.php';

$face_id = Horde_Util::getFormData('face');

$faces = Ansel_Faces::factory();
try {
    $face = $faces->getFaceById($face_id);
} catch (Horde_Exception $e) {
    $notification->push($e->getMessage());
    header('Location: ' . Horde::applicationUrl('faces/search/all.php'));
    exit;
}

$title = _("Report face");

$vars = Horde_Variables::getDefaultVariables();
$form = new Horde_Form($vars, $title);
$form->addHidden('', 'face', 'int', true);
$form->addVariable(_("Reason"), 'reason', 'longtext', true, false, _("Please describe the reasons. For example, you don't want to be mentioned etc..."));
$form->setButtons($title);

if ($form->validate()) {

    if (Horde_Util::getFormData('submitbutton') == _("Cancel")) {
        $notification->push(_("Action was cancelled."), 'horde.warning');
    } else {
        require ANSEL_BASE . '/lib/Report.php';
        $report = Ansel_Report::factory();
        $gallery = $ansel_storage->getGallery($face['gallery_id']);

        $face_link = Horde_Util::addParameter(Horde::applicationUrl('faces/face.php', true),
                        array('name' => $vars->get('person'),
                              'face' => $face_id,
                                'image' => $face['image_id']), null, false);

        $body = _("Gallery Name") . ': ' . $gallery->get('name') . "\n"
                . _("Gallery Description") . ': ' . $gallery->get('desc') . "\n\n"
                . $title . "\n"
                . _("Reason") . ': ' . $vars->get('reason') . "\n"
                . _("Face") . ': ' . $face_link;

        $report->setTitle($title);
        try {
            $result = $report->report($body, $gallery->get('owner'));
        } catch (Horde_Exception $e) {
            $notification->push(sprintf(_("Face name was not reported: %s"), $e->getMessage()), 'horde.error');
        }
        $notification->push(_("The owner of the photo was notified."), 'horde.success');
    }

    header('Location: ' . Ansel_Faces::getLink($face));
    exit;
}

require ANSEL_TEMPLATES . '/common-header.inc';
require ANSEL_TEMPLATES . '/menu.inc';

$form->renderActive(null, null, null, 'post');

require $registry->get('templates', 'horde') . '/common-footer.inc';