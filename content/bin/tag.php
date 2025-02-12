<?php

require $CONTENT_DIR . 'lib/Tagger.php';

$options = array(
    new Horde_Argv_Option('-u', '--user-id', array('type' => 'int')),
    new Horde_Argv_Option('-o', '--object-id', array('type' => 'int')),
);
$parser = new Horde_Argv_Parser(array('optionList' => $options));
list($opts, $tags) = $parser->parseArgs();
if (!$opts->user_id || !$opts->object_id) {
    throw new InvalidArgumentException('user-id and object-id are both required');
}
if (!count($tags)) {
    throw new InvalidArgumentException('List at least one tag to add.');
}

/* @TODO Switch to using the TagController */
$tagger = new Content_Tagger();
$tagger->tag($opts->user_id, $opts->object_id, $tags);
exit(0);
