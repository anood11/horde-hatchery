<?php

$block_name = _("Menu List");
$block_type = 'tree';

/**
 * @package Horde_Block
 */
class Horde_Block_turba_tree_menu extends Horde_Block {

    var $_app = 'turba';

    function _buildTree(&$tree, $indent = 0, $parent = null)
    {
        global $registry;

        require_once dirname(__FILE__) . '/../base.php';

        $browse = Horde::applicationUrl('browse.php');
        $add = Horde::applicationUrl('add.php');
        $icondir = $registry->getImageDir() . '/menu';

        if ($GLOBALS['addSources']) {
            $tree->addNode($parent . '__new',
                           $parent,
                           _("New Contact"),
                           $indent + 1,
                           false,
                           array('icon' => 'new.png',
                                 'icondir' => $icondir,
                                 'url' => $add));

            foreach ($GLOBALS['addSources'] as $addressbook => $config) {
                $tree->addNode($parent . $addressbook . '__new',
                               $parent . '__new',
                               sprintf(_("in %s"), $config['title']),
                               $indent + 2,
                               false,
                               array('icon' => 'new.png',
                                     'icondir' => $icondir,
                                     'url' => Horde_Util::addParameter($add, array('source' => $addressbook))));
            }
        }

        foreach (Turba::getAddressBooks() as $addressbook => $config) {
            if (!empty($config['browse'])) {
                $tree->addNode($parent . $addressbook,
                               $parent,
                               $config['title'],
                               $indent + 1,
                               false,
                               array('icon' => 'browse.png',
                                     'icondir' => $icondir,
                                     'url' => Horde_Util::addParameter($browse, array('source' => $addressbook))));
            }
        }

        $tree->addNode($parent . '__search',
                       $parent,
                       _("Search"),
                       $indent + 1,
                       false,
                       array('icon' => 'search.png',
                             'icondir' => $registry->getImageDir('horde'),
                             'url' => Horde::applicationUrl('search.php')));
    }

}
