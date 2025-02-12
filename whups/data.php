<?php
/**
 * Copyright 2002-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author Chuck Hagenbuch <chuck@horde.org>
 */

define('WHUPS_BASE', dirname(__FILE__));
require_once WHUPS_BASE . '/lib/base.php';
require_once 'Horde/Template.php';
require WHUPS_BASE . '/config/templates.php';

if (!Horde_Auth::getAuth()) {
    header('Location: ' . Horde::applicationUrl('search.php', true));
    exit;
}

$tpl = Horde_Util::getFormData('template');

if (empty($_templates[$tpl])) {
    Horde::fatal(_("The requested template does not exist."), __FILE__, __LINE__);
}
if ($_templates[$tpl]['type'] != 'searchresults') {
    Horde::fatal(_("This is not a search results template."), __FILE__, __LINE__);
}

// Fetch all unresolved tickets assigned to the current user.
$info = array('id' => explode(',', Horde_Util::getFormData('ids')));
$tickets = $whups_driver->getTicketsByProperties($info);
foreach ($tickets as $id => $info) {
    $tickets[$id]['#'] = $id + 1;
    $tickets[$id]['link'] = Whups::urlFor('ticket', $info['id'], true, -1);
    $tickets[$id]['date_created'] = strftime('%x', $info['timestamp']);
    $tickets[$id]['owners'] = Whups::getOwners($info['id']);
    $tickets[$id]['owner_name'] = Whups::getOwners($info['id'], false, true);
    $tickets[$id]['owner_email'] = Whups::getOwners($info['id'], true, false);
    if (!empty($info['date_assigned'])) {
        $tickets[$id]['date_assigned'] = strftime('%x', $info['date_assigned']);
    }
    if (!empty($info['date_resolved'])) {
        $tickets[$id]['date_resolved'] = strftime('%x', $info['date_resolved']);
    }

    // If the template has a callback function defined for data
    // filtering, call it now.
    if (!empty($_templates[$tpl]['callback'])) {
        array_walk($tickets[$id], $_templates[$tpl]['callback']);
    }
}

Whups::sortTickets($tickets,
                   isset($_templates[$tpl]['sortby']) ? $_templates[$tpl]['sortby'] : null,
                   isset($_templates[$tpl]['sortdir']) ? $_templates[$tpl]['sortdir'] : null);

$template = new Horde_Template();
$template->set('tickets', $tickets);
$template->set('now', strftime('%x'));
$template->set('values', Whups::getSearchResultColumns(null, true));

$browser->downloadHeaders(isset($_templates[$tpl]['filename']) ? $_templates[$tpl]['filename'] : 'report.html');
echo $template->parse($_templates[$tpl]['template']);
