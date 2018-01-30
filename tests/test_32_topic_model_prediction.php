<?php

include 'test_utils.php';

//importing
if (!class_exists('BigML\BigML')) {
  include '../bigml/bigml.php';
}

if (!class_exists('BigML\TopicModel')) {
  include '../bigml/topicmodel.php';
}

use BigML\BigML;
use BigML\BigMLRequest;
use BigML\TopicModel;

class BigMLTestBoostedEnsemble extends PHPUnit_Framework_TestCase
{
    protected static $username; # "you_username"
    protected static $api_key; # "your_api_key"
    protected static $api;
    protected static $project;

    public static function setUpBeforeClass() {
       self::$api =  new BigML(self::$username, self::$api_key, false);
       ini_set('memory_limit', '512M');
       $test_name=basename(preg_replace('/\.php$/', '', __FILE__));
       self::$api->delete_all_project_by_name($test_name);
       self::$project=self::$api->create_project(array('name'=> $test_name));
    }

    public static function tearDownAfterClass() {
       self::$api->delete_all_project_by_name(basename(preg_replace('/\.php$/', '', __FILE__)));
    }

    // Successfully creating a distribution from a Topic Model
    public function test_scenario1() {

        $data = array(
              array("model" => 
                      array("topic_model" => 
                            array("alpha" => 0.08,
                                  "beta" => 0.1,
                                  "hashed_seed" => 0,
                                  "language" => "en",
                                  "bigrams" => True,
                                  "case_sensitive" => False,
                                  "term_topic_assignments" => array(array(0, 0, 1, 2),
                                                                    array(0, 1, 2, 0),
                                                                    array(1, 2, 0, 0),
                                                                    array(0, 0, 2, 0)),
                                  "termset" => array("cycling", "playing", "shouldn't", "uńąnimous court"),
                                  "options" => [],
                                  "topics" => array(array("name" => "Topic 1",
                                                          "id" => "000000",
                                                          "top_terms" => array("a", "b"),
                                                          "probability" => 0.1),
                                                    array("name" => "Topic 2",
                                                          "id" => "000001",
                                                          "top_terms" => array("c", "d"),
                                                          "probability" => 0.1),
                                                    array("name" => "Topic 3",
                                                          "id" => "000000",
                                                          "top_terms" => array("e", "f"),
                                                          "probability" => 0.1),
                                                    array("name" => "Topic 4",
                                                          "id" => "000000",
                                                          "top_terms" => array("g", "h"),
                                                          "probability" => 0.1)),
                                  "fields" => array("000001" => array("datatype" => "string",
                                                                      "name" => "TEST TEXT",
                                                                      "optype" => "text",
                                                                      "order" => 0,
                                                                      "preferred" => True,
                                                                      "summary" => [],
                                                                      "term_analysis" => []))),
                            "resource" => "topicmodel/aaaaaabbbbbbccccccdddddd",
                            "status" => array("code" => 5)),
                      "text" => array("TEST TEXT" => "uńąnimous court 'UŃĄNIMOUS COURT' `play``the plays PLAYing SHOULDN'T CYCLE cycling shouldn't uńąnimous or court's"),
                      "expected" => array(array("name" => 'Topic 1', "probability" => 0.1211135786802),
                                          array("name" => 'Topic 2', "probability" => 0.23017131979695),
                                          array("name" => 'Topic 3', "probability" => 0.49785850253807),
                                          array("name" => 'Topic 4', "probability" => 0.15085659898477))));

        foreach($data as $item) {
            print "\nSuccessfully creating a local distribution from a Topic Model\n";
            print "I create a local topic model\n";
            $object = json_decode(json_encode($item["model"]), FALSE);
            $local_topic_model = new TopicModel($object);

            print "I create a distribution for local topic model\n";
            $distribution = $local_topic_model->distribution($item["text"]);

            print "And the distribution for local topic model equals the expected values.\n";
                $this->assertEquals($distribution, $item["expected"]);
        }
    }

    public function testscenerio2() {

        $data = array(array("filename" => 'data/spam.csv', 
                      "topic_model_name" => 'my new topic model name', 
                      "params" => array("fields"=> 
                                        array("000001"=> 
                                              array("optype"=> "text", 
                                                    "term_analysis"=> array("case_sensitive" => true, 
                                                                            "stem_words" => true, 
                                                                            "use_stopwords" => false, 
                                                                            "language" => "en"))))));

        foreach($data as $item) {
            print "\nGiven I create a data source uploading a data file\n";
            $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
            $this->assertEquals(1, $source->object->status->code);

            print "And I wait until the source is ready\n";
            $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I update the source\n";
            $updated_source = self::$api->update_source($source->resource, $item["params"], 3000, 30);

            print "And I create a dataset\n";
            $dataset = self::$api->create_dataset($updated_source->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
            $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

            print "And I wait until the dataset is ready\n";
            $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create topic model from a dataset\n";
            $topicmodel = self::$api->create_topicmodel($dataset->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $topicmodel->code);

            print "And I wait until the topic model is ready\n";
            $resource = self::$api->_check_resource($topicmodel->resource, null, 3000, 50);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I update the topic model name to " . $item["topic_model_name"] . "\n";
            $updated_topicmodel = self::$api->update_topicmodel($topicmodel->resource, array("name" => $item["topic_model_name"]));

            print "Then the topic model name is " . $item["topic_model_name"] . "\n";
            $new_topicmodel=self::$api->get_topicmodel($updated_topicmodel->resource);
            $this->assertEquals($new_topicmodel->object->name, $item["topic_model_name"]);
        }
    }
}
?>