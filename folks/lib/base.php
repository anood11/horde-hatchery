<?php
/**
 * Folks base application file.
 *
 *
 * This file brings in all of the dependencies that every Folks script will
 * need, and sets up objects that all scripts use.
 */

// Check for a prior definition of HORDE_BASE (perhaps by an auto_prepend_file
// definition for site customization).
if (!defined('HORDE_BASE')) {
    define('HORDE_BASE', dirname(__FILE__) . '/../..');
}

// Load the Horde Framework core, and set up inclusion paths and autoloading.
require_once HORDE_BASE . '/lib/core.php';

// Registry.
$registry = Horde_Registry::singleton();
try {
    $registry->pushApp('folks', array('check_perms' => (Horde_Util::nonInputVar('folks_authentication') != 'none')));
} catch (Horde_Exception $e) {
    Horde_Auth::authenticateFailure('folks', $e);
}
$conf = &$GLOBALS['conf'];
define('FOLKS_TEMPLATES', $registry->get('templates'));

// Notification system.
$notification = Horde_Notification::singleton();
$notification->attach('status');

// Define the base file path of Folks.
if (!defined('FOLKS_BASE')) {
    define('FOLKS_BASE', dirname(__FILE__) . '/..');
}

$GLOBALS['folks_driver'] = Folks_Driver::factory();

// Cache
$GLOBALS['cache'] = Horde_Cache::singleton($GLOBALS['conf']['cache']['driver'],
                                            Horde::getDriverConfig('cache', $GLOBALS['conf']['cache']['driver']));

// Update user online status
$GLOBALS['folks_driver']->updateOnlineStatus();

// Start output compression.
if (!Horde_Util::nonInputVar('no_compress')) {
    Horde::compressOutput();
}
