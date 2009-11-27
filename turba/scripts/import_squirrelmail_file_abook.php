#!/usr/bin/php
<?php
/**
 * This script imports SquirrelMail file-based addressbooks into Turba.
 * It was developed against SquirrelMail 1.4.0, so use at your own risk
 * against different versions.
 *
 * Input can be either a single squirrelmail .abook file, or a directory
 * containing multiple .abook files.
 *
 * Copyright 2007-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author Ben Chavet <ben@horde.org>
 */

// Do CLI checks and environment setup first.
require_once dirname(__FILE__) . '/../lib/base.load.php';
require_once HORDE_BASE . '/lib/core.php';

// Makre sure no one runs this from the web.
if (!Horde_Cli::runningFromCli()) {
    exit("Must be run from the command line\n");
}

// Load the CLI environment - make sure there's no time limit, init some
// variables, etc.
$cli = Horde_Cli::singleton();
$cli->init();

// Read command line parameters.
if ($argc != 2) {
    $cli->message('Too many or too few parameters.', 'cli.error');
    $cli->writeln('Usage: import_squirrelmail_file_abook.php path-to-squirrelmail-data');
    exit;
}
$data = $argv[1];

// Make sure we load Horde base to get the auth config
$horde_authentication = 'none';
require_once HORDE_BASE . '/lib/base.php';
if ($conf['auth']['admins']) {
    Horde_Auth::setAuth($conf['auth']['admins'][0], array());
}

// Now that we are authenticated, we can load Turba's base. Otherwise, the
// share code breaks, causing a new, completely empty share to be created on
// the DataTree with no owner.
require_once TURBA_BASE . '/lib/base.php';
require_once TURBA_BASE . '/lib/Object/Group.php';

// Get list of SquirrelMail address book files
if (is_dir($data)) {
    if (!($handle = opendir($data))) {
        exit;
    }
    $files = array();
    while (false !== ($file = readdir($handle))) {
        if (preg_match('/.abook$/', $file)) {
            $files[] = $data . '/' . $file;
        }
    }
    closedir($handle);
} else {
    $files = array($data);
}

// Loop through SquirrelMail address book files
foreach($files as $file) {
    if (!($handle = fopen($file, 'r'))) {
        continue;
    }

    // Set current user
    $user = substr(basename($file), 0, -6);
    Horde_Auth::setAuth($user, array());
    $cli->message('Importing ' . $user . '\'s address book');

    // Reset user prefs
    unset($prefs);
    $prefs = Horde_Prefs::factory($conf['prefs']['driver'], 'turba', $user, null, null, false);

    // Reset $cfgSources for current user.
    unset($cfgSources);
    include TURBA_BASE . '/config/sources.php';
    $cfgSources = Turba::getConfigFromShares($cfgSources);
    $cfgSources = Turba::permissionsFilter($cfgSources);

    // Get user's default addressbook
    $import_source = $prefs->getValue('default_dir');
    if (empty($import_source)) {
        $import_source = array_keys($cfgSources);
        $import_source = $import_source[0];
    }

    // Check existance of the specified source.
    if (!isset($cfgSources[$import_source])) {
        PEAR::raiseError(sprintf(_("Invalid address book: %s"), $import_source), 'horde.warning');
        continue;
    }

    // Initiate driver
    $driver = &Turba_Driver::singleton($import_source);
    if (is_a($driver, 'PEAR_Error')) {
        PEAR::raiseError(sprintf(_("Connection failed: %s"), $driver->getMessage()), 'horde.error', null, null, $import_source);
        continue;
    }

    // Read addressbook file, one line at a time
    while (!feof($handle)) {
        $buffer = fgets($handle);
        if (empty($buffer)) {
            continue;
        }

        $entry = explode('|', $buffer);
        $members = explode(',', $entry[3]);

        if (count($members) > 1) {
            // Entry is a list of contacts, import each individually and
            // create a group that contains them.
            $attributes = array('alias' => $entry[0],
                                'firstname' => $entry[1],
                                'lastname' => $entry[2],
                                'notes' => $entry[4]);
            $gid = $driver->add($attributes);
            $group = new Turba_Object_Group($driver, array_merge($attributes, array('__key' => $gid)));
            foreach ($members as $member) {
                $result = $driver->add(array('firstname' => $member, 'email' => $member));
                if ($result && !is_a($result, 'PEAR_Error')) {
                    $added = $group->addMember($result, $import_source);
                    if (is_a($added, 'PEAR_Error')) {
                        $cli->message('  ' . $added->getMessage(), 'cli.error');
                    } else {
                        $cli->message('  Added ' . $member, 'cli.success');
                    }
                }
            }
            $group->store();
        } else {
            // entry only contains one contact, import it
            $contact = array('alias' => $entry[0],
                             'firstname' => $entry[1],
                             'lastname' => $entry[2],
                             'email' => $entry[3],
                             'notes' => $entry[4]);
            $added = $driver->add($contact);
            if (is_a($added, 'PEAR_Error')) {
                $cli->message('  ' . $added->getMessage(), 'cli.error');
            } else {
                $cli->message('  Added ' . $entry[3], 'cli.success');
            }
        }
    }

    fclose($handle);
}
