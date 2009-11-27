<?php
/**
 * Copyright 1999-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author Chuck Hagenbuch <chuck@horde.org>
 */

@define('AUTH_HANDLER', true);
@define('NAG_BASE', dirname(__FILE__) . '/..');
require_once NAG_BASE . '/lib/base.php';

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
