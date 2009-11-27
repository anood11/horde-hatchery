<?php
/**
 * Tests for the Horde_Image package. Designed to return image data in response
 * to an <img> tag on another page. Set the test parameter to one of the
 * cases below.
 *  <img src="imtest.php?driver=im&test=polaroid" />
 *
 * @package Horde_Image
 */
define('HORDE_BASE', '/var/www/html/horde');
$horde_authentication = 'none';
require_once HORDE_BASE . '/lib/base.php';
$GLOBALS['conf']['sql']['adapter'] = $GLOBALS['conf']['sql']['phptype'] == 'mysqli' ? 'mysqli' : 'pdo_' . $GLOBALS['conf']['sql']['phptype'];
$db = Horde_Db_Adapter::factory($GLOBALS['conf']['sql']);

// Putting these here so they don't interfere with timing/memory data when
// profiling.
$driver = Horde_Util::getFormData('driver', 'Im');
$test = Horde_Util::getFormData('test');
$convert = trim(`which convert`);
$handler = new Horde_Log_Handler_Stream(fopen('/tmp/imagetest.log','a+'));
$logger = new Horde_Log_Logger($handler);

switch ($test) {
case 'testInitialState':
    // Solid blue background color - basically tests initial state of the
    // Horde_Image object.
    $time = xdebug_time_index();
    $image = getImageObject(array('height' => '200',
                                  'width' => '200',
                                  'background' => 'blue'));
    $image->display();
    $time = xdebug_time_index() - $time;
    $memory = xdebug_peak_memory_usage();
    logThis($test, $time, $memory);
    exit;
    break;

case 'testInitialStateAfterLoad':
    // Test loading an image from file directly.
    $image = getImageObject(array('filename' => 'img1.jpg'));
    $image->display();
    break;

case 'testResize':
    $time = xdebug_time_index();
    $image = getImageObject(array('filename' => 'img2.jpg'));
    $image->resize(150, 150);
    $image->display();

    $time = xdebug_time_index() - $time;
    $memory = xdebug_peak_memory_usage();
    logThis($test, $time, $memory);
    break;

case 'testPrimitivesTransparentBG':
    $time = xdebug_time_index();

    // Transparent PNG image with various primitives.
    $image = getImageObject(array('height' => '200',
                                  'width' => '200',
                                  'background' => 'none'));
    $image->rectangle(30, 30, 100, 60, 'black', 'yellow');
    $image->roundedRectangle(30, 30, 100, 60, 15, 'black', 'red');
    $image->circle(30, 30, 30, 'black', 'blue');
    $image->display();
    $time = xdebug_time_index() - $time;
    $memory = xdebug_peak_memory_usage();
    logThis($test, $time, $memory);
    break;

case 'testTransparentPrimitivesReversed':
    // Transparent PNG image with various primitives.
    // Circle should appear *under* the rectangles...
    $image = getImageObject(array('height' => '200',
                                  'width' => '200',
                                  'background' => 'none'));
    $image->circle(30, 30, 30, 'black', 'blue');
    $image->rectangle(30, 30, 100, 60, 'black', 'yellow');
    $image->roundedRectangle(30, 30, 100, 60, 15, 'black', 'red');
    $image->display();
    break;

case 'testTransparentBGWithBorder':
    $time = xdebug_time_index();
    // Same as above, but with border.
     $image = getImageObject(array('height' => '200',
                                   'width' => '200',
                                   'background' => 'none'));
    $image->rectangle(30, 30, 100, 60, 'black', 'yellow');
    $image->roundedRectangle(30, 30, 100, 60, 15, 'black', 'red');
    $image->circle(30, 30, 30, 'black', 'blue');
    $image->addEffect('border', array('bordercolor' => 'blue',
                                      'borderwidth' => 1));
    $image->display();
    $time = xdebug_time_index() - $time;
    $memory = xdebug_peak_memory_usage();
    logThis($test, $time, $memory);
    break;


case 'testAnnotateImage':
        $image = getImageObject(array('filename' => 'img1.jpg'));
        $image->resize(300,300);
        $image->text("Hello World", 1, 150, '', 'blue', 0, 'large');
        $image->display();
        break;

case 'testPolylineCircleLineText':
    // Various other primitives. Using different colors and strokewidths
    // to make sure that they get reset after each call - so we don't
    // inadvetantly apply a color/stroke/etc setting to a primitive
    // further down the line...
    $image = getImageObject(array('height' => '200',
                                  'width' => '200',
                                  'background' => 'none'));
    // Pie slice. Black outline, green fill
    $image->polygon(array(array('x' => 30, 'y' => 50),
                          array('x' => 40, 'y' => 60),
                          array('x' => 50, 'y' => 40)),
                   'black', 'green');

    // Yellow 'pizza slice' with blue outline
    $image->arc(50, 50, 100, 0, 70, 'blue', 'yellow');

    // Small red circle dot.
    $image->brush(80, 150, 'red', 'circle');

    // Thicker verticle green line
    $image->line(5, 30, 5, 200, 'green', 5);

    //Thinner verticle blue line
    $image->line(20, 60, 20, 200, 'blue', 2);

    // Yellow checkmark
    $image->polyline(array(array('x' => 130, 'y' => 150),
                           array('x' => 140, 'y' => 160),
                           array('x' => 150, 'y' => 140)),
                     'yellow', 4);

    $image->text('Hello World', 60, 10, 'Arial', 'black', 0, 'large');
    $image->display();
    break;

case 'testRoundCorners':
    $time = xdebug_time_index();
    // Tests resizing, and rounding corners with appropriate background maintained.
    $image = getImageObject(array('filename' => 'img1.jpg'));
    $image->resize(150,150);
    $image->addEffect('RoundCorners',
                      array('border' => 2,
                            'bordercolor' => '#333',
                            'background' => 'none'));
    $image->applyEffects();

    $time = xdebug_time_index() - $time;
    $memory = xdebug_peak_memory_usage();
    logThis($test, $time, $memory);

    $image->display();
    break;
case 'testRoundCornersRedBG':
    $time = xdebug_time_index();
    // Tests resizing, and rounding corners with appropriate background maintained.
    $image = getImageObject(array('filename' => 'img1.jpg'));
    $image->resize(150,150);
    $image->addEffect('RoundCorners',
                      array('border' => 2,
                            'bordercolor' => '#333',
                            'background' => 'red'));
    $image->applyEffects();
    $image->display();
    $time = xdebug_time_index() - $time;
    $memory = xdebug_peak_memory_usage();
    logThis($test, $time, $memory);
    break;
case 'testRoundCornersDropShadowTransparentBG':
    $time = xdebug_time_index();
    $image = getImageObject(array('filename' => 'img1.jpg'));
    $image->resize(150,150);
    $image->addEffect('RoundCorners',
                      array('border' => 2,
                            'bordercolor' => '#333'));
    $image->addEffect('DropShadow',
                      array('background' => 'none',
                            'padding' => 5,
                            'distance' => 5,
                            'fade' => 3));
    $time = xdebug_time_index() - $time;
    $mem = xdebug_peak_memory_usage();
    logThis($test, $time, $mem);
    $image->display();
    break;

case 'testRoundCornersDropShadowYellowBG':
    $time = xdebug_time_index();
    $image = getImageObject(array('filename' => 'img1.jpg'));
    $image->resize(150,150);
    $image->addEffect('RoundCorners',
                      array('border' => 2,
                            'bordercolor' => '#333'));
    $image->addEffect('DropShadow',
                      array('background' => 'yellow',
                            'padding' => 5,
                            'distance' => 5,
                            'fade' => 3));
    $image->display();
    $time = xdebug_time_index() - $time;
    $memory = xdebug_peak_memory_usage();
    logThis($test, $time, $memory);
    break;

case 'testBorderedDropShadowTransparentBG':
    $time = xdebug_time_index();

    $image = getImageObject(array('filename' => 'img1.jpg',
                                  'background' => 'none'));
    $image->resize(150,150);
    $image->addEffect('Border', array('bordercolor' => '#333', 'borderwidth' => 1));
    $image->addEffect('DropShadow',
                      array('background' => 'none',
                            'padding' => 10,
                            'distance' => '10',
                            'fade' => 5));
    $image->display();
    $time = xdebug_time_index() - $time;
    $memory = xdebug_peak_memory_usage();
    logThis($test, $time, $memory);
    break;

case 'testBorderedDropShadowBlueBG':
    $time = xdebug_time_index();
    $image = getImageObject(array('filename' => 'img1.jpg',
                                  'background' => 'none'));
    $image->resize(150,150);
    $image->addEffect('Border', array('bordercolor' => '#333', 'borderwidth' => 1));
    $image->addEffect('DropShadow',
                      array('background' => 'blue',
                            'padding' => 10,
                            'distance' => '10',
                            'fade' => 5));
    $image->display();
    $time = xdebug_time_index() - $time;
    $mem = xdebug_peak_memory_usage();
    logThis($test, $time, $mem);
    break;

case 'testPolaroidTransparentBG':
    $time = xdebug_time_index();
    $image = getImageObject(array('filename' => 'img1.jpg'));
    $image->resize(150, 150);
    $image->addEffect('PolaroidImage',
                      array('background' => 'none',
                            'padding' => 5));
    $image->display();
    $time = xdebug_time_index() - $time;
    $memory = xdebug_peak_memory_usage();
    logThis($test, $time, $memory);
    break;

case 'testPolaroidBlueBG':
    $time = xdebug_time_index();
    $image = getImageObject(array('filename' => 'img1.jpg'));
    $image->resize(150, 150);
    $image->addEffect('PolaroidImage',
                      array('background' => 'blue',
                            'padding' => 5));
    $image->display();
    $time = xdebug_time_index() - $time;
    $memory = xdebug_peak_memory_usage();
    logThis($test, $time, $memory);
    break;

case 'testPlainstackTransparentBG':
    $time = xdebug_time_index();
    $imgs = array(getImageObject(array('filename' => 'img1.jpg')),
                  getImageObject(array('filename' => 'img2.jpg')),
                  getImageObject(array('filename' => 'img3.jpg')));
    $baseImg = getImageObject(array('width' => 1,
                                    'height' => 1,
                                    'background' => 'none'));

    $baseImg->addEffect('PhotoStack',
                        array('images' => $imgs,
                              'resize_height' => 150,
                              'padding' => 0,
                              'background' => 'none',
                              'type' => 'plain'));
    $baseImg->applyEffects();
    $baseImg->display();
    $time = xdebug_time_index() - $time;
    $memory = xdebug_peak_memory_usage();
    logThis($test, $time, $memory);
    break;

case 'testPlainstackBlueBG':
    $time = xdebug_time_index();

    $imgs = array(getImageObject(array('filename' => 'img1.jpg')),
                  getImageObject(array('filename' => 'img2.jpg')),
                  getImageObject(array('filename' => 'img3.jpg')));
    $baseImg = getImageObject(array('width' => 1,
                                    'height' => 1,
                                    'background' => 'none'));

    $baseImg->addEffect('PhotoStack',
                        array('images' => $imgs,
                              'resize_height' => 150,
                              'padding' => 5,
                              'background' => 'blue',
                              'type' => 'plain'));
    $baseImg->applyEffects();
    $baseImg->display();
    $time = xdebug_time_index() - $time;
    $memory = xdebug_peak_memory_usage();
    logThis($test, $time, $memory);
    break;

case 'testRoundstackTransparentBG':
    $time = xdebug_time_index();
    $imgs = array(getImageObject(array('filename' => 'img1.jpg')),
                  getImageObject(array('filename' => 'img2.jpg')),
                  getImageObject(array('filename' => 'img3.jpg')));
    $baseImg = getImageObject(array('width' => 1,
                                    'height' => 1,
                                    'background' => 'none'));

    $baseImg->addEffect('PhotoStack',
                        array('images' => $imgs,
                              'resize_height' => 150,
                              'padding' => 0,
                              'background' => 'none',
                              'type' => 'rounded'));
    $baseImg->applyEffects();
    $baseImg->display();
    $time = xdebug_time_index() - $time;
    $memory = xdebug_peak_memory_usage();
    logThis($test, $time, $memory);
    break;

case 'testRoundstackBlueBG':
    $time = xdebug_time_index();
    $imgs = array(getImageObject(array('filename' => 'img1.jpg')),
                  getImageObject(array('filename' => 'img2.jpg')),
                  getImageObject(array('filename' => 'img3.jpg')));
    $baseImg = getImageObject(array('width' => 1,
                                    'height' => 1,
                                    'background' => 'none'));

    $baseImg->addEffect('PhotoStack',
                        array('images' => $imgs,
                              'resize_height' => 150,
                              'padding' => 0,
                              'background' => 'blue',
                              'type' => 'rounded'));
    $baseImg->applyEffects();
    $baseImg->display();
    $time = xdebug_time_index() - $time;
    $memory = xdebug_peak_memory_usage();
    logThis($test, $time, $memory);
    break;

case 'testPolaroidstackTransparentBG':
    $time = xdebug_time_index();
    $imgs = array(getImageObject(array('filename' => 'img1.jpg')),
              getImageObject(array('filename' => 'img2.jpg')),
              getImageObject(array('filename' => 'img3.jpg')));
    $baseImg = getImageObject(array('width' => 1,
                                    'height' => 1,
                                    'background' => 'none'));

    $baseImg->addEffect('PhotoStack',
                        array('images' => $imgs,
                              'resize_height' => 150,
                              'padding' => 0,
                              'background' => 'none',
                              'type' => 'polaroid'));
    $baseImg->applyEffects();
    $baseImg->display();
    $time = xdebug_time_index() - $time;
    $memory = xdebug_peak_memory_usage();
    logThis($test, $time, $memory);
    break;

case 'testPolaroidstackBlueBG':
    $imgs = array(getImageObject(array('filename' => 'img1.jpg')),
              getImageObject(array('filename' => 'img2.jpg')),
              getImageObject(array('filename' => 'img3.jpg')));
    $baseImg = getImageObject(array('width' => 1,
                                    'height' => 1,
                                    'background' => 'none'));

    $baseImg->addEffect('PhotoStack',
                        array('images' => $imgs,
                              'resize_height' => 150,
                              'padding' => 0,
                              'background' => 'blue',
                              'type' => 'polaroid'));
    $baseImg->applyEffects();
    $baseImg->display();
    break;
}

/**
 * Obtain a Horde_Image object
 *
 * @param array $params  Any additional parameters
 *
 * @return Horde_Image object | PEAR_Error
 */
function getImageObject($params = array())
{
    global $conf;

    // Always pass the convert parameter to be consistent when profiling.
    $context = array('tmpdir' => Horde::getTempDir(),
                     'convert' => $GLOBALS['convert']);
    $params['context'] = $context;
    return Horde_Image::factory($GLOBALS['driver'], $params);
}

function logThis($effect, $time, $memory)
{
    global $driver, $logger;

    $logger->debug("$driver, $effect, $time, $memory");

//    global $driver, $logger, $db;
//    $sql = "INSERT INTO image_tests (test, driver, peak_memory, execution_time) VALUES (?, ?, ?, ?);";
//    $db->insert($sql, array('test' => $effect,
//                                     'driver' => $driver,
//                                     'peak_memory' => $memory,
//                                     'execution_time' => $time));
}
