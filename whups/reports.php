<?php
/**
 * Copyright 2002-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author Chuck Hagenbuch <chuck@horde.org>
 */

@define('WHUPS_BASE', dirname(__FILE__));
require_once WHUPS_BASE . '/lib/base.php';
require_once WHUPS_BASE . '/lib/Reports.php';

/* Supported graph types. Unused at the moment. */
$graphs = array('open|queue_name' => array('chart', _("Open Tickets by Queue")),
                'open|state_name' => array('chart', _("Open Tickets by State")),
                'open|type_name' => array('chart', _("Open Tickets by Type")),
                'open|priority_name' => array('chart', _("Open Tickets by Priority")),
                'open|user_id_requester' => array('chart', _("Open Tickets by Requester")),
                'open|owner' => array('chart', _("Open Tickets by Owner")),
                '@closed:avg:open|owner' => array('plot', _("Average days to close by Owner")),
                '@closed:avg:open|user_id_requester' => array('plot', _("Average days to close by Requester")),
                '@closed:avg:open|queue_name' => array('plot', _("Average days to close by Queue")));

/* Supported statistic types. */
$stats = array('avg|open' => _("Average time a ticket is unresolved"),
               'max|open' => _("Maximum time a ticket is unresolved"),
               'min|open' => _("Minimum time a ticket is unresolved"));

$queues = Whups::permissionsFilter($whups_driver->getQueues(), 'queue', Horde_Perms::READ);
if (!count($queues)) {
    $notification->push(_("No stats available."));
}

$reporter = new Whups_Reports($whups_driver);

$title = _("Reports");
require WHUPS_TEMPLATES . '/common-header.inc';
require WHUPS_TEMPLATES . '/menu.inc';
if (count($queues)) {
    require WHUPS_TEMPLATES . '/reports/stats.inc';
}
require $registry->get('templates', 'horde') . '/common-footer.inc';
