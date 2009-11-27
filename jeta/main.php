<?php
/**
 * Copyright 2002-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Eric Rostetter <eric.rostetter@physics.utexas.edu>
 */

require_once dirname(__FILE__) . '/lib/base.php';

$applet = Jeta_Applet::factory($prefs->getValue('sshdriver'));

$template = new Horde_Template();
$template->set('menu', Jeta::getMenu()->render());
$template->set('notification', $notification->notify(array('listeners' => 'status')));
$template->set('applet', $applet->generateAppletCode());

require JETA_TEMPLATES . '/common-header.inc';
echo $template->fetch(JETA_TEMPLATES . '/main.html');
require $registry->get('templates', 'horde') . '/common-footer.inc';
