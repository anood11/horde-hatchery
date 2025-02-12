<?php
/**
 * Copyright 2003-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you did not
 * did not receive this file, see http://cvs.horde.org/co.php/vilma/LICENSE.
 *
 * @author Marko Djukic <marko@oblo.com>
 */

@define('VILMA_BASE', dirname(__FILE__) . '/..');
require_once VILMA_BASE . '/lib/base.php';
require_once 'Horde/Form.php';
require_once VILMA_BASE . '/lib/Forms/DeleteDomainForm.php';

/* Only admin should be using this. */
if (!Vilma::hasPermission($domain)) {
    Horde::authenticationFailureRedirect();
}

$vars = Horde_Variables::getDefaultVariables();
$form = new DeleteDomainForm($vars);

if ($vars->get('submitbutton') == _("Delete")) {
    if ($form->validate($vars)) {
        $form->getInfo($vars, $info);
        $delete = $vilma_driver->deleteDomain($info['domain_id']);
        if (is_a($delete, 'PEAR_Error')) {
            Horde::logMessage($delete, __FILE__, __LINE__, PEAR_LOG_ERR);
            $notification->push(sprintf(_("Error deleting domain. %s."), $delete->getMessage()), 'horde.error');
        } else {
            $notification->push(_("Domain deleted."), 'horde.success');
            $url = Horde::applicationUrl('domains/index.php', true);
            header('Location: ' . $url);
            exit;
        }
    }
} elseif ($vars->get('submitbutton') == _("Do not delete")) {
    $notification->push(_("Domain not deleted."), 'horde.message');
    header('Location: ' . Horde::applicationUrl('domains/index.php'));
    exit;
}

/* Render the form. */
require_once 'Horde/Form/Renderer.php';
$renderer = new Horde_Form_Renderer();
$main = Horde_Util::bufferOutput(array($form, 'renderActive'), $renderer, $vars, 'delete.php', 'post');

$template->set('main', $main);
$template->set('menu', Vilma::getMenu('string'));
$template->set('notify', Horde_Util::bufferOutput(array($notification, 'notify'), array('listeners' => 'status')));

require VILMA_TEMPLATES . '/common-header.inc';
echo $template->fetch(VILMA_TEMPLATES . '/main/main.html');
require $registry->get('templates', 'horde') . '/common-footer.inc';
