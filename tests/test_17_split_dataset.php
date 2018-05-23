<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
   include '../bigml/bigml.php';
}

use BigML\BigML;
use BigML\BigMLRequest;

class BigMLTestSplitDataset extends PHPUnit_Framework_TestCase
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
     Successfully creating a split dataset
    */

    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv', 'rate' => 0.8 ));


      foreach($data as $item) {
          print "\nSuccessfully creating a split dataset\n";
          print "Given I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "And I wait until the source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create dataset with local source\n";
          $dataset = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

          print "And I wait until the dataset is ready " . $dataset->resource . " \n";
          $resource = self::$api->_check_resource($dataset->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create a dataset extracting a " . $item["rate"] ." sample\n";
          $dataset_sample = self::$api->create_dataset($dataset->resource, array('sample_rate' => $item["rate"]));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset_sample->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset_sample->object->status->code);

          print "And I wait until the dataset is ready " . $dataset_sample->resource . " \n";
          $resource = self::$api->_check_resource($dataset_sample->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "When I compare the datasets' instances \n";
          $dataset = self::$api->get_dataset($dataset->resource);
          $dataset_sample = self::$api->get_dataset($dataset_sample->resource);

          print "Then the proportion of instances between datasets is " . $item["rate"] . "\n";
          $this->assertEquals(intval($dataset->object->rows * floatval($item['rate'])), $dataset_sample->object->rows);


      }
    }
}
