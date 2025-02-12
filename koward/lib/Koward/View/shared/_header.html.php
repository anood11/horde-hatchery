<?php
if (isset($language)) {
    header('Content-type: text/html; charset=' . Horde_Nls::getCharset());
    header('Vary: Accept-Language');
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<!--
Koward - The Kolab warden

Copyright

2004 - 2009 Klarälvdalens Datakonsult AB
2009        The Horde Project

Koward is under the GPL. GNU Public License: http://www.fsf.org/copyleft/gpl.html -->

<?php echo !empty($language) ? '<html lang="' . strtr($language, '_', '-') . '">' : '<html>' ?>
<head>
<?php

global $registry;

$page_title = $registry->get('name');
$page_title .= !empty($this->title) ? ' :: ' . $this->title : '';

Horde::includeScriptFiles();
?>
<title><?php echo htmlspecialchars($page_title) ?></title>
<link href="<?php echo $GLOBALS['registry']->getImageDir()?>/favicon.ico" rel="SHORTCUT ICON" />

<?php Horde::includeStylesheetFiles() ?>

</head>

<body>
