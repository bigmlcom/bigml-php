<?php

include('test_utils.php');

if (!class_exists('bigml')) {
   include '../bigml/bigml.php';
}

use BigML\BigML;
use BigML\BigMLRequest;

class BigMLTestPredictions extends PHPUnit_Framework_TestCase
{
    protected static $username; # "you_username"
    protected static $api_key; # "your_api_key"

    protected static $api;
    protected static $data_localfile = './data/iris.csv';
    protected static $data_missingfile ='./data/iris_missing.csv';
    protected static $remote_localfile = 'http://jkcray.maths.ul.ie/ms4024/R-Files/SampleRDataFiles/Iris.txt';
    protected static $project;

    public static function setUpBeforeClass() {
       print __FILE__;
       self::$api =  new BigML(self::$username, self::$api_key, false);
       ini_set('memory_limit', '512M');
       $test_name=basename(preg_replace('/\.php$/', '', __FILE__));
       self::$api->delete_all_project_by_name($test_name);
       self::$project=self::$api->create_project(array('name'=> $test_name));
    }

    public static function tearDownAfterClass() {
       self::$api->delete_all_project_by_name(basename(preg_replace('/\.php$/', '', __FILE__)));
    }

    /*  Scenario: Successfully creating a prediction: */

    public function test_scenario1() {

        $data = array(array("filename"=>  self::$data_localfile,
	                    "data_input" => array('petal width'=> 0.5),
			    "objective" => "000004",
			    "prediction" => "Iris-setosa"),
		      array("filename"=>  "./data/iris_sp_chars.csv",
		            "data_input" => array('pétal&width'=> 0.5),
			    "objective" => "000004",
			    "prediction" => "Iris-setosa")
	             );

        foreach($data as $item) {
	    print "\nSuccessfully creating a prediction:\n";
	    print "Given I create a data source uploading a ". $item["filename"]. " file\n";
	    $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
	    $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
	    $this->assertEquals(1, $source->object->status->code);

            print "And I wait until the source is ready\n";
	    $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
	    $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create dataset with local source\n";
	    $dataset = self::$api->create_dataset($source->resource);
	    $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
	    $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

            print "And I wait until the dataset is ready\n";
	    $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
	    $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create model\n";
	    $model = self::$api->create_model($dataset->resource);
	    $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

            print "And I wait until the model is ready\n";
            $resource = self::$api->_check_resource($model->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

	    print "When I create a prediction for " . json_encode($item["data_input"]) . "\n";
	    $prediction = self::$api->create_prediction($model, $item["data_input"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);

            print "Then the prediction for " . $item["objective"] . " is " . $item["prediction"];

	    $this->assertEquals($item["prediction"], $prediction->object->prediction->{$item["objective"]});

        }


    }

    /*Scenario: Successfully creating a prediction from a source in a remote location */

    public function test_scenario2() { //TODO
        $data = array(array("remote"=>  "s3://bigml-public/csv/iris.csv",
                            "data_input" => array('petal width'=> 0.5),
                            "objective" => "000004",
                            "prediction" => "Iris-setosa")
                     );

        foreach($data as $item) {
            print "I create a data source uploading a ". $item["remote"]. " file\n";
	    $source = self::$api->create_source($item["remote"], $options=array('name'=>'remote_test_source'));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
            $this->assertEquals(1, $source->object->status->code);

            print "check local source is ready\n";
            $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "create dataset with local source\n";
            $dataset = self::$api->create_dataset($source->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
            $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

            print "check the dataset is ready\n";
            $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "create model\n";
            $model = self::$api->create_model($dataset->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

            print "check model is ready\n";
            $resource = self::$api->_check_resource($model->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "When I create a prediction for ";
            print_r($item["data_input"]);
            $prediction = self::$api->create_prediction($model, $item["data_input"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);

            print "Then the prediction for " . $item["objective"] . " is " . $item["prediction"];

            $this->assertEquals($item["prediction"], $prediction->object->prediction->{$item["objective"]});

        }
    }

    public function test_scenario3() {
       //TODO  Successfully creating a prediction from a asynchronous uploaded file:
    }

    public function test_scenario4() {
       //TODO Successfully creating a prediction from inline data source
    }

    /* Successfully creating a centroid and the associated dataset: */

    public function test_scenario5() {
       $data = array(array("filename"=> "./data/diabetes.csv",
                            "data_input" => array("pregnancies"=> 0,
                                                  "plasma glucose"=> 118,
                                                  "blood pressure"=> 84,
                                                  "triceps skin thickness"=> 47,
           					  "insulin"=> 230,
						  "bmi"=> 45.8,
						  "diabetes pedigree"=> 0.551,
						  "age"=> 31,
						  "diabetes"=> "true"),
                            "centroid" => "Cluster 3"));

        foreach($data as $item) {
	    print "\nSuccessfully creating a centroid and the associated dataset\n";
            print "Given I create a data source uploading a ". $item["filename"]. " file\n";
            $source = self::$api->create_source($item["filename"], $options=array('name'=>'diabetes_test_source', 'project'=> self::$project->resource));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
            $this->assertEquals(1, $source->object->status->code);

            print "And I wait until the source is ready\n";
            $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create dataset with local source\n";
            $dataset = self::$api->create_dataset($source->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
            $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

            print "And I wait until the dataset is ready\n";
            $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create a cluster\n";
            $cluster = self::$api->create_cluster($dataset->resource, array('seed'=>'BigML tests', 'k' =>  8, 'cluster_seed' => 'BigML'));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $cluster->code);
            $this->assertEquals(BigMLRequest::QUEUED, $cluster->object->status->code);

            print "And I wait until the cluster is ready\n";
            $resource = self::$api->_check_resource($cluster->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "When create a centroid for " . json_encode($item["data_input"]). "\n";
            $centroid = self::$api->create_centroid($cluster->resource, $item["data_input"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $centroid->code);

            print "Then the centroid is " . $item["centroid"] . "\n";
            $this->assertEquals($item["centroid"], $centroid->object->centroid_name);

        }
    }

    public function test_scenario6() {
         $data = array(array("filename"=> 'data/tiny_kdd.csv', "data_input" => array("src_bytes" => 350), "score" =>  0.92846 ),
	               array("filename"=> 'data/iris_sp_chars.csv', "data_input" => array( utf8_encode("p\xe9tal&width\x00") => 300), "score" =>  0.89313));

        foreach($data as $item) {
            print "\nSuccessfully creating an anomaly score\n";
            print "Given I create a data source uploading a ". $item["filename"]. " file\n";
            $source = self::$api->create_source($item["filename"], $options=array('name'=>'tiny', 'project' => self::$project->resource));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
            $this->assertEquals(1, $source->object->status->code);

            print "And I wait until the source is ready\n";
            $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create dataset with local source\n";
            $dataset = self::$api->create_dataset($source->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
            $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

            print "And I wait until the dataset is ready\n";
            $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "Then I create an anomaly detector from a dataset\n";
            $anomaly = self::$api->create_anomaly($dataset->resource, array('seed' =>  'BigML'));

            print "And I wait until the anomaly detector is ready\n";
            $resource = self::$api->_check_resource($anomaly->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
            print "When I create an anomaly score for " . json_encode($item["data_input"]).  "\n";
            $resource = self::$api->create_anomaly_score($anomaly->resource, $item["data_input"]);
	    print "Then the anomaly score is " . $item["score"] . "\n";
            $this->assertEquals($resource->object->score, $item["score"]);

        }
    }


}
