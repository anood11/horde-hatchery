<?php
/**
 * References:
 * http://code.google.com/apis/gdata/docs/2.0/reference.html#Queries
 */

$mapper->connect(':controller/:action/:id');

// Local route overrides
if (file_exists(dirname(__FILE__) . '/routes.local.php')) {
    include dirname(__FILE__) . '/routes.local.php';
}
