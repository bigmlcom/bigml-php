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

class BigMLTestMultimodelBatch extends PHPUnit_Framework_TestCase
{
    protected static $username; # "you_username"
    protected static $api_key; # "your_api_key"
    protected static $api;
    protected static $project;

    public static function setUpBeforeClass() {
       print __FILE__;
       self::$api =  new BigML(self::$username, self::$api_key, false);
       ini_set('memory_limit', '512M');
       $test_name=basename(preg_replace('/\.php$/', '', __FILE__));
       self::$api->delete_all_project_by_name($test_name);
       self::$project=self::$api->create_project(array('name'=> $test_name));
    }

    public static function tearDownAfterClass() {
       self::$api->delete_all_project_by_name(basename(preg_replace('/\.php$/', '', __FILE__)));
    }
    /*
     Successfully creating a batch prediction from a multi model
    */
    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv',
                          'params' => array("tags" => array("mytag"), 'missing_splits' => false),
			  'data_input' => array(array("petal width"=> 0.5), array("petal length"=> 6, "petal width"=> 2), array("petal length"=> 4, "petal width"=> 1.5)),
			  'tag' => 'mytag',
			  'path' => 'tmp/',
			  'predictions' => array("Iris-setosa", "Iris-virginica", "Iris-versicolor")));


      foreach($data as $item) {
          print "\nSuccessfully creating a batch prediction from a multi model\n";
          print "I create a data source uploading a ". $item["filename"]. " file\n";
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

          print "And I create a model with " . json_encode($item["params"]) . "\n";
          $model_1 = self::$api->create_model($dataset->resource, $item["params"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_1->code);

          $list_of_models = array();
          print "And I wait until the model is ready\n";
          $resource = self::$api->_check_resource($model_1->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
          array_push($list_of_models, self::$api->get_model($model_1->resource));

          print "And I create a model with " . json_encode($item["params"]) . "\n";
          $model_2 = self::$api->create_model($dataset->resource, $item["params"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_2->code);

          print "And I wait until the model is ready\n";
          $resource = self::$api->_check_resource($model_2->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
          array_push($list_of_models, self::$api->get_model($model_2->resource));

          print "And I create a model with " . json_encode($item["params"]) . "\n";
          $model_3 = self::$api->create_model($dataset->resource, $item["params"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_3->code);

          print "And I wait until the model is ready\n";
          $resource = self::$api->_check_resource($model_3->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
          array_push($list_of_models, self::$api->get_model($model_3->resource));

          print "And I create a local multi model\n";
          $local_multimodel = new MultiModel($list_of_models);

          if (!is_dir($item["path"]) )
              mkdir($item["path"]);

          print "When I create a batch prediction for ". json_encode($item["data_input"]).
                 "and save it in " . $item["path"] . "\n";
          $batch_predict = $local_multimodel->batch_predict($item["data_input"], $item["path"]);

          print "And I combine the votes in " . $item["path"] . "\n";
          $votes=$local_multimodel->batch_votes($item["path"]);

          print "Then the plurarity combined predictions are ". json_encode($item["predictions"]) . "\n";
          $i=0;
          foreach($votes as $vote) {
             $this->assertEquals($item["predictions"][$i], $vote->combine());
             $i+=1;
          }

          print "And the confidence weighted predictions are ". json_encode($item["predictions"]) . "\n";
          $i=0;
          foreach($votes as $vote) {
             $this->assertEquals($item["predictions"][$i], $vote->combine(1));
            $i+=1;
          }

      }
    }

}
