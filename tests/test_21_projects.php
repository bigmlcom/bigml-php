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
      Testing projects REST api calls
    */

    public function test_scenario1() {
      $data = array(array('name' => 'my project',
			  'new_name' => 'my new project'));

      print "Testing projects REST api calls\n";

      foreach($data as $item) {
          print "I create a project with name ". $item["name"]. "\n";
          $project = self::$api->create_project(array('name'=> $item["name"]));
	  print "check project is ready\n";
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $project->code);
          print "i check project name\n";
	  $this->assertEquals($item["name"], $project->object->name);
          print "i update the project with new name\n";
	  $updated= self::$api->update_project($project->resource, array('name'=> $item["new_name"]));

	  $project = self::$api->get_project($project->resource);

	  print "i check project name\n";
	  $this->assertEquals($item["name"], $project->object->name);

          print  "i delete the project\n";
	  $resource = self::$api->delete_project($project->resource);

          foreach (range(0, 3) as $count) {
	     $resource = self::$api->get_project($project->resource);
	     if ($resource->code == BigMLRequest::HTTP_NOT_FOUND) break;
	     sleep(2);
	  }

          $this->assertEquals($resource->code, BigMLRequest::HTTP_NOT_FOUND);

      } 
    }

}    
