<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
  include '../bigml/bigml.php';
}

use BigML\BigML;
use BigML\BigMLRequest;

class BigMLTestScripts extends PHPUnit_Framework_TestCase
{
    protected static $username;
    protected static $api_key;
    protected static $api;
    protected static $project;

    public static function setUpBeforeClass() {
       print __FILE__;
       self::$api =  new BigML(self::$username, self::$api_key, true);
       #self::$api->setDebug(true);
       ini_set('memory_limit', '512M');
       $test_name=basename(preg_replace('/\.php$/', '', __FILE__));
       #self::$api->delete_all_project_by_name($test_name);
       #self::$project=self::$api->create_project(array('name'=> $test_name));
    }

    public static function tearDownAfterClass() {
       #self::$api->delete_all_project_by_name(basename(preg_replace('/\.php$/', '', __FILE__)));
    }

    /*
     Successfully creating a whizzml script
    */

    public function test_scenario1() {
      $data = array(array('source_code' => '(+ 1 1)', 'param' => 'name', 'param_value' => 'my script'));

      foreach($data as $item) {
          print "\nSuccessfully creating a Script\n";
          print "Given I create a whizzml script from a excerpt of code ". json_encode($item["source_code"]) . "\n";
          $script = self::$api->create_script($item["source_code"]);

          $this->assertEquals(BigMLRequest::HTTP_CREATED, $script->code);
          $this->assertEquals(1, $script->object->status->code);

          print "And I wait until the script is ready\n";
          $resource = self::$api->_check_resource($script->resource, null, 10000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

	  print "And I update the script with " . $item["param"] .  ", ".  $item["param_value"] . "\n";
          $resource = self::$api->update_script($script->resource, array($item["param"]=>$item["param_value"]));

          print "And I wait until the script is ready\n";
          $resource = self::$api->_check_resource($script->resource, null, 10000, 30);
          $script = self::$api->get_script($script->resource);

	  print "Then the script code is " . json_encode($item["source_code"]) . " and the value of ". $item["param"] . " is " . $item["param_value"] . "\n";
          $this->assertEquals($script->object->{$item["param"]},  $item["param_value"]);

      }
    }

}
