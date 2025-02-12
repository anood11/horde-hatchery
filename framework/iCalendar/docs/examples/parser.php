#!/usr/bin/php
<?php
/**
 * $Horde: framework/iCalendar/docs/examples/parser.php,v 1.5 2009/06/10 19:57:56 slusarz Exp $
 *
 * Takes a filename on the command line and parses it, displaying what it
 * finds. Intended for use in debugging the iCalendar parser's behavior with
 * problem files or for adding new features.
 *
 * @package Horde_iCalendar
 */

require_once 'Horde/Cli.php';
require_once 'Horde/iCalendar.php';

// This only works on the command line.
if (!Horde_Cli::runningFromCLI()) {
    exit("Must be run from the command line\n");
}

// Load the CLI environment - make sure there's no time limit, init
// some variables, etc.
Horde_Cli::init();
$cli = &Horde_Cli::singleton();

if (empty($argv[1])) {
    $cli->fatal('No file specified on the command line.');
}

$input_file = $argv[1];
if (!file_exists($input_file)) {
    $cli->fatal($input_file . ' does not exist.');
}
if (!is_readable($input_file)) {
    $cli->fatal($input_file . ' is not readable.');
}

$cli->writeln($cli->blue('Parsing ' . $input_file . ' ...'));

$data = file_get_contents($input_file);
$ical = new Horde_iCalendar();
if (!$ical->parseVCalendar($data)) {
    $cli->fatal('iCalendar parsing failed.');
}

$cli->writeln($cli->green('Parsing successful, found ' . $ical->getComponentCount() . ' component(s).'));

$components = $ical->getComponents();
foreach ($components as $component) {
    var_dump($component->toHash(true));
}
