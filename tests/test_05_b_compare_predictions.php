<?php
use PHPUnit\Framework\TestCase;


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

class BigMLTestComparePredictionsB extends TestCase
{
    protected static $username; # "you_username"
    protected static $api_key; # "your_api_key"
    protected static $api;
    protected static $project;

    public static function setUpBeforeClass(): void {
       print __FILE__;
       self::$api =  new BigML(self::$username, self::$api_key, false);
       ini_set('memory_limit', '512M');
       ini_set('xdebug.max_nesting_level', '500');
       $test_name=basename(preg_replace('/\.php$/', '', __FILE__));
       self::$api->delete_all_project_by_name($test_name);
       self::$project=self::$api->create_project(array('name'=> $test_name));
    }

    public static function tearDownAfterClass(): void {
       self::$api->delete_all_project_by_name(basename(preg_replace('/\.php$/', '', __FILE__)));
    }

    #
    # Successfully comparing predictions
    #

    public function test_scenario1() {

        $data = array(array("filename" => "data/iris.csv",
	 		    "data_input" => array("petal width"=> 0.5),
			    "objective"=> '000004',
			    "prediction" => "Iris-setosa"),
		      array("filename" => 'data/iris.csv',
		            "data_input" => array("petal length" => 6, "petal width"=> 2),
			    "objective" => '000004',
			    "prediction" => 'Iris-virginica'),
                      array("filename" => 'data/iris.csv',
                            "data_input" => array('petal length' => 4, '"petal width' => 1.5),
                            "objective" => '000004',
                            "prediction" => 'Iris-versicolor'),
                      array("filename" => 'data/iris_sp_chars.csv',
                            "data_input" => array('pétal.length' => 4, '"pétal&width' => 1.5),
                            "objective" => '000004',
                            "prediction" => 'Iris-versicolor'));

        foreach($data as $item) {
            print "\nSuccessfully comparing predictions:\n";

            print "Given I create a data source uploading a ". $item["filename"]. " file\n";
            $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
            $this->assertEquals(1, $source->object->status->code);

            print "And I wait until the source is ready\n";
            $resource = self::$api->_check_resource($source->resource, null, 10000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create dataset with local source\n";
            $dataset = self::$api->create_dataset($source->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
            $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

            print "And I wait until the dataset is ready\n";
            $resource = self::$api->_check_resource($dataset->resource, null, 10000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create model\n";
            $model = self::$api->create_model($dataset->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

            print "And I wait until the model is ready\n";
            $resource = self::$api->_check_resource($model->resource, null, 10000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create a local model " . $model->resource . "\n";
            $localmodel = new Model($model->resource, self::$api);

            print "When I create a prediction for " . json_encode($item["data_input"]) . "\n";
	    $prediction = self::$api->create_prediction($model, $item["data_input"]);
	    $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);

            print "Then the prediction for " . $item["objective"] . " is " . $item["prediction"];
            $this->assertEquals($item["prediction"], $prediction->object->prediction->{$item["objective"]});

            print "And I create a local prediction for " . json_encode($item["data_input"]) . "\n";
            $prediction = $localmodel->predict($item["data_input"]);

	    print "Then the local prediction is " . $item["prediction"] . "\n";
            $this->assertEquals($prediction->output, $item["prediction"]);

        }
    }
    #
    # Successfully comparing predictions
    #

    public function test_scenario2() {
       $data = array(array("filename" => "data/spam.csv",
                           "options" => array("fields" => array("000001" => array("optype" => "text",
										  "term_analysis" => array("case_sensitive" => true,
													   "stem_words" => true,
													   "use_stopwords"=> false,
													   "language" => "en")))),
			   "data_input" => array("Message" => "Mobile call"),
			   "objective" => "000000",
			   "prediction" => "spam") ,
                array("filename" => "data/spam.csv",
                           "options" => array("fields" => array("000001" => array("optype" => "text",
			   							  "term_analysis" => array("case_sensitive" => true,
										  	                   "stem_words" => true,
													   "use_stopwords"=> false,
													   "language" => "en")))),
                           "data_input" => array("Message" => "A normal message"),
                           "objective" => "000000",
                           "prediction" => "ham"),
	             array("filename" => "data/spam.csv",
                           "options" => array("fields" => array("000001" => array("optype" => "text", "term_analysis" => array("case_sensitive" => false, "stem_words" => false, "use_stopwords"=> false,"language" => "en")))),
                           "data_input" => array("Message" => "Mobile calls"),
		     	   "objective" => "000000",
		     	   "prediction" => "spam",
		          ),
		     array("filename" => "data/spam.csv",
                           "options" => array("fields" => array("000001" => array("optype" => "text", "term_analysis" => array("case_sensitive" => false, "stem_words" => false, "use_stopwords"=> false,"language" => "en")))),
                           "data_input" => array("Message" => "A normal message"),
                           "objective" => "000000",
                           "prediction" => "ham",
                          ),
                     array("filename" => "data/spam.csv",
                           "options" => array("fields" => array("000001" => array("optype" => "text", "term_analysis" => array("case_sensitive" => false, "stem_words" => true, "use_stopwords"=> true,"language" => "en")))),
                           "data_input" => array("Message" => "Mobile Call"),
                           "objective" => "000000",
                           "prediction" => "spam",
                          ),
                     array("filename" => "data/spam.csv",
                           "options" => array("fields" => array("000001" => array("optype" => "text", "term_analysis" => array("case_sensitive" => false, "stem_words" => true, "use_stopwords"=> true,"language" => "en")))),
                           "data_input" => array("Message" => "A normal message"),
                           "objective" => "000000",
                           "prediction" => "ham",
                          ),
                     array("filename" => "data/spam.csv",
                           "options" => array("fields" => array("000001" => array("optype" => "text", "term_analysis" => array("token_mode" => "full_terms_only", "language" => "en")))),
                           "data_input" => array("Message" => "FREE for 1st week! No1 Nokia tone 4 ur mob every week just txt NOKIA to 87077 Get txting and tell ur mates. zed POBox 36504 W45WQ norm150p/tone 16+"),
                           "objective" => "000000",
                           "prediction" => "spam",
                           ),
                      array("filename" => "data/spam.csv",
                           "options" => array("fields" => array("000001" => array("optype" => "text", "term_analysis" => array("token_mode" => "full_terms_only", "language" => "en")))),
                           "data_input" => array("Message" => "Ok"),
                           "objective" => "000000",
                           "prediction" => "ham",
                          ),
		      array("filename" => "data/movies.csv",
		            "options" => array("fields" => array("000007" => array("optype"=> "items", "item_analysis" => array("separator" => "\$")))),
			    "data_input" => array("genres" => "Adventure\$Action", "timestamp" => 993906291, "occupation" => "K-12 student"),
			    "objective" => "000009",
			    "prediction" => 3.92135
		           ),
		      array("filename"=> "data/text_missing.csv",
		            "options" => array("fields" => array("000001" => array("optype" => "text", "term_analysis" => array("token_mode" => "all", "language" => "en")), "000000" => array("optype" => "text", "term_analysis" => array("token_mode" => "all", "language" => "en" )))),
			    "data_input" => array(),
			    "objective" => "000003",
			    "prediction" => "swap"
		           )
		     );
       $waitTime = 2000;
       foreach($data as $item) {
	    print "Given I create a data source uploading a ". $item["filename"]. " file\n";
            $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
            $this->assertEquals(1, $source->object->status->code);

            print "And I wait until the source is ready\n";
            $resource = self::$api->_check_resource($source->resource, null, $waitTime, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I update the source with params " . json_encode($item["options"]) . "\n";
            $source = self::$api->update_source($source->resource, $item["options"]);

            print "And I create dataset with local source\n";
            $dataset = self::$api->create_dataset($source->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
            $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

            print "And I wait until the dataset is ready\n";
            $resource = self::$api->_check_resource($dataset->resource, null, $waitTime, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create model\n";
            $model = self::$api->create_model($dataset->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

            print "And I wait until the model is ready\n";
            $resource = self::$api->_check_resource($model->resource, null, $waitTime, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create a local model";
            $localmodel = new Model($model->resource, self::$api);

            print "When I create a prediction for " . json_encode($item["data_input"]) . "\n";
            $prediction = self::$api->create_prediction($model, $item["data_input"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);

            print "Then the prediction for " . $item["objective"] . " is " . $item["prediction"] . "\n";
            $this->assertEquals($item["prediction"], $prediction->object->prediction->{$item["objective"]});

            print "And I create a local prediction for " . json_encode($item["data_input"]) ."\n";
            $prediction = $localmodel->predict($item["data_input"]);

            print "Then the local prediction is " .  $item["prediction"] ." \n";
            $this->assertEquals($prediction->output, $item["prediction"]);
       }
    }

    #
    # Successfully comparing predictions with proportional missing strategy
    #

    public function test_scenario3() {
        $data = array(array("filename" => "data/iris.csv",
                               "data_input" => array(),
			       "objective" => "000004",
			       "prediction" => "Iris-setosa",
			       "confidence" => 0.2629),
	              array("filename" => "data/grades.csv",
                               "data_input" => array(),
                               "objective" => "000005",
                               "prediction" => "68.62224",
                               "confidence" => 27.5358),
                      array("filename" => "data/grades.csv",
                               "data_input" => array('Midterm' => 20),
                               "objective" => "000005",
                               "prediction" => "40.46667",
                               "confidence" => 54.89713),
                      array("filename" => "data/grades.csv",
                               "data_input" => array('Midterm' => 20, "Tutorial" => 90, "TakeHome" => 100),
                               "objective" => "000005",
                               "prediction" => "28.06",
                               "confidence" => 25.65806));

        foreach($data as $item) {
	    print "\nSuccessfully comparing predictions with proportional missing strategy\n";
            print "Given I create a data source uploading a ". $item["filename"]. " file\n";
            $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
            $this->assertEquals(1, $source->object->status->code);

            print "And I wait until the source is ready\n";
            $resource = self::$api->_check_resource($source->resource, null, 10000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create dataset with local source\n";
            $dataset = self::$api->create_dataset($source->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
            $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

            print "And I wait until the dataset is ready\n";
            $resource = self::$api->_check_resource($dataset->resource, null, 10000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create model\n";
            $model = self::$api->create_model($dataset->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

            print "And I wait until the model is ready\n";
            $resource = self::$api->_check_resource($model->resource, null, 10000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create a local model\n";
            $localmodel = new Model($model->resource, self::$api);

            print "When I create a proportional missing strategy prediction for " . json_encode($item["data_input"]) . "\n";
            $prediction = self::$api->create_prediction($model->resource, $item["data_input"], array('missing_strategy' => 1));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);

            print "Then the prediction for " . $item["objective"] . " is " . $item["prediction"];
            $this->assertEquals($item["prediction"], $prediction->object->prediction->{$item["objective"]});

            print "And I create a local prediction for " .  json_encode($item["data_input"]) . "\n";
            $prediction = $localmodel->predict($item["data_input"], true, false, STDOUT, true, 1);

            print "Then the local prediction is  " . $item["prediction"] . "\n";
	    $prediction_value = null;
	    $confidence_value = null;
	    if (is_object($prediction)) {
	       $prediction_value = $prediction->prediction;
	       $confidence_value = $prediction->confidence;
	    } else {
               $prediction_value = $prediction[0];
               $confidence_value = $prediction[1];
	    }

            $this->assertEquals(is_numeric($prediction_value) ? round($prediction_value, 5) : $prediction_value, $item["prediction"]);
	    print "And the local prediction's confidence is " . $item["confidence"]. "\n";
	    $this->assertEquals(round($confidence_value, 3), round($item["confidence"], 3));
        }
    }

}
