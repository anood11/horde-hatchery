#!/usr/bin/php
<?php
/*
 * This script accepts a MIME message on standard input and creates a new
 * wiki page from it.  It can also append the e-mail to the end of another
 * page.
 */

@define('AUTH_HANDLER', true);
@define('HORDE_BASE', dirname(__FILE__) . '/../..');

// Do CLI checks and environment setup first.
require_once HORDE_BASE . '/lib/core.php';

$keepHeaders = array('From', 'To', 'Subject', 'Cc', 'Date');
$dateFormat = "F j, Y";

// Make sure no one runs this from the web.
if (!Horde_Cli::runningFromCLI()) {
    exit("Must be run from the command line\n");
}

// Load the CLI environment - make sure there's no time limit, init
// some variables, etc.
Horde_Cli::init();
$cli = Horde_Cli::singleton();

@define('WICKED_BASE', HORDE_BASE . '/wicked');
require_once WICKED_BASE . '/lib/base.php';

$text = '';
while (!feof(STDIN)) {
    $text .= fgets(STDIN, 512);
}

$message = Horde_Mime_Part::parseMessage($text);
if (is_a($message, 'PEAR_Error')) {
    $cli->fatal(sprintf(_("Error parsing MIME message: %s\n"),
                        $message->getMessage()));
}

if (preg_match("/^(.*?)\r?\n\r?\n/s", $text, $matches)) {
    $hdrText = $matches[1];
} else {
    $hdrText = $text;
}
$headers = Horde_Mime_Headers::parseHeaders($hdrText);

// Format the message into a pageBody.
$pageBody = "";
foreach ($headers as $name => $vals) {
    foreach ($keepHeaders as $kh) {
        if (!strcasecmp($kh, $name)) {
            if (is_array($vals)) {
                foreach ($vals as $val) {
                    $pageBody .= "'''" . $name . ":''' " . $val . " _\n";
                }
            } else {
                $pageBody .= "'''" . $name . ":''' " . $vals . " _\n";
            }
        }
    }
}
$pageBody .= "\n\n";

// Create a new name for the page.
$pageName = headerValue($headers, 'Subject');
if (empty($pageName)) {
    $pageName = 'no subject';
}
$pageName .= " -- ";

$msgFrom = headerValue($headers, 'From');
if (preg_match('/^\s*"?(.*?)"?\s*<.*>/', $msgFrom, $matches)) {
    $msgFrom = $matches[1];
} elseif (preg_match('/<(.*)>/', $msgFrom, $matches)) {
    $msgFrom = $matches[1];
}
if (!empty($msgFrom)) {
    $pageName .= $msgFrom . " ";
}

$msgDate = headerValue($headers, 'Date');
if (empty($msgDate)) {
    $time = time();
} else {
    $time = strtotime($msgDate);
}
$pageName .= date($dateFormat, $time);

// We could have two messages with the same name, so append a number.
if ($wicked->pageExists($pageName)) {
    $counter = 2;
    while ($wicked->pageExists($pageName . " (" . $counter . ")")) {
        $counter++;
    }
    $pageName .= " (" . $counter . ")";
}

// Look for a text part.
// FIXME: this is _extremely_ crude.
if ($message->getType() == 'text/plain') {
    $pageBody .= $message->getContents();
} elseif ($message->getType() == 'multipart/alternative') {
    foreach ($message->getParts() as $part) {
        if ($part->getType() == 'text/plain') {
            $pageBody .= $part->getContents();
            break;
        }
    }
} else {
    $pageBody .= "[ Could not render body of message. ]";
}

$pageBody .= "\n";

if (is_null($pageName)) {
    $pageName = "EmailMessage" . ucfirst(md5(uniqid('wicked', true)));
}

$res = $wicked->newPage($pageName, $pageBody);
if (is_a($res, 'PEAR_Error')) {
    $cli->fatal(sprintf(_("Error creating new page: %s"), $res->getMessage()));
}

exit(0);

function headerValue($headers, $name)
{
    $val = null;
    foreach ($headers as $headerName => $headerVal) {
        if (!strcasecmp($name, $headerName)) {
            if (is_array($headerVal)) {
                $thisVal = join(', ', $headerVal);
            } else {
                $thisVal = $headerVal;
            }
            if (is_null($val)) {
                $val = $thisVal;
            } else {
                $val .= ", " . $thisVal;
            }
        }
    }
    return $val;
}
