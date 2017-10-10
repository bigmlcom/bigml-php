<?php

include 'test_utils.php';

//importing
if (!class_exists('bigml')) {
  include '../bigml/bigml.php';
}

if (!class_exists('Ensemble')) {
  include '../bigml/ensemble.php';
}

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


    // Successfully creating a prediction from a Ensemble

    public function test_scenario1() {

        $data = array(array("filename" => 'data/iris.csv',
                            "number_of_iterations" => 5,
                            "data_input" => array("petal width" => 1.5),
                            "prediction" => "Iris-versicolor",
                            "probabilities" => array(0.3041, 0.4243, 0.2715)),
                      array("filename" => 'data/grades.csv',
                            "number_of_iterations" => 5,
                            "data_input" => array("Midterm" => 95.4),
                            "prediction" => 77.85,
                            "probabilities" => array(77.8462))
                     );

        foreach($data as $item) {
            print "\nSuccessfully creating a local prediction from a Boosted Ensemble\n";
            print "I create a data source uploading a ". $item["filename"]. " file\n";
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

            print "And create a ensemble of ". $item["number_of_iterations"] . " iterations\n";
            $ensemble = self::$api->create_ensemble($dataset->resource, array("boosting" => array("iterations"=> $item["number_of_iterations"]), "ensemble_sample" => array("seed" => 'c71814f0fb38391a53976be721e8c5e2')));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $ensemble->code);

            print "And I wait until the ensemble is ready\n";
            $resource = self::$api->_check_resource($ensemble->resource, null, 3000, 50);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "When I create a local boosted ensemble\n";
            $ensemble = self::$api->get_ensemble($ensemble->resource);
            $local_ensemble = new Ensemble($ensemble, self::$api);

            print "When I create prediction for local ensemble for " . json_encode($item["data_input"]) . " \n";
            $prediction = $local_ensemble->predict($item["data_input"]);

            print "Then the prediction for local ensemble is equals " . $item["prediction"] . "\n";
            if(is_numeric($prediction)) {
                $this->assertEquals($item["prediction"], round($prediction, 2));
            } else {
                $this->assertEquals($item["prediction"], $prediction);
            }

            print "And the local probabilities are " . json_encode($item["probabilities"]) . "\n";
            $predict_probability = $local_ensemble->predict_probability($item["data_input"], true, MultiVote::PROBABILITY_CODE, Tree::LAST_PREDICTION, true);
            foreach (range(0, count($predict_probability) - 1) as $index) {
                $predict_probability[$index] = round($predict_probability[$index], 4);
            }
            $this->assertEquals($item["probabilities"], $predict_probability);

        }
    }
}
