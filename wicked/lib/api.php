<?php
/**
 * Wicked external API interface.
 *
 * This file defines Wicked's external API interface. Other applications
 * can interact with Wicked through this API.
 *
 * Copyright 2003-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @package Wicked
 */

$_services['perms'] = array(
    'args' => array(),
    'type' => 'array',
);

$_services['show'] = array(
    'link' => '%application%/display.php?page=|page|&version=|version|#|toc|',
);

$_services['list'] = array(
    'args' => array('special' => 'boolean',
                    'no_cache' => 'boolean'),
    'type' => 'array',
);

$_services['getPageInfo'] = array(
    'args' => array('pagename' => 'string'),
    'type' => 'string',
);

$_services['getMultiplePageInfo'] = array(
    'args' => array('pagenames' => '{urn:horde}stringArray'),
    'type' => 'string',
);

$_services['getPageHistory'] = array(
    'args' => array('pagename' => 'string'),
    'type' => 'string',
);

$_services['pageExists'] = array(
    'args' => array('pagename' => 'string'),
    'type' => 'string',
);

$_services['display'] = array(
    'args' => array('pagename' => 'string'),
    'type' => 'string',
);

$_services['renderPage'] = array(
    'args' => array('pagename' => 'string',
                    'format' => 'string'),
    'type' => 'string',
);

$_services['edit'] = array(
    'args' => array('pagename' => 'string',
                    'text' => 'string',
                    'changelog' => 'string',
                    'minorchange' => 'boolean'),
    'type' => 'boolean',
);

$_services['listTemplates'] = array(
    'args' => array(),
    'type' => 'array',
);

$_services['getTemplate'] = array(
    'args' => array('name' => 'string'),
    'type' => 'string',
);

$_services['saveTemplate'] = array(
    'args' => array('name' => 'string',
                    'data' => 'string'),
    'type' => 'boolean',
);

$_services['getPageSource'] = array(
    'args' => array('name' => 'string'),
    'type' => 'text',
);

$_services['getRecentChanges'] = array(
    'args' => array('days' => 'int'),
    'type' => 'array',
);

/**
 * Returns a list of available permissions.
 *
 * @return array  An array describing all available permissions.
 */
function _wicked_perms()
{
    static $perms = array();
    if (!empty($perms)) {
        return $perms;
    }

    require_once dirname(__FILE__) . '/base.php';
    global $wicked;

    $perms['tree']['wicked']['pages'] = array();
    $perms['title']['wicked:pages'] = _("Pages");

    $perms['tree']['wicked']['pages'] = array();
    $perms['title']['wicked:pages'] = _("Pages");

    $perms['tree']['wicked']['pages']['AllPages'] = false;
    $perms['title']['wicked:pages:AllPages'] = 'AllPages';

    $perms['tree']['wicked']['pages']['LeastPopular'] = false;
    $perms['title']['wicked:pages:LeastPopular'] = 'LeastPopular';

    $perms['tree']['wicked']['pages']['MostPopular'] = false;
    $perms['title']['wicked:pages:MostPopular'] = 'MostPopular';

    $perms['tree']['wicked']['pages']['RecentChanges'] = false;
    $perms['title']['wicked:pages:RecentChanges'] = 'RecentChanges';

    $pages = $wicked->getPages();
    if (is_a($pages, 'PEAR_Error')) {
        return $pages;
    }

    foreach ($pages as $pagename) {
        $pageId = $wicked->getPageId($pagename);
        $perms['tree']['wicked']['pages'][$pageId] = false;
        $perms['title']['wicked:pages:' . $pageId] = $pagename;
    }

    ksort($perms['tree']['wicked']['pages']);
    return $perms;
}

/**
 * Returns a list of available pages.
 *
 * @param boolean $special Include special pages
 * @param boolean $no_cache Always retreive pages from backed
 *
 * @return array  An array of all available pages.
 */
function _wicked_list($special = true, $no_cache = false)
{
    require_once dirname(__FILE__) . '/base.php';
    return $GLOBALS['wicked']->getPages($special, $no_cache);
}

/**
 * Return basic page information.
 *
 * @param string $pagename Page name
 *
 * @return array  An array of page parameters.
 */
