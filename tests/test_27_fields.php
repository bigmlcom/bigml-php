<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
  include '../bigml/bigml.php';
}
if (!class_exists('BigML\Fields')) {
  include '../bigml/fields.php';
}

use BigML\BigML;
use BigML\BigMLRequest;
use BigML\Fields;

class BigMLTestFields extends PHPUnit_Framework_TestCase
{
    protected static $username; # "you_username"
    protected static $api_key; # "your_api_key"
    protected static $api;
    protected static $project;

    public static function setUpBeforeClass() {
       print __FILE__;
       self::$api =  new BigML(self::$username, self::$api_key, true);
       ini_set('memory_limit', '512M');
       $test_name=basename(preg_replace('/\.php$/', '', __FILE__));
       self::$api->delete_all_project_by_name($test_name);
       self::$project=self::$api->create_project(array('name'=> $test_name));
    }

    public static function tearDownAfterClass() {
       self::$api->delete_all_project_by_name(basename(preg_replace('/\.php$/', '', __FILE__)));
    }

    /*
      Successfully creating a Fields object
    */

    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv', 'objective_column' => 0, 'objective_id' => '000000'));

      foreach($data as $item) {
          print "\nSuccessfully creating a Fields object\n";
          print "Given I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "And I wait until the source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          $source = self::$api->get_source($source->resource);
          print "And I create a Fields object from the source with objective column " . $item["objective_column"] . "\n";
          $fields = new Fields($source, null, null, null, intval($item["objective_column"]), true);

	  print "Then the object id is " . $item["objective_id"] . "\n";
          $this->assertEquals($fields->field_id($fields->objective_field), $item["objective_id"]);

      }
    }

}
