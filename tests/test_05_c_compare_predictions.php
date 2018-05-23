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

    public function test_scenario7() {
      $data = array(array('filename' => 'data/tiny_kdd.csv', 'data_input' => array("000020" => 255.0, "000004" => 183.0, "000016" => 4.0, "000024" => 0.04, "000025" => 0.01, "000026" => 0.0, "000019" => 0.25, "000017" => 4.0, "000018" => 0.25, "00001e" => 0.0, "000005" => 8654.0, "000009" => 0, "000023" => 0.01, "00001f" => 123.0) , 'score' => 0.69802));

      foreach($data as $item) {
          print "\nSuccessfully comparing scores from anomaly detectors\n";
          print "Given I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "And I wait until the source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And create dataset with local source\n";
          $dataset = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

          print "And I wait until the dataset is ready " . $dataset->resource . " \n";
          $resource = self::$api->_check_resource($dataset->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "Then I create an anomaly detector from a dataset\n";
          $anomaly = self::$api->create_anomaly($dataset->resource);

          print "And I wait until the anomaly detector is ready\n";
          $resource = self::$api->_check_resource($anomaly->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
          print "And I create a local anomaly detector\n";
          $local_anomaly = new Anomaly($anomaly->resource, self::$api);

          print "When I create an anomaly score for " . json_encode($item["data_input"]) . "\n";
          $anomaly_score = self::$api->create_anomaly_score($anomaly->resource, $item["data_input"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $anomaly_score->code);

	  print "Then the anomaly score is " . $item['score'] . "\n";
          $this->assertEquals(round($item['score'], 5), round($anomaly_score->object->score,5));

          print "And I create a local anomaly score for " . json_encode($item["data_input"]) . "\n";
          $local_anomaly_score = $local_anomaly->anomaly_score($item["data_input"], false);
	  print "Then the local anomaly score is " . $item['score'] . "\n";
          $this->assertEquals(round($item['score'], 5), round($local_anomaly_score,5));

      }
    }

    public function test_scenario8() {
       $data = array(array('filename' => 'data/iris.csv',
                           'data_input' => array("petal width" => 0.5, "petal length"=> 0.5, "sepal width" => 0.5, "sepal length"=>0.5),
			   'prediction' => 'Iris-versicolor'),
		     array('filename' => 'data/iris.csv',
                           'data_input' => array("petal width" => 2, "petal length" => 6, "sepal width" => 0.5, "sepal length" => 0.5),
                           'prediction' => 'Iris-versicolor'),
                     array('filename' => 'data/iris.csv',
                           'data_input' => array("petal width" => 1.5, "petal length" => 4, "sepal width" => 0.5, "sepal length" => 0.5),
                           'prediction' => 'Iris-versicolor'),
                     array('filename' => 'data/iris.csv',
                           'data_input' => array("petal length" => 1),
                           'prediction' => 'Iris-setosa'),
                     array('filename' => 'data/iris_sp_chars.csv',
                           'data_input' => array("pétal.length" => 4, "pétal&width".json_decode('"'.'\u0000'.'"') => 1.5, "sépal&width" => 0.5, "sépal.length" => 0.5),
                           'prediction' => 'Iris-versicolor'),
		     array('filename' => 'data/price.csv',
		           'data_input' => array("Price" => 1200),
			   'prediction' => 'Product1')
   	             );
       foreach($data as $item) {
          print "\nSuccessfully comparing logistic regression predictions\n";
          print "Given I create a data source uploading a ". $item["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "And I wait until the source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create dataset with local source\n";
          $dataset = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

          print "And I wait until the dataset is ready " . $dataset->resource . " \n";
          $resource = self::$api->_check_resource($dataset->resource, null, 20000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "And I create a logistic regresssion model\n";
          $logistic_regression = self::$api->create_logistic_regression($dataset->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $logistic_regression->code);

          print "And I wait until the logistic regression model is ready " . $logistic_regression->resource . "\n";
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

          print "And I create a local logistic regression prediction for " . json_encode($item["data_input"]) . "\n";
          $local_prediction = $localLogisticRegression->predict($item["data_input"]);

          print "Then the local logistic regression prediction is " . $item["prediction"] . "\n";
          $this->assertEquals($item["prediction"],  $local_prediction["prediction"]);

       }
    }


    public function test_scenario9() {
        $data = array(array("filename" => "data/spam.csv",
	                    "options"=> array("fields" => array("000001" => array("optype"=> "text",
			                                                          "term_analysis"=> array("case_sensitive"=> true, "stem_words" => true,
										                          "use_stopwords" => false, "language" => "en")))),
			    "data_input" => array("Message" => "Mobile call"),
			    "prediction" => 'ham'),
                      array("filename" => "data/spam.csv",
                            "options"=> array("fields" => array("000001" => array("optype"=> "text",
                                                                                  "term_analysis"=> array("case_sensitive"=> true, "stem_words" => true,
                                                                                                          "use_stopwords" => false, "language" => "en")))),
                            "data_input" => array("Message" => "A normal message"),
                            "prediction" => 'ham'),
                      array("filename" => "data/spam.csv",
                            "options"=> array("fields" => array("000001" => array("optype"=> "text",
                                                                                  "term_analysis"=> array("case_sensitive"=> false, "stem_words" => false,
                                                                                                          "use_stopwords" => false, "language" => "en")))),
                            "data_input" => array("Message" => "Mobile calls"),
                            "prediction" => 'ham'),
                      array("filename" => "data/spam.csv",
                            "options"=> array("fields" => array("000001" => array("optype"=> "text",
                                                                                  "term_analysis"=> array("case_sensitive"=> false, "stem_words" => false,
                                                                                                          "use_stopwords" => false, "language" => "en")))),
                            "data_input" => array("Message" => "A normal message"),
                            "prediction" => 'ham'),
                      array("filename" => "data/spam.csv",
                            "options"=> array("fields" => array("000001" => array("optype"=> "text",
                                                                                  "term_analysis"=> array("case_sensitive"=> false, "stem_words" => true,
                                                                                                          "use_stopwords" => true, "language" => "en")))),
                            "data_input" => array("Message" => "Mobile call"),
                            "prediction" => 'ham'),
                      array("filename" => "data/spam.csv",
                            "options"=> array("fields" => array("000001" => array("optype"=> "text",
                                                                                  "term_analysis"=> array("case_sensitive"=> false, "stem_words" => true,
                                                                                                          "use_stopwords" => true, "language" => "en")))),
                            "data_input" => array("Message" => "A normal message"),
                            "prediction" => 'ham'),
                      array("filename" => "data/spam.csv",
                            "options"=> array("fields" => array("000001" => array("optype"=> "text",
                                                                                  "term_analysis"=> array("token_mode"=> "full_terms_only",
                                                                                                          "language" => "en")))),
                            "data_input" => array("Message" => "FREE for 1st week! No1 Nokia tone 4 ur mob every week just txt NOKIA to 87077 Get txting and tell ur mates. zed POBox 36504 W45WQ norm150p/tone 16+"),
                            "prediction" => 'ham'),
                      array("filename" => "data/spam.csv",
                            "options"=> array("fields" => array("000001" => array("optype"=> "text",
                                                                                  "term_analysis"=> array("token_mode"=> "full_terms_only",
                                                                                                          "language" => "en")))),
                            "data_input" => array("Message" => "Ok"),
                            "prediction" => 'ham')
                     );

        foreach($data as $item) {
           print " Successfully comparing predictions with text options\n";
           print "Given I create a data source uploading a ". $item["filename"]. " file\n";
           $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
           $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
           $this->assertEquals(1, $source->object->status->code);

           print "And I wait until the source is ready\n";
           $resource = self::$api->_check_resource($source->resource, null, 30000, 30);
           $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

           print "And I update the source with params " . json_encode($item["options"]) . "\n";
	   $source = self::$api->update_source($source->resource, $item["options"]);

           print "And I create dataset with local source\n";
           $dataset = self::$api->create_dataset($source->resource);
           $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
           $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

           print "And I wait until the dataset is ready " . $dataset->resource . " \n";
           $resource = self::$api->_check_resource($dataset->resource, null, 30000, 30);
           $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

           print "And I create a logistic regresssion model\n";
           $logistic_regression = self::$api->create_logistic_regression($dataset->resource);
           $this->assertEquals(BigMLRequest::HTTP_CREATED, $logistic_regression->code);

           print "And I wait until the logistic regression model is ready " . $logistic_regression->resource . "\n";
           $resource = self::$api->_check_resource($logistic_regression->resource, null, 10000, 30);
           $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

           $logistic_regression = self::$api->get_logistic_regression($logistic_regression->resource);

           print "And I create a local logistic regression model . " . $logistic_regression->resource . " .\n";
           $localLogisticRegression = new LogisticRegression($logistic_regression);

           print "When I create a logistic regression prediction for " . json_encode($item["data_input"])  . "\n";

           $prediction = self::$api->create_prediction($logistic_regression->resource, $item["data_input"]);
           $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);

           print "Then the logistic regression prediction is " . $item["prediction"] . "\n";
           $this->assertEquals($prediction->object->output, $item["prediction"]);

           print "And I create a local logistic regression prediction for " . json_encode($item["data_input"]) . "\n";
           $local_prediction = $localLogisticRegression->predict($item["data_input"]);

           print "Then the local logistic regression prediction is " . $item["prediction"] . "\n";
           $this->assertEquals($item["prediction"],  $local_prediction["prediction"]);

        }
    }

    public function test_scenario10() {
      $data = array(array('filename' => 'data/spam.csv',
                          'objective' => '000000',
                          'options' => array("fields" => array("000001" => array("optype"=> "text",
                                                                                  "term_analysis"=> array("token_mode"=> "full_terms_only",
                                                                                                          "language" => "en")))),
                          'data_input' => array("Message"=> "A normal message"),
                          'prediction' => 'ham',
                          'probability' => 0.9169
                         ),
                     array('filename' => 'data/movies.csv',
                           'objective' => '000002',
                           'options' => array("fields" => array("000007" => array("optype" => "items",
                                                                                   "item_analysis" => array("separator" => "\$")))),
                           'data_input' => array("gender" => "Female",
                                                  "genres" =>"Adventure\$Action",
                                                  "timestamp" => 993906291,
                                                  "occupation" => "K-12 student",
                                                  "zipcode" => 59583,
                                                  "rating" => 3),
                           'prediction' => 'Under 18',
                           'probability' => 0.8393)
                   );

      foreach($data as $item) {
         print "Successfully comparing predictions with text options\n";
         print "Given I create a data source uploading a ". $item["filename"]. " file\n";
         $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
         $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
         $this->assertEquals(1, $source->object->status->code);

         print "And I wait until the source is ready\n";
         $resource = self::$api->_check_resource($source->resource, null, 30000, 30);
         $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

         print "And I update the source with params " . json_encode($item["options"]) . "\n";
         $source = self::$api->update_source($source->resource, $item["options"]);

         print "And I create dataset with local source\n";
         $dataset = self::$api->create_dataset($source->resource);
         $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
         $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

         print "And I wait until the dataset is ready " . $dataset->resource . " \n";
         $resource = self::$api->_check_resource($dataset->resource, null, 30000, 30);
         $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

         print "And I create a logistic regresssion model with objective field ". $item["objective"] . "\n";
         $logistic_regression = self::$api->create_logistic_regression($dataset->resource,
                                                                        array('objective_field' => $item["objective"]));
         $this->assertEquals(BigMLRequest::HTTP_CREATED, $logistic_regression->code);

         print "And I wait until the logistic regression model is ready " . $logistic_regression->resource . "\n";
         $resource = self::$api->_check_resource($logistic_regression->resource, null, 10000, 30);
         $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

         $logistic_regression = self::$api->get_logistic_regression($logistic_regression->resource);

         print "And I create a local logistic regression model . " . $logistic_regression->resource . " .\n";
         $localLogisticRegression = new LogisticRegression($logistic_regression);

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

      }
    }

    public function test_scenario11() {
       $data = array(array('filename' => 'data/text_missing.csv',
                           'options' => array("fields" => array("000001" => array("optype" => "text", "term_analysis" => array("token_mode" => "all", "language"=> "en")), "000000"=> array("optype" => "text", "term_analysis" => array("token_mode" => "all", "language" => "en")))),
                           'data_input' => array(),
                           'objective' => "000003",
                           'prediction' => "swap"
                          ),
                     array('filename' => 'data/text_missing.csv',
                           'options' => array("fields" => array("000001" => array("optype" => "text", "term_analysis" => array("token_mode" => "all", "language"=> "en")), "000000"=> array("optype" => "text", "term_analysis" => array("token_mode" => "all", "language" => "en")))),
                           'data_input' => array("category1"=> "a"),
                           'objective' => "000003",
                           'prediction' => "paperwork"
                          )
                    );

       foreach($data as $item) {

         print "Scenario: Successfully comparing predictions with text options and proportional missing strategy:\n";
         print "Given I create a data source uploading a " . $item["filename"] . " file\n";

         $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
         $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
         $this->assertEquals(1, $source->object->status->code);

         print "And I wait until the source is ready \n";
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

         print "And I create a model\n";
         $model = self::$api->create_model($dataset->resource);
         $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

         print "And I wait until the model is ready\n";
         $resource = self::$api->_check_resource($model->resource, null, 10000, 30);
         $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

         print "When I create a prediction for " . json_encode($item["data_input"]) . "\n";
         $prediction = self::$api->create_prediction($model, $item["data_input"], array('missing_strategy' => 1));
         $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);

         print "Then the prediction for " . $item["objective"] . " is " . $item["prediction"] . "\n";
         $this->assertEquals($item["prediction"], $prediction->object->prediction->{$item["objective"]});

         print "When I create a proportional missing strategy prediction for " . json_encode($item["data_input"])  . "\n";
         $prediction = self::$api->create_prediction($model->resource, $item["data_input"], array('missing_strategy' => 1));
         $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);

         print "Then the prediction for " . $item["objective"] . " is " . $item["prediction"] . "\n";
         $this->assertEquals($item["prediction"], $prediction->object->prediction->{$item["objective"]});

         print "And I create a local model\n";
	 $localmodel = new Model($model->resource, self::$api);

         print "And I create a proportional missing strategy local prediction for ". json_encode($item["data_input"]) . "\n";
         $local_prediction = $localmodel->predict($item["data_input"], true, false, STDOUT, true, 1);

	 if (is_object($local_prediction)) {
           $prediction_value  = $local_prediction->output;
	 } else {
	   $prediction_value = $local_prediction[0];
	 }

         print "Then the local prediction is " . $item["prediction"] . "\n";
	 $this->assertEquals($prediction_value, $item["prediction"]);

       }

    }

}