function _wicked_getPageInfo($pagename)
{
    require_once dirname(__FILE__) . '/base.php';

    $page = Page::getPage($pagename);
    if (is_a($page, 'PEAR_Error')) {
        return $page;
    }

    return array(
        'page_majorversion' => $page->_page['page_majorversion'],
        'page_minorversion' => $page->_page['page_minorversion'],
        'page_checksum' => md5($page->getText()),
        'version_created' => $page->_page['version_created'],
        'change_author' => $page->_page['change_author'],
        'change_log' => $page->_page['change_log'],
    );
}

/**
 * Return basic information for multiple pages.
 *
 * @param array $pagenames Page names
 *
 * @return array  An array of arrays of page parameters.
 */
function _wicked_getMultiplePageInfo($pagenames = array())
{
    require_once dirname(__FILE__) . '/base.php';

    if (empty($pagenames)) {
        $pagenames = $GLOBALS['wicked']->getPages(false);
        if (is_a($pagenames, 'PEAR_Error')) {
            return $pagenames;
        }
    }

    $info = array();

    foreach ($pagenames as $pagename) {
        $page = Page::getPage($pagename);
        if (is_a($page, 'PEAR_Error')) {
            return $page;
        }
        $info[$pagename] = array(
            'page_majorversion' => $page->_page['page_majorversion'],
            'page_minorversion' => $page->_page['page_minorversion'],
            'page_checksum' => md5($page->getText()),
            'version_created' => $page->_page['version_created'],
            'change_author' => $page->_page['change_author'],
            'change_log' => $page->_page['change_log']
        );
    }

    return $info;
}

/**
 * Return page history.
 *
 * @param string $pagename Page name
 *
 * @return array  An array of page parameters.
 */
function _wicked_getPageHistory($pagename)
{
    require_once dirname(__FILE__) . '/base.php';

    $page = Page::getPage($pagename);
    if (is_a($page, 'PEAR_Error')) {
        return $page;
    }

    $summaries = $GLOBALS['wicked']->getHistory($pagename);
    if (is_a($summaries, 'PEAR_Error')) {
        return $summaries;
    }

    foreach ($summaries as $i => $summary) {
        $summaries[$i]['page_checksum'] = md5($summary['page_text']);
        unset($summaries[$i]['page_text']);
    }

    return $summaries;
}

/**
 * Chech if a page exists
 *
 * @param string $pagename Page name
 *
 * @return boolean
 */
function _wicked_pageExists($pagename)
{
    require_once dirname(__FILE__) . '/base.php';
    return $GLOBALS['wicked']->pageExists($pagename);
}

/**
 * Returns a rendered wiki page.
 *
 * @param string $pagename Page to display
 *
 * @return array  Page without CSS link
 */
function _wicked_display($pagename)
{
    require_once dirname(__FILE__) . '/base.php';

    $page = Page::getPage($pagename);
    if (is_a($page, 'PEAR_Error')) {
        return $page;
    }

    $GLOBALS['wicked']->logPageView($page->pageName());

    return $page->displayContents(false);
}

/**
 * Returns a rendered wiki page.
 *
 * @param string $pagename Page to display
 * @param string $format Format to render page to (Plain, XHtml)
 *
 * @return array  Rendered page
 */
function _wicked_renderPage($pagename, $format = 'Plain')
{
    require_once dirname(__FILE__) . '/base.php';

    $page = Page::getPage($pagename);
    if (is_a($page, 'PEAR_Error')) {
        return $page;
    }

    $wiki = &$page->getProcessor();
    $content = $wiki->transform($page->getText(), $format);
    if (is_a($content, 'PEAR_Error')) {
        return $content;
    }

    $GLOBALS['wicked']->logPageView($page->pageName());
    return $content;
}

/**
 * Updates content of a wiki page. If the page does not exist it is
 * created.
 *
 * @param string $pagename Page to edit
 * @param string $text Page content
 * @param string $changelog Description of the change
 * @param boolean $minorchange True if this is a minor change
 *
 * @return boolean | PEAR_Error True on success, PEAR_Error on failure.
 */
