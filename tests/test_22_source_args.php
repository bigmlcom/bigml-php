<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
   include '../bigml/bigml.php';
}

use BigML\BigML;
use BigML\BigMLRequest;

class BigMLTestSourceArgs extends PHPUnit_Framework_TestCase
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
     Uploading source with structured args
    */

    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv',
			  'args' =>  array("tags" => array("my tag", "my second tag"), 'project'=> self::$project->resource)),
	           array('filename' => 'data/iris.csv',
                          'args' =>  array("name" => "Testing unicode names: áé", 'project'=> self::$project->resource)));

      foreach($data as $item) {
          print "\nUploading source with structured args\n";
          print "Given I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"], $options=$item["args"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "And I wait until the source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
          print "Then the source exists and has args " . json_encode($item["args"]) . "\n";

          foreach ($item["args"] as $key => $value) {
            $this->assertEquals($resource["resource"]->object->{$key}, $value);
          }
      }
    }

}
