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
    /* Successfully creating a model from a dataset list */
    public function test_scenario1() {
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
          $dataset_1 = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset_1->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset_1->object->status->code);

          print "check the dataset is ready " . $dataset_1->resource . " \n";
          $resource = self::$api->_check_resource($dataset_1->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);


          print "create dataset with local source\n";
          $dataset_2 = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset_2->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset_2->object->status->code);

          print "check the dataset is ready " . $dataset_2->resource . " \n";
          $resource = self::$api->_check_resource($dataset_2->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          $model = self::$api->create_model(array($dataset_1->resource, $dataset_2->resource));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

          print "check model is ready\n";
          $resource = self::$api->_check_resource($model->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
          $this->assertEquals((property_exists($model, 'object') && property_exists($model->object, 'datasets') && $model->object->datasets == array($dataset_1->resource, $dataset_2->resource)),true);

      } 
    }

    /*Successfully creating a model from a dataset list and predicting with it using median */
    public function test_scenario2() {
      $data = array(array('filename' => 'data/grades.csv', 'input_data' => array('Tutorial'=> 99.47, 'Midterm'=> 53.12, 'TakeHome'=> 87.96), 'prediction' => 50 ));

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

          $model = self::$api->create_model($dataset->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

          print "check model is ready\n";
          $resource = self::$api->_check_resource($model->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          $list_of_models = array(self::$api->get_model($model->resource));

          print "I create a local multi model\n";
          $local_multimodel = new MultiModel($list_of_models);

          print "I create a batch prediction\n";
          $batch_predict = $local_multimodel->batch_predict(array($item["input_data"]), null, true, false, Tree::LAST_PREDICTION, null, false, true);
         
          $this->assertEquals(current($batch_predict)->predictions[0]["prediction"][0], $item["prediction"]); 

      }
    }

}    
