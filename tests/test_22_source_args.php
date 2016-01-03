<?php
include '../bigml/bigml.php';
include '../bigml/ensemble.php';
include '../bigml/cluster.php';
include '../bigml/fields.php';

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
     Uploading source with structured args
    */

    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv',
			  'args' =>  array("tags" => array("my tag", "my second tag"))),
	           array('filename' => 'data/iris.csv',
                          'args' =>  array("name" => "Testing unicode names: áé")));


      print "Uploading source with structured args\n";
      foreach($data as $item) {
          print "I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"], $options=$item["args"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "check local source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
          print "the source exists and has args " . json_encode($item["args"]) . "\n";

          foreach ($item["args"] as $key => $value) {
            $this->assertEquals($resource["resource"]->object->{$key}, $value); 
          }
      } 
    }

}    
