#!@php_bin@
<?php
/**
 * Copyright 2003-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @package Horde_Scheduler
 */

require_once 'Horde/Cli.php';
require_once 'Horde/Scheduler.php';

// Make sure no one runs this from the web.
if (!Horde_Cli::runningFromCLI()) {
    exit("Must be run from the command line\n");
}

// Load the CLI environment - make sure there's no time limit, init
// some variables, etc.
Horde_Cli::init();

// Get an instance of the cron scheduler.
$daemon = Horde_Scheduler::factory('cron');

// Now add some cron jobs to do, or add parsing to read a config file.
// $daemon->addTask('ls', '0,5,10,15,20,30,40 * * * *');

// Start the daemon going.
$daemon->run();