function _wicked_edit($pagename, $text, $changelog = '', $minorchange = false)
{
    require_once dirname(__FILE__) . '/base.php';

    $page = Page::getPage($pagename);
    if (is_a($page, 'PEAR_Error')) {
        return $page;
    }
    if (!$page->allows(WICKED_MODE_EDIT)) {
        return PEAR::RaiseError(sprintf(_("You don't have permission to edit \"%s\"."), $pagename));
    }
    if ($GLOBALS['conf']['wicked']['require_change_log'] &&
        empty($changelog)) {
        return PEAR::raiseError(_("You must provide a change log."));
    }

    $content = $page->getText();
    if (is_a($content, 'PEAR_Error')) {
        // Maybe the page does not exists, if not create it
        if ($GLOBALS['wicked']->pageExists($pagename)) {
            return $content;
        } else {
            return $GLOBALS['wicked']->newPage($pagename, $text);
        }
    }

    if (trim($text) == trim($content)) {
        return PEAR::raiseError(_("No changes made"));
    }

    $result = $page->updateText($text, $changelog, $minorchange);
    if (is_a($result, 'PEAR_Error')) {
        return $result;
    } else {
        return true;
    }
}

/**
 * Get a list of templates provided by Wicked.  A template is any page whose
 * name begins with "Template"
 *
 * @return mixed  Array on success; PEAR_Error on failure
 */
function _wicked_listTemplates()
{
    require_once dirname(__FILE__) . '/base.php';
    global $wicked;
    $templates = $wicked->getMatchingPages('Template', WICKED_PAGE_MATCH_ENDS);
    $list = array(array('category' => _("Wiki Templates"),
                        'templates' => array()));
    foreach ($templates as $page) {
        $list[0]['templates'][] = array('id' => $page['page_name'],
                                        'name' => $page['page_name']);
    }
    return $list;
}

/**
 * Get a template specified by its name.  This is effectively an alias for
 * getPageSource() since Wicked templates are also normal pages.
 * Wicked templates are pages that include "Template" at the beginning of the
 * name.
 *
 * @param string $name  The name of the template to fetch
 *
 * @return mixed        String of template data on success; PEAR_Error on fail
 */
function _wicked_getTemplate($name)
{
    return _wicked_getPageSource($name);
}

/**
 * Get the wiki source of a page specified by its name.
 *
 * @param string $name  The name of the page to fetch
 * @param string $version  Page version
 *
 * @return mixed        String of page data on success; PEAR_Error on fail
 */
function _wicked_getPageSource($pagename, $version = null)
{
    require_once dirname(__FILE__) . '/base.php';
    global $wicked;

    $page = Page::getPage($pagename, $version);

    if (!$page->allows(WICKED_MODE_CONTENT)) {
        return PEAR::raiseError(_("Permission denied."));
    }

    if (!$page->isValid()) {
        return PEAR::raiseError(_("Invalid page requested."));
    }

    return $page->getText();
}

/**
 * Process a completed template to update the named Wiki page.  This method is
 * basically a passthrough to _wicked_edit()
 *
 * @param string $name   Name of the new or modified page
 * @param string $data   Text content of the populated template
 *
 * @return mixed         True on success; PEAR_Error on failure
 */
function _wicked_saveTemplate($name, $data)
{
    return _wicked_edit($name, $data, 'Template Auto-fill', false);
}

/**
 * Returns the most recently changed pages.
 *
 * @param integer $days  The number of days to look back.
 *
 * @return mixed  An array of pages, or PEAR_Error on failure.
 */
function _wicked_getRecentChanges($days = 3)
{
    require_once dirname(__FILE__) . '/base.php';

    $summaries = $GLOBALS['wicked']->getRecentChanges($days);
    if (is_a($summaries, 'PEAR_Error')) {
        return $summaries;
    }

    $info = array();
    foreach ($summaries as $page) {
        $info[$page['page_name']] = array(
            'page_majorversion' => $page['page_majorversion'],
            'page_minorversion' => $page['page_minorversion'],
            'page_checksum' => md5($page['page_text']),
            'version_created' => $page['version_created'],
            'change_author' => $page['change_author'],
            'change_log' => $page['change_log'],
        );
    }

    return $info;
}
