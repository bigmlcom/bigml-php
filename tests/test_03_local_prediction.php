<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
  include '../bigml/bigml.php';
}

if (!class_exists('BigML\Model')) {
 include '../bigml/model.php';
}

use BigML\BigML;
use BigML\Model;

class BigMLTestLocalPredictions extends PHPUnit_Framework_TestCase
{
    protected static $username; # "you_username"
    protected static $api_key; # "your_api_key"

    protected static $api;

    public static function setUpBeforeClass() {
       print __FILE__;
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
	   print "\nSuccessfully creating a prediction from a local model in a json file\n";
           print "Given I create a local model from a " . $item["model"] . " file\n";
           $model =  new Model($item["model"], self::$api);
	   print "When I create a local prediction for " . json_encode($item["data_input"]) . " with confidence\n";
           $prediction = $model->predict($item["data_input"]);
	   print "Then the local prediction is " . $item["prediction"] . "\n";
           $this->assertEquals($prediction->output, $item["prediction"]);
	   print "And the local prediction's confidence is " . $item["confidence"] . "\n";
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
	   print "\nSuccessfully creating a multiple prediction from a local model in a json file\n";
	   print "Given I create a local model from a " . $item["model"] . "\n";
	   $model =  new Model($item["model"], self::$api);
	   print "When I create a multiple local prediction for " . json_encode($item["data_input"]) . "\n";
	   $prediction = $model->predict($item["data_input"], true, false, STDOUT, false, \BigML\Tree::LAST_PREDICTION, false, false, false, false, false, false, false, false, false, 'all');
	   print " Then the multiple local prediction is " . json_encode($item["prediction"]) . "\n";
	   $this->assertEquals($prediction, $item["prediction"]);
	}

    }

}
