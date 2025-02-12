<?php
/**
 * Copyright 2002-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsdl.php.
 *
 * @author Chuck Hagenbuch <chuck@horde.org>
 */

require_once dirname(__FILE__) . '/lib/base.php';
require_once 'Horde/Block/Layout/View.php';

// @TODO: remove this when there are blocks useful to guests
// available.
if (!Horde_Auth::getAuth()) {
    require WHUPS_BASE . '/search.php';
    exit;
}

// Get refresh interval.
if ($r_time = $prefs->getValue('summary_refresh_time')) {
    if ($browser->hasFeature('xmlhttpreq')) {
        Horde::addScriptFile('prototype.js', 'horde', true);
    } else {
        $refresh_time = $r_time;
        $refresh_url = Horde::applicationUrl('mybugs.php');
    }
}

// Load layout from preferences for authenticated users, and a default
// block set for guests.
$mybugs_layout = @unserialize($prefs->getValue('mybugs_layout'));
if (!$mybugs_layout) {
    if (Horde_Auth::isAuthenticated()) {
        $mybugs_layout = array(
            array(array('app' => 'whups', 'params' => array('type' => 'mytickets', 'params' => false), 'height' => 1, 'width' => 1)),
            array(array('app' => 'whups', 'params' => array('type' => 'myrequests', 'params' => false), 'height' => 1, 'width' => 1)),
            array(array('app' => 'whups', 'params' => array('type' => 'myqueries', 'params' => false), 'height' => 1, 'width' => 1)));
        $prefs->setValue('mybugs_layout', serialize($mybugs_layout));
    } else {
        // @TODO: show some blocks that are useful to guests.
        $mybugs_layout = array();
    }
}
$layout = new Horde_Block_Layout_View(
    $mybugs_layout,
    Horde::applicationUrl('mybugs_edit.php'),
    Horde::applicationUrl('mybugs.php', true));
$layout_html = $layout->toHtml();

$title = sprintf(_("My %s"), $registry->get('name'));
$menuBottom = '<div id="menuBottom"><a href="' . Horde::applicationUrl('mybugs_edit.php') . '">' . _("Add Content") . '</a></div><div class="clear">&nbsp;</div>';
require WHUPS_TEMPLATES . '/common-header.inc';
require WHUPS_TEMPLATES . '/menu.inc';
echo $layout_html;
require $registry->get('templates', 'horde') . '/common-footer.inc';
