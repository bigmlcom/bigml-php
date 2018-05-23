<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
  include '../bigml/bigml.php';
}

use BigML\BigML;
use BigML\BigMLRequest;

class BigMLTestCreateDataset extends PHPUnit_Framework_TestCase
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
     Successfully creating an evaluation
    */

    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv',
			  'measure' => 'average_phi',
			  'value' => 1));


      foreach($data as $item) {
          print "\nSuccessfully creating an evaluation\n";
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

          print "When I create an evaluation for the model with the dataset\n";
          $evaluation = self::$api->create_evaluation($model, $dataset);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $evaluation->code);

          print "And I wait until the evaluation is ready\n";
          $resource = self::$api->_check_resource($evaluation->resource, null, 10000, 50);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

	  print "Then the measured " . $item["measure"] ." is " . $item["value"] . "\n";
          $evaluation = self::$api->get_evaluation($evaluation->resource);
          $this->assertEquals(floatval($evaluation->object->result->model->{$item["measure"]}), floatval($item["value"]));

      }
    }

    /*  Successfully creating an evaluation for an ensemble */
    public function test_scenario2() {
        $data = array(array('filename' => 'data/iris.csv',
                          'number_of_models' => 5,
                          'measure' => 'average_phi',
                          'value' => '0.97064',
                          'tlp' => 1));
        foreach($data as $item) {
          print "\nSuccessfully creating an evaluation for an ensemble\n";
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

          print "And I create a ensemble of ". $item["number_of_models"] . " models.\n";
          $ensemble = self::$api->create_ensemble($dataset->resource, array("number_of_models"=> $item["number_of_models"], "ensemble_sample" => array("seed" => 'BigML', 'rate'=> 0.70)));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $ensemble->code);

          print "And I wait until the ensemble is ready\n";
          $resource = self::$api->_check_resource($ensemble->resource, null, 10000, 50);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "When I create an evaluation for the model with the dataset\n";
          $evaluation = self::$api->create_evaluation($ensemble, $dataset);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $evaluation->code);

          print "And I wait until the evaluation is ready\n";
          $resource = self::$api->_check_resource($evaluation->resource, null, 10000, 50);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "Then the measured " . $item["measure"] ." is " . $item["value"] . "\n";
          $evaluation = self::$api->get_evaluation($evaluation->resource);
          $this->assertEquals(floatval($evaluation->object->result->model->{$item["measure"]}), floatval($item["value"]));

        }
    }


    /* Successfully creating an evaluation for a logistic regression  */
    public function test_scenario3() {
        $data = array(array('filename' => 'data/iris.csv',
                          'number_of_models' => 5,
                          'measure' => 'average_phi',
                          'value' => '0.89054',
                          'tlp' => 1));

       foreach($data as $item) {
          print "\nSuccessfully creating an evaluation for a logistic regression\n";
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

          print "And I create a logistic regression\n";
          $logistic_regression = self::$api->create_logistic_regression($dataset->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $logistic_regression->code);

          print "And I wait until the logistic regression is ready\n";
          $resource = self::$api->_check_resource($logistic_regression->resource, null, 10000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "When I create an evaluation for the logistic regression with the dataset\n";
          $evaluation = self::$api->create_evaluation($logistic_regression, $dataset);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $evaluation->code);

          print "And I wait until the evaluation is ready\n";
          $resource = self::$api->_check_resource($evaluation->resource, null, 10000, 50);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "Then the measured ". $item["measure"] ." is " . $item["value"] ."\n";
          $evaluation = self::$api->get_evaluation($evaluation->resource);
          $this->assertEquals(floatval($evaluation->object->result->model->{$item["measure"]}), floatval($item["value"]));

       }

    }
}
