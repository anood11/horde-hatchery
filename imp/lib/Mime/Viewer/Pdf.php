<?php
/**
 * The IMP_Horde_Mime_Viewer_Pdf class enables generation of thumbnails for
 * PDF attachments.
 *
 * Copyright 2008-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/gpl.html.
 *
 * @author  Michael Slusarz <slusarz@horde.org>
 * @package Horde_Mime_Viewer
 */
class IMP_Horde_Mime_Viewer_Pdf extends Horde_Mime_Viewer_Pdf
{
    /**
     * Can this driver render various views?
     *
     * @var boolean
     */
    protected $_capability = array(
        'embedded' => false,
        'forceinline' => false,
        'full' => true,
        'info' => true,
        'inline' => false,
        'raw' => false
    );

    /**
     * Return the full rendered version of the Horde_Mime_Part object.
     *
     * URL parameters used by this function:
     * <pre>
     * 'pdf_view_thumbnail' - (boolean) Output the thumbnail info.
     * </pre>
     *
     * @return array  See Horde_Mime_Viewer_Driver::render().
     */
    protected function _render()
    {
        /* Create the thumbnail and display. */
        if (!Horde_Util::getFormData('pdf_view_thumbnail')) {
            return parent::_render();
        }

        $img = $this->_getHordeImageOb(true);

        if ($img) {
            $img->resize(96, 96, true);
            $type = $img->getContentType();
            $data = $img->raw(true);
        }

        if (!$img || !$data) {
            $type = 'image/png';
            $data = file_get_contents(IMP_BASE . '/themes/graphics/mini-error.png');
        }

        return array(
            $this->_mimepart->getMimeId() => array(
                'data' => $data,
                'status' => array(),
                'type' => $type
            )
        );
    }

    /**
     * Return the rendered information about the Horde_Mime_Part object.
     *
     * @return array  See Horde_Mime_Viewer_Driver::render().
     */
    protected function _renderInfo()
    {
        /* Check to see if convert utility is available. */
        if (!$this->_getHordeImageOb(false)) {
            return array();
        }

        $status = array(_("This is a thumbnail of a PDF file attached to this message."));

        if ($GLOBALS['browser']->hasFeature('javascript')) {
            $status[] = $this->_params['contents']->linkViewJS($this->_mimepart, 'view_attach', $this->_outputImgTag(), null, null, null);
        } else {
            $status[] = Horde::link($this->_params['contents']->urlView($this->_mimepart, 'view_attach')) . $this->_outputImgTag() . '</a>';
        }

        return array(
            $this->_mimepart->getMimeId() => array(
                'data' => '',
                'status' => array(
                    array(
                        'icon' => Horde::img('mime/image.png', _("Thumbnail of attached PDF file")),
                        'text' => $status
                    )
                ),
                'type' => 'text/html; charset=' . Horde_Nls::getCharset()
            )
        );
    }

    /**
     * Return a Horde_Image object.
     *
     * @param boolean $load  Whether to load the image data.
     *
     * @return mixed  The Hore_Image object, or false on error.
     */
    protected function _getHordeImageOb($load)
    {
        if (empty($GLOBALS['conf']['image']['convert'])) {
            return false;
        }

        try {
            $img = Horde_Image::factory('Im', array('context' => array('tmpdir' => Horde::getTempdir(),
                                                                       'convert'=> $GLOBALS['conf']['image']['convert'])));
        } catch (Horde_Image_Exception $e) {
            return false;
        }
        if ($load) {
            try {
                $ret = $img->loadString(1, $this->_mimepart->getContents());
            } catch (Horde_Image_Exception $e) {
                return false;
            }
        }

        return $img;
    }

    /**
     * Output an image tag for the thumbnail.
     *
     * @return string  An image tag.
     */
    protected function _outputImgTag()
    {
        return '<img src="' . $this->_params['contents']->urlView($this->_mimepart, 'view_attach', array('params' => array('pdf_view_thumbnail' => 1))) . '" alt="' . htmlspecialchars(_("View PDF File"), ENT_COMPAT, Horde_Nls::getCharset()) . '" />';
    }

}
