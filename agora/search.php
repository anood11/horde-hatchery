<?php
/**
 * The Agora search page.
 *
 * Copyright 2005-2009 The Horde Project (http://www.horde.org/)
 *
 * $Horde: agora/search.php,v 1.27 2009-12-10 17:42:30 jan Exp $
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Jason Felice <jason.m.felice@gmail.com>
 */

define('AGORA_BASE', dirname(__FILE__));
require_once AGORA_BASE . '/lib/base.php';
require_once AGORA_BASE . '/lib/Forms/Search.php';

/* Set up the forums object. */
$scope = Horde_Util::getGet('scope', 'agora');
$messages = &Agora_Messages::singleton($scope);
$vars = Horde_Variables::getDefaultVariables();
$form = new SearchForm($vars, $scope);
$thread_page = Horde_Util::getFormData('thread_page');

$view = new Agora_View();

if ($form->isSubmitted() || $thread_page != null) {

    $form->getInfo($vars, $info);

    if (!empty($info['keywords'])) {
        $info['keywords'] = preg_split('/\s+/', $info['keywords']);
    }

    $sort_by = Agora::getSortBy('thread');
    $sort_dir = Agora::getSortDir('thread');
    $thread_per_page = $prefs->getValue('thread_per_page');
    $thread_start = $thread_page * $thread_per_page;

    $searchResults = $messages->search($info, $sort_by, $sort_dir, $thread_start, $thread_per_page);
    if ($searchResults instanceof PEAR_Error) {
        $notification->push($searchResults->getMessage(), 'horde.error');
        header('Location:' . Horde::applicationUrl('search.php'));
        exit;
    }

    if ($searchResults['total'] > count($searchResults['results'])) {
        $pager_ob = new Horde_Ui_Pager('thread_page', $vars, array('num' => $searchResults['total'], 'url' => 'search.php', 'perpage' => $thread_per_page));
        foreach ($info as $key => $val) {
            if ($val) {
                if ($key == 'keywords') {
                    $val = implode(' ', $val);
                }
                $pager_ob->preserve($key, $val);
            }
        }
        $view->pager_link = $pager_ob->render();
    }

    $view->searchTotal = number_format($searchResults['total']);
    $view->searchResults = $searchResults['results'];
}

$view->menu = Agora::getMenu('string');
$view->notify = Horde_Util::bufferOutput(array($notification, 'notify'), array('listeners' => 'status'));
$view->searchForm = Horde_Util::bufferOutput(array($form, 'renderActive'), null, $vars, 'search.php', 'get');

$title = _("Search Forums");
require AGORA_TEMPLATES . '/common-header.inc';
echo $view->render('search.html.php');
require $registry->get('templates', 'horde') . '/common-footer.inc';
