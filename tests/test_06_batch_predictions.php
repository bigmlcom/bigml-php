<?php
include '../bigml/bigml.php';
include '../bigml/ensemble.php';
include '../bigml/cluster.php';
include '../bigml/fields.php';

class BigMLTest extends PHPUnit_Framework_TestCase
{
    protected static $username; # "you_username"
    protected static $api_key; # "your_api_key"

    protected static $api;

    public static function setUpBeforeClass() {
       self::$api =  new BigML(self::$username, self::$api_key, true);
       ini_set('memory_limit', '512M');
    }

    /*  Successfully creating a batch prediction
    */
    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv', 'local_file' => 'tmp/batch_predictions.csv', 'predictions_file' => 'data/batch_predictions.csv')); 


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

          print "create model\n";
          $model = self::$api->create_model($dataset->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);
 
          print "check model is ready\n";
          $resource = self::$api->_check_resource($model->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "I create a batch prediction\n";
          $batch_prediction=self::$api->create_batch_prediction($model,$dataset);
	  $this->assertEquals(BigMLRequest::HTTP_CREATED, $batch_prediction->code);
           
	  print "check a batch_predicion is ready\n";
          $resource = self::$api->_check_resource($batch_prediction, null, 3000, 50);
	  $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "download batch predictions file\n";
	  $filename = self::$api->download_batch_prediction($batch_prediction, $item["local_file"]);
	  $this->assertNotNull($filename);

          print "i compare the prediction file is correct\n";
          $this->assertTrue(compareFiles($item["local_file"], $item["predictions_file"]));

      } 
    }

    /* Successfully creating a batch prediction for an ensemble */
    public function test_scenario2() {
      $data = array(array('filename' => 'data/iris.csv',
                          'number_of_models' => 5,
                          'tlp' => 1, 
                          'local_file' => 'tmp/batch_predictions.csv',
                          'predictions_file' => 'data/batch_predictions_e.csv')); 


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

          print "create a ensemble from ";
          $ensemble = self::$api->create_ensemble($dataset->resource, array("number_of_models"=> $item["number_of_models"], "tlp"=> $item["tlp"],"seed" => 'BigML', 'sample_rate'=> 0.70));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $ensemble->code);
       
          print "check the ensemble is ready\n";
          $resource = self::$api->_check_resource($ensemble->resource, null, 3000, 50);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
 
          print "I create a batch prediction ensemble\n";
          $batch_prediction=self::$api->create_batch_prediction($ensemble, $dataset);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $batch_prediction->code);
           
          print "check a batch_predicion is ready\n";
          $resource = self::$api->_check_resource($batch_prediction, null, 3000, 50);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "download batch predictions file\n";
          $filename = self::$api->download_batch_prediction($batch_prediction, $item["local_file"]);
          $this->assertNotNull($filename);

          print "i compare the prediction file is correct\n";
          $this->assertTrue(compareFiles($item["local_file"], $item["predictions_file"]));

      } 
    }
    /* Successfully creating a batch centroid from a cluster */
    public function test_scenario3() {
      $data = array(array('filename' => 'data/diabetes.csv',
                          'local_file' => 'tmp/batch_predictions_c.csv',
                          'predictions_file' => 'data/batch_predictions_c.csv'));


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

          print "create a cluster\n";
          $cluster = self::$api->create_cluster($dataset->resource, array('seed'=>'BigML', 'cluster_seed'=> 'BigML', 'k' =>  8));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $cluster->code);
          $this->assertEquals(BigMLRequest::QUEUED, $cluster->object->status->code);

          print "I wait until the cluster is ready  "  . $cluster->resource . "\n";
          $resource = self::$api->_check_resource($cluster->resource, null, 50000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
 
          print "I create a batch prediction cluster\n";
          $batch_prediction=self::$api->create_batch_centroid($cluster, $dataset);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $batch_prediction->code);

          print "check a batch_predicion is ready\n";
          $resource = self::$api->_check_resource($batch_prediction, null, 50000, 50);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "download batch predictions file\n";
          $filename = self::$api->download_batch_centroid($batch_prediction, $item["local_file"]);
          $this->assertNotNull($filename);

          print "i compare the prediction file is correct\n";
          $this->assertTrue(compareFiles($item["local_file"], $item["predictions_file"]));

      }
    }
    /* Successfully creating a source from a batch prediction */
    public function test_scenario4() {
      $data = array(array('filename' => 'data/iris.csv'));

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

          print "create model\n";
          $model = self::$api->create_model($dataset->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);
 
          print "check model is ready\n";
          $resource = self::$api->_check_resource($model->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "I create a batch prediction\n";
          $batch_prediction=self::$api->create_batch_prediction($model,$dataset);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $batch_prediction->code);
           
          print "check a batch_predicion is ready\n";
          $resource = self::$api->_check_resource($batch_prediction, null, 3000, 50);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
 
          print "Then I create a source from the batch prediction\n";
          $source = self::$api->source_from_batch_prediction($batch_prediction);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);
         
          print "check local source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]); 

      }
    }
    /* Successfully creating a batch anomaly score from an anomaly detector */
    public function test_scenario5() {

      $data = array(array('filename' => 'data/tiny_kdd.csv', 
                          'local_file' => 'tmp/batch_predictions.csv', 
                          'predictions_file' => 'data/batch_predictions_a.csv'));

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

          print "I create a batch anomaly score\n";
          $batch_prediction=self::$api->create_batch_anomaly_score($anomaly, $dataset);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $batch_prediction->code);

          print "check a batch_predicion is ready\n";
          $resource = self::$api->_check_resource($batch_prediction, null, 50000, 50);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "download batch predictions file\n";
          $filename = self::$api->download_batch_anomaly_score($batch_prediction, $item["local_file"]);
          $this->assertNotNull($filename);

          print "i compare the prediction file is correct\n";
          $this->assertTrue(compareFiles($item["local_file"], $item["predictions_file"]));

      }
    }
}    
