<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
  include '../bigml/bigml.php';
}

use BigML\BigML;
use BigML\BigMLRequest;

class BigMLTestExecutions extends PHPUnit_Framework_TestCase
{
    protected static $username;
    protected static $api_key;
    protected static $api;
    protected static $project;

    public static function setUpBeforeClass() {
       print __FILE__;
       self::$api =  new BigML(self::$username, self::$api_key, true);
       ini_set('memory_limit', '512M');
       $test_name=basename(preg_replace('/\.php$/', '', __FILE__));
       #self::$api->delete_all_project_by_name($test_name);
       #self::$project=self::$api->create_project(array('name'=> $test_name));
    }

    public static function tearDownAfterClass() {
       #self::$api->delete_all_project_by_name(basename(preg_replace('/\.php$/', '', __FILE__)));
    }

    /*
     Successfully creating a whizzml execution
    */

    public function test_scenario1() {
      $data = array(array('source_code' => '(+ 1 1)', 'param' => 'name', 'param_value' => 'my execution', 'result' => 2));

      foreach($data as $item) {
          print "\nSuccessfully creating a Execution\n";
          print "Given I create a whizzml script from a excerpt of code ". json_encode($item["source_code"]) . "\n";
          $script = self::$api->create_script($item["source_code"]);

          $this->assertEquals(BigMLRequest::HTTP_CREATED, $script->code);
          $this->assertEquals(1, $script->object->status->code);

          print "And I wait until the script is ready\n";
          $resource = self::$api->_check_resource($script->resource, null, 10000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create a whizzml script execution from an existing script\n";
          $execution = self::$api->create_execution($script);
	  print "And I wait until the execution is ready\n";
	  $resource = self::$api->_check_resource($execution->resource, null, 10000, 30);
	  $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

	  print "And I update the execution with " . $item["param"] .  ", ".  $item["param_value"] . "\n";
          $resource = self::$api->update_execution($execution->resource, array($item["param"]=>$item["param_value"]));

          print "And I wait until the script is ready\n";
          $resource = self::$api->_check_resource($execution->resource, null, 10000, 30);
          $execution = self::$api->get_execution($execution->resource);

	  print "Then the script id is correct and the value of ". $item["param"] . " is " . $item["param_value"] . " and the result is " . $item["result"] . "\n";
	  $this->assertEquals($execution->object->{$item["param"]},  $item["param_value"]);
	  $this->assertEquals($execution->object->execution->results[0],  $item["result"]);

      }
    }

    public function test_scenario2() {
      $data = array(array('source_code' => '(+ 1 1)', 'param' => 'name', 'param_value' => 'my execution', 'result' => array(2,2)));

      foreach($data as $item) {
          print "\nScenario: Successfully creating a whizzml script execution from a list of scripts:\n";
          print "Given I create a whizzml script from a excerpt of code ". json_encode($item["source_code"]) . "\n";
          $script = self::$api->create_script($item["source_code"]);

          $this->assertEquals(BigMLRequest::HTTP_CREATED, $script->code);
          $this->assertEquals(1, $script->object->status->code);

          print "And I wait until the script is ready\n";
          $resource = self::$api->_check_resource($script->resource, null, 10000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
           print "And I create a other whizzml script from a excerpt of code ". json_encode($item["source_code"]) . "\n";
          $script2 = self::$api->create_script($item["source_code"]);

          $this->assertEquals(BigMLRequest::HTTP_CREATED, $script2->code);
          $this->assertEquals(1, $script2->object->status->code);

          print "And I wait until the script is ready\n";
          $resource = self::$api->_check_resource($script2->resource, null, 10000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create a whizzml execution from the last two scripts\n";
          $execution = self::$api->create_execution(array($script, $script2));
          print "And I wait until the execution is ready\n";
          $resource = self::$api->_check_resource($execution->resource, null, 10000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I update the execution with " . $item["param"] .  ", ".  $item["param_value"] . "\n";
          $resource = self::$api->update_execution($execution->resource, array($item["param"]=>$item["param_value"]));

          print "And I wait until the script is ready\n";
          $resource = self::$api->_check_resource($execution->resource, null, 10000, 30);
          $execution = self::$api->get_execution($execution->resource);

          print "Then the script id is correct and the value of ". $item["param"] . " is " . $item["param_value"] . " and the result is " . json_encode($item["result"]) . "\n";
          $this->assertEquals($execution->object->{$item["param"]},  $item["param_value"]);
          $this->assertEquals($execution->object->execution->results,  $item["result"]);

      }
    }

}
