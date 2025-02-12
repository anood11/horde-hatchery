<?php

define('AUTH_HANDLER', true);
require $CONTENT_DIR . 'lib/Tags/Tag.php';
require $CONTENT_DIR . 'lib/Tags/TagMapper.php';

$parser = new Horde_Argv_Parser();
list($opts, $tags) = $parser->parseArgs();
if (!count($tags)) {
    throw new InvalidArgumentException('List at least tag to delete.');
}

$m = new Content_TagMapper;
foreach ($tags as $tag) {
    $t = $m->find(Horde_Rdo::FIND_FIRST, array('tag_name' => $tag));
    if (!$t) {
        echo "$tag doesn't seem to exist, skipping it.\n";
        continue;
    }
    if ($t->delete()) {
        echo "Delete tag '$tag' (#".$t->tag_id.")\n";
        continue;
    } else {
        echo "Failed to delete '$tag'\n";
        exit(1);
    }
}
exit(0);
