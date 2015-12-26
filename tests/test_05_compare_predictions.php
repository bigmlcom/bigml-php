<?php
include '../bigml/bigml.php';
include '../bigml/ensemble.php';
include '../bigml/cluster.php';
include '../bigml/fields.php';
#include '../bigml/multimodel.php';

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
      Successfully comparing predictions
     */
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

            print "I create a data source uploading a ". $item["filename"]. " file\n";
            $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source'));
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

            print "And I create a local model";
            $localmodel = new Model($model->resource, self::$api);

            print "When I create a prediction for ";
	    $prediction = self::$api->create_prediction($model, $item["data_input"]);
	    $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);

            print "Then the prediction for " . $item["objective"] . " is " . $item["prediction"]; 
            $this->assertEquals($item["prediction"], $prediction->object->prediction->{$item["objective"]});

            $prediction = $localmodel->predict($item["data_input"]);
            $this->assertEquals($prediction->output, $item["prediction"]);

        }
    }
    /*
     Successfully comparing predictions
    */

    public function test_scenario2() {
       $data = array(array("filename" => "data/spam.csv",
                           "options" => array("fields" => array("000001" => array("optype" => "text", 
										  "term_analysis" => array("case_sensitive" => true, 
													   "stem_words" => true, 
													   "use_stopwords"=> false,
													   "language" => "en")))),
			   "data_input" => array("Message" => "Mobile call"),
			   "objective" => "000000",
			   "prediction" => "ham"),
                     array("filename" => "data/spam.csv",
                           "options" => array("fields" => array("000001" => array("optype" => "text", 
			   							  "term_analysis" => array("case_sensitive" => true, 
										  	                   "stem_words" => true, 
													   "use_stopwords"=> false,
													   "language" => "en")))),
                           "data_input" => array("Message" => "A normal message"),
                           "objective" => "000000",
                           "prediction" => "ham"),
	             #array("filename" => "data/spam.csv",
                     #      "options" => array("fields" => array("000001" => array("optype" => "text", "term_analysis" => array("case_sensitive" => false, "stem_words" => false, "use_stopwords"=> false,"language" => "en")))),
                     #      "data_input" => array("Message" => "Mobile calls"),
		     #	   "objective" => "000000",
		     #	   "prediction" => "spam",
		     #     ),
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
                      #array("filename" => "data/spam.csv",
                      #     "options" => array("fields" => array("000001" => array("optype" => "text", "term_analysis" => array("token_mode" => "full_terms_only", "language" => "en")))),
                      #     "data_input" => array("Message" => "FREE for 1st week! No1 Nokia tone 4 ur mob every week just txt NOKIA to 87077 Get txting and tell ur mates. zed POBox 36504 W45WQ norm150p/tone 16+"),                           
                      #     "objective" => "000000",
                      #     "prediction" => "spam", 
                      #    ),
                      array("filename" => "data/spam.csv",
                           "options" => array("fields" => array("000001" => array("optype" => "text", "term_analysis" => array("token_mode" => "full_terms_only", "language" => "en")))),
                           "data_input" => array("Message" => "Ok"),  
                           "objective" => "000000",
                           "prediction" => "ham",
                          )
		     );

       foreach($data as $item) {
	    print "I create a data source uploading a ". $item["filename"]. " file\n";
            $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source'));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
            $this->assertEquals(1, $source->object->status->code);

            print "check local source is ready\n";
            $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "I update the source with params ";
            $source = self::$api->update_source($source->resource, $item["options"]);

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

            print "And I create a local model";
            $localmodel = new Model($model->resource, self::$api);#$model->resource, self::$api);

            print "When I create a prediction for ";
            $prediction = self::$api->create_prediction($model, $item["data_input"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);

            print "Then the prediction for " . $item["objective"] . " is " . $item["prediction"] . "\n";
            $this->assertEquals($item["prediction"], $prediction->object->prediction->{$item["objective"]});

            print "And I create a local prediction for \n"; 
            $prediction = $localmodel->predict($item["data_input"]);
            print "Then the local prediction is \n";
            $this->assertEquals($prediction->output, $item["prediction"]);
            
       }
    }
    /*
     Successfully comparing predictions with proportional missing strategy
    */
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
                               "prediction" => "46.69889",
                               "confidence" => 37.2760),
                      array("filename" => "data/grades.csv",
                               "data_input" => array('Midterm' => 20, "Tutorial" => 90, "TakeHome" => 100),
                               "objective" => "000005",
                               "prediction" => "28.06",
                               "confidence" => 24.86634));
      
        foreach($data as $item) {
            print "I create a data source uploading a ". $item["filename"]. " file\n";
            $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source'));
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

            print "And I create a local model";
            $localmodel = new Model($model->resource, self::$api);

            print "When I create a proportional missing strategy prediction for ";
            $prediction = self::$api->create_prediction($model->resource, $item["data_input"], array('missing_strategy' => 1));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);

            print "Then the prediction for " . $item["objective"] . " is " . $item["prediction"];
            $this->assertEquals($item["prediction"], $prediction->object->prediction->{$item["objective"]});

            print "And I create a local prediction for \n";
            $prediction = $localmodel->predict($item["data_input"], true, false, STDOUT, true, 1);

            print "Then the local prediction is \n";
            $this->assertEquals(is_numeric($prediction->prediction) ? round($prediction->prediction, 5) : $prediction->prediction, $item["prediction"]);
	    print "And the local prediction's confidence is \n";
	    $this->assertEquals(round($prediction->confidence, 4), round($item["confidence"], 4));
        }
    }
    /*
      Successfully comparing centroids with or without text option
    */
  /*  
    public function test_scenario4() {
     $data = array(/*array("filename" => "data/spam.csv",
                               "options" => array("fields" => array("000001" => array("optype" => "text",  
                                  						      "term_analysis" => array("case_sensitive" => true, 
													       "stem_words"=> true, 	
													       "use_stopwords"=>false, 
													       "language"=> "en")))), 
                               "data_input" => array("Type" => "ham", "Message" => "Mobile call"),
                               "centroid" => "Cluster 3",
                               "distance" => 0.311018),*//*
                   array("filename" => "data/spam.csv",
                               "options" => array("fields" => array("000001" => array("optype" => "text",
                                                                                      "term_analysis" => array("case_sensitive" => true,
                                                                                                               "stem_words"=> true,
                                                                                                               "use_stopwords"=>false
                                                                                                               )))),
                               "data_input" => array("Type" => "ham", "Message" => "A normal message"),
                               "centroid" => "Cluster 5",
                               "distance" => 0.375)); 

      foreach($data as $item) {
            print "I create a data source uploading a ". $item["filename"]. " file\n";
            $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source'));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
            $this->assertEquals(1, $source->object->status->code);

            print "check local source is ready\n";
            $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "I update the source with params ";
            $source = self::$api->update_source($source->resource, $item["options"]);

            print "create dataset with local source\n";
            $dataset = self::$api->create_dataset($source->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
            $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

            print "check the dataset is ready\n";
            $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
             
            $cluster = self::$api->create_cluster($dataset->resource, array('seed'=>'BigML tests','cluster_seed'=> 'BigML', 'k' => 8));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $cluster->code);
            $this->assertEquals(BigMLRequest::QUEUED, $cluster->object->status->code);

            print "I wait until the cluster is ready\n";
            $resource = self::$api->_check_resource($cluster->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
           
            print "I create a local cluster\n";
            $local_cluster = new Cluster($cluster->resource, self::$api);

            print "create a centroid\n";
            $centroid = self::$api->create_centroid($cluster->resource, $item["data_input"]);

            print_r($centroid); 
            /*print  "Then the centroid is <centroid> with distance <distance>";
            $this->assertEquals($item["distance"], round($centroid->object->distance, 6));

            $local_centroid = $local_cluster->centroid($item["data_input"]);
    
            print "the local centroid is " . $item["centroid"] . " with distance " . $item["distance"] . "\n";
            $this->assertEquals($item["centroid"], $local_centroid["centroid_name"]);
            $this->assertEquals($item["distance"], round($local_centroid["distance"], 6));*/
/*
      }

    }*/
    // TODO
    public function test_scenario5() {
    }
    public function test_scenario6() {
    }
    public function test_scenario7() {
    }
    public function test_scenario8() {
    }
    public function test_scenario9() {
    }
    public function test_scenario10() {
    }
}    
