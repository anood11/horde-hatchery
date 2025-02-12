<?php

Horde::addScriptFile('effects.js', 'horde');
Horde::addScriptFile('views.js', 'kronolith');
Horde::addScriptFile('stripe.js', 'horde');

if (isset($language)) {
    header('Content-type: text/html; charset=' . Horde_Nls::getCharset());
    header('Vary: Accept-Language');
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "DTD/xhtml1-transitional.dtd">
<!--   Kronolith: Copyright 2000-2009 The Horde Project.  Kronolith is under the GPL.  -->
<!-- Horde Project: http://www.horde.org/ | Kronolith: http://www.horde.org/kronolith/ -->
<!--              GNU Public License: http://www.fsf.org/copyleft/gpl.html             -->
<?php echo !empty($language) ? '<html lang="' . strtr($language, '_', '-') . '">' : '<html>' ?>
<head>
<?php

$page_title = $registry->get('name');
if (!empty($title)) $page_title .= ' :: ' . $title;
if (!empty($refresh_time) && ($refresh_time > 0) && !empty($refresh_url)) {
    echo "<meta http-equiv=\"refresh\" content=\"$refresh_time;url=$refresh_url\">\n";
}

Horde::includeScriptFiles();
require KRONOLITH_TEMPLATES . '/javascript_defs.php';

$bc = Horde_Util::nonInputVar('bodyClass');
if (isset($view) && is_object($view) && $prefs->getValue('show_panel')) {
    if ($bc) {
        $bc .= ' ';
    }
    $bc .= 'rightPanel';
}

$currentDate = Kronolith::currentDate();

?>
<script type="text/javascript">//<![CDATA[
var kronolithView = '<?php if (isset($view) && is_object($view)) echo $view->getName() ?>';
var kronolithDate = new Date(<?php printf('%d, %d, %d', $currentDate->year, $currentDate->month - 1, $currentDate->mday) ?>);
//]]></script>
<title><?php echo htmlspecialchars($page_title) ?></title>
<link href="<?php echo $GLOBALS['registry']->getImageDir()?>/favicon.ico" rel="SHORTCUT ICON" />
<?php Horde::includeStylesheetFiles() ?>
<?php foreach ($GLOBALS['display_calendars'] as $calendar): ?>
<link href="<?php echo Kronolith::feedUrl($calendar) ?>" rel="alternate" type="application/atom+xml" />
<?php endforeach; ?>
<?php Horde::outputInlineScript(); ?>
</head>

<body<?php if ($bc) echo ' class="' . $bc . '"'; if ($bi = Horde_Util::nonInputVar('bodyId')) echo ' id="' . $bi . '"'; ?>>
