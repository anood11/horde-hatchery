<?php
if (isset($language)) {
    header('Content-type: text/html; charset=' . Horde_Nls::getCharset());
    header('Vary: Accept-Language');
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "DTD/xhtml1-transitional.dtd">
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
<script src="<?php echo $GLOBALS['registry']->get('jsuri', 'horde') ?>/glow/1.5.1/core/core.js" type="text/javascript"></script>
<script src="<?php echo $GLOBALS['registry']->get('jsuri', 'horde') ?>/glow/1.5.1/widgets/widgets.js" type="text/javascript"></script>
<link href="<?php echo $GLOBALS['registry']->get('jsuri', 'horde') ?>/glow/1.5.1/widgets/widgets.css" type="text/css" rel="stylesheet" />
<script type="text/javascript">
var Nag = {};

glow.ready(function() {
    $('quickAddInfoPanel').style.display = 'block';
    Nag.quickAddPanel = new glow.widgets.InfoPanel('#quickAddInfoPanel', { context: '.quickAdd', pointerPosition: 't' });
});
</script>
</head>

<body<?php if ($bc) echo ' class="' . $bc . '"' ?><?php if ($bi = Horde_Util::nonInputVar('bodyId')) echo ' id="' . $bi . '"'; ?>>

<div id="quickAddInfoPanel" style="display:none;">
 <h2><?php echo _("Quick Task Creation") ?></h2>
 <form method="post" action="quick.php">
  <p><?php echo _("Enter one task per line. Create child tasks by indenting them below their parent task. Include due dates like \"laundry tomorrow\" or \"get dry cleaning next Thursday\".") ?></p>
  <textarea name="quickText" id="quickText"></textarea>
  <p><input type="submit" value="<?php echo _("Create") ?>" /></p>
 </form>
</div>
