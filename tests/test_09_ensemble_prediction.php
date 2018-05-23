<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
  include '../bigml/bigml.php';
}

use BigML\BigML;
use BigML\BigMLRequest;

class BigMLTestEnsemble extends PHPUnit_Framework_TestCase
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
     Successfully creating a prediction from a local model
     */
    public function test_scenario1() {
        $data = array(array("filename" => 'data/iris.csv',
                            "number_of_models" => 5,
                            "tlp" => 1,
                            "data_input" => array("petal width" => 0.5),
                            "objective" => "000004",
                            "prediction" => "Iris-versicolor"),
                     array("filename" => 'data/iris_sp_chars.csv',
                            "number_of_models" => 5,
                            "tlp" => 1,
                            "data_input" => array("pétal&width" => 0.5),
                            "objective" => "000004",
                            "prediction" => "Iris-versicolor"),
                      array("filename" => 'data/grades.csv',
                            "number_of_models" => 10,
                            "tlp" => 1,
                            "data_input" => array("Assignment" => 81.22,  "Tutorial"=> 91.95, "Midterm"=> 79.38, "TakeHome"=> 105.93),
                            "objective" => "000005",
                            "prediction" => "84.556"),
                      array("filename" => 'data/grades.csv',
                            "number_of_models" => 10,
                            "tlp" => 1,
                            "data_input" => array("Assignment" => 97.33,  "Tutorial"=> 106.74, "Midterm"=> 76.88, "TakeHome"=> 108.89),
                            "objective" => "000005",
                            "prediction" => "73.13558")
                     );

        foreach($data as $item) {
            print "I create a data source uploading a ". $item["filename"]. " file\n";
            $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
            $this->assertEquals(1, $source->object->status->code);

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

            print "And I create an ensemble of " . json_encode($item["number_of_models"]) . "\n";
            $ensemble = self::$api->create_ensemble($dataset->resource, array("number_of_models"=> $item["number_of_models"], "ensemble_sample" => array("seed" => 'BigML', 'rate'=> 0.70)));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $ensemble->code);

            print "And I wait until the ensemble is ready\n";
            $resource = self::$api->_check_resource($ensemble->resource, null, 3000, 50);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print " When I create an ensemble prediction for" . json_encode($item["data_input"]) . "\n";
            $prediction = self::$api->create_prediction($ensemble, $item["data_input"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);

            print "And I wait until the prediction is ready\n";
            $resource = self::$api->_check_resource($prediction, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "Then the prediction for ". $item["objective"] ." is " . $item["prediction"] . "\n";
            $this->assertEquals(round($item["prediction"], 4), round($prediction->object->prediction->{$item["objective"]}, 4));
        }
    }
}
