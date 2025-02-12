<?php
/**
 * Ansel_View_GalleryRenderer::  Base class for all gallery renderers.
 *
 * Copyright 2008-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael J. Rubinsky <mrubinsk@horde.org>
 * @package Ansel
 */
abstract class Ansel_View_GalleryRenderer_Base
{
    /**
     * The Ansel_View_Gallery object that this Renderer belongs to.
     *
     * @var Ansel_View_Gallery
     */
    public $view;

    /**
     * The gallery id for this view's gallery
     *
     * @var integer
     */
    public $galleryId;   // TODO: probably can remove this (get the id from the view's gallery)

    /**
     * Gallery slug for current gallery.
     *
     * @var string
     */
    var $gallerySlug; // Ditto.

    /**
     * The current page we are viewing
     * //TODO: use __get() for these type of things...
     * @var integer
     */
    public $page = 0;

    /**
     * The display mode of the current gallery.
     * 0 == Normal
     * 1 == Group by date
     *
     * @var integer
     */
    public $mode;

    /**
     * The style definition array for this gallery.
     *
     * @var array
     */
    public $style;

    /**
     * Holds number of tiles to display per page
     *
     * @var integer
     */
    public $perpage;

    /**
     * The tile number we are starting with on the current page.
     *
     * @var integer
     */
    public $pagestart;

    /**
     * The last tile number on the current page.
     *
     * @var integer
     */
    public $pageend;

    /**
     * The total number of tiles that this view contains
     *
     * @var integer
     */
    public $numTiles;

    /**
     * The Ansel_Image or Ansel_DateGallery objects that appear on the current
     * page in the current view.
     *
     * @var array of Ansel_Image or Ansel_DateGallery objects.
     */
    public $children;

    /**
     * If we are grouping by date, this holds the currently selected date parts.
     *
     * @var array containing sufficient date parts for the current depth.
     */
    public $date = array();

    /**
     * Constructor
     *
     * @param Ansel_View_Gallery  The view object for this renderer.
     *
     * @return Ansel_View_Renderer_Gallery
     */
    public function __construct($view)
    {
        $this->view = $view;
    }

    /**
     * Initialize the renderer. This *must* be called before any attempt is made
     * to display or otherwise interact with the renderer.
     *
     * @TODO: Not sure why I didn't put this in the const'r - try moving it.
     *
     */
    public function init()
    {
        global $prefs, $conf;

        $this->galleryId = $this->view->gallery->id;
        $this->gallerySlug = $this->view->gallery->get('slug');
        $this->page = $this->view->page;

        /* Number perpage from prefs or config */
        $this->perpage = min($prefs->getValue('tilesperpage'),
                             $conf['thumbnail']['perpage']);

        /* Calculate the starting and ending images on this page */
        $this->pagestart = ($this->page * $this->perpage) + 1;

        /* Fetch the children */
        $this->fetchChildren($this->view->force_grouping);

        /* Do we have an explicit style set? If not, use the gallery's */
        if (!empty($this->view->style)) {
            $this->style = Ansel::getStyleDefinition($this->view->style);
        } else {
            $this->style = $this->view->gallery->getStyle();
        }

        /* Include any widgets */
        if (!empty($this->style['widgets']) && !$this->view->api) {
            /* Special case widgets - these are built in */
            if (array_key_exists('Actions', $this->style['widgets'])) {
                /* Don't show action widget if no actions */
                if (Horde_Auth::getAuth() ||
                    !empty($conf['report_content']['driver']) &&
                    (($conf['report_content']['allow'] == 'authenticated' && Horde_Auth::isAuthenticated()) ||
                     $conf['report_content']['allow'] == 'all')) {

                    $this->view->addWidget(Ansel_Widget::factory('Actions'));
                }
                unset($this->style['widgets']['Actions']);
            }

            // I *think* this is more efficient, iterate over the children
            // since we already have them instead of calling listImages.
            //$image_ids = $this->view->gallery->listImages($this->pagestart, $this->pagestart + $this->perpage);
            $ids = array();
            foreach ($this->children as $child) {
                if (is_a($child, 'Ansel_Image')) {
                    $ids[] = $child->id;
                }
            }
            // Gallery widgets always receive an array of image ids for
            // the current page.
            foreach ($this->style['widgets'] as $wname => $wparams) {
                $wparams = array_merge($wparams, array('images' => $ids));
                $this->view->addWidget(Ansel_Widget::factory($wname, $wparams));
            }
        }

        /* See if any renderer specific tasks need to be done as well */
        $this->_init();
    }

    /**
     * Default implementation for fetching children/images for this view.
     * Other view classes can override this if they need anything special.
     *
     */
    public function fetchChildren($noauto)
    {
        /* Total number of tiles for this gallery view */
        $this->numTiles = $this->view->gallery->countGalleryChildren(Horde_Perms::SHOW, false, $noauto);

        /* Children to display on this page */
        $this->children = $this->view->gallery->getGalleryChildren(
            Horde_Perms::SHOW,
            $this->page * $this->perpage,
            $this->perpage,
            !empty($this->view->force_grouping));

        /* The last tile number to display on the current page */
        $this->pageend = min($this->numTiles, $this->pagestart + $this->perpage - 1);
    }

    /**
     * Return the HTML for this view. Done this way so we can override this in
     * subclasses if desired.
     *
     * @return string
     */
    abstract public function html();
    abstract protected function _init();
}
