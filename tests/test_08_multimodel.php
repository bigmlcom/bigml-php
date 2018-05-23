<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
  include '../bigml/bigml.php';
}

if (!class_exists('BigML\MultiModel')) {
  include '../bigml/multimodel.php';
}

use BigML\BigML;
use BigML\BigMLRequest;
use BigML\MultiModel;

class BigMLTestMultiModel extends PHPUnit_Framework_TestCase
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

    /* Successfully creating a model from a dataset list */
    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv'));

      foreach($data as $item) {
          print "\nSuccessfully creating a model from a dataset list\n";
          print "I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "And I check local source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create dataset with local source\n";
          $dataset_1 = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset_1->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset_1->object->status->code);

          print "And I check the dataset is ready " . $dataset_1->resource . " \n";
          $resource = self::$api->_check_resource($dataset_1->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create dataset with local source\n";
          $dataset_2 = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset_2->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset_2->object->status->code);

          print "And I check the dataset is ready " . $dataset_2->resource . " \n";
          $resource = self::$api->_check_resource($dataset_2->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "Then I create a model from a dataset list\n";
          $model = self::$api->create_model(array($dataset_1->resource, $dataset_2->resource));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

          print "And I wait until model is ready\n";
          $resource = self::$api->_check_resource($model->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
          print "And I check the model stems from the original dataset list\n";
          $this->assertEquals((property_exists($model, 'object') && property_exists($model->object, 'datasets') && $model->object->datasets == array($dataset_1->resource, $dataset_2->resource)),true);

      }
    }

    /*Successfully creating a model from a dataset list and predicting with it using median */
    public function test_scenario2() {
      $data = array(array('filename' => 'data/grades.csv', 'input_data' => array('Tutorial'=> 99.47, 'Midterm'=> 53.12, 'TakeHome'=> 87.96), 'prediction' => 63.33 ));

      foreach($data as $item) {
          print "\nSuccessfully creating a model from a dataset list and predicting with it using median\n";
          print "I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "And I wait until the source is ready \n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create dataset with local source\n";
          $dataset = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
          print "And I wait until the dataset is ready\n";
          $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

          print "And I create a model\n";
          $model = self::$api->create_model($dataset->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

          print "And I wait until the model is ready\n";
          $resource = self::$api->_check_resource($model->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          $list_of_models = array(self::$api->get_model($model->resource));

          print "I create a local multi model\n";
          $local_multimodel = new MultiModel($list_of_models);

          print "When I create a local multimodel batch prediction using median for " . json_encode($item["input_data"]) . "\n";
          $batch_predict = $local_multimodel->batch_predict(array($item["input_data"]), null, true, false, \BigML\Tree::LAST_PREDICTION, null, false, true);

          print "Then the local prediction is " . $item["prediction"] . "\n";
          $this->assertEquals(current($batch_predict)->predictions[0]["prediction"][0], $item["prediction"]);

      }
    }

}
