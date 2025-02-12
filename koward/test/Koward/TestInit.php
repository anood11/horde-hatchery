<?php
/**
 * Initialize testing for this application.
 *
 * PHP version 5
 *
 * @category Kolab
 * @package  Koward
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @license  http://www.fsf.org/copyleft/lgpl.html LGPL
 * @link     http://pear.horde.org/index.php?package=Koward
 */

/**
 * The Autoloader allows us to omit "require/include" statements.
 */
require_once 'Horde/Autoloader.php';

if (!defined('KOWARD_BASE')) {
    define('KOWARD_BASE', dirname(__FILE__) . '/../');
}

/* Set up the application class and controller loading */
Horde_Autoloader::addClassPattern('/^Koward_/', KOWARD_BASE . '/lib/');
Horde_Autoloader::addClassPattern('/^Koward_/', KOWARD_BASE . '/app/controllers/');
