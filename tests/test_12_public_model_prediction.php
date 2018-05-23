<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
  include '../bigml/bigml.php';
}

if (!class_exists('BigML\Model')) {
  include '../bigml/model.php';
}

use BigML\BigML;
use BigML\BigMLRequest;
use BigML\Model;


class BigMLTestPublicModel extends PHPUnit_Framework_TestCase
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
     Successfully creating a prediction using a public model
    */

    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv',
			  'data_input' => array("petal width"=> 0.5),
			  'objective' => '000004',
			  'prediction' => "Iris-setosa"));


      foreach($data as $item) {
          print "Give I create a data source uploading a ". $item["filename"]. " file\n";
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

          print "And I make the model public\n";
	  $model = self::$api->update_model($model->resource, array('private'=> false, 'white_box' => true));
          $this->assertEquals(BigMLRequest::HTTP_ACCEPTED, $model->code);

          print "And I check the model status using the model\'s public url \n";
          $model = self::$api->get_model("public/" . $model->resource);
          $this->assertEquals(BigMLRequest::FINISHED, $model->object->status->code);

          print "When I create a prediction for " . json_encode($item["data_input"]) . "\n";
          $prediction = self::$api->create_prediction($model->resource, $item["data_input"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);

          print "Then the prediction for " . $item["objective"] . " is " . $item["prediction"] . "\n";
          $this->assertEquals($item["prediction"], $prediction->object->prediction->{$item["objective"]});

	  print "And I make the model private again\n";
	  $model = self::$api->update_model($model->resource, array('private'=> true, 'white_box' => true));
	  $this->assertEquals(BigMLRequest::HTTP_ACCEPTED, $model->code);

      }
    }
    /*Successfully creating a local batch prediction from a multi model */
    public function test_scenario2() {
        $data = array(array('filename' => 'data/iris.csv',
                          'data_input' => array("petal width"=> 0.5),
                          'prediction' => "Iris-setosa"));
        foreach($data as $item) {
          print "\nSuccessfully creating a prediction using a shared model\n";
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

          print "I make the model shared\n";
          $model = self::$api->update_model($model->resource, array('shared' => true));
          $this->assertEquals(BigMLRequest::HTTP_ACCEPTED, $model->code);

          $shared_hash = $model->object->shared_hash;
          $sharing_key = $model->object->sharing_key;

          print "I check the model status using the model\'s shared url \n";
          $model = self::$api->get_model("shared/model/" . $shared_hash);
          $this->assertEquals(BigMLRequest::FINISHED, $model->object->status->code);

          print "I check the model status using the model\'s shared key \n";
          $model = self::$api->get_model($model->resource, null, getenv("BIGML_USERNAME"), $sharing_key);
          $this->assertEquals(BigMLRequest::FINISHED, $model->object->status->code);

          print "And I create a local model\n";
          $local_model = new Model($model, self::$api);
          print "When I create a local prediction for " . json_encode($item["data_input"]) . "\n";
          $prediction = $local_model->predict($item["data_input"]);
          print "Then the prediction for is  " . $item["prediction"] . "\n";
          $this->assertEquals($prediction->output, $item["prediction"]);

        }
    }
}
