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
     Creating datasets and models associated to a cluster
    */

/*    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv', 'centroid' => '000001'));


      print "Successfully creating datasets for first centroid of a cluster\n";

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

          print "create a cluster\n";
          $cluster = self::$api->create_cluster($dataset->resource, array('seed'=>'BigML tests', 'k' =>  8));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $cluster->code);
          $this->assertEquals(BigMLRequest::QUEUED, $cluster->object->status->code);

          print "I wait until the cluster is ready\n";
          $resource = self::$api->_check_resource($cluster->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
   
          print "I create a dataset associated to centroid\n";
          $dataset = self::$api->create_dataset($cluster->resource, array('centroid' => $item["centroid"]));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
 
          print "check the dataset is ready\n";
          $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "the dataset is associated to the centroid " .$item["centroid"] . " of the cluster \n";
          $cluster = self::$api->get_cluster($cluster->resource);
          $this->assertEquals(BigMLRequest::HTTP_OK, $cluster->code);

          $this->assertEquals("dataset/" . $cluster->object->cluster_datasets->{$item["centroid"]}, $dataset->resource);

 
      } 
    }
*/
    /* Successfully creating models for first centroid of a cluster */ 
    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv', 
                          'centroid' => '000001', 
                          'options' => array("model_clusters"=> true, "k" => 8)));

      print " Successfully creating models for first centroid of a cluster\n";

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

          print "create a cluster with options " . json_encode($item["options"]) .  " \n";
          $cluster = self::$api->create_cluster($dataset->resource, $item["options"]); 
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $cluster->code);
          $this->assertEquals(BigMLRequest::QUEUED, $cluster->object->status->code);

          print "I wait until the cluster is ready\n";
          $resource = self::$api->_check_resource($cluster->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "I create a dataset associated to centroid\n";
          $dataset = self::$api->create_dataset($cluster->resource, array('centroid' => $item["centroid"]));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);

          print "check the dataset is ready\n";
          $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "the dataset is associated to the centroid " .$item["centroid"] . " of the cluster \n";
          $cluster = self::$api->get_cluster($cluster->resource);
          $this->assertEquals(BigMLRequest::HTTP_OK, $cluster->code);

          $this->assertEquals("dataset/" . $cluster->object->cluster_datasets->{$item["centroid"]}, $dataset->resource);

      }
   }   

}    
