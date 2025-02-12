<?php
/**
 * Copyright 2009-2010 Alkaloid Networks LLC (http://projects.alkaloid.net)
 *
 * See the enclosed file COPYING for license information (BSD). If you
 * did not receive this file, see
 * http://www.opensource.org/licenses/bsd-license.php.
 *
 * @author  Ben Klang <ben@alkaloid.net>
 */
require_once dirname(__FILE__) . '/lib/Application.php';

$shout = new Shout_Application(array('init' => true));
$context = $_SESSION['shout']['context'];

require_once SHOUT_BASE . '/lib/Forms/DeviceForm.php';

$action = Horde_Util::getFormData('action');
$vars = Horde_Variables::getDefaultVariables();

//$tabs = Shout::getTabs($context, $vars);

$RENDERER = new Horde_Form_Renderer();

$title = _("Devices: ");

switch ($action) {
case 'add':
case 'edit':
    $vars = Horde_Variables::getDefaultVariables();
    $vars->set('context', $context);
    $Form = new DeviceDetailsForm($vars);

    // Show the list if the save was successful, otherwise back to edit.
    if ($Form->isSubmitted() && $Form->isValid()) {
        // Form is Valid and Submitted
        try {
            $devid = Horde_Util::getFormData('devid');

            $Form->execute();
            $notification->push(_("Device information updated."),
                                  'horde.success');
            $action = 'list';
            break;

        } catch (Exception $e) {
            $notification->push($e);
        }
    } elseif ($Form->isSubmitted()) {
        // Submitted but not valid
        $notification->push(_("Problem processing the form.  Please check below and try again."), 'horde.warning');
    }

    // Create a new add/edit form
    $devid = Horde_Util::getFormData('devid');
    $devices = $shout->devices->getDevices($context);
    $vars = new Horde_Variables($devices[$devid]);

    $vars->set('action', $action);
    $Form = new DeviceDetailsForm($vars);
    $Form->open($RENDERER, $vars, Horde::applicationUrl('devices.php'), 'post');
    // Make sure we get the right template below.
    $action = 'edit';

    break;
case 'delete':
    $title .= sprintf(_("Delete Devices %s"), $extension);
    $devid = Horde_Util::getFormData('devid');

    $vars = Horde_Variables::getDefaultVariables();
    $vars->set('context', $context);
    $Form = new DeviceDeleteForm($vars);

    $FormValid = $Form->validate($vars, true);

    if ($Form->isSubmitted() && $FormValid) {
        try {
            $Form->execute();
            $notification->push(_("Device Deleted."));
            $action = 'list';
        } catch (Exception $e) {
            $notification->push($e);
        }
    } elseif ($Form->isSubmitted()) {
        $notification->push(_("Problem processing the form.  Please check below and try again."), 'horde.warning');
    }

    $vars = Horde_Variables::getDefaultVariables(array());
    $vars->set('context', $context);
    $Form = new DeviceDeleteForm($vars);
    $Form->open($RENDERER, $vars, Horde::applicationUrl('devices.php'), 'post');

    break;

case 'list':
default:
    $action = 'list';
    $title .= _("List Devices");
}

// Fetch the (possibly updated) list of extensions
$devices = $shout->devices->getDevices($context);

Horde::addScriptFile('stripe.js', 'horde');
require SHOUT_TEMPLATES . '/common-header.inc';
require SHOUT_TEMPLATES . '/menu.inc';

$notification->notify();

echo "<br>\n";

require SHOUT_TEMPLATES . '/devices/' . $action . '.inc';

require $registry->get('templates', 'horde') . '/common-footer.inc';