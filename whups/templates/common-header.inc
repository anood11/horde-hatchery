<?php
if (isset($language)) {
    header('Content-type: text/html; charset=' . Horde_Nls::getCharset());
    header('Vary: Accept-Language');
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "DTD/xhtml1-transitional.dtd">
<!-- Whups: Copyright 2002-2009 The Horde Project.  Whups is under a Horde license. -->
<!--   Horde Project: http://www.horde.org/ | Whups: http://www.horde.org/whups/    -->
<!--              Horde Licenses: http://www.horde.org/licenses/                    -->
<?php echo !empty($language) ? '<html lang="' . strtr($language, '_', '-') . '">' : '<html>' ?>
<head>
<?php

$page_title = $GLOBALS['registry']->get('name');
if (!empty($title)) $page_title .= ' :: ' . $title;
if (!empty($refresh_time) && !empty($refresh_url)) {
    echo "<meta http-equiv=\"refresh\" content=\"$refresh_time;url=$refresh_url\" />\n";
}

Horde::includeScriptFiles();

if (isset($ticket)) {
    echo '<link rel="alternate" type="application/rss+xml" title="' . htmlspecialchars('[#' . $ticket->getId() . '] ' . $ticket->get('summary')) . '" href="' . Whups::urlFor('ticket_rss', $ticket->getId(), true, -1) . '" />';
}
if (isset($whups_query) && isset($whups_query->id)) {
    echo '<link rel="alternate" type="application/rss+xml" title="' . htmlspecialchars($whups_query->name) . '" href="' . Whups::urlFor('query_rss', empty($whups_query->slug) ? array('id' => $whups_query->id) : array('slug' => $whups_query->slug), true, -1) . '" />';
}

?>
<title><?php echo htmlspecialchars($page_title) ?></title>
<link href="<?php echo $GLOBALS['registry']->getImageDir()?>/favicon.ico" rel="SHORTCUT ICON" />
<link href="<?php echo Horde::applicationUrl('opensearch.php', true, -1) ?>" rel="search" type="application/opensearchdescription+xml" title="<?php echo $registry->get('name') . ' (' . Horde::applicationUrl('', true) . ')' ?>" />
<?php Horde::includeStylesheetFiles() ?>
</head>

<body<?php if ($bc = Horde_Util::nonInputVar('bodyClass')) echo ' class="' . $bc . '"' ?><?php if ($bi = Horde_Util::nonInputVar('bodyId')) echo ' id="' . $bi . '"'; ?>>
