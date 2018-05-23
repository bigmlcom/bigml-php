<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
  include '../bigml/bigml.php';
}

if (!class_exists('BigML\Cluster')) {
   include '../bigml/cluster.php';
}

if (!class_exists('BigML\Anomaly')) {
   include '../bigml/anomaly.php';
}

if (!class_exists('BigML\Model')) {
   include '../bigml/model.php';
}

if (!class_exists('BigML\LogisticRegression')) {
  include  '../bigml/logistic.php';
}

use BigML\BigML;
use BigML\BigMLRequest;
use BigML\Cluster;
use BigML\Anomaly;
use BigML\Model;
use BigML\LogisticRegression;

class BigMLTestComparePredictions extends PHPUnit_Framework_TestCase
{
    protected static $username; # "you_username"
    protected static $api_key; # "your_api_key"
    protected static $api;
    protected static $project;

    public static function setUpBeforeClass() {
       print __FILE__;
       self::$api =  new BigML(self::$username, self::$api_key, false);
       ini_set('memory_limit', '512M');
       ini_set('xdebug.max_nesting_level', '500');
       $test_name=basename(preg_replace('/\.php$/', '', __FILE__));
       self::$api->delete_all_project_by_name($test_name);
       self::$project=self::$api->create_project(array('name'=> $test_name));
    }

    public static function tearDownAfterClass() {
       self::$api->delete_all_project_by_name(basename(preg_replace('/\.php$/', '', __FILE__)));
    }

    #
    # Successfully comparing predictions
    #


