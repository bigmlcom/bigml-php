<?php

include 'test_utils.php';

//importing
if (!class_exists('BigML\BigML')) {
   include '../bigml/bigml.php';
}

if (!class_exists('BigML\Deepnet')) {
   include '../bigml/deepnet.php';
}

use BigML\BigML;
use BigML\BigMLRequest;
use BigML\Deepnet;

class BigMLTestDeepnets extends PHPUnit_Framework_TestCase
{
   protected static $username; # "you_username"
   protected static $api_key; # "your_api_key"
   protected static $api;
   protected static $project;

   public static function setUpBeforeClass() {
      print __FILE__;
      self::$api =  new BigML(self::$username, self::$api_key, false);
      ini_set('memory_limit', '5120M');
      $test_name=basename(preg_replace('/\.php$/', '', __FILE__));
      self::$api->delete_all_project_by_name($test_name);
      self::$project=self::$api->create_project(array('name'=> $test_name));
   }

   public static function tearDownAfterClass() {
      self::$api->delete_all_project_by_name(basename(preg_replace('/\.php$/', '', __FILE__)));
   }

   public function test_scenario1() {

      $data = array(array("filename" => "data/iris.csv",
                           "data_input" => array("petal width" => 4),
                           "objective" => "000004",
                           "prediction" => "Iris-virginica",
                           "params" => array()),
                     array("filename" => "data/iris.csv",
                           "data_input" => array("sepal length" => 4.1,
                                                 "sepal width" => 2.4),
                           "objective" => "000004",
                           "prediction" => "Iris-setosa",
                           "params" => array()));

      foreach($data as $item) {
         print "\n\nSuccessfully comparing predictions for deepnets:\n";
         print "Given I create a data source uploading a " . $item["filename"] . " file\n";
         $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
         $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
         $this->assertEquals(1, $source->object->status->code);

         print "And I wait until the source is ready\n";
         $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
         $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

         if (isset($item["update_params"])) {
            print "And I update the source\n";
            $source = self::$api->update_source($source->resource, $item["update_params"], 3000, 30);
         }

         print "And I create a dataset\n";
         $dataset = self::$api->create_dataset($source->resource);
         $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
         $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

         print "And I wait until the dataset is ready\n";
         $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
         $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

         print "And I create a deepnet with objective " . $item["objective"] .
                                                        " and " . json_encode($item["params"]) . "\n";
         $deepnet = self::$api->create_deepnet($dataset->resource, $item["params"]);
         $this->assertEquals(BigMLRequest::HTTP_CREATED, $deepnet->code);

         print "And I wait until the deepnet is ready\n";
         $resource = self::$api->_check_resource($deepnet->resource, null, 3000, 500);
         $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

         print "And I create a local deepnet\n";
         $local_deepnet = new Deepnet($deepnet->resource);

         print "And I create a deepnet prediction\n";
         $prediction = self::$api->create_prediction($deepnet->resource, $item["data_input"]);

         print "The prediction is ";
         $prediction_value = $prediction->object->prediction->$item["objective"];
         print_r($prediction_value);


         print "\nAnd I create a local deepnet prediction\n";
         $local_prediction = $local_deepnet->predict($item["data_input"]);

         if (is_array($local_prediction["prediction"])) {
            $local_prediction = $local_prediction["prediction"];
         } else {
            $prediction_value = round($prediction_value, 5);
            $local_prediction = round($local_prediction, 5);
         }

         print "The local prediction is ";
         print_r($local_prediction);
         $this->assertEquals($prediction_value,
                             $local_prediction);

      }
   }

   public function test_scenario2() {

      $data = array(array("filename" => "data/iris_missing2.csv",
                           "data_input" => array(),
                           "objective" => "000004",
                           "prediction" => "Iris-setosa",
                           "params" => array()),
                     array("filename" => "data/spam.csv",
                           "data_input" => array(),
                           "objective" => "000000",
                           "prediction" => "ham",
                           "params" => array(),
                           "update_params" => array(
                              "fields"=> array(
                                 "000001"=> array(
                                    "optype"=> "text",
                                    "term_analysis"=> array(
                                       "case_sensitive" => true,
                                       "stem_words" => true,
                                       "use_stopwords" => false,
                                       "language" => "en"))))));

      foreach($data as $item) {
         print "\n\nSuccessfully comparing predictions for deepnets:\n";
         print "Given I create a data source uploading a " . $item["filename"] . " file\n";
         $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
         $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
         $this->assertEquals(1, $source->object->status->code);

         print "And I wait until the source is ready\n";
         $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
         $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

         if (isset($item["update_params"])) {
            print "And I update the source\n";
            $source = self::$api->update_source($source->resource, $item["update_params"], 3000, 30);
         }

         print "And I create a dataset\n";
         $dataset = self::$api->create_dataset($source->resource);
         $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
         $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

         print "And I wait until the dataset is ready\n";
         $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
         $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

         print "And I create a deepnet with objective " . $item["objective"] .
                                                        " and " . json_encode($item["params"]) . "\n";
         $deepnet = self::$api->create_deepnet($dataset->resource, $item["params"]);
         $this->assertEquals(BigMLRequest::HTTP_CREATED, $deepnet->code);

         print "And I wait until the deepnet is ready\n";
         $resource = self::$api->_check_resource($deepnet->resource, null, 3000, 500);
         $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

         print "And I create a local deepnet\n";
         $local_deepnet = new Deepnet($deepnet->resource);

         print "And I create a deepnet prediction\n";
         $prediction = self::$api->create_prediction($deepnet->resource, $item["data_input"]);

         print "The prediction is ";
         $prediction_value = $prediction->object->prediction->$item["objective"];
         print_r($prediction_value);


         print "\nAnd I create a local deepnet prediction\n";
         $local_prediction = $local_deepnet->predict($item["data_input"]);

         if (is_array($local_prediction["prediction"])) {
            $local_prediction = $local_prediction["prediction"];
         } else {
            $prediction_value = round($prediction_value, 5);
            $local_prediction = round($local_prediction, 5);
         }

         print "The local prediction is ";
         print_r($local_prediction);
         $this->assertEquals($prediction_value,
                             $local_prediction);

      }
   }
}
?>
