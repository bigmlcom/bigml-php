<?php

if (!class_exists('bigml')) {
   include '../bigml/bigml.php';
}

if (!class_exists('association')) {
  include '../bigml/association.php';
}

class BigMLTestAssociations extends PHPUnit_Framework_TestCase
{
    protected static $username; # "you_username"
    protected static $api_key; # "your_api_key"

    protected static $api;

    public static function setUpBeforeClass() {
       self::$api =  new BigML(self::$username, self::$api_key, true);
       ini_set('memory_limit', '512M');
    }
    /*
      Creating association
    */

    public function test_scenario1() {
      $data = array(array('filename' => 'data/iris.csv', 'test_name'=> 'my new association name'));

      foreach($data as $item) {
          print "\nSuccessfully creating associations from a dataset \n";
          print "Given I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "And I wait until the source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create dataset with local source\n";
          $dataset = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

          print "And I wail until the dataset is ready\n";
          $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create an associations from a dataset\n";
          $association = self::$api->create_association($dataset->resource,  array('name'=> 'new association'));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $association->code);

          print "And I wait until the association is ready\n";
          $resource = self::$api->_check_resource($association->resource, null, 10000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]); 

          print "And I update the association name to " . $item["test_name"] . "\n";
          $updated= self::$api->update_association($association->resource, array('name'=> $item["test_name"]));

          print "When I wait until the association is ready\n";
          $association = self::$api->get_association($association->resource);
            
          print "Then the associations  name is " . $item["test_name"] . "\n";
          $this->assertEquals($item["test_name"], $association->object->name);

      } 
    }

    public function test_scenario2() {
      $data = array(array('filename' => 'data/tiny_mushrooms.csv', 
	                      'item_list'=> array('Edible'),
			      'json_rule' => '{"rule_id":"000038","confidence":1,"leverage":0.07885,"lhs":[14],"lhs_cover":[0.704,176],"p_value":2.08358e-17,"rhs":[1],"rhs_cover":[0.888,222],"lift":1.12613,"support":[0.704,176]}'));

      foreach($data as $item) {
          print "\nSuccessfully creating local association object\n";
          print "Given I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"]);
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

          print "And I create an associations from a dataset\n";
          $association = self::$api->create_association($dataset->resource,  array('name'=> 'new association'));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $association->code);

          print "And I wait until the association is ready\n";
          $resource = self::$api->_check_resource($association->resource, null, 10000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print $association->resource . "\n";
          $association = self::$api->get_association($association->resource);
          print "And I create a local association\n";
          $local_association = new Association($association);

          print "When I get the rules for " . json_encode($item["item_list"]) . "\n";
          $association_rules = $local_association->get_rules(null, null, null, null, $item["item_list"]);

          print "Then the first rule is <". $item["json_rule"] .">\n";
          $this->assertEquals($association_rules[0]->to_json(), $item["json_rule"]);
      }

    }
}    
