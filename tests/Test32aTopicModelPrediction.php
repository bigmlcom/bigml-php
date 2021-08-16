<?php
use PHPUnit\Framework\TestCase;


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

class Test32aTopiModelPrediction extends TestCase
{
    protected static $username; # "you_username"
    protected static $api_key; # "your_api_key"
    protected static $api;
    protected static $project;

    public static function setUpBeforeClass(): void {
       print __FILE__;
       self::$api =  new BigML(self::$username, self::$api_key, false);
       ini_set('memory_limit', '512M');
       $test_name=basename(preg_replace('/\.php$/', '', __FILE__));
       self::$api->delete_all_project_by_name($test_name);
       self::$project=self::$api->create_project(array('name'=> $test_name));
    }

    public static function tearDownAfterClass(): void {
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
                      "expected" => array(array("name" => 'Topic 1', "probability" => 0.17068527918781726),
                                          array("name" => 'Topic 2', "probability" => 0.1865482233502538),
                                          array("name" => 'Topic 3', "probability" => 0.5157043147208121),
                                          array("name" => 'Topic 4', "probability" => 0.12706218274111675))));

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
}
?>
