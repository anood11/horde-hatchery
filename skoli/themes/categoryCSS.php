<?php
/**
 * Copyright 1999-2008 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Chuck Hagenbuch <chuck@horde.org>
 */

$skoli_authentication = 'none';
require_once dirname(__FILE__) . '/../lib/base.php';

header('Content-Type: text/css');

$cManager = new Horde_Prefs_CategoryManager();

$colors = $cManager->colors();
$fgColors = $cManager->fgColors();
foreach ($colors as $category => $color) {
    if ($category == '_unfiled_' || $category == '_default_') {
        continue;
    }

    $class = '.category' . md5($category);

    echo "$class, .linedRow td$class, .overdue td$class, .closed td$class { "
        . 'color: ' . (isset($fgColors[$category]) ? $fgColors[$category] : $fgColors['_default_']) . '; '
        . 'background: ' . $color . '; '
        . "padding: 0 4px; }\n";
}
