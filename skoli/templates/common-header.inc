<?php
if (isset($language)) {
    header('Content-type: text/html; charset=' . Horde_Nls::getCharset());
    header('Vary: Accept-Language');
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "DTD/xhtml1-transitional.dtd">
<!-- Skoli: Copyright 2004-2008 The Horde Project.  Skoli is under a Horde license. -->
<!--    Horde Project: http://www.horde.org/ | Skoli: http://www.horde.org/horde/   -->
<!--                Horde Licenses: http://www.horde.org/licenses/                  -->
<?php echo !empty($language) ? '<html lang="' . strtr($language, '_', '-') . '">' : '<html>' ?>
<head>
<?php

$page_title = $registry->get('name');
if (!empty($title)) $page_title .= ' :: ' . $title;
if (!empty($refresh_time) && ($refresh_time > 0) && !empty($refresh_url)) {
    echo "<meta http-equiv=\"refresh\" content=\"$refresh_time;url=$refresh_url\">\n";
}

Horde::includeScriptFiles();

$bc = Horde_Util::nonInputVar('bodyClass');
if ($prefs->getValue('show_panel')) {
    if ($bc) {
        $bc .= ' ';
    }
    $bc .= 'rightPanel';
}

?>
<title><?php echo htmlspecialchars($page_title) ?></title>
<link href="<?php echo $GLOBALS['registry']->getImageDir()?>/favicon.ico" rel="SHORTCUT ICON" />
<?php Horde::includeStylesheetFiles() ?>
<link href="<?php echo Horde::applicationUrl('themes/categoryCSS.php') ?>" rel="stylesheet" type="text/css" />
</head>

<body<?php if ($bc) echo ' class="' . $bc . '"' ?><?php if ($bi = Horde_Util::nonInputVar('bodyId')) echo ' id="' . $bi . '"'; ?>>
