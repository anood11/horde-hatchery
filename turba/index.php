<?php
/**
 * Turba index page.
 *
 * Copyright 2000-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (ASL).  If you did
 * did not receive this file, see http://www.horde.org/licenses/asl.php.
 *
 * @author Chuck Hagenbuch <chuck@horde.org>
 */

require_once dirname(__FILE__) . '/lib/base.php';
require TURBA_BASE . '/' . ($browse_source_count
                            ? basename($prefs->getValue('initial_page'))
                            : 'search.php');
