<?php
include '../bigml/bigml.php';
include '../bigml/ensemble.php';
include '../bigml/cluster.php';
include '../bigml/fields.php';
include '../bigml/anomaly.php';

class BigMLTest extends PHPUnit_Framework_TestCase
{
    protected static $username; # "you_username"
    protected static $api_key; # "your_api_key"

    protected static $api;

    public static function setUpBeforeClass() {
       self::$api =  new BigML(self::$username, self::$api_key, true);
       ini_set('memory_limit', '512M');
    }
    /*
     Successfully creating an anomaly detector from a dataset and a dataset list
    */

    public function test_scenario1() {
      $data = array(array('filename' => 'data/tiny_kdd.csv'));


      foreach($data as $item) {
          print "I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source'));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "check local source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "create dataset with local source\n";
          $dataset = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

          print "check the dataset is ready " . $dataset->resource . " \n";
          $resource = self::$api->_check_resource($dataset->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "Then I create an anomaly detector from a dataset\n";
          $anomaly = self::$api->create_anomaly($dataset->resource);

          print "I wait until the anomaly detector is ready\n";
          $resource = self::$api->_check_resource($anomaly->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "I check the anomaly detector stems from the original dataset\n";
          $this->assertEquals($anomaly->object->dataset,$dataset->resource);

          print "create a new dataset with local source\n";
          $dataset_1 = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset_1->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset_1->object->status->code);

          print "check the dataset is ready " . $dataset_1->resource . " \n";
          $resource = self::$api->_check_resource($dataset_1->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "Then I create an anomaly detector from a dataset ids\n";
          $anomaly = self::$api->create_anomaly(array($dataset->resource, $dataset_1->resource));
          print "I wait until the anomaly detector is ready\n";
          $resource = self::$api->_check_resource($anomaly->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
          
          print "I check the anomaly detector stems from the original dataset\n";
          $this->assertEquals($anomaly->object->datasets, array($dataset->resource, $dataset_1->resource));

      } 
    }

    /*
     Successfully creating an anomaly detector from a dataset and generating the anomalous dataset
    */
    public function test_scenario2() {
      $data = array(array('filename' => 'data/iris_anomalous.csv', 'rows' => 1));


      foreach($data as $item) {
          print "I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source'));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "check local source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "create dataset with local source\n";
          $dataset = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

          print "check the dataset is ready " . $dataset->resource . " \n";
          $resource = self::$api->_check_resource($dataset->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "I create an anomaly detector with (\d+) anomalies from a dataset\n";
          $anomaly = self::$api->create_anomaly($dataset->resource, array('seed'=> 'BigML', 'top_n'=> $item["rows"]));

          print "I wait until the anomaly detector is ready\n";
          $resource = self::$api->_check_resource($anomaly->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "I create a dataset with only the anomalies\n";
          $local_anomaly = new Anomaly($anomaly->resource, self::$api);

          $new_dataset = self::$api->create_dataset($dataset->resource, array('lisp_filter' => $local_anomaly->anomalies_filter()));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $new_dataset->code);
          $this->assertEquals(BigMLRequest::QUEUED, $new_dataset->object->status->code);

          print "check the dataset is ready " . $new_dataset->resource . " \n";
          $resource = self::$api->_check_resource($new_dataset->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]); 

          $dataset = self::$api->get_dataset($new_dataset->resource);
          print "I check the anomaly detector stems from the original dataset\n";
          $this->assertEquals($dataset->object->rows,$item["rows"]);

      }

   }
}    
