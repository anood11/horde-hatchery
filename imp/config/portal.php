<?php
/**
 * Dynamic portal configuration page.
 *
 * Format: An array named $dimp_block_list
 *     KEY: Block label text
 *     VALUE: An array with the following entries:
 *            'ob' => The Horde_Block object to display
 *
 *            These entries are optional and will only be used if you need
 *            to customize the portal output:
 *            'class' => A CSS class to assign to the containing block.
 *                       Defaults to "headerbox".
 *            'domid' => A DOM ID to assign to the containing block
 *            'tag' => A tag name to add to the template array. Allows
 *                     the use of <if:block.tag> in custom template files.
 *
 * $Id: fc35863e634219d65dde5db35d9d72310567741e $
 */

$collection = new Horde_Block_Collection();
$dimp_block_list = array();

// Show a folder summary of the mailbox.  All polled folders are displayed.
$dimp_block_list[_("Folder Summary")] = array(
    'ob' => new IMP_Block_Foldersummary(array())
);

// Alternate DIMP block - shows details of 'msgs_shown' number of the most
// recent unseen messages.
//$dimp_block_list[_("Newest Unseen Messages")] = array(
//    'ob' => new IMP_Block_Newmail(array('msgs_shown' => 3))
//);

// Show a contact search box.
// Must include 'turba' in $conf['menu']['apps']
$dimp_block_list[$collection->getName('turba', 'minisearch')] = array(
    'ob' => $collection->getBlock('turba', 'minisearch', array())
);

// Display calendar events
// Must include 'kronolith' in $conf['menu']['apps']
$dimp_block_list[$collection->getName('kronolith', 'summary')] = array(
    'ob' => $collection->getBlock('kronolith', 'summary', array())
);
