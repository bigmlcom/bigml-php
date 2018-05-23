<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
   include '../bigml/bigml.php';
}

use BigML\BigML;
use BigML\BigMLRequest;

class BigMLTestSampleDataset extends PHPUnit_Framework_TestCase
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
     Successfully creating a sample from a dataset
    */

    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv', 'sample_name' => 'my new sample name' ));


      foreach($data as $item) {
          print "\nSuccessfully creating a sample from a dataset\n";
          print "Given I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "And I wait the source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create dataset with local source\n";
          $dataset = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

          print "And I wait the  dataset is ready " . $dataset->resource . " \n";
          $resource = self::$api->_check_resource($dataset->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create a sample from a dataset\n";
	  $sample = self::$api->create_sample($dataset->resource, array('name'=> 'new sample'));
	  $this->assertEquals(BigMLRequest::HTTP_CREATED, $sample->code);
	  $this->assertEquals(BigMLRequest::QUEUED, $sample->object->status->code);

          print "And I wait the sample is ready " . $sample->resource . " \n";
	  $resource = self::$api->_check_resource($sample->resource, null, 20000, 30);
	  $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

	  print "I update the sample name to " . $item["sample_name"] . "\n";
          $sample = self::$api->update_sample($sample->resource, array('name'=> $item["sample_name"]));
          $this->assertEquals(BigMLRequest::HTTP_ACCEPTED, $sample->code);

	  print "When I wait until the sample is ready\n";
	  $resource = self::$api->_check_resource($sample->resource, null, 20000, 30);
	  $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

	  $sample = self::$api->get_sample($sample->resource);
          print "Then the sample name is " . $item["sample_name"] . "\n";

          $this->assertEquals($sample->object->name, $item["sample_name"]);
      }
    }
}
