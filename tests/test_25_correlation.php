<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
  include '../bigml/bigml.php';
}

use BigML\BigML;
use BigML\BigMLRequest;

class BigMLTestCorrelation extends PHPUnit_Framework_TestCase
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
     Successfully creating a correlation from a dataset
    */

    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv',
			  'correlation_name' => "my new correlation name"));


      foreach($data as $item) {
          print "\nSuccessfully creating a correlation from a dataset\n";
          print "Given I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"], $options=array('project'=> self::$project->resource));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "And I wait until the source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create dataset with local source\n";
          $dataset = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

          print "And I wait until the dataset is ready\n";
          $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create a correlation from a dataset\n";
          $correlation = self::$api->create_correlation($dataset->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $correlation->code);

          print "And I wait until the correlation is ready\n";
          $resource = self::$api->_check_resource($correlation->resource, null, 10000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I update the correlation with new name ". $item["correlation_name"] . "\n";
          $updated= self::$api->update_correlation($correlation->resource, array('name'=> $item["correlation_name"]));

          $correlation = self::$api->get_correlation($correlation->resource);

          print "Then the correlation name is " . $item["correlation_name"]. "\n";
          $this->assertEquals($item["correlation_name"], $correlation->object->name);

      }
    }

}
