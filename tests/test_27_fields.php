<?php
if (!class_exists('bigml')) {
  include '../bigml/bigml.php';
} 
if (!class_exists('fields')) { 
  include '../bigml/fields.php';
}  

class BigMLTestFields extends PHPUnit_Framework_TestCase
{
    protected static $username; # "you_username"
    protected static $api_key; # "your_api_key"

    protected static $api;

    public static function setUpBeforeClass() {
       self::$api =  new BigML(self::$username, self::$api_key, true);
       ini_set('memory_limit', '512M');
    }
    /*
      Successfully creating a Fields object
    */

    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv', 'objective_column' => 0, 'objective_id' => '000000'));

      foreach($data as $item) {
          print "\nSuccessfully creating a Fields object\n";
          $source = self::$api->get_source("source/568ee7be8a318f5bfc00e874");#$source->resource);
          print "And I create a Fields object from the source with objective column " . $item["objective_column"] . "\n";
          $fields = new Fields($source, null, null, null, intval($item["objective_column"]), true);

	  print "Then the object id is " . $item["objective_id"] . "\n";
          $this->assertEquals($fields->field_id($fields->objective_field), $item["objective_id"]); 
	  
      } 
    }

}    
