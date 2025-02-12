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

/* Only admin should be using this. */
if (!Vilma::hasPermission($domain)) {
    Horde::authenticationFailureRedirect();
}

// Having a current domain doesn't make sense on this page
Vilma::setCurDomain(false);

$domains = $vilma_driver->getDomains();
if (is_a($domains, 'PEAR_Error')) {
    $notification->push($domains, 'horde.error');
    $domains = array();
}
foreach ($domains as $id => $domain) {
    $url = Horde::applicationUrl('domains/edit.php');
    $domains[$id]['edit_url'] = Horde_Util::addParameter($url, 'domain_id', $domain['domain_id']);
    $url = Horde::applicationUrl('domains/delete.php');
    $domains[$id]['del_url'] = Horde_Util::addParameter($url, 'domain_id', $domain['domain_id']);
    $url = Horde::applicationUrl('users/index.php');
    $domains[$id]['view_url'] = Horde_Util::addParameter($url, 'domain_id', $domain['domain_id']);
}

/* Set up the template fields. */
$template->set('domains', $domains, true);
$template->set('menu', Vilma::getMenu('string'));
$template->set('notify', Horde_Util::bufferOutput(array($notification, 'notify'), array('listeners' => 'status')));

/* Set up the field list. */
$images = array('delete' => Horde::img('delete.png', _("Delete Domain"), '', $registry->getImageDir('horde')),
                'edit' => Horde::img('edit.png', _("Edit Domain"), '', $registry->getImageDir('horde')));
$template->set('images', $images);

/* Render the page. */
require VILMA_TEMPLATES . '/common-header.inc';
echo $template->fetch(VILMA_TEMPLATES . '/domains/index.html');
require $registry->get('templates', 'horde') . '/common-footer.inc';
