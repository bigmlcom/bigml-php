<?php

include('test_utils.php');

if (!class_exists('bigml')) {
   include '../bigml/bigml.php';
}

use BigML\BigML;
use BigML\BigMLRequest;

class BigMLTestOrganizations extends PHPUnit_Framework_TestCase
{
    protected static $api;
    protected static $api_org;
    protected static $data_localfile = './data/iris.csv';
    protected static $project;
    protected static $test_name;

    public static function setUpBeforeClass() {

       print __FILE__;
       $org = getenv("BIGML_ORGANIZATION");
       if ($org == null) {
          throw new Exception("You need to define env variable " .
                              "BIGML_ORGANIZATION to run this test.");
       }

       self::$api_org =  new BigML(["organization" => $org]);
       ini_set('memory_limit', '512M');
       self::$test_name = basename(preg_replace('/\.php$/', '', __FILE__));
       self::$api_org->delete_all_project_by_name(self::$test_name);
       self::$project = self::$api_org->create_project(array('name'=> self::$test_name));
       self::assertEquals($org, self::$project->object->organization);

       self::$api = new BigML(["project" => self::$project->resource]);
    }

    public static function tearDownAfterClass() {
       self::$api_org->delete_all_project_by_name(self::$test_name);
    }

    /*  Scenario: Successfully creating a prediction: */

    public function test_scenario1() {

        $data = array(array("filename"=>  self::$data_localfile,
                            "data_input" => array('petal width'=> 0.5),
                            "objective" => "000004",
                            "prediction" => "Iris-setosa")
        );

        foreach($data as $item) {

           print "\nSuccessfully creating a prediction:\n";
           print "Given I create a data source uploading a ". $item["filename"]. " file\n";
           $source = self::$api->create_source($item["filename"],
                                               $options = array(
                                                  'name'=>'local_test_source'));
           $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
           $this->assertEquals(1, $source->object->status->code);
           $this->assertEquals(self::$project->resource, $source->object->project);

           print "And I wait until the source is ready\n";
           $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
           $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

           print "And I create dataset with local source\n";
           $dataset = self::$api->create_dataset($source->resource);
           $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
           $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

           print "And I wait until the dataset is ready\n";
           $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
           $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

           print "And I create model\n";
           $model = self::$api->create_model($dataset->resource);
           $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

           print "And I wait until the model is ready\n";
           $resource = self::$api->_check_resource($model->resource, null, 3000, 30);
           $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

           print "When I create a prediction for " . json_encode($item["data_input"]) . "\n";
           $prediction = self::$api->create_prediction($model, $item["data_input"]);
           $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);

           print "Then the prediction for " . $item["objective"] . " is " . $item["prediction"];

           $this->assertEquals($item["prediction"],
                               $prediction->object->prediction->{$item["objective"]});

        }
    }
}
