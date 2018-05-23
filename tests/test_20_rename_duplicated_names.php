<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
  include '../bigml/bigml.php';
}

if (!class_exists('BigML\Model')) {
  include '../bigml/model.php';
}

use BigML\BigML;
use BigML\BigMLRequest;
use BigML\Model;

class BigMLTestRenameDuplicatedNames extends PHPUnit_Framework_TestCase
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
     Successfully changing duplicated field names
    */

    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv',
                          'options' => array("fields" => array("000001" => array("name" => "species"))),
			  'new_name' => 'species1',
                          'field_id' => '000001'),
                    array('filename' => 'data/iris.csv',
                          'options' => array("fields" => array("000001" => array("name" => "petal width"))),
                          'new_name' => 'petal width3',
                          'field_id' => '000003'));

      print "Successfully changing duplicated field names\n";

      foreach($data as $item) {
          print "\nSuccessfully changing duplicated field names\n";
          print "Given I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "And I wait until the source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create dataset with local source\n";
          $dataset = self::$api->create_dataset($source->resource, $item["options"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

          print "And I wait until the dataset is ready " . $dataset->resource . " \n";
          $resource = self::$api->_check_resource($dataset->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create model\n";
          $model = self::$api->create_model($dataset->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

          print "And I wait until the model is ready\n";
          $resource = self::$api->_check_resource($model->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create a local model\n";
          $local_model =  new Model($model->resource, self::$api);
          print "Then <". $item["field_id"] . "> field's name is changed to <". $item["new_name"].">\n";
          $this->assertEquals($local_model->tree->fields->{$item["field_id"]}->name, $item["new_name"]);


      }
    }

}
