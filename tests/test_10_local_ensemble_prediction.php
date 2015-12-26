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
     Successfully creating a prediction from a local model
     */

    public function test_scenario1() {

        $data = array(array("filename" => 'data/iris.csv',
                            "number_of_models" => 5,
                            "tlp" => 1,
                            "data_input" => array("petal width" => 0.5),
                            "prediction" => "Iris-versicolor",
			    "confidence" => 0.3687)
                     );
	
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

            print "create a ensemble from "; 
            $ensemble = self::$api->create_ensemble($dataset->resource, array("number_of_models"=> $item["number_of_models"], "tlp"=> $item["tlp"],"seed" => 'BigML', 'sample_rate'=> 0.70));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $ensemble->code);

            print "check the ensemble is ready\n";
            $resource = self::$api->_check_resource($ensemble->resource, null, 3000, 50);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "create a local ensemble:\n";
            $ensemble = self::$api->get_ensemble($ensemble->resource);
            $local_ensemble = new Ensemble($ensemble, self::$api);

            print "create prediction for local ensemble with confidence true  \n";
            $prediction = $local_ensemble->predict($item["data_input"], true, MultiVote::PLURALITY_CODE, true);

            print "the prediction for local ensemble is equals " . $item["prediction"] . "\n";
            $this->assertEquals($item["prediction"], $prediction[0]);

            print "And the local prediction's confidence is " . $item["confidence"] . "\n";
            $this->assertEquals($item["confidence"], round($prediction[1], 4));

        }
    }

    public function test_scenario2() {

        $data = array(array("filename" => 'data/iris.csv',
                            "params1" => array("input_fields" => array("000000", "000001","000003", "000004")), 
                            "params2" => array("input_fields" => array("000000", "000001","000002", "000004")),
                            "params3" => array("input_fields" => array("000000", "000001","000002", "000003", "000004")),
                            "number_of_models" => 3,
                            "field_importance" => array("000002"=>0.5269933333333333, "000003" => 0.38936, "000000" => 0.04662333333333333, "000001" => 0.037026666666666666)));

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

            print "create model with params1\n";
            $model_1 = self::$api->create_model($dataset->resource, $item["params1"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_1->code);
    
            print "check model is ready\n";
            $resource = self::$api->_check_resource($model_1->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "create model with params2\n";
            $model_2 = self::$api->create_model($dataset->resource, $item["params2"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_2->code);
        
            print "check model is ready\n";
            $resource = self::$api->_check_resource($model_2->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "create model with params3\n";
            $model_3 = self::$api->create_model($dataset->resource, $item["params3"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_3->code);
 
            print "check model is ready\n";
            $resource = self::$api->_check_resource($model_3->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "create a local ensemble:\n";

            $local_ensemble = new Ensemble(array($model_1->resource, $model_2->resource, $model_3->resource), self::$api, $item["number_of_models"]);

            $field_importance_data = $local_ensemble->field_importance_data();
  
            $this->assertEquals($item["field_importance"], $field_importance_data[0]);
        }
 
    }

    public function test_scenario3() {

        $data = array(array("filename" => 'data/iris.csv',
                            "number_of_models" => 5,
                            "tlp" => 1,
                            "data_input" => array("petal width" => 0.5),
                            "prediction" => "Iris-versicolor",
                            "confidence" => 0.3687)
                     );
        
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

            print "create a ensemble from "; 
            $ensemble = self::$api->create_ensemble($dataset->resource, array("number_of_models"=> $item["number_of_models"], "tlp"=> $item["tlp"],"seed" => 'BigML', 'sample_rate'=> 0.70));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $ensemble->code);

            print "check the ensemble is ready\n";
            $resource = self::$api->_check_resource($ensemble->resource, null, 3000, 50);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "create a local ensemble:\n";
            $ensemble = self::$api->get_ensemble($ensemble->resource);
            $local_ensemble = new Ensemble($ensemble, self::$api);

            print "create prediction for local ensemble with add_confidence true  \n";
            $prediction = $local_ensemble->predict($item["data_input"], true, MultiVote::PLURALITY_CODE, false, true);

            print "the prediction for local ensemble is equals " . $item["prediction"] . "\n";
            $this->assertEquals($item["prediction"], $prediction[0]);

            print "And the local prediction's confidence is " . $item["confidence"] . "\n";
            $this->assertEquals($item["confidence"], round($prediction[1], 4));

        }

   }

   public function test_scenario4() {

        $data = array(array("filename" => 'data/iris.csv',
                            "params1" => array("input_fields" => array("000000", "000001","000003", "000004")), 
                            "params2" => array("input_fields" => array("000000", "000001","000002", "000004")),
                            "params3" => array("input_fields" => array("000000", "000001","000002", "000003", "000004")),
                            "number_of_models" => 3,
                            "field_importance" => array("000002"=>0.5269933333333333, "000003" => 0.38936, "000000" => 0.04662333333333333, "000001" => 0.037026666666666666)));

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

            print "create model with params1\n";
            $model_1 = self::$api->create_model($dataset->resource, $item["params1"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_1->code);
    
            print "check model is ready\n";
            $resource = self::$api->_check_resource($model_1->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "create model with params2\n";
            $model_2 = self::$api->create_model($dataset->resource, $item["params2"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_2->code);
        
            print "check model is ready\n";
            $resource = self::$api->_check_resource($model_2->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "create model with params3\n";
            $model_3 = self::$api->create_model($dataset->resource, $item["params3"]);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model_3->code);
 
            print "check model is ready\n";
            $resource = self::$api->_check_resource($model_3->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
          
            $model_1 = self::$api->get_model($model_1->resource);
	    $model_2 = self::$api->get_model($model_2->resource);
	    $model_3 = self::$api->get_model($model_3->resource);

            $local_model_1 = new Model($model_1);
            $local_model_2 = new Model($model_2);
	    $local_model_3 = new Model($model_3);
	    
            $local_ensemble = new Ensemble(array($local_model_1, $local_model_2, $local_model_3), self::$api);
             
            $field_importance_data = $local_ensemble->field_importance_data();
  
            $this->assertEquals($item["field_importance"], $field_importance_data[0]);

        }
   }

   public function test_scenario5() {
        $data = array(array("filename" => 'data/grades.csv',
                            "number_of_models" => 2,
                            "tlp" => 1,
                            "data_input" => array(),
                            "prediction" => 67.8816)
        );

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

            print "create a ensemble from ";
            $ensemble = self::$api->create_ensemble($dataset->resource, array("number_of_models"=> $item["number_of_models"], "tlp"=> $item["tlp"],"seed" => 'BigML', 'sample_rate'=> 0.70));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $ensemble->code);

            print "check the ensemble is ready\n";
            $resource = self::$api->_check_resource($ensemble->resource, null, 3000, 50);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "create a local ensemble:\n";
            $ensemble = self::$api->get_ensemble($ensemble->resource);
            $local_ensemble = new Ensemble($ensemble, self::$api);

            print "create prediction for local ensemble using_median_with_confidence  \n";
            $prediction = $local_ensemble->predict($item["data_input"], true, MultiVote::PLURALITY_CODE, true, false, false, false, false, false, false, null, Tree::LAST_PREDICTION, true);

            print "the prediction for local ensemble is equals " . $item["prediction"] . "\n";
            $this->assertEquals($item["prediction"], round($prediction[0], 4));


        }
   }
}    
