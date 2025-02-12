<?php

define('AUTH_HANDLER', true);
require $CONTENT_DIR . 'lib/Objects/Object.php';
require $CONTENT_DIR . 'lib/Objects/ObjectMapper.php';

$options = array(
    new Horde_Argv_Option('-m', '--object-id', array('type' => 'int')),
);
$parser = new Horde_Argv_Parser(array('optionList' => $options));
list($opts, $positional) = $parser->parseArgs();

if (!$opts->object_id) {
    throw new InvalidArgumentException('object_id is required');
}

$m = new Content_ObjectMapper;
if ($m->delete($opts->object_id)) {
    echo 'Deleted object with id ' . $opts->object_id . ".\n";
    exit(0);
} else {
    echo 'Object #' . $opts->object_id . " not found.\n";
    exit(1);
}
