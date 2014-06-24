<?php
include '../bigml/bigml.php';
include '../bigml/ensemble.php';

class ApiTest extends PHPUnit_Framework_TestCase
{
    protected static $username = "antonio_machine";
    protected static $api_key = "8f9a21db4318c4b6c1e89075ff610c935b2db359";

    protected static $api;
    protected static $sources;
    protected static $datasets;
    protected static $clusters;
    protected static $models;
    protected static $predictions;
    protected static $centroids;
    protected static $ensembles;
    protected static $data;
    protected static $data_localfile = './data/iris.csv';
    protected static $remote_localfile = 'http://jkcray.maths.ul.ie/ms4024/R-Files/SampleRDataFiles/Iris.txt';
    protected static $local_ensemble;

    public static function setUpBeforeClass() {
       self::$api =  new BigML(self::$username, self::$api_key, true);
       self::$sources = array();
       self::$datasets = array();
       self::$models = array();
       self::$ensembles = array();
       self::$predictions = array();
       self::$clusters = array();
       self::$centroids = array();
	}

    public function test_i_create_a_source_uploading_local_file() {
       $source = self::$api->create_source(self::$data_localfile, $options=array('name'=>'local_test_source'));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
       $this->assertEquals(1, $source->object->status->code);
       array_push(self::$sources,$source->resource);
    }
  
    public function test_i_create_a_source_uploading_remote_file() {
	   $source = self::$api->create_source(self::$remote_localfile, $options=array('name'=>'remote_test_source'));
	   $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
	   $this->assertEquals(1, $source->object->status->code);
	   array_push(self::$sources,$source->resource);
	}

    public function test_i_wait_until_the_source_is_ready() {
	   foreach(self::$sources as $source) {
          $resource = self::$api->_check_resource($source, null, 3000, 10);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
	   }	  
    }

