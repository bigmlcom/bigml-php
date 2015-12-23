<?php
include '../bigml/bigml.php';
include '../bigml/ensemble.php';
include '../bigml/cluster.php';
include '../bigml/fields.php';
#include '../bigml/multimodel.php';

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
                            "prediction" => "88.205575"),
                      array("filename" => 'data/grades.csv',
                            "number_of_models" => 10,
                            "tlp" => 1,
                            "data_input" => array("Assignment" => 97.33,  "Tutorial"=> 106.74, "Midterm"=> 76.88, "TakeHome"=> 108.89), 
                            "objective" => "000005",
                            "prediction" => "84.29401")
                     );
	
        foreach($data as $item) {
            print "I create a data source uploading a ". $item["filename"]. " file\n";
            $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source'));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
            $this->assertEquals(1, $source->object->status->code);

            print "check local source is ready\n";
            $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "create dataset with local source\n";
            $dataset = self::$api->create_dataset($source->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
            $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

            print "check the dataset is ready\n";
            $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "create a ensemble from "; 
            $ensemble = self::$api->create_ensemble($dataset->resource, array("number_of_models"=> $item["number_of_models"], "tlp"=> $item["tlp"],"seed" => 'BigML', 'sample_rate'=> 0.70));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $ensemble->code);

            print "check the ensemble is ready\n";
            $resource = self::$api->_check_resource($ensemble->resource, null, 3000, 50);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "create a prediction for ensemble\n";
            $prediction = self::$api->create_prediction($ensemble, $item["data_input"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);

            print "check the prediction is ready\n";
            $resource = self::$api->_check_resource($prediction, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "the prediction ensemble for \n";
            $this->assertEquals($item["prediction"], $prediction->object->prediction->{$item["objective"]});
        }
    }
}    
