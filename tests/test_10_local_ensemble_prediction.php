<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
  include '../bigml/bigml.php';
}

if (!class_exists('BigML\Ensemble')) {
  include '../bigml/ensemble.php';
}

use BigML\BigML;
use BigML\BigMLRequest;
use BigML\Ensemble;

class BigMLTestLocalEnsemble extends PHPUnit_Framework_TestCase
{
    protected static $username; # "you_username"
    protected static $api_key; # "your_api_key"
    protected static $api;
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


    // Successfully creating a prediction from a Ensemble

    public function test_scenario1() {

        $data = array(array("filename" => 'data/iris.csv',
                            "number_of_models" => 5,
                            "tlp" => 1,
                            "data_input" => array("petal width" => 0.5),
                            "prediction" => "Iris-versicolor",
    		                "confidence" => 0.3687,
                            "probabilities" => array(0.3403, 0.4150, 0.2447))
                     );

        foreach($data as $item) {
            print "\nSuccessfully creating a local prediction from an Ensemble\n";
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

            print "And create a ensemble of ". $item["number_of_models"] . " models.\n";
            $ensemble = self::$api->create_ensemble($dataset->resource, array("number_of_models"=> $item["number_of_models"], "ensemble_sample" => array("seed" => 'BigML', 'rate'=> 0.70)));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $ensemble->code);

            print "And I wait until the ensemble is ready\n";
            $resource = self::$api->_check_resource($ensemble->resource, null, 3000, 50);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "When I create a local ensemble\n";
            $ensemble = self::$api->get_ensemble($ensemble->resource);
            $local_ensemble = new Ensemble($ensemble, self::$api);

            print "When I create prediction for local ensemble with confidence for " . json_encode($item["data_input"]) . " \n";
            $prediction = $local_ensemble->predict($item["data_input"], true, \BigML\MultiVote::PLURALITY_CODE, true);

            print "Then the prediction for local ensemble is equals " . $item["prediction"] . "\n";
            $this->assertEquals($item["prediction"], $prediction[0]);

            print "And the local prediction's confidence is " . $item["confidence"] . "\n";
            $this->assertEquals($item["confidence"], round($prediction[1], 4));

            print "And the local probabilities are " . json_encode($item["probabilities"]) . "\n";
            $predict_probability = $local_ensemble->predict_probability($item["data_input"], true, \BigML\MultiVote::PROBABILITY_CODE, \BigML\Tree::LAST_PREDICTION, true);
            foreach (range(0, count($predict_probability) - 1) as $index) {
                $predict_probability[$index] = round($predict_probability[$index], 4);
            }
            $this->assertEquals($item["probabilities"], $predict_probability);

        }
    }

    // Successfully obtaining field importance from an Ensemble
    public function test_scenario2() {

        $data = array(array("filename" => 'data/iris.csv',
                            "params1" => array("input_fields" => array("000000", "000001","000003", "000004")),
                            "params2" => array("input_fields" => array("000000", "000001","000002", "000004")),
                            "params3" => array("input_fields" => array("000000", "000001","000002", "000003", "000004")),
                            "number_of_models" => 3,
                            "field_importance" => array("000002"=>0.5269933333333333, "000003" => 0.38936, "000000" => 0.04662333333333333, "000001" => 0.037026666666666666)));

        foreach($data as $item) {
            print "\nSuccessfully obtaining field importance from an Ensemble\n";
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

            print "And I create model with params " .  json_encode($item["params1"]) . "\n";
            $model_1 = self::$api->create_model($dataset->resource, $item["params1"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_1->code);

            print "And I wait until the model is ready\n";
            $resource = self::$api->_check_resource($model_1->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create model with params " .  json_encode($item["params2"]) . "\n";
            $model_2 = self::$api->create_model($dataset->resource, $item["params2"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_2->code);

            print "And I wait until the model is ready\n";
            $resource = self::$api->_check_resource($model_2->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create model with params " .  json_encode($item["params3"]) . "\n";
            $model_3 = self::$api->create_model($dataset->resource, $item["params3"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_3->code);

            print "And I wait until the model is ready\n";
            $resource = self::$api->_check_resource($model_3->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "When I create a local ensemble with the last  ". $item["number_of_models"] .  " models \n";

            $local_ensemble = new Ensemble(array($model_1->resource, $model_2->resource, $model_3->resource), self::$api, $item["number_of_models"]);

            $field_importance_data = $local_ensemble->field_importance_data();

            print "Then the field importance text is " . json_encode($item["field_importance"]) ." \n";
            $this->assertEquals($item["field_importance"], $field_importance_data[0]);
        }

    }
    // Successfully creating a local prediction from an Ensemble adding confidence
    public function test_scenario3() {

        $data = array(array("filename" => 'data/iris.csv',
                            "number_of_models" => 5,
                            "tlp" => 1,
                            "data_input" => array("petal width" => 0.5),
                            "prediction" => "Iris-versicolor",
                            "confidence" => 0.3687)
                     );

        foreach($data as $item) {
            print "\nSuccessfully creating a local prediction from an Ensemble adding confidence\n";
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

            print "And I create a ensemble of " .$item["number_of_models"] . "  models.\n";
            $ensemble = self::$api->create_ensemble($dataset->resource, array("number_of_models"=> $item["number_of_models"], "ensemble_sample" => array("seed" => 'BigML', 'rate'=> 0.70)));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $ensemble->code);

            print "And I wait until the ensemble is ready\n";
            $resource = self::$api->_check_resource($ensemble->resource, null, 3000, 50);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create a local ensemble:\n";
            $ensemble = self::$api->get_ensemble($ensemble->resource);
            $local_ensemble = new Ensemble($ensemble, self::$api);

            print "When I create a local ensemble prediction for ". json_encode($item["data_input"]) ." in JSON adding confidence\n";
            $prediction = $local_ensemble->predict($item["data_input"], true, \BigML\MultiVote::PLURALITY_CODE, false, true);

            print "Then the local prediction is equals " . $item["prediction"] . "\n";
            $this->assertEquals($item["prediction"], $prediction[0]);

            print "And the local prediction's confidence is " . $item["confidence"] . "\n";
            $this->assertEquals($item["confidence"], round($prediction[1], 4));

        }

   }
   // Successfully obtaining field importance from an Ensemble created from local models
   public function test_scenario4() {

        $data = array(array("filename" => 'data/iris.csv',
                            "params1" => array("input_fields" => array("000000", "000001","000003", "000004")),
                            "params2" => array("input_fields" => array("000000", "000001","000002", "000004")),
                            "params3" => array("input_fields" => array("000000", "000001","000002", "000003", "000004")),
                            "number_of_models" => 3,
                            "field_importance" => array("000002"=>0.5269933333333333, "000003" => 0.38936, "000000" => 0.04662333333333333, "000001" => 0.037026666666666666)));

        foreach($data as $item) {
            print "\nSuccessfully obtaining field importance from an Ensemble created from local models\n";
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

            print "And I create model with params " . json_encode($item["params1"]) . "\n";
            $model_1 = self::$api->create_model($dataset->resource, $item["params1"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_1->code);

            print "And I wait until the model is ready\n";
            $resource = self::$api->_check_resource($model_1->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "create model with params " .json_encode($item["params2"]) . "\n";
            $model_2 = self::$api->create_model($dataset->resource, $item["params2"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_2->code);

            print "And I wait until the model is ready\n";
            $resource = self::$api->_check_resource($model_2->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "create model with params ". json_encode($item["params3"]) . "\n";
            $model_3 = self::$api->create_model($dataset->resource, $item["params3"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_3->code);

            print "And I wait until the model is ready\n";
            $resource = self::$api->_check_resource($model_3->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            $model_1 = self::$api->get_model($model_1->resource);
        $model_2 = self::$api->get_model($model_2->resource);
        $model_3 = self::$api->get_model($model_3->resource);

            $local_model_1 = new \BigML\Model($model_1);
            $local_model_2 = new \BigML\Model($model_2);
        $local_model_3 = new \BigML\Model($model_3);

            print "When I create a local Ensemble with the last " . $item["number_of_models"] ." local models\n";
            $local_ensemble = new Ensemble(array($local_model_1, $local_model_2, $local_model_3), self::$api);

            $field_importance_data = $local_ensemble->field_importance_data();

            print "Then the field importance text is " . json_encode($item["field_importance"]) . "\n";
            $this->assertEquals($item["field_importance"], $field_importance_data[0]);

        }
   }

   //  Successfully creating a local prediction from an Ensemble
   public function test_scenario5() {
        $data = array(array("filename" => 'data/grades.csv',
                            "number_of_models" => 2,
                            "tlp" => 1,
                            "data_input" => array(),
                            "prediction" => 65.83)
        );

        foreach($data as $item) {
            print "\nSuccessfully creating a local prediction from an Ensemble\n";
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

            print "And I create a ensemble of " . $item["number_of_models"] . " models.\n";
            $ensemble = self::$api->create_ensemble($dataset->resource, array("number_of_models"=> $item["number_of_models"], "ensemble_sample" => array("seed" => 'BigML', 'rate'=> 0.70)));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $ensemble->code);

            print "And I wait until the ensemble is ready\n";
            $resource = self::$api->_check_resource($ensemble->resource, null, 3000, 50);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create a local ensemble:\n";
            $ensemble = self::$api->get_ensemble($ensemble->resource);
            $local_ensemble = new Ensemble($ensemble, self::$api);

            print "When I create a local ensemble prediction using median with confidence  for " . json_encode($item["data_input"]) ."\n";
            $prediction = $local_ensemble->predict($item["data_input"], true, \BigML\MultiVote::PLURALITY_CODE, true, false, false, false, false, false, false, false, null, \BigML\Tree::LAST_PREDICTION, true);

            print "Then the local prediction is " . $item["prediction"] . "\n";
            $this->assertEquals($item["prediction"], round($prediction[0], 4));

        }
   }

    // Successfully creating a prediction from a Boosted Ensemble

    public function test_scenario6() {

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
            $predict_probability = $local_ensemble->predict_probability($item["data_input"], true, \BigML\MultiVote::PROBABILITY_CODE, \BigML\Tree::LAST_PREDICTION, true);
            foreach (range(0, count($predict_probability) - 1) as $index) {
                $predict_probability[$index] = round($predict_probability[$index], 4);
            }
            $this->assertEquals($item["probabilities"], $predict_probability);
        }
    }

}
