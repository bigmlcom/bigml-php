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
        $data = array(array("model" => 'data/iris_model.json',
                            "data_input" => array("petal length" => 0.5),
                            "prediction" => "Iris-setosa",
                            "confidence" => 0.90594));
	
        foreach($data as $item) {
           $model =  new Model($item["model"], self::$api);
           $prediction = $model->predict($item["data_input"]);
           $this->assertEquals($prediction->output, $item["prediction"]);
	   $this->assertEquals($prediction->confidence, $item["confidence"]);
        }
    }

    /*
     Successfully creating a multiple prediction from a local model in a json file
    */
    public function test_scenario2() {
        $data = array(array("model"=> 'data/iris_model.json', 
                            "data_input" => array("petal length" => 3), 
                            "prediction" => array(array("count" => 42, 
                                                        "confidence" => 0.4006020980792863, 
                                                        "prediction" => "Iris-versicolor", 
                                                        "probability" => 0.5060240963855421), 
                                            array("count" => 41, 
                                                  "confidence" => 0.3890868795664999, 
                                                  "prediction" => "Iris-virginica", 
                                                  "probability" => 0.4939759036144578))));
        foreach($data as $item) {
	   $model =  new Model($item["model"], self::$api);
	   $prediction = $model->predict($item["data_input"], true, false, STDOUT, false, Tree::LAST_PREDICTION, false, false, false, false, false, false, false, false, 'all');
	   $this->assertEquals($prediction, $item["prediction"]);
	}

    }

}    
