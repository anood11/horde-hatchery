<?php
/**
 * This class defines the Horde_Image:: API, and also provides some
 * utility functions, such as generating highlights of a color.
 *
 * Copyright 2002-2009 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author  Chuck Hagenbuch <chuck@horde.org>
 * @author  Michael J. Rubinsky <mrubinsk@horde.org>
 *
 * @package Horde_Image
 *
 * @TODO: - Can we depend on the Horde_Util:: class or some other solution needed?
 *        - Exceptions
 */
class Horde_Image_Base
{
    /**
     * Background color.
     *
     * @var string
     */
    protected $_background = 'white';

    /**
     * Observers.
     *
     * @var array
     */
    protected $_observers = array();

    /**
     * Capabilites of this driver.
     *
     * @var array
     */
    protected $_capabilities = array();

    /**
     * The current image data.
     *
     * @var string
     */
    protected $_data = '';

    /**
     * The current image id.
     *
     * @TODO: Do we *really* need an image id...and if so, we can make the
     *        parameter optional in the methods that take one?
     *
     * @var string
     */
    protected $_id = '';

    /**
     * Logger
     */
    protected $_logger;

    /**
     * The current width of the image data.
     *
     * @var integer
     */
    protected $_width = 0;

    /**
     * The current height of the image data.
     *
     * @var integer
     */
    protected $_height = 0;

    /**
     * A directory for temporary files.
     *
     * @var string
     */
    protected $_tmpdir;

    /**
     * Array containing available Effects
     *
     * @var array
     */
    protected $_loadedEffects = array();

    /**
     * What kind of images should ImageMagick generate? Defaults to 'png'.
     *
     * @var string
     */
    protected $_type = 'png';

    /**
     * Constructor.
     *
     * @param string $rgb  The base color for generated pixels/images.
     */
    protected function __construct($params, $context = array())
    {

        if (empty($context['tmpdir'])) {
            throw new InvalidArgumentException('A path to a temporary directory is required.');
        }

        $this->_tmpdir = $context['tmpdir'];
        if (isset($params['width'])) {
            $this->_width = $params['width'];
        }
        if (isset($params['height'])) {
            $this->_height = $params['height'];
        }
        if (!empty($params['type'])) {
            $this->_type = $params['type'];
        }

        if (!empty($context['logger'])) {
            $this->_logger = $context['logger'];
        }

        $this->_background = isset($params['background']) ? $params['background'] : 'white';
    }

    /**
     * Getter for the capabilities array
     *
     * @return array
     */
    public function getCapabilities()
    {
        return $this->_capabilities;
    }

    /**
     * Check the existence of a particular capability.
     *
     * @param string $capability  The capability to check for.
     *
     * @return boolean
     */
    public function hasCapability($capability)
    {
        return in_array($capability, $this->_capabilities);
    }

    /**
     * Generate image headers.
     */
    public function headers()
    {
        header('Content-type: ' . $this->getContentType());
    }

    /**
     * Return the content type for this image.
     *
     * @return string  The content type for this image.
     */
    public function getContentType()
    {
        return 'image/' . $this->_type;
    }

    /**
     * Getter for the simplified image type.
     *
     * @return string  The type of image (png, jpg, etc...)
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * Draw a shaped point at the specified (x,y) point. Useful for
     * scatter diagrams, debug points, etc. Draws squares, circles,
     * diamonds, and triangles.
     *
     * @param integer $x     The x coordinate of the point to brush.
     * @param integer $y     The y coordinate of the point to brush.
     * @param string $color  The color to brush the point with.
     * @param string $shape  What brush to use? Defaults to a square.
     */
    public function brush($x, $y, $color = 'black', $shape = 'square')
    {
        switch ($shape) {
        case 'triangle':
            $verts[0] = array('x' => $x + 3, 'y' => $y + 3);
            $verts[1] = array('x' => $x, 'y' => $y - 3);
            $verts[2] = array('x' => $x - 3, 'y' => $y + 3);
            $this->polygon($verts, $color, $color);
            break;

        case 'circle':
            $this->circle($x, $y, 3, $color, $color);
            break;

        case 'diamond':
            $verts[0] = array('x' => $x - 3, 'y' => $y);
            $verts[1] = array('x' => $x, 'y' => $y + 3);
            $verts[2] = array('x' => $x + 3, 'y' => $y);
            $verts[3] = array('x' => $x, 'y' => $y - 3);
            $this->polygon($verts, $color, $color);
            break;

        case 'square':
        default:
            $this->rectangle($x - 2, $y - 2, 4, 4, $color, $color);
            break;
        }
    }

    /**
     * Reset the image data to defaults.
     */
    public function reset()
    {
        $this->_data = '';
        $this->_id = '';
        $this->_width = null;
        $this->_height = null;
        $this->_background = 'white';
    }

