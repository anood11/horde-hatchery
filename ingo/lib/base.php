<?php
/**
 * Ingo base inclusion file.
 *
 * This file brings in all of the dependencies that every Ingo
 * script will need and sets up objects that all scripts use.
 *
 * The following global variables are used:
 * <pre>
 * $ingo_authentication - The type of authentication to use:
 *   'none'  - Do not authenticate
 *   [DEFAULT] - Authenticate; on failed auth redirect to login screen
 * </pre>
 *
 * Global variables defined:
 *   $ingo_shared  - TODO
 *   $ingo_storage - The Ingo_Storage:: object to use for storing rules.
 *   $no_compress  - Controls whether the page should be compressed
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 */

// Determine BASE directories.
require_once dirname(__FILE__) . '/base.load.php';

// Load the Horde Framework core.
require_once HORDE_BASE . '/lib/core.php';

// Registry.
$registry = Horde_Registry::singleton();
try {
    $registry->pushApp('ingo', array('check_perms' => (Horde_Util::nonInputVar('ingo_authentication') != 'none'), 'logintasks' => true));
} catch (Horde_Exception $e) {
    Horde_Auth::authenticateFailure('ingo', $e);
}
$conf = &$GLOBALS['conf'];

if (!defined('INGO_TEMPLATES')) {
    define('INGO_TEMPLATES', $registry->get('templates'));
}

// Notification system.
$notification = Horde_Notification::singleton();
$notification->attach('status');

// Start compression.
if (!Horde_Util::nonInputVar('no_compress')) {
    Horde::compressOutput();
}

// Load the Ingo_Storage driver. It appears in the global variable
// $ingo_storage.
$GLOBALS['ingo_storage'] = Ingo_Storage::factory();

// Create the ingo session (if needed).
if (!isset($_SESSION['ingo']) || !is_array($_SESSION['ingo'])) {
    Ingo_Session::createSession();
}

// Create shares if necessary.
$driver = Ingo::getDriver();
if ($driver->supportShares()) {
    $GLOBALS['ingo_shares'] = Horde_Share::singleton($registry->getApp());
    $GLOBALS['all_rulesets'] = Ingo::listRulesets();

    /* If personal share doesn't exist then create it. */
    $signature = $_SESSION['ingo']['backend']['id'] . ':' . Horde_Auth::getAuth();
    if (!$GLOBALS['ingo_shares']->exists($signature)) {
        $identity = Horde_Prefs_Identity::singleton();
        $name = $identity->getValue('fullname');
        if (trim($name) == '') {
            $name = Horde_Auth::getOriginalAuth();
        }
        $share = &$GLOBALS['ingo_shares']->newShare($signature);
        $share->set('name', $name);
        $GLOBALS['ingo_shares']->addShare($share);
        $GLOBALS['all_rulesets'][$signature] = &$share;
    }

    /* Select current share. */
    $_SESSION['ingo']['current_share'] = Horde_Util::getFormData('ruleset', @$_SESSION['ingo']['current_share']);
    if (empty($_SESSION['ingo']['current_share']) ||
        empty($GLOBALS['all_rulesets'][$_SESSION['ingo']['current_share']]) ||
        !$GLOBALS['all_rulesets'][$_SESSION['ingo']['current_share']]->hasPermission(Horde_Auth::getAuth(), Horde_Perms::READ)) {
        $_SESSION['ingo']['current_share'] = $signature;
    }
} else {
    $GLOBALS['ingo_shares'] = null;
}