    public function test_scenario12() {
       # check the local logistic regression object for bugs
       $data = array(array('filename' => 'data/iris.csv',
                           'options' => array("fields" => array("000000" => array("optype" => "categorical"))),
                           'data_input' => array("species" => "Iris-setosa"),
                           'probability' => 0.0394,
                           'prediction' => "5.0",
                           'objective' => '000000',
                           'params' => array("field_codings" => array(array("field" => "species", "coding" => "dummy", "dummy_class" => "Iris-setosa"))),
                          ),
                       array('filename' => 'data/iris.csv',
                           'options' => array("fields" => array("000000" => array("optype" => "categorical"))),
                           'data_input' => array("species" => "Iris-setosa"),
                           'probability' => 0.051,
                           'prediction' => "5.0",
                           'objective' => '000000',
                           'params' => array("balance_fields" => false, "field_codings" => array(array("field" => "species", "coding" => "contrast", "coefficients" => array(array(1,2,-1,-2)))))
                          ),
                       array('filename' => 'data/iris.csv',
                           'options' => array("fields" => array("000000" => array("optype" => "categorical"))),
                           'data_input' => array("species" => "Iris-setosa"),
                           'probability' => 0.051,
                           'prediction' => "5.0",
                           'objective' => '000000',
                           'params' => array("balance_fields" => false, "field_codings" => array(array("field" => "species", "coding" => "other", "coefficients" => array(array(1,2,-1,-2))))),
                          )
                    );


       foreach($data as $item) {

           print "Scenario: Successfully comparing predictions with text options\n";
           print "Given I create a data source uploading a ". $item['filename'] . " file\n";
           $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
           $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
           $this->assertEquals(1, $source->object->status->code);
           print "And I wait until the source is ready\n";
           $resource = self::$api->_check_resource($source->resource, null, 30000, 30);
           $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
           print "And I update the source with params ". json_encode($item['options']) . "\n";
           $source = self::$api->update_source($source->resource, $item["options"]);

           print "And I create a dataset\n";
           $dataset = self::$api->create_dataset($source->resource);
           $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
           $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

           print "And I wait until the dataset is ready\n";
           $resource = self::$api->_check_resource($dataset->resource, null, 30000, 30);
           $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

           print "And I create a logistic regression model with objective " . $item["objective"] . " and params " . json_encode($item["params"]) . "\n";
           $logistic_regression = self::$api->create_logistic_regression($dataset->resource,
                                                                         array_merge($item["params"],
                                                                              array('objective_field' =>  $item["objective"])));
           print "And I wait until the logistic regression model\n";
           $resource = self::$api->_check_resource($logistic_regression->resource, null, 10000, 30);
           $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

           $logistic_regression = self::$api->get_logistic_regression($logistic_regression->resource);
           print "And I create a local logistic regression model\n";
           $localLogisticRegression = new LogisticRegression($logistic_regression);

           print "When I create a logistic regression prediction for ". json_encode($item['data_input']) ."\n";
           $prediction = self::$api->create_prediction($logistic_regression->resource, $item["data_input"]);
           $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);

           print "Then the logistic regression prediction is ". $item["prediction"] . "\n";
           $this->assertEquals($prediction->object->output, $item["prediction"]);

           print "And the logistic regression probability for the prediction is " . $item["probability"] . "\n";
           foreach ($prediction->object->probabilities as $key => $value) {
             if ($value[0] == $prediction->object->output) {
                $this->assertEquals(round($value[1],4), round($item["probability"], 4));
                break;
             }
           }

           print "And I create a local logistic regression prediction for " . json_encode($item["data_input"]) . "\n";
           $local_prediction = $localLogisticRegression->predict($item["data_input"]);
           print "Then the local logistic regression prediction is " . $item["prediction"] . "\n";
           $this->assertEquals($item["prediction"],  $local_prediction["prediction"]);
           print "And the local logistic regression probability for the prediction is " . $item["probability"] . "\n";
           $this->assertEquals(round($local_prediction["probability"], 4), round($item["probability"], 4));

       }
    }

    public function test_scenario13() {
       $data = array(array('filename' => 'data/iris_unbalanced.csv',
                           'data_input' => array(),
			   'objective' => '000004',
			   'prediction' => 'Iris-setosa',
			   'confidence' => 0.25284,
                          ),
                     array('filename' => 'data/iris_unbalanced.csv',
		           'data_input' => array('petal length' => 1, 'sepal length' => 1, 'petal width' => 1, 'sepal width' => 1),
			   'objective' => '000004',
			   'prediction' => 'Iris-setosa',
			   'confidence' => 0.7575
		          )
                    );
       foreach($data as $item) {

           print "Scenario: Successfully comparing predictions with proportional missing strategy and balanced models\n";
           print "Given I create a data source uploading a ". $item['filename'] . " file\n";
           $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
           $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
           $this->assertEquals(1, $source->object->status->code);

           print "And I wait until the source is ready\n";
           $resource = self::$api->_check_resource($source->resource, null, 30000, 30);
           $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

           print "And I create a dataset\n";
           $dataset = self::$api->create_dataset($source->resource);
           $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
           $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

           print "And I wait until the dataset is ready\n";
           $resource = self::$api->_check_resource($dataset->resource, null, 30000, 30);
           $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

           print "And I create a balanced model\n";
           $model= self::$api->create_model($dataset, array("missing_splits" => false, "balance_objective" => true));

           print "And I wait until the model is ready\n";
           $resource = self::$api->_check_resource($model->resource, null, 10000, 30);
           $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

           print "And I create a local model " . $model->resource . "\n";
           $localmodel = new Model($model->resource, self::$api);

           print "When I create a proportional missing strategy prediction for " . json_encode($item["data_input"]) . "\n";
           $prediction = self::$api->create_prediction($model->resource, $item["data_input"], array('missing_strategy' => 1));
           $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);

           print "Then the prediction for " . $item["objective"] . " is " . $item["prediction"];
           $this->assertEquals($item["prediction"], $prediction->object->prediction->{$item["objective"]});

           print "\nAnd the confidence for the prediction is <". $item["confidence"] .">\n";
           $this->assertEquals(round($item["confidence"], 4), round($prediction->object->confidence, 4));

           print "And I create a proportional missing strategy local prediction for ". json_encode($item["data_input"]) . "\n";
           $local_prediction = $localmodel->predict($item["data_input"], true, false, STDOUT, true, 1);

           $confidence_value = null;
           $prediction_value = null;

           if (is_object($local_prediction)) {
              $prediction_value  = $local_prediction->output;
              $confidence_value = $local_prediction->confidence;
           } else {
              $prediction_value = $local_prediction[0];
              $confidence_value = $local_prediction[1];
           }

           print "Then the local prediction is " . $item["prediction"] . "\n";
           $this->assertEquals($prediction_value, $item["prediction"]);

           print "And the local prediction's confidence is " . $item["confidence"] . "\n";
           $this->assertEquals(round($confidence_value, 4), round($item["confidence"], 4));

       }
    }

    public function test_scenario14() {
       # check the local logistic regression object for bugs
       $data = array(array('filename' => 'data/movies.csv',
                            'options' => array("fields"=> array("000000"=> array("name"=> "user_id", "optype"=> "numeric"),
                                                   "000001"=> array("name"=> "gender", "optype"=> "categorical"),
                                                   "000002"=> array("name"=> "age_range", "optype"=> "categorical"),
                                                   "000003"=> array("name"=> "occupation", "optype"=> "categorical"),
                                                   "000004" => array("name" => "zipcode", "optype"=> "numeric"),
                                                   "000005"=> array("name"=> "movie_id", "optype"=> "numeric"),
                                                   "000006"=> array("name"=> "title", "optype"=> "text"),
                                                   "000007"=> array("name"=> "genres", "optype"=> "items",
                                                                    "item_analysis" => array("separator"=> "\$")),
                                                   "000008"=> array("name"=> "timestamp", "optype"=> "numeric"),
                                                   "000009"=> array("name"=> "rating", "optype"=> "categorical")),
                                                  "source_parser"=> array("separator" => ";")),
                            'data_input' => array("timestamp" => "999999999"),
                            'prediction' => "4",
                            'probability' => 0.4028,
                            'objective' => "000009",
                            'parms' => array("balance_fields" => false)
                          ),
                      array('filename' => 'data/movies.csv',
                            'options' => array("fields"=> array("000000"=> array("name"=> "user_id", "optype"=> "numeric"),
                                                   "000001"=> array("name"=> "gender", "optype"=> "categorical"),
                                                   "000002"=> array("name"=> "age_range", "optype"=> "categorical"),
                                                   "000003"=> array("name"=> "occupation", "optype"=> "categorical"),
                                                   "000004" => array("name" => "zipcode", "optype"=> "numeric"),
                                                   "000005"=> array("name"=> "movie_id", "optype"=> "numeric"),
                                                   "000006"=> array("name"=> "title", "optype"=> "text"),
                                                   "000007"=> array("name"=> "genres", "optype"=> "items",
                                                                    "item_analysis" => array("separator"=> "\$")),
                                                   "000008"=> array("name"=> "timestamp", "optype"=> "numeric"),
                                                   "000009"=> array("name"=> "rating", "optype"=> "categorical")),
                                                  "source_parser"=> array("separator" => ";")),
                            'data_input' => array("timestamp" => "999999999"),
                            'prediction' => "4",
                            'probability' => 0.2622,
                            'objective' => "000009",
                            'parms' => array("normalize" => true)
                          ),
                       array('filename' => 'data/movies.csv',
                            'options' => array("fields"=> array("000000"=> array("name"=> "user_id", "optype"=> "numeric"),
                                                   "000001"=> array("name"=> "gender", "optype"=> "categorical"),
                                                   "000002"=> array("name"=> "age_range", "optype"=> "categorical"),
                                                   "000003"=> array("name"=> "occupation", "optype"=> "categorical"),
                                                   "000004" => array("name" => "zipcode", "optype"=> "numeric"),
                                                   "000005"=> array("name"=> "movie_id", "optype"=> "numeric"),
                                                   "000006"=> array("name"=> "title", "optype"=> "text"),
                                                   "000007"=> array("name"=> "genres", "optype"=> "items",
                                                                    "item_analysis" => array("separator"=> "\$")),
                                                   "000008"=> array("name"=> "timestamp", "optype"=> "numeric"),
                                                   "000009"=> array("name"=> "rating", "optype"=> "categorical")),
                                                  "source_parser"=> array("separator" => ";")),
                            'data_input' => array("timestamp" => "999999999"),
                            'prediction' => "4",
                            'probability' => 0.2622,
                            'objective' => "000009",
                            'parms' => array("balance_fields" => true, "normalize" => true)
                          )
                    );
       foreach($data as $item) {

           print "Scenario: Successfully comparing predictions for logistic regression with balance_fields\n";
           print "Given I create a data source uploading a ". $item['filename'] . " file\n";
           $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
           $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
           $this->assertEquals(1, $source->object->status->code);

           print "And I wait until the source is ready\n";
           $resource = self::$api->_check_resource($source->resource, null, 30000, 30);
           $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

           print "And I update the source with params " . json_encode($item["options"]) . "\n";
           $source = self::$api->update_source($source->resource, $item["options"]);

           print "And I create a dataset\n";
           $dataset = self::$api->create_dataset($source->resource);
           $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
           $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

           print "And I wait until the dataset is ready\n";
           $resource = self::$api->_check_resource($dataset->resource, null, 30000, 30);
           $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

           print "And I create a logistic regression model with objective " . $item["objective"] . " and params " . json_encode($item["parms"]) . "\n";
           $logistic_regression = self::$api->create_logistic_regression($dataset->resource,
                                                                         array_merge($item["parms"],
                                                                              array('objective_field' =>  $item["objective"])));
           print "And I wait until the logistic regression model\n";
           $resource = self::$api->_check_resource($logistic_regression->resource, null, 10000, 30);
           $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

           $logistic_regression = self::$api->get_logistic_regression($logistic_regression->resource);

           print "And I create a local logistic regression model\n";
           $localLogisticRegression = new LogisticRegression($logistic_regression);

           print "When I create a logistic regression prediction for " . json_encode($item["data_input"])  . "\n";
           $prediction = self::$api->create_prediction($logistic_regression->resource, $item["data_input"]);
           $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);

           print "Then the logistic regression prediction is " . $item["prediction"] . "\n";
           $this->assertEquals($prediction->object->output, $item["prediction"]);

           print "And the logistic regression probability for the prediction is ". $item["probability"] ."\n";

           foreach ($prediction->object->probabilities as $key => $value) {
              if ($value[0] == $prediction->object->output) {
                 $this->assertEquals(round($value[1],4), round($item["probability"], 4));
                 break;
              }
           }

           print "And I create a local logistic regression prediction for " . json_encode($item["data_input"]) . "\n";
           $local_prediction = $localLogisticRegression->predict($item["data_input"]);

           print "Then the local logistic regression prediction is " . $item["prediction"] . "\n";
           $this->assertEquals($item["prediction"],  $local_prediction["prediction"]);

           print "And the local logistic regression probability for the prediction is " . $item["probability"] . "\n";
           $this->assertEquals(round($local_prediction["probability"], 4), round($item["probability"], 4));
       }
    }
}
