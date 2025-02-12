<?php
/**
 * $Horde: agora/rss/index.php,v 1.7 2009/07/09 08:17:48 slusarz Exp $
 *
 * Copyright 2007-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Duck <duck@obala.net>
 */

define('AUTH_HANDLER', true);
define('AGORA_BASE', dirname(__FILE__) . '/../');
require_once AGORA_BASE . '/lib/base.php';

// Show a specific scope?
$scope = Horde_Util::getGet('scope', 'agora');
$cache_key = 'agora_rss_' . $scope;

/* Initialize the Cache object. */
$cache = &Horde_Cache::singleton($GLOBALS['conf']['cache']['driver'],
                                    Horde::getDriverConfig('cache', $GLOBALS['conf']['cache']['driver']));

$rss = $cache->get($cache_key, $conf['cache']['default_lifetime']);
if (!$rss) {

    $title = sprintf(_("Forums in %s"), $registry->get('name', $scope));
    $forums = Agora_Messages::singleton($scope);
    $forums_list = $forums->getForums(0, true, 'forum_name', 0);

    $rss = '<?xml version="1.0" encoding="' . Horde_Nls::getCharset() . '" ?>
    <rss version="2.0">
        <channel>
        <title>' . htmlspecialchars($title) . '</title>
        <language>' . str_replace('_', '-', strtolower(Horde_Nls::select())) . '</language>
        <lastBuildDate>' . date('r') . '</lastBuildDate>
        <description>' . htmlspecialchars($title) . '</description>
        <link>' . Horde::applicationUrl('index.php', true, -1) . '</link>
        <generator>' . htmlspecialchars($registry->get('name')) . '</generator>';

    foreach ($forums_list as $forum_id => $forum) {
        $rss .= '
        <item>
            <title>' . htmlspecialchars($forum['forum_name']) . ' </title>
            <description>' . htmlspecialchars($forum['forum_description']) . ' </description>
            <link>' . Horde_Util::addParameter(Horde::applicationUrl('threads.php', true, -1), array('scope' => $scope, 'forum_id' => $forum_id)) . '</link>
        </item>';
    }

    $rss .= '
    </channel>
    </rss>';

    $cache->set($cache_key, $rss);
}

header('Content-type: text/xml; charset=' . Horde_Nls::getCharset());
echo $rss;
