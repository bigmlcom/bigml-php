<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
  include '../bigml/bigml.php';
}

use BigML\BigML;
use BigML\BigMLRequest;

class BigMLTestBatchPredictions extends PHPUnit_Framework_TestCase
{
    protected static $username; # "you_username"
    protected static $api_key; # "your_api_key"

    protected static $api;
    protected static $project;

    public static function setUpBeforeClass() {
       print __FILE__;
       self::$api =  new BigML(self::$username, self::$api_key, true);
       ini_set('memory_limit', '512M');

       if (!file_exists('tmp')) {
          mkdir('tmp');
       }
       $test_name=basename(preg_replace('/\.php$/', '', __FILE__));
       self::$api->delete_all_project_by_name($test_name);
       self::$project=self::$api->create_project(array('name'=> $test_name));

    }

    public static function tearDownAfterClass() {
       self::$api->delete_all_project_by_name(basename(preg_replace('/\.php$/', '', __FILE__)));
    }

    /*  Successfully creating a batch prediction
    */
    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv', 'local_file' => 'tmp/batch_predictions.csv', 'predictions_file' => 'data/batch_predictions.csv'));


      foreach($data as $item) {
          print "\nSuccessfully creating a batch prediction\n";
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

          print "And I create model\n";
          $model = self::$api->create_model($dataset->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

          print "And I wait until the model is ready\n";
          $resource = self::$api->_check_resource($model->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create a batch prediction\n";
          $batch_prediction=self::$api->create_batch_prediction($model,$dataset);
	  $this->assertEquals(BigMLRequest::HTTP_CREATED, $batch_prediction->code);

	  print "And I wait until the batch_predicion is ready\n";
          $resource = self::$api->_check_resource($batch_prediction, null, 3000, 50);
	  $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I download the created predictions file to " . $item["local_file"] . "\n";
	  $filename = self::$api->download_batch_prediction($batch_prediction, $item["local_file"]);
	  $this->assertNotNull($filename);

          print "Then the batch prediction file is like " . $item["predictions_file"]. "\n";
          $this->assertTrue(\BigML\compareFiles($item["local_file"], $item["predictions_file"]));

      }
    }

    // Successfully creating a batch prediction for an ensemble

    public function test_scenario2() {
      $data = array(array('filename' => 'data/iris.csv',
                          'number_of_models' => 5,
                          'tlp' => 1,
                          'local_file' => 'tmp/batch_predictions.csv',
                          'predictions_file' => 'data/batch_predictions_e.csv'));


      foreach($data as $item) {
          print_r("\nSuccessfully creating a batch prediction for an ensemble\n");
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

          print "And I create a ensemble from ";
          $ensemble = self::$api->create_ensemble($dataset->resource, array("number_of_models"=> $item["number_of_models"], "ensemble_sample" => array("seed" => 'BigML', 'rate'=> 0.70)));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $ensemble->code);

          print "And I wait until the ensemble is ready\n";
          $resource = self::$api->_check_resource($ensemble->resource, null, 3000, 50);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create a batch prediction ensemble\n";
          $batch_prediction=self::$api->create_batch_prediction($ensemble, $dataset);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $batch_prediction->code);

          print "And I wait until the batch_predicion is ready\n";
          $resource = self::$api->_check_resource($batch_prediction, null, 3000, 50);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I download the created predictions file to " . $item["local_file"] . "\n";
          $filename = self::$api->download_batch_prediction($batch_prediction, $item["local_file"]);
          $this->assertNotNull($filename);

          print "Then the batch prediction file is like " . $item["predictions_file"]. "\n";
          $this->assertTrue(\BigML\compareFiles($item["local_file"], $item["predictions_file"]));

      }
    }
    /* Successfully creating a batch centroid from a cluster */
    public function test_scenario3() {
      $data = array(array('filename' => 'data/diabetes.csv',
                          'local_file' => 'tmp/batch_predictions_c.csv',
                          'predictions_file' => 'data/batch_predictions_c.csv'));


      foreach($data as $item) {
          print "Successfully creating a batch centroid from a cluster\n";
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

          print "And I create a cluster\n";
          $cluster = self::$api->create_cluster($dataset->resource, array('seed'=>'BigML', 'cluster_seed'=> 'BigML', 'k' =>  8));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $cluster->code);
          $this->assertEquals(BigMLRequest::QUEUED, $cluster->object->status->code);

          print "I wait until the cluster is ready  "  . $cluster->resource . "\n";
          $resource = self::$api->_check_resource($cluster->resource, null, 50000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "I create a batch prediction cluster\n";
          $batch_prediction=self::$api->create_batch_centroid($cluster, $dataset);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $batch_prediction->code);

          print "And I wait until the batch_predicion is ready\n";
          $resource = self::$api->_check_resource($batch_prediction, null, 50000, 50);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I download the created centroid file to " . $item["local_file"] . "\n";
          $filename = self::$api->download_batch_centroid($batch_prediction, $item["local_file"]);
          $this->assertNotNull($filename);

          print "Then the batch centroid file is like " . $item["predictions_file"]. "\n";
          $this->assertTrue(\BigML\compareFiles($item["local_file"], $item["predictions_file"]));

      }
    }

    // Successfully creating a source from a batch prediction
    public function test_scenario4() {
      $data = array(array('filename' => 'data/iris.csv'));

      foreach($data as $item) {
          print "\nSuccessfully creating a source from a batch prediction\n";
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

          print "And I create model\n";
          $model = self::$api->create_model($dataset->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

          print "And I wait until the model is ready\n";
          $resource = self::$api->_check_resource($model->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create a batch prediction\n";
          $batch_prediction=self::$api->create_batch_prediction($model,$dataset);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $batch_prediction->code);

          print "And I wait until the batch_predicion is ready\n";
          $resource = self::$api->_check_resource($batch_prediction, null, 3000, 50);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "Then I create a source from the batch prediction\n";
          $source = self::$api->source_from_batch_prediction($batch_prediction);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "And I wait until the source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

      }
    }
    // Successfully creating a batch anomaly score from an anomaly detector
    public function test_scenario5() {

      $data = array(array('filename' => 'data/tiny_kdd.csv',
                          'local_file' => 'tmp/batch_predictions.csv',
                          'predictions_file' => 'data/batch_predictions_a.csv'));

      foreach($data as $item) {
          print "\nSuccessfully creating a batch anomaly score from an anomaly detector\n";
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

          print "Then I create an anomaly detector from a dataset\n";
          $anomaly = self::$api->create_anomaly($dataset->resource);

          print "I wait until the anomaly detector is ready\n";
          $resource = self::$api->_check_resource($anomaly->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "I create a batch anomaly score\n";
          $batch_prediction=self::$api->create_batch_anomaly_score($anomaly, $dataset);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $batch_prediction->code);

          print "And I wait until the batch_predicion is ready\n";
          $resource = self::$api->_check_resource($batch_prediction, null, 50000, 50);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I download the created anomaly score file to " . $item["local_file"] . "\n";
          $filename = self::$api->download_batch_anomaly_score($batch_prediction, $item["local_file"]);
          $this->assertNotNull($filename);

          print "Then the batch anomaly score file is like " . $item["predictions_file"] . "\n";
          $this->assertTrue(\BigML\compareFiles($item["local_file"], $item["predictions_file"]));

      }
    }

    // Successfully creating a batch prediction for a logistic regression
    public function test_scenario6() {

      $data = array(array('filename' => 'data/iris.csv',
                          'local_file' => 'tmp/batch_predictions.csv',
                          'predictions_file' => 'data/batch_predictions_lr.csv'));

      foreach($data as $item) {
          print "\nSuccessfully creating a batch prediction for a logistic regression\n";
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

          print "And I create a logistic regresssion modeld \n";
          $logistic_regression = self::$api->create_logistic_regression($dataset->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $logistic_regression->code);

          print "And I wait until the logistic regression model is ready " . $logistic_regression->resource . "\n";
          $resource = self::$api->_check_resource($logistic_regression->resource, null, 10000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "I create a batch prediction for the dataset with the logistic regression\n";
          $batch_prediction=self::$api->create_batch_prediction($logistic_regression, $dataset);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $batch_prediction->code);

          print "And I wait until the batch_predicion is ready\n";
          $resource = self::$api->_check_resource($batch_prediction, null, 3000, 50);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I download the created predictions file to " . $item["local_file"] . "\n";
          $filename = self::$api->download_batch_prediction($batch_prediction, $item["local_file"]);
          $this->assertNotNull($filename);

          print "Then the batch prediction file is like " . $item["predictions_file"]. "\n";
          $this->assertTrue(\BigML\compareFiles($item["local_file"], $item["predictions_file"]));

      }

    }


}
