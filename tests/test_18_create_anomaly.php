<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
  include '../bigml/bigml.php';
}
if (!class_exists('BigML\Anomaly')) {
  include '../bigml/anomaly.php';
}

use BigML\BigML;
use BigML\BigMLRequest;
use BigML\Anomaly;

class BigMLTestAnomaly extends PHPUnit_Framework_TestCase
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
     Successfully creating an anomaly detector from a dataset and a dataset list
    */

    public function test_scenario1() {
      $data = array(array('filename' => 'data/tiny_kdd.csv'));


      foreach($data as $item) {
          print "\nSuccessfully creating an anomaly detector from a dataset and a dataset list\n";
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

          print "And I wait he dataset is ready " . $dataset->resource . " \n";
          $resource = self::$api->_check_resource($dataset->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "Then I create an anomaly detector from a dataset\n";
          $anomaly = self::$api->create_anomaly($dataset->resource);

          print "And I wait until the anomaly detector is ready\n";
          $resource = self::$api->_check_resource($anomaly->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I check the anomaly detector stems from the original dataset\n";
          $this->assertEquals($anomaly->object->dataset,$dataset->resource);

          print "And I create a new dataset with local source\n";
          $dataset_1 = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset_1->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset_1->object->status->code);

          print "And I wait until the dataset is ready " . $dataset_1->resource . " \n";
          $resource = self::$api->_check_resource($dataset_1->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "Then I create an anomaly detector from a dataset ids\n";
          $anomaly = self::$api->create_anomaly(array($dataset->resource, $dataset_1->resource));
          print "I wait until the anomaly detector is ready\n";
          $resource = self::$api->_check_resource($anomaly->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "I check the anomaly detector stems from the original dataset list\n";
          $this->assertEquals($anomaly->object->datasets, array($dataset->resource, $dataset_1->resource));

      }
    }

    /*
     Successfully creating an anomaly detector from a dataset and generating the anomalous dataset
    */
    public function test_scenario2() {
      $data = array(array('filename' => 'data/iris_anomalous.csv', 'rows' => 1));


      foreach($data as $item) {
          print "\nSuccessfully creating an anomaly detector from a dataset and generating the anomalous dataset\n";
          print "Given I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "And I wait until the local source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create dataset with local source\n";
          $dataset = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

          print "And I wait until the dataset is ready " . $dataset->resource . " \n";
          $resource = self::$api->_check_resource($dataset->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "Then I create an anomaly detector with " . $item["rows"] ."  anomalies from a dataset\n";
          $anomaly = self::$api->create_anomaly($dataset->resource, array('seed'=> 'BigML', 'top_n'=> $item["rows"]));

          print "And I wait until the anomaly detector is ready\n";
          $resource = self::$api->_check_resource($anomaly->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create a dataset with only the anomalies\n";
          $local_anomaly = new Anomaly($anomaly->resource, self::$api);

          $new_dataset = self::$api->create_dataset($dataset->resource, array('lisp_filter' => $local_anomaly->anomalies_filter()));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $new_dataset->code);
          $this->assertEquals(BigMLRequest::QUEUED, $new_dataset->object->status->code);

          print "And I wait until the dataset is ready " . $new_dataset->resource . " \n";
          $resource = self::$api->_check_resource($new_dataset->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          $dataset = self::$api->get_dataset($new_dataset->resource);
          print "And I check that the dataset has "  . $item["rows"] ." rows\n";
          $this->assertEquals($dataset->object->rows,$item["rows"]);

      }

   }
}
