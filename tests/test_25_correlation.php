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
     Successfully creating a correlation from a dataset
    */

    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv',
			  'correlation_name' => "my new correlation name")); 


      print "Successfully creating a correlation from a dataset\n";
      foreach($data as $item) {
          print "I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "check local source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "create dataset with local source\n";
          $dataset = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);
          
          print "check the dataset is ready\n";
          $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create a correlation from a dataset\n";
          $correlation = self::$api->create_correlation($dataset->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $correlation->code);

          print "And I wait until the correlation is ready\n";
          $resource = self::$api->_check_resource($correlation->resource, null, 10000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "i update the correlation with new name ". $item["correlation_name"] . "\n";
          $updated= self::$api->update_correlation($correlation->resource, array('name'=> $item["correlation_name"]));

          $correlation = self::$api->get_correlation($correlation->resource);

          print "Then the correlation name is " . $item["correlation_name"]. "\n";
          $this->assertEquals($item["correlation_name"], $correlation->object->name);
         
      } 
    }

}    
