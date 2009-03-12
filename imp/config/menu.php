<?php
/**
 * This file lets you extend IMP's menu with your own items.
 *
 * To add a new menu item, simply add a new entry to the $_menu array.
 * Valid attributes for a new menu item are:
 *
 *  'url'       The URL value for the menu item.
 *  'text'      The text to accompany the menu item.
 *
 * These attributes are optional:
 *
 *  'action'    The javascript code for the menu item (dimp only).
 *  'icon'      The filename of an icon to use for the menu item.
 *  'icon_path' The path to the icon if it doesn't exist in the graphics/
 *              directory.
 *  'target'    The "target" of the link (e.g. '_top', '_blank').
 *  'onclick'   Any JavaScript to execute on the "onclick" event.
 *
 * Here's an example entry:
 *
 *  $_menu[] = array(
 *      'url' =>        'http://www.example.com/',
 *      'text' =>       'Example, Inc.',
 *      'icon' =>       'example.png',
 *      'icon_path' =>  'http://www.example.com/images/',
 *      'target' =>     '_blank',
 *      'onclick' =>    '',
 *      'action' =>     ''
 *  );
 *
 * You can also add a "separator" (a spacer) between menu items.  To add a
 * separator, simply add a new string to the $_menu array set to the text
 * 'separator'.  It should look like this:
 *
 *  $_menu[] = 'separator';
 *
 * $Id: f8b2f030625f918d7a37c72ad57cf76dc9087652 $
 */

$_menu = array();

/* Add your custom entries below this line. */
