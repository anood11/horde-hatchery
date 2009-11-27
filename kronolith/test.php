<?php
/* Include Horde's core.php file. */
require_once dirname(__FILE__) . '/lib/base.load.php';
include_once HORDE_BASE . '/lib/core.php';

/* We should have loaded the Horde_String class, from the Horde_Util package, in
 * core.php. If Horde_String:: isn't defined, then we're not finding some critical
 * libraries. */
if (!class_exists('Horde_String')) {
    echo '<br /><h2 style="color:red">The Horde_Util package was not found. If PHP\'s error_reporting setting is high enough and display_errors is on, there should be error messages printed above that may help you in debugging the problem. If you are simply missing these files, then you need to get the <a href="http://cvs.horde.org/cvs.php/framework">framework</a> module from <a href="http://www.horde.org/source/">Horde CVS</a>, and install the packages in it with the install-packages.php script.</h2>';
    exit;
}

/* Initialize the Horde_Test:: class. */
if (!is_readable(HORDE_BASE . '/lib/Test.php')) {
    echo 'ERROR: You must install Horde before running this script.';
    exit;
}
require_once HORDE_BASE . '/lib/Test.php';
$horde_test = new Horde_Test;

$module = 'Kronolith';
require_once dirname(__FILE__) . '/lib/Application.php';
$app = new Kronolith_Application();

$module_version = $app->version;

require TEST_TEMPLATES . 'header.inc';
require TEST_TEMPLATES . 'version.inc';

/* Display PHP Version information. */
$php_info = $horde_test->getPhpVersionInformation();
require TEST_TEMPLATES . 'php_version.inc';

/* PHP module capabilities. */
$module_list = array(
    'pdo' => array(
        'descrip' => 'PDO',
        'error' => 'The tagging capabilites require either the PDO or mysqli extension.'),
    'mysqli' => array(
        'descrip' => 'mysqli',
        'error' => 'The tagging capabilites require either the PDO or mysqli extension.')
);
$module_output = $horde_test->phpModuleCheck($module_list);

/* PEAR */
$pear_list = array(
    'Date' => array(
        'path' => 'Date/Calc.php',
        'error' => 'Kronolith requires the Date_Calc class to calculate dates.',
        'required' => true,
    ),
    'Date_Holidays' => array(
	'path' => 'Date/Holidays.php',
	'error' => 'Date_Holidays can be used to calculate and display national and/or religious holidays.',
	'required' => false,
    ),
    'XML_Serializer' => array(
	'path' => 'XML/Unserializer.php',
	'error' => 'The XML_Serializer might be needed by the Date_Holidays package for the translation of holidays',
	'required' => false,
    ),
);

?>
<h1>PHP Module Capabilities</h1>
<ul>
    <?php echo $horde_test->phpModuleCheck($module_list); ?>
</ul>

<h1>PEAR Modules</h1>
<ul>
    <?php echo $horde_test->PEARModuleCheck($pear_list) ?>
</ul>

<?php
require TEST_TEMPLATES . 'footer.inc';