    public function test_i_create_a_dataset_with_source_id() {
       $dataset = self::$api->create_dataset(self::$sources[0]);
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
       $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);
       array_push(self::$datasets, $dataset->resource);
    }

    public function test_i_wait_until_the_dataset_is_ready() {
       $resource = self::$api->_check_resource(self::$datasets[0], null, 3000, 10);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_i_create_a_model_with_dataset() {
       $model = self::$api->create_model(self::$datasets[0]);
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);
       array_push(self::$models, $model->resource); 
    }

    public function test_i_wait_until_the_model_is_ready() {
       $resource = self::$api->_check_resource(self::$models[0], null, 3000, 10);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_i_create_a_prediction_for_petal_width_0_5() {
       $data = array("petal width" => 0.5); 
       $prediction = self::$api->create_prediction(self::$models[0], $data);
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);
       array_push(self::$predictions, $prediction);
    }

    public function test_the_prediction_for_000004_is_Iris_setosa() { 
       $prediction = self::$predictions[0];
       $objective = "000004";
       $this->assertEquals("Iris-setosa", $prediction->object->prediction->{$objective});
    }
 
    public function test_i_create_a_cluster() {
       $cluster = self::$api->create_cluster(self::$datasets[0], array('seed'=>'BigML tests'));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $cluster->code);
       $this->assertEquals(BigMLRequest::QUEUED, $cluster->object->status->code);
       array_push(self::$clusters, $cluster->resource);
    }
   
    public function test_i_wait_until_the_cluster_is_ready() {
       $resource = self::$api->_check_resource(self::$clusters[0], null, 3000, 10);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_i_create_centroid_for_data_0() {
       $data= array("petal width" => 0.5, "petal length"=>0.1, "sepal length"=>0.5, "sepal width"=>0.2, "species" => "Iris-setosa");
       $centroid = self::$api->create_centroid(self::$clusters[0], $data);

       $this->assertEquals(BigMLRequest::HTTP_CREATED, $centroid->code);
       array_push(self::$centroids, $centroid->resource); 
    }
 
    public function test_i_wait_until_the_centroid_is_ok() {
       $resource = self::$api->_check_resource(self::$centroids[0], null, 3000, 10);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
       $centroid = self::$api->get_centroid(self::$centroids[0]);
       $this->assertEquals(BigMLRequest::HTTP_OK, $centroid->code);
       self::$centroids[0] = $centroid;
    }  
    
    public function test_the_centroid_is_equals_to_cluster_2() {
       $centroid = self::$centroids[0];
       $this->assertEquals('Cluster 3', $centroid->object->centroid_name);
    }

    public function test_I_create_a_dataset_from_the_cluster_and_centroid() {
       $dataset =self::$api->create_dataset(self::$clusters[0], array("centroid" => self::$centroids[0]->object->centroid_id));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
       array_push(self::$datasets, $dataset->resource);
    }

    public function test_i_wait_until_the_dataset_with_cluster_is_ready() {
       $resource = self::$api->_check_resource(self::$datasets[1], null, 3000, 10);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_i_check_the_dataset_is_created_for_cluster_and_centroid() {
       $cluster = self::$api->get_cluster(self::$clusters[0]);
       $centroid  = self::$centroids[0];
       $this->assertEquals(BigMLRequest::HTTP_OK, $cluster->code);
       $this->assertEquals("dataset/" . $cluster->object->cluster_datasets->{$centroid->object->centroid_id}, self::$datasets[1]);
    }

    public function test_i_create_a_ensemble_of_5_models_and_1_tlp() {
       $ensemble = self::$api->create_ensemble(self::$datasets[0], array("number_of_models"=> 5, "tlp"=>1, "sample_rate"=>0.70, "seed" => 'BigML'));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $ensemble->code);
       array_push(self::$ensembles, $ensemble->resource);
    }

    public function test_i_wait_until_the_ensemble_0_is_ready() {
       $resource = self::$api->_check_resource(self::$ensembles[0], null, 3000, 50);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_i_create_an_ensemble_prediction_for_petal_width_0_5() {
       $data = array("petal width" => 0.5);
       $prediction = self::$api->create_prediction(self::$ensembles[0], $data);
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);
       array_push(self::$predictions, $prediction);
    }

    public function test_i_wait_the_prediction_for_ensemble_is_ready() {
       $resource = self::$api->_check_resource(self::$predictions[1], null, 3000, 10);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_the_prediction_ensemble_for_000004_is_Iris_setosa() {
       $prediction = self::$predictions[1];
       $objective = "000004";
       $this->assertEquals("Iris-setosa", $prediction->object->prediction->{$objective});
    }

    public function test_i_create_a_local_ensemble() {
       $ensemble = self::$api->get_ensemble(self::$ensembles[0]);
       self::$local_ensemble = new Ensemble($ensemble, self::$api);
    }

    public function test_the_local_ensemble_prediction_for_petal_width_0_5() {
       $data = array("petal width" => 0.5);
       $prediction = self::$local_ensemble->predict($data);
       array_push(self::$predictions, $prediction);
    }

    public function test_the_prediction_local_ensemble_is_Iris_setosa() {
       $prediction = self::$predictions[2]; 
       if (is_array($prediction)) {
          $prediction = $prediction[0];
       }
       $this->assertEquals("Iris-setosa", $prediction);
    }

/*
def the_local_prediction_is(step, prediction):
    if isinstance(world.local_prediction, list):
        local_prediction = world.local_prediction[0]
    else:
        local_prediction = world.local_prediction
    try:
        local_model = world.local_model
        if local_model.tree.regression:
            local_prediction = round(float(local_prediction), 4)
            prediction = round(float(prediction), 4)
    except:
        local_model = world.local_ensemble.multi_model.models[0]
        if local_model.tree.regression:
            local_prediction = round(float(local_prediction), 4)
            prediction = round(float(prediction), 4)

    assert local_prediction == prediction
 
        | data               | time_1  | time_2 | time_3 | time_4 | number_of_models | tlp   |  data_input    | objective | prediction  |
        | ../data/grades.csv | 10      | 10     | 150     | 20     | 10               | 1     | {"Assignment": 81.22, "Tutorial": 91.95, "Midterm": 79.38, "TakeHome": 105.93} | 000005    | 82.928846 |
        | ../data/grades.csv | 10      | 10     | 150     | 20     | 10               | 1     | {"Assignment": 97.33, "Tutorial": 106.74, "Midterm": 76.88, "TakeHome": 108.89} | 000005    | 68.861652 | 
	 
	 */
}

?>
