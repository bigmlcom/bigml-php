<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
   include '../bigml/bigml.php';
}
if (!class_exists('BigML\MultiVote')) {
   include '../bigml/multivote.php';
}

use BigML\BigML;
use BigML\MultiVote;

class BigMLTestMultiVote extends PHPUnit_Framework_TestCase
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
	   print "\nSuccessfully computing predictions combinations\n";
	   $predictions = json_decode(file_get_contents($item["predictions"]));

           print "Given I create a MultiVote for the set of predictions in file " . $item["predictions"] . "\n";
           $multivote =  new MultiVote($predictions);
	   print "When I compute the prediction with confidence using method " . $item["method"] . "\n";
	   $combined_results = $multivote->combine($item["method"], true);
	   print "And I compute the prediction without confidence using method " . $item["method"] . "\n";
           $combined_results_no_confidence = $multivote->combine($item["method"]);


           if ($multivote->is_regression()) {
	      print "Then the combined prediction is "  . $item["prediction"] . "\n";
              $this->assertEquals(round($combined_results[0], 6), round($item["prediction"],6));
	      print "And the combined prediction without confidence is " . $item["prediction"] . "\n";
	      $this->assertEquals(round($combined_results_no_confidence, 6), round($item["prediction"] ,6));
	   } else {
	      print "Then the combined prediction is "  . $item["prediction"] . "\n";
	      $this->assertEquals($combined_results[0], $item["prediction"]);
	      print "And the combined prediction without confidence is " . $item["prediction"] . "\n";
	      $this->assertEquals($combined_results_no_confidence, $item["prediction"]);
	   }

           print "And the confidence for the combined prediction is " . $item["confidence"] . "\n";
	   $this->assertEquals(round($combined_results[1], 6), round($item["confidence"],6));
        }
    }
}
