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
      Successfully computing predictions combinations
     */
    public function test_scenario1() {
        $data = array(array("predictions" => "./data/predictions_c.json", "method" => 0, "prediction" => "a", "confidence" => 0.450471270879), 
	              array("predictions" => "./data/predictions_c.json", "method" => 1, "prediction" => "a", "confidence" => 0.552021302649),
		      array("predictions" => "./data/predictions_c.json", "method" => 2, "prediction" => "a", "confidence" => 0.403632421178),
		      array("predictions" => "./data/predictions_r.json", "method" => 0, "prediction" => 1.55555556667, "confidence" => 0.400079152063),
		      array("predictions" => "./data/predictions_r.json", "method" => 1, "prediction" => 1.59376845074, "confidence" => 0.248366474212),
		      array("predictions" => "./data/predictions_r.json", "method" => 2, "prediction" => 1.55555556667 , "confidence" => 0.400079152063));
	
        foreach($data as $item) {
	   $predictions = json_decode(file_get_contents($item["predictions"]));

           $multivote =  new MultiVote($predictions);
	   $combined_results = $multivote->combine($item["method"], true); 
           $combined_results_no_confidence = $multivote->combine($item["method"]); 
           
           if ($multivote->is_regression()) {
              $this->assertEquals(round($combined_results[0], 6), round($item["prediction"],6));
	      $this->assertEquals(round($combined_results_no_confidence, 6), round($item["prediction"] ,6));
	   } else {
	      $this->assertEquals($combined_results[0], $item["prediction"]);
	      $this->assertEquals($combined_results_no_confidence, $item["prediction"]);
	   }

	   $this->assertEquals(round($combined_results[1], 6), round($item["confidence"],6));
        }
    }
}    