    /**
     * Get the height and width of the current image data.
     *
     * @return array  An hash with 'width' containing the width,
     *                'height' containing the height of the image.
     */
    public function getDimensions()
    {
        // Check if we know it already
        if ($this->_width == 0 && $this->_height == 0) {
            $tmp = $this->toFile();
            $details = @getimagesize($tmp);
            list($this->_width, $this->_height) = $details;
            unlink($tmp);
        }

        return array('width' => $this->_width,
                     'height' => $this->_height);
    }

    /**
     * Load the image data from a string.
     *
     * @param string $id          An arbitrary id for the image.
     * @param string $image_data  The data to use for the image.
     */
    public function loadString($id, $image_data)
    {
        if ($id != $this->_id) {
            $this->reset();
            $this->_data = $image_data;
            $this->_id = $id;
        }
    }

    /**
     * Load the image data from a file.
     *
     * @param string $filename  The full path and filename to the file to load
     *                          the image data from. The filename will also be
     *                          used for the image id.
     *
     * @return mixed  True if successful or already loaded, PEAR Error if file
     *                does not exist or could not be loaded.
     * @throws Horde_Image_Exception
     */
    public function loadFile($filename)
    {
        if ($filename != $this->_id) {
            $this->reset();
            if (!file_exists($filename)) {
                throw new Horde_Image_Exception(sprintf("The image file, %s, does not exist.", $filename));
            }
            if ($this->_data = file_get_contents($filename)) {
                $this->_id = $filename;
            } else {
                throw new Horde_Image_Exception(sprintf("Could not load the image file %s", $filename));
            }
        }

        return true;
    }

    /**
     * Ouputs image data to file.  If $data is false, outputs current
     * image data after performing any pending operations on the data.
     * If $data contains raw image data, outputs that data to file without
     * regard for $this->_data
     *
     * @param mixed  String of binary image data | false
     *
     * @return string  Path to temporary file.
     */
    public function toFile($data = false)
    {
        $tmp = Horde_Util::getTempFile('img', false, $this->_tmpdir);
        $fp = @fopen($tmp, 'wb');
        fwrite($fp, $data ? $data : $this->raw());
        fclose($fp);
        return $tmp;
    }

    /**
     * Display the current image.
     */
    public function display()
    {
        $this->headers();
        echo $this->raw();
    }

    /**
     * Returns the raw data for this image.
     *
     * @param boolean $convert  If true, the image data will be returned in the
     *                          target format, independently from any image
     *                          operations.
     *
     * @return string  The raw image data.
     */
    public function raw($convert = false)
    {
        return $this->_data;
    }

    /**
     * Attempts to apply requested effect to this image.  If the
     * effect cannot be found a PEAR_Error is returned.
     *
     * @param string $type    The type of effect to apply.
     * @param array $params   Any parameters for the effect.
     *
     * @return mixed  true on success | PEAR_Error on failure.
     */
    public function addEffect($type, $params)
    {
        $class = str_replace('Horde_Image_', '', get_class($this));
        $effect = Horde_Image_Effect::factory($type, $class, $params);
        $effect->setImageObject($this);
        return $effect->apply();
    }

    /**
     * Load a list of available effects for this driver.
     */
    public function getLoadedEffects()
    {
        if (!count($this->_loadedEffects)) {
            $class = str_replace('Horde_Image_', '', get_class($this));
            $this->_loadedEffects = array();
            // First, load the driver-agnostic Effects.
            $path = dirname(__FILE__) . '/Effect/';
            if (is_dir($path)) {
                if ($handle = opendir($path)) {
                    while (($file = readdir($handle)) !== false) {
                        if (substr($file, -4, 4) == '.php') {
                            $this->_loadedEffects[] = substr($file, 0, strlen($file) - 4);
                        }
                    }
                }
            }

            // Driver specific effects.
            $path = $path . $class;
            if (is_dir($path)) {
                if ($handle = opendir($path)) {
                    while (($file = readdir($handle)) !== false) {
                        if (substr($file, -4, 4) == '.php') {
                            $this->_loadedEffects[] = substr($file, 0, strlen($file) - 4);
                        }
                    }
                }
            }
        }

        return $this->_loadedEffects;
    }

    /**
     * Apply any effects in the effect queue.
     */
    public function applyEffects()
    {
        $this->raw();
    }

    public function getTmpDir()
    {
        return $this->_tmpdir;
    }

    /**
     * Utility function to zero out cached geometry information. Shouldn't
     * really be called from client code, but is needed since Effects may need
     * to clear these.
     *
     */
    public function clearGeometry()
    {
        $this->_height = 0;
        $this->_width = 0;
    }

    protected function _logDebug($message)
    {
        if (!empty($this->_logger)) {
            $this->_logger->debug($message);
        }
    }

    protected function _logErr($message)
    {
        if (!empty($this->_logger)) {
            $this->_logger->err($message);
        }
    }

}
