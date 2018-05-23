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

class BigMLTestMissingErrors extends PHPUnit_Framework_TestCase
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
      Successfully obtaining missing values counts
    */

    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris_missing.csv',
                          'params' => array("fields"=> array("000000"=> array("optype"=> "numeric"))),
                          'missing_values' => array("000000" =>  1)));

      foreach($data as $item) {
          print "\nSuccessfully obtaining missing values counts\n";
          print "Given I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "And I wait until the source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I update the source with params " . json_encode($item["params"]) . "\n";
	  $source = self::$api->update_source($source->resource, $item["params"]);

          print "And I create dataset with local source\n";
          $dataset = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

          print "And I wait the dataset is ready " . $dataset->resource . " \n";
          $resource = self::$api->_check_resource($dataset->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          $dataset = self::$api->get_dataset($dataset->resource);
          print "When I ask for the missing values counts in the fields\n";
          $fields = new Fields($dataset);
	  print "Then the missing values counts dict is " . json_encode($item["missing_values"]) . "\n";
	  $this->assertEquals($item["missing_values"], $fields->missing_counts());

      }
    }

    /*
     Successfully obtaining parsing error counts
    */

    public function test_scenario2() {

      $data = array(array('filename' => 'data/iris_missing.csv',
                          'params' => array("fields"=> array("000000"=> array("optype"=> "numeric"))),
                          'missing_values' => array("000000" =>  1)));

      foreach($data as $item) {
          print "\nSuccessfully obtaining parsing error counts\n";
          print "\nGiven I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "And I wait until the source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I update the source with params " . json_encode($item["params"]) ."\n";
          $source = self::$api->update_source($source->resource, $item["params"]);

          print "And I create dataset with local source\n";
          $dataset = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

          print "And I wait until the dataset is ready " . $dataset->resource . " \n";
          $resource = self::$api->_check_resource($dataset->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "I ask for the error counts in the fields\n";
          $step_results = self::$api->error_counts($dataset->resource);
          print "Then the error counts dict is <". json_encode($item["missing_values"]) .">\n";
          $this->assertEquals($item["missing_values"], $step_results);
      }

   }
}
