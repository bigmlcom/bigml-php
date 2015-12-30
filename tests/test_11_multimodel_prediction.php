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
    /*
     Successfully creating a prediction from a multi model
    */
    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv', 
                          'params' => array("tags" => array("mytag"), 'missing_splits' => false),
			  'data_input' => array("petal width"=> 0.5),
			  'tag' => 'mytag',
			  'predictions' => "Iris-setosa"));


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

          print "create model_1\n";
          $model_1 = self::$api->create_model($dataset->resource, $item["params"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_1->code);
 
          print "check model_1 is ready\n";
          $resource = self::$api->_check_resource($model_1->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "create model_2\n";
          $model_2 = self::$api->create_model($dataset->resource, $item["params"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_2->code);
 
          print "check model_2 is ready\n";
          $resource = self::$api->_check_resource($model_2->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "create model_3\n";
          $model_3 = self::$api->create_model($dataset->resource, $item["params"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_3->code);
 
          print "check model_3 is ready\n";
          $resource = self::$api->_check_resource($model_3->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          $list_of_models = array();
          $models = self::$api->list_models("tags__in="+$item["tag"]);
          foreach ($models->resources as $m) {
             array_push($list_of_models, self::$api->get_model($m->resource));
          }

          print "I create a local multi model\n";
          $local_multimodel = new MultiModel($list_of_models);

          print "I create a prediction\n";
          $prediction = $local_multimodel->predict($item["data_input"]);

          $this->assertEquals($prediction, $item["predictions"]);
 
      } 
    }
    /*Successfully creating a local batch prediction from a multi model */
    public function test_scenario2() {
      $data = array(array('filename' => 'data/iris.csv',
                          'params' => array("tags" => array("mytag"), 'missing_splits' => false),
                          'data_input' => array(array("petal width"=> 0.5), array("petal length"=>6, "petal width"=>2)),
                          'tag' => 'mytag',
                          'predictions' => array("Iris-setosa", "Iris-virginica")));

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

          print "create model_1\n";
          $model_1 = self::$api->create_model($dataset->resource, $item["params"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_1->code);

          print "check model_1 is ready\n";
          $resource = self::$api->_check_resource($model_1->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "create model_2\n";
          $model_2 = self::$api->create_model($dataset->resource, $item["params"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_2->code);

          print "check model_2 is ready\n";
          $resource = self::$api->_check_resource($model_2->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "create model_3\n";
          $model_3 = self::$api->create_model($dataset->resource, $item["params"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_3->code);

          print "check model_3 is ready\n";
          $resource = self::$api->_check_resource($model_3->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          $list_of_models = array();
          $models = self::$api->list_models("tags__in="+$item["tag"]);
          foreach ($models->resources as $m) {
             array_push($list_of_models, self::$api->get_model($m->resource));
          }

          print "I create a local multi model\n";
          $local_multimodel = new MultiModel($list_of_models);

          print "I create a prediction\n";
          $predictions = $local_multimodel->batch_predict($item["data_input"], null, true, false, Tree::LAST_PREDICTION, null, false, false);
          $i = 0;
	  foreach ($predictions as $multivote) {
	     foreach ($multivote->predictions as $prediction) {
	        $this->assertEquals($prediction["prediction"], $item["predictions"][$i]); 
	     } 
	     $i+=1; 
	  }
          $this->assertEquals($i, count($predictions));
      }
    }
}    
