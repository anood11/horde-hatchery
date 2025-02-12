<?php
if (isset($language)) {
    header('Content-type: text/html; charset=' . Horde_Nls::getCharset());
    header('Vary: Accept-Language');
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "DTD/xhtml1-transitional.dtd">
<!-- Kastalia: Copyright 2009 Andre Pawlowski  -->
<!--     Horde Project: http://www.horde.org/ | Kastalia: http://h4des.org/index.php?inhalt=kastalia     -->
<!--                 Horde Licenses: http://www.horde.org/licenses/                       -->
<?php echo !empty($language) ? '<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="' . strtr($language, '_', '-') . '" lang="' . strtr($language, '_', '-') . '">' : '<html>' ?>


<head>

<?php

$page_title = $registry->get('name');
if (!empty($title)) $page_title .= ' :: ' . $title;
if (!empty($refresh_time) && ($refresh_time > 0) && !empty($refresh_url)) {
    echo "<meta http-equiv=\"refresh\" content=\"$refresh_time;url=$refresh_url\">\n";
}

Horde::includeScriptFiles();

?>
<title><?php echo htmlspecialchars($page_title) ?></title>
<link href="<?php echo $GLOBALS['registry']->getImageDir()?>/favicon.ico" rel="SHORTCUT ICON" />
<?php Horde::includeStylesheetFiles() ?>
</head>

<body<?php if ($bc = Util::nonInputVar('bodyClass')) echo ' class="' . $bc . '"' ?><?php if ($bi = Util::nonInputVar('bodyId')) echo ' id="' . $bi . '"'; ?>>
