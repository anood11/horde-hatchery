<?php
if (isset($language)) {
    header('Content-type: text/html; charset=' . Horde_Nls::getCharset());
    header('Vary: Accept-Language');
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "DTD/xhtml1-transitional.dtd">
<!-- news: Copyright 2004-2009 The Horde Project. news is under a Horde license. -->
<!--     Horde Project: http://www.horde.org/ | news: http://www.horde.org/horde/     -->
<!--                 Horde Licenses: http://www.horde.org/licenses/                       -->
<?php echo !empty($language) ? '<html lang="' . strtr($language, '_', '-') . '">' : '<html>' ?>
<head>
<?php

$page_title = $registry->get('name');
if (!empty($title)) $page_title .= ' :: ' . $title;
if (!empty($refresh_time) && ($refresh_time > 0) && !empty($refresh_url)) {
    echo "<meta http-equiv=\"refresh\" excerpt=\"$refresh_time;url=$refresh_url\">\n";
}

Horde::includeScriptFiles();

?>
<title><?php echo htmlspecialchars($page_title) ?></title>
<link href="<?php echo $GLOBALS['registry']->getImageDir()?>/favicon.ico" rel="SHORTCUT ICON" />
<link rel="alternate" title="<?php echo _("Last news") ?>" href="<?php echo Horde::applicationUrl('rss/news.php', true, -1) ?>" type="application/rss+xml" />
<link rel="alternate" title="<?php echo _("Last comments") ?>" href="<?php echo Horde::applicationUrl('rss/comments.php', true, -1) ?>" type="application/rss+xml" />
<?php Horde::includeStylesheetFiles() ?>
</head>

<body<?php if ($bc = Horde_Util::nonInputVar('bodyClass')) echo ' class="' . $bc . '"' ?><?php if ($bi = Horde_Util::nonInputVar('bodyId')) echo ' id="' . $bi . '"'; ?>>
