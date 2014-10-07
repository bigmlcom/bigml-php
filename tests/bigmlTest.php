<?php
include '../bigml/bigml.php';
include '../bigml/ensemble.php';
include '../bigml/cluster.php';
#include '../bigml/multimodel.php';

class BigMLTest extends PHPUnit_Framework_TestCase
{
    protected static $username; # "you_username"
    protected static $api_key; # "your_api_key"

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
    protected static $local_model;
    protected static $local_cluster;
    protected static $evaluations;
    protected static $models_tag_list;
    protected static $local_multimodel;
    protected static $batch_predictions;
    protected static $votes;

    static function clean_all($api) {
        print "DELETE CENTROIDS\n";
        $items = $api::list_centroids();
        while (count($items->resources) > 0) {
            foreach($items->resources as $resource) {
                $api::delete_centroid($resource->resource);
            }
            $items = $api::list_centroids();
        }
        print "DELETE CLUSTERS\n";
        $items = $api::list_clusters();
        while (count($items->resources) > 0) {
            foreach($items->resources as $resource) {
                $api::delete_cluster($resource->resource);
            }
            $items = $api::list_clusters();
        }
        print "DELETE ENSEMBLES\n";
        $items = $api::list_ensembles();
        while (count($items->resources) > 0) {
            foreach($items->resources as $resource) {
                $api::delete_ensemble($resource->resource);
            }
            $items = $api::list_ensembles();
        }
        print "DELETE MODELS\n";
        $items = $api::list_models();
        while (count($items->resources) > 0) {
            foreach($items->resources as $resource) {
                $api::delete_model($resource->resource);
            }
            $items = $api::list_models();
        }
        print "DELETE EVALUATIONS\n";
        $items = $api::list_evaluations();
        while (count($items->resources) > 0) {
            foreach($items->resources as $resource) {
                $api::delete_evaluation($resource->resource);
            }
            $items = $api::list_evaluations();
        }
        print "DELETE DATASETS\n";
        $items = $api::list_datasets();
        while (count($items->resources) > 0) {
            foreach($items->resources as $resource) {
                $api::delete_dataset($resource->resource);
            }
            $items = $api::list_datasets();
        }
        print "DELETE SOURCES\n";
        $items = $api::list_sources();
        while (count($items->resources) > 0) {
            foreach($items->resources as $resource) {
                $api::delete_source($resource->resource);
            }
            $items = $api::list_sources();
        }
        print "DELETE PREDICTIONS\n";
        $items = $api::list_predictions();
        while (count($items->resources) > 0) {
            foreach($items->resources as $resource) {
                $api::delete_prediction($resource->resource);
            }
            $items = $api::list_predictions();
        }
    }

    public static function setUpBeforeClass() {
       self::$api =  new BigML(self::$username, self::$api_key, true);
       self::$sources = array();
       self::$datasets = array();
       self::$models = array();
       self::$ensembles = array();
       self::$predictions = array();
       self::$clusters = array();
       self::$centroids = array();
       self::$evaluations = array();
       self::$models_tag_list = array();
       self::$batch_predictions = array();
       self::clean_all(self::$api);
       ini_set('memory_limit', '512M');
    }

    public function test_create_an_anomaly_detector_from_a_dataset_or_dataset_list() {
       $datasetIds = array();

       print "create_a_source_uploading_local_file\n";
       $source = self::$api->create_source(self::$data_localfile, $options=array('name'=>'test_source'));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
       $this->assertEquals(1, $source->object->status->code);
  
       print "check local source is ready\n";
       $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
 
       print "I create a dataset\n";
       $dataset = self::$api->create_dataset($source->resource);
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
       $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

       print "I wait until the dataset is ready\n";
       $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

       print "Then I create an anomaly detector from a dataset\n";
       $anomaly = self::$api->create_anomaly($dataset->resource);

       print "I wait until the anomaly detector is ready\n";
       $resource = self::$api->_check_resource($anomaly->resource, null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

       $anomaly = self::$api->get_anomaly($anomaly->resource);

       print "I check the anomaly detector stems from the original dataset\n";
       $this->assertEquals($anomaly->object->dataset, $dataset->resource);

       array_push($datasetIds, $dataset->resource);

       print "I create a dataset\n";
       $dataset = self::$api->create_dataset($source->resource);
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
       $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

       print "I wait until the dataset is ready\n";
       $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

       array_push($datasetIds, $dataset->resource);
       print "Then I create an anomaly detector from a dataset\n";
       $anomaly = self::$api->create_anomaly($datasetIds);

       print "I wait until the anomaly detector is ready\n";
       $resource = self::$api->_check_resource($anomaly->resource, null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

       $anomaly = self::$api->get_anomaly($anomaly->resource);
       print "I check the anomaly detector stems from the original dataset list\n";
       $this->assertEquals($anomaly->object->datasets, $datasetIds);

    }

    public function test_creating_an_anomaly_score() {
       print "create_a_source_uploading_local_file\n";
       $source = self::$api->create_source('./data/tiny_kdd.csv', $options=array('name'=>'tiny_source'));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
       $this->assertEquals(1, $source->object->status->code);

       print "check local source is ready\n";
       $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

       print "I create a dataset\n";
       $dataset = self::$api->create_dataset($source->resource);
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
       $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

       print "I wait until the dataset is ready\n";
       $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

       print "Then I create an anomaly detector from a dataset\n";
       $anomaly = self::$api->create_anomaly($dataset->resource);

       print "I wait until the anomaly detector is ready\n";
       $resource = self::$api->_check_resource($anomaly->resource, null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

       $resource = self::$api->create_anomaly_score($anomaly->resource, array("src_bytes"=>350));

       $this->assertEquals($resource->object->score, 0.92618);

    }

    function test_creating_a_batch_anomaly_score_from_an_anomaly_detector() {
       print "create_a_source_uploading_local_file\n";
       $source = self::$api->create_source('./data/tiny_kdd.csv', $options=array('name'=>'tiny_source'));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
       $this->assertEquals(1, $source->object->status->code);

       print "check local source is ready\n";
       $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

       print "I create a dataset\n";
       $dataset = self::$api->create_dataset($source->resource);
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
       $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

       print "I wait until the dataset is ready\n";
       $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

       print "Then I create an anomaly detector from a dataset\n";
       $anomaly = self::$api->create_anomaly($dataset->resource);

       print "I wait until the anomaly detector is ready\n";
       $resource = self::$api->_check_resource($anomaly->resource, null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

       print "I create a batch anomaly score\n";
       $batch_anomaly_score = self::$api->create_batch_anomaly_score($anomaly->resource, $dataset->resource);
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $batch_anomaly_score->code);

       print "I check the batch anomaly score is ok\n";
       $resource = self::$api->_check_resource($batch_anomaly_score, null, 3000, 50);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

       print "I download the created anomaly score file to local file\n";
       $filename = self::$api->download_batch_anomaly_score($batch_anomaly_score->resource, "./tmp/batch_predictions_score.csv");
       $this->assertNotNull($filename);

       print "Then the batch anomaly score file is like <predictions_file>";
       $this->assertTrue(compareFiles("./tmp/batch_predictions_score.csv", "./checkfiles/batch_predictions_a.csv"));

    }

    public function test_successfully_creating_a_batch_centroid_from_a_cluster() {
       $data = array(array("filename"=> "./data/diabetes.csv",
                           "local_file" => "./tmp/batch_predictions.csv",
                           "predictions_file" => "./checkfiles/batch_predictions_c.csv"
                          )
                    );
       print "Successfully creating a batch centroid from a cluster\n";
       foreach($data as $item)
       {
          print "I create a data source uploading a ". $data["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "I wait until the source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "I create a dataset\n";
          $dataset = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

          print "I wait until the dataset is ready\n";
          $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "create a cluster\n";
          $cluster = self::$api->create_cluster($dataset->resource, array('seed'=>'BigML tests'));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $cluster->code);
          $this->assertEquals(BigMLRequest::QUEUED, $cluster->object->status->code);

          print "I wait until the cluster is ready\n";
          $resource = self::$api->_check_resource($cluster->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "I create a batch centroid for the dataset\n";
          $cluster = self::$api->get_cluster($cluster->resource);
          $dataset = self::$api->get_dataset($dataset->resource);
          $batch_centroid = self::$api->create_batch_centroid($cluster, $dataset);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $batch_centroid->code);

          print "I wait until the batch centroid  is ready\n";
          $resource = self::$api->_check_resource($batch_centroid->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "i download the batch centroid to local file " .$item["local_file"] . " \n";
          $filename = self::$api->download_batch_centroid($batch_centroid->resource, $item["local_file"]);
          $this->assertNotNull($filename);

          print "then the batch centroid file is like " .$item["predictions_file"] . " \n";
          $this->assertTrue(compareFiles($item["predictions_file"], $item["local_file"]));

       }
    }

    public function test_successfully_creating_a_centroid_and_the_associated_dataset() {
       $data = array(array("filename"=> "./data/diabetes.csv",
                           "input_data"=> array("pregnancies" => 0,
                                                "plasma glucose" => 118,
                                                "blood pressure" => 84,
                                                "triceps skin thickness" => 47,
                                                "insulin" => 230,
                                                "bmi"=> 45.8,
                                                "diabetes pedigree"=> 0.551,
                                                "age" => 31,
                                                "diabetes" => "true"),
                           "centroid" => "Cluster 4"
                    ));

       print "Successfully creating a centroid and the associated dataset\n";
       foreach($data as $item)
       {
          print "I create a data source uploading a ". $data["filename"]. " file\n";
          $source = self::$api->create_source($item["filename"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "I wait until the source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "I create a dataset\n";
          $dataset = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

          print "I wait until the dataset is ready\n";
          $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "create a cluster\n";
          $cluster = self::$api->create_cluster($dataset->resource, array('seed'=>'BigML tests'));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $cluster->code);
          $this->assertEquals(BigMLRequest::QUEUED, $cluster->object->status->code);

          print "I wait until the cluster is ready\n";
          $resource = self::$api->_check_resource($cluster->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "create a centroid\n";
          $centroid = self::$api->create_centroid($cluster->resource, $item["input_data"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $centroid->code);

          print "the centroid is " . $item["centroid"] . "\n";
          $this->assertEquals($item["centroid"], $centroid->object->centroid_name);

          print "I create a dataset from the cluster and the centroid";
          $dataset =self::$api->create_dataset($cluster->resource, array("centroid" => $centroid->object->centroid_id));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);

          print "I wait until the dataset is ready\n";
          $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "I check that the dataset is created for the cluster and the centroid\n";
          $cluster = self::$api->get_cluster($cluster->resource);
          $this->assertEquals(BigMLRequest::HTTP_OK, $cluster->code);
          $this->assertEquals("dataset/" . $cluster->object->cluster_datasets->{$centroid->object->centroid_id}, $dataset->resource);

       }
    }

    public function test_successfully_comparing_centroids_with_or_without_text_options() {

       $data = array(array("filename"=> "./data/diabetes.csv",
                           "options" => array("fields"=> new stdClass()),
                           "input_data"=> array("pregnancies" =>0,
                                          "plasma glucose"=>118,
                                          "blood pressure"=> 84,
                                          "triceps skin thickness"=>47,
                                          "insulin" => 230,
                                          "bmi"=>45.8,
                                          "diabetes pedigree"=>"0.551",
                                          "age"=>31,
                                          "diabetes" => "true"),
                           "centroid"=>"Cluster 4",
                           "distance"=>0.45411));

       print "Successfully comparing centroids with or without text options\n";
       foreach($data as $item)
       {
          print "I create a data source uploading a ". $data["filename"]. " file\n";

          $source = self::$api->create_source($item["filename"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
          $this->assertEquals(1, $source->object->status->code);

          print "I wait until the source is ready\n";
          $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "I update the source\n";
          $source = self::$api->update_source($source->resource, $item["options"]);
          $this->assertEquals(BigMLRequest::HTTP_ACCEPTED, $source->code);

          print "I create a dataset\n";
          $dataset = self::$api->create_dataset($source->resource);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
          $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

          print "I wait until the dataset is ready\n";
          $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "create a cluster\n";
          $cluster = self::$api->create_cluster($dataset->resource, array('seed'=>'BigML tests'));
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $cluster->code);
          $this->assertEquals(BigMLRequest::QUEUED, $cluster->object->status->code);

          print "I wait until the cluster is ready\n";
          $resource = self::$api->_check_resource($cluster->resource, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

          print "I create a local cluster\n";
          $this->local_cluster = new Cluster($cluster->resource, self::$api);

          print "create a centroid\n";
          $centroid = self::$api->create_centroid($cluster->resource, $item["input_data"]);
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $centroid->code);

          print "the centroid is " . $item["centroid"] . " with distance " . $item["distance"] . "\n";
          $this->assertEquals(BigMLRequest::HTTP_CREATED, $centroid->code);
          $this->assertEquals($item["centroid"], $centroid->object->centroid_name);
          $this->assertEquals($item["distance"], round($centroid->object->distance, 6));

          print "create a local centroid\n";
          $local_centroid = $this->local_cluster->centroid($item["input_data"]);

          print "the local centroid is " . $item["centroid"] . " with distance " . $item["distance"] . "\n";
          $this->assertEquals($item["centroid"], $local_centroid["centroid_name"]);
          $this->assertEquals($item["distance"], round($local_centroid["distance"], 6));

       }
    }

    public function test_i_create_a_source_uploading_local_file() {
       print "create_a_source_uploading_local_file\n";
       $source = self::$api->create_source(self::$data_localfile, $options=array('name'=>'local_test_source'));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
       $this->assertEquals(1, $source->object->status->code);
       array_push(self::$sources,$source->resource);
    }
  
    public function test_i_create_a_source_uploading_remote_file() {
       print "create_a_source_uploading_remote_file\n";
	   $source = self::$api->create_source(self::$remote_localfile, $options=array('name'=>'remote_test_source'));
	   $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
	   $this->assertEquals(1, $source->object->status->code);
	   array_push(self::$sources,$source->resource);
	}

    public function test_i_wait_until_the_source_is_ready() {
       print "check local source is ready\n";
	   foreach(self::$sources as $source) {
          $resource = self::$api->_check_resource($source, null, 3000, 30);
          $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
	   }	  
    }

    public function test_i_create_a_dataset_with_source_id() {
       print "create dataset with local source\n";
       $dataset = self::$api->create_dataset(self::$sources[0]);
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
       $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);
       array_push(self::$datasets, $dataset->resource);
    }

    public function test_i_wait_until_the_dataset_is_ready() {
       print "check the dataset is ready\n";
       $resource = self::$api->_check_resource(self::$datasets[0], null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_i_create_a_model_with_dataset() {
       print "create model\n";
       $model = self::$api->create_model(self::$datasets[0]);
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);
       array_push(self::$models, $model->resource); 
    }

    public function test_i_wait_until_the_model_is_ready() {
       print "check model is ready\n";
       $resource = self::$api->_check_resource(self::$models[0], null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_i_create_a_prediction_for_petal_width_0_5() {
       print "create prediction for model\n";
       $data = array("petal width" => 0.5); 
       $prediction = self::$api->create_prediction(self::$models[0], $data);
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);
       array_push(self::$predictions, $prediction);
    }

    public function test_the_prediction_for_000004_is_Iris_setosa() {
       print "check prediction for 000004\n"; 
       $prediction = self::$predictions[0];
       $objective = "000004";
       $this->assertEquals("Iris-setosa", $prediction->object->prediction->{$objective});
    }

    public function test_i_create_a_cluster() {
       print "create a cluster\n";
       $cluster = self::$api->create_cluster(self::$datasets[0], array('seed'=>'BigML tests'));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $cluster->code);
       $this->assertEquals(BigMLRequest::QUEUED, $cluster->object->status->code);
       array_push(self::$clusters, $cluster->resource);
    }
   
    public function test_i_wait_until_the_cluster_is_ready() {
       print "check a cluster is ready\n";
       $resource = self::$api->_check_resource(self::$clusters[0], null, 3000, 100);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_i_create_centroid_for_data_0() {
       print "create a centroid\n";
       $data= array("petal width" => 0.5, "petal length"=>0.1, "sepal length"=>0.5, "sepal width"=>0.2, "species" => "Iris-setosa");
       $centroid = self::$api->create_centroid(self::$clusters[0], $data);

       $this->assertEquals(BigMLRequest::HTTP_CREATED, $centroid->code);
       array_push(self::$centroids, $centroid->resource); 
    }
 
    public function test_i_wait_until_the_centroid_is_ok() {
       print "check centroid is ok\n";
       $resource = self::$api->_check_resource(self::$centroids[0], null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
       $centroid = self::$api->get_centroid(self::$centroids[0]);
       $this->assertEquals(BigMLRequest::HTTP_OK, $centroid->code);
       self::$centroids[0] = $centroid;
    }  
    
    public function test_the_centroid_is_equals_to_cluster_2() {
       print "check the centroid is equals to cluster \n";
       $centroid = self::$centroids[0];
       $this->assertEquals('Cluster 3', $centroid->object->centroid_name);
    }

    public function test_I_create_a_dataset_from_the_cluster_and_centroid() {
       print "create a dataset from cluster and centroid\n";
       $dataset =self::$api->create_dataset(self::$clusters[0], array("centroid" => self::$centroids[0]->object->centroid_id));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
       array_push(self::$datasets, $dataset->resource);
    }

    public function test_i_wait_until_the_dataset_with_cluster_is_ready() {
       print "check the dataset with cluster is ready\n";
       $resource = self::$api->_check_resource(self::$datasets[1], null, 3000, 50);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_i_check_the_dataset_is_created_for_cluster_and_centroid() {
       print "check the dataset for cluster and centroid is ready\n";
       $cluster = self::$api->get_cluster(self::$clusters[0]);
       $centroid  = self::$centroids[0];
       $this->assertEquals(BigMLRequest::HTTP_OK, $cluster->code);
       $this->assertEquals("dataset/" . $cluster->object->cluster_datasets->{$centroid->object->centroid_id}, self::$datasets[1]);
    }

    public function test_i_create_a_ensemble_of_5_models_and_1_tlp() {
       print "create a ensemble from 5 models and tlp 1\n";
       $ensemble = self::$api->create_ensemble(self::$datasets[0], array("number_of_models"=> 5, "tlp"=>1, "sample_rate"=>0.70, "seed" => 'BigML'));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $ensemble->code);
       array_push(self::$ensembles, $ensemble->resource);
    }

    public function test_i_wait_until_the_ensemble_0_is_ready() {
       print "check the ensemble is ready\n";
       $resource = self::$api->_check_resource(self::$ensembles[0], null, 3000, 50);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_i_create_an_ensemble_prediction_for_petal_width_0_5() {
       print "create a prediction for ensemble\n";
       $data = array("petal width" => 0.5);
       $prediction = self::$api->create_prediction(self::$ensembles[0], $data);
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $prediction->code);
       array_push(self::$predictions, $prediction);
    }

    public function test_i_wait_the_prediction_for_ensemble_is_ready() {
       print "check the prediction is ready\n";
       $resource = self::$api->_check_resource(self::$predictions[1], null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_the_prediction_ensemble_for_000004_is_Iris_virginica() {
       print "the prediction ensemble for 000004 is Iris-virginica\n";
       $prediction = self::$predictions[1];
       $objective = "000004";
       $this->assertEquals("Iris-virginica", $prediction->object->prediction->{$objective});
    }

    public function test_i_create_a_local_ensemble() {
       print "create a local ensemble\n";
       $ensemble = self::$api->get_ensemble(self::$ensembles[0]);
       self::$local_ensemble = new Ensemble($ensemble, self::$api);
    }

    public function test_the_local_ensemble_prediction_for_petal_width_0_5() {
       print "create prediction for local ensemble\n"; 
       $data = array("petal width" => 0.5);
       $prediction = self::$local_ensemble->predict($data);
       array_push(self::$predictions, $prediction);
    }

    public function test_the_prediction_local_ensemble_is_Iris_virginica() {
       print "the prediction for local ensemble is equals Iris_virginica\n";
       $prediction = self::$predictions[2]; 
       if (is_array($prediction)) {
          $prediction = $prediction[0];
       }
       $this->assertEquals("Iris-virginica", $prediction);
    }

    public function test_create_new_model_params_1() {
       print "create new model with input_fields 000000,000001,000003, 000004\n";
       $model = self::$api->create_model(self::$datasets[0], array("input_fields"=>array("000000","000001","000003", "000004")));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);
       array_push(self::$models, $model->resource);
    }

    public function test_i_wait_until_the_model_1_is_ready() {
       print "check from model is ready\n";
       $resource = self::$api->_check_resource(self::$models[1], null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_create_new_model_params_2() {
       print "create new model with input_fields 000000,000001,000002, 000004\n";
       $model = self::$api->create_model(self::$datasets[0], array("input_fields"=>array("000000","000001","000002", "000004")));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);
       array_push(self::$models, $model->resource);
    }
  
    public function test_i_wait_until_the_model_2_is_ready() {
       print "check from model is ready\n";
       $resource = self::$api->_check_resource(self::$models[2], null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }
 
    public function test_create_new_model_params_3() {
        print "create new model with input_fields 000000,000001,000002,000003, 000004\n";
       $model = self::$api->create_model(self::$datasets[0], array("input_fields"=>array("000000", "000001","000002", "000003", "000004")));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);
       array_push(self::$models, $model->resource); 
    }

    public function test_i_wait_until_the_model_3_is_ready() {
       print "check from model is ready\n";
       $resource = self::$api->_check_resource(self::$models[3], null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_i_create_a_local_ensemble_with_last_models() {
       print "create a local ensmeble with last models list\n";
       $models = array_slice(self::$models, 1, 4);
       self::$local_ensemble = new Ensemble($models, self::$api);
	   $field_importance_data = self::$local_ensemble->field_importance_data();
    }
  
    public function test_the_field_importance_text_for_local_ensemble_is() {
       print "check the field importance text for local ensemble\n";
       $data= array( array("000002", 0.53),
                      array("000003", 0.39), 
                      array("000000", 0.05),
                      array("000001", 0.04));

       $field_importance_data = self::$local_ensemble->field_importance_data();
       foreach ($data as $value) {
			$this->assertEquals(floatval($value[1]), round($field_importance_data[0][strval($value[0])], 2));
       }
    }

    public function test_i_create_a_local_model() {
       print "i create a local model\n";
       self::$local_model = new Model(self::$models[0], self::$api);
    }

    public function test_the_local_model_prediction_for_petal_width_0_5() {
       print "create prediction for local model\n";
       $data = array("petal width" => 0.5);
       $prediction = self::$local_model->predict($data);
       array_push(self::$predictions, $prediction);
    }
    
    public function test_the_prediction_local_model_is_Iris_setosa() {
       print "check prediction for local model is Iris-setosa\n";
       $prediction = self::$predictions[3];
       if (is_array($prediction)) {
          $prediction = $prediction[0];
       }
       $this->assertEquals("Iris-setosa", $prediction);
    }

    public function test_i_create_an_evaluation_for_the_model_with_the_dataset() {
       print "create an evaluation for the model with the dataset\n";
       $evaluation = self::$api->create_evaluation(self::$models[0], self::$datasets[0]);
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $evaluation>code);
       array_push(self::$evaluations, $evaluation->resource); 
    }

    public function test_i_wait_until_the_evaluation_for_model_is_ready() {
       print "check from evaluation for model is ready\n";
       $resource = self::$api->_check_resource(self::$evaluations[0], null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }
  
    public function test_the_measures_for_evaluation_model() {
       print "check the measures from evaluation model\n";
       $evaluation = self::$api->get_evaluation(self::$evaluations[0]);
       $this->assertEquals($evaluation->object->result->model->average_phi+0.0, floatval(1));
    }

    public function test_i_create_an_evaluation_for_the_ensemble_with_the_dataset() {
       print "create an evaluation for the ensemble with the dataset\n";
       $evaluation = self::$api->create_evaluation(self::$ensembles[0], self::$datasets[0]);
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $evaluation>code);
       array_push(self::$evaluations, $evaluation->resource);
    }
 
    public function test_i_wait_until_the_evaluation_for_ensemble_is_ready() {
       print "check the evaluation for ensemble is ready\n"; 
       $resource = self::$api->_check_resource(self::$evaluations[1], null, 3000, 50);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_the_measures_for_evaluation_ensemble() {
       print "check the measures for evaluation ensemble is ok\n";
       $evaluation = self::$api->get_evaluation(self::$evaluations[1]);
       $this->assertGreaterThan(0.9, $evaluation->object->result->model->average_phi+0.0);
    }

    public function test_i_create_a_dataset_2_with_source_id() {
       print "create a new dataset\n"; 
       $dataset = self::$api->create_dataset(self::$sources[0]);
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
       $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);
       array_push(self::$datasets, $dataset->resource);
    }

    public function test_i_wait_until_the_dataset_2_is_ready() {
       print "check from dataset is ready\n";
       $resource = self::$api->_check_resource(self::$datasets[2], null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_i_create_a_model_with_dataset_list() {
       print "create a new model from dataset list\n";
       $model = self::$api->create_model(array(self::$datasets[0], self::$datasets[2]));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);
       array_push(self::$models, $model->resource);
    }

    public function test_i_wait_until_the_model_with_dataset_list_is_ready() {
       print "check a new model from datasets is ready\n";
       $resource = self::$api->_check_resource(self::$models[4], null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_i_check_the_model_stems_from_the_original_dataset_list() {
       print "check the model stems from the original dataset list\n";
       $model = self::$api->get_model(self::$models[4]);
       $this->assertEquals(array(self::$datasets[0], self::$datasets[2]), $model->object->datasets); 
    }

    public function test_i_create_a_model_with_tag_1() {
       print "create a new model with tags\n"; 
       $model = self::$api->create_model(self::$datasets[0], array("tags"=> array("mytag")));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);
       array_push(self::$models, $model->resource);
    }

    public function test_i_wait_until_the_model_1_with_tag_is_ready() {
       print "check a model is ready\n";  
       $resource = self::$api->_check_resource(self::$models[5], null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_i_create_a_model_with_tag_2() {
       print "create a new model with tags\n"; 
       $model = self::$api->create_model(self::$datasets[0], array('tags'=> array('mytag')));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);
       array_push(self::$models, $model->resource);
    }

    public function test_i_wait_until_the_model_2_with_tag_is_ready() {
       print "check a model is ready\n"; 
       $resource = self::$api->_check_resource(self::$models[6], null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_i_create_a_model_with_tag_3() {
       print "create a new model with tags\n";
       $model = self::$api->create_model(self::$datasets[0], array('tags'=> array('mytag')));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);
       array_push(self::$models, $model->resource);
    }

    public function test_i_wait_until_the_model_3_with_tag_is_ready() {
       print "check a model is ready\n";
       $resource = self::$api->_check_resource(self::$models[7], null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_i_retrieve_a_list_of_remote_models_tagged_with_mytag() { 
       print "get a model list by tags\n";
       foreach (self::$api->list_models("tags__in=mytag")->resources as $model) {
          array_push(self::$models_tag_list, $model->resource);
       }
       $this->assertEquals(3, count(self::$models_tag_list));
    }

    public function test_i_create_a_local_multi_model() {
       print "create a local multi model with model list\n";
       self::$local_multimodel = new MultiModel(self::$models_tag_list, self::$api);
    }

    public function test_the_local_multimodel_prediction_for_petal_width_0_5() {
       print "create a multimodel prediction\n";
       $data = array("petal width" => 0.5);
       $prediction = self::$local_multimodel->predict($data);
       array_push(self::$predictions, $prediction);
    }

    public function test_the_local_multimodel_prediction_is_Iris_setosa() { 
       print "the local multimodel prediction is Iris-setosa\n";
       $prediction = self::$predictions[4];
       $this->assertEquals("Iris-setosa", $prediction);
    }

    public function test_i_create_a_dataset_with_source_id_and_options() {
       print "create dataset with local source and options\n";
       $options = array("fields"=>array("000001"=> array("name"=>"species")));
       $dataset = self::$api->create_dataset(self::$sources[0], $options);
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
       $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);
       array_push(self::$datasets, $dataset->resource);
    }  

    public function test_i_wait_until_the_dataset_with_options_is_ready() {
       print "check the dataset with options is ready\n";
       $resource = self::$api->_check_resource(end(self::$datasets), null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    } 
   
    public function test_i_create_a_model_with_dataset_with_options() {
       print "create model\n";
       $model = self::$api->create_model(end(self::$datasets));
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);
       array_push(self::$models, $model->resource);
    }

    public function test_i_wait_until_the_model_with_options_is_ready() {
       print "check model is ready\n";
       $resource = self::$api->_check_resource(self::$models[8], null, 3000, 30);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }   

    public function test_i_create_a_new_local_model() {
       print "i create a local model\n";
       self::$local_model = new Model(self::$models[8], self::$api);
    }

    public function test_the_field_name_is_changed_to_new_name() {
       print "check the field_name is changed to new name\n";
       $field = "000001";
       $tree = self::$local_model->tree;
       $this->assertEquals("species1", $tree->fields->{$field}->name);
    }

    public function test_create_a_batch_prediction_for_a_dataset_with_model() {
       print "create a batch prediction for a dataset with model";
       $batch_prediction=self::$api->create_batch_prediction(self::$models[0],self::$datasets[0]);
       $this->assertEquals(BigMLRequest::HTTP_CREATED, $batch_prediction->code);
       array_push(self::$batch_predictions, $batch_prediction->resource);
    }

    public function test_i_wait_until_the_batch_prediction_is_ready() {
       print "check a batch_predicion is ready\n";
       $resource = self::$api->_check_resource(self::$batch_predictions[0], null, 3000, 50);
       $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);
    }

    public function test_i_download_the_created_predictions_file() {
       print "download batch predictions file\n";
       $filename = self::$api->download_batch_prediction(self::$batch_predictions[0], "./tmp/batch_predictions.csv");
       $this->assertNotNull($filename);
    }

	public function test_i_compare_the_prediction_file_is_correct() {
	   print "i compare the prediction file is correct\n";
       $this->assertTrue(compareFiles("./tmp/batch_predictions.csv", "./checkfiles/batch_predictions.csv"));
    }

    public function test_create_a_batch_prediction_from_multimodel_and_save_it() {
       print "create a batch prediction from multimodel and save it";
       $data = array(array("petal width"=>0.5), 
                     array("petal length"=>6, "petal width"=> 2),
                     array("petal length"=>4,"petal width"=> 1.5));

       self::$local_multimodel->batch_predict($data, "./tmp");
    }

    public function test_i_combine_the_votes() {
       print "combine the votes\n";
       #world.votes = world.local_model.batch_votes(directory)
       self::$votes=self::$local_multimodel->batch_votes("./tmp");
    }

    public function test_the_plurality_combined_prediction() {
       print "test the plurarity combined prediction\n";
       $predictions = array("Iris-setosa", "Iris-virginica", "Iris-versicolor");
       $i=0;
       foreach(self::$votes as $vote) {
          $this->assertEquals($predictions[$i], $vote->combine()); 
          $i+=1;
       }
    }
 
    public function test_the_confidence_weighted_prediction() {
       print "test the confidence weighted prediction\n";
       $predictions = array("Iris-setosa", "Iris-virginica", "Iris-versicolor");
       $i=0;
       foreach(self::$votes as $vote) {
          $this->assertEquals($predictions[$i], $vote->combine(1));
          $i+=1; 
       }
    }

    public function test_compute_multivote_predictions() {
       $results = array(array("file"=> "./data/predictions_c.json", "data"=>array(array(0, "a", 0.450471270879), array(1,"a",0.552021302649), array(2, "a", 0.403632421178))),
                  array("file"=> "./data/predictions_r.json", "data"=>array(array(0, 1.55555556667, 0.400079152063), array(1,1.59376845074,0.248366474212), array(2, 1.55555556667,0.400079152063))));

       foreach($results as $item) {
          $file_data = file_get_contents($item["file"]);
          $json_c=json_decode($file_data,true);
          $multivote = new MultiVote($json_c);
          print "test compute multivote predictions " . $item["file"] . "\n";
          
          foreach($item["data"] as $result) {
             print "compute the prediction without confidence using method " . $result[0] . "\n";
             $prediction = $multivote->combine($result[0], true);
             $combined_prediction=$prediction[0];
             $combined_confidence=$prediction[1];

             $prediction = $result[1];
             $confidence = $result[2];
             $combined_prediction_not_confidence = $multivote->combine($result[0], false);

             print "check the combined prediction \n"; 
             if ($multivote->is_regression()) {
                $this->assertEquals(round($combined_prediction,6), round($prediction,6));
             } else {
                $this->assertEquals($combined_prediction, $prediction);
             }
             print "check the combined prediction without confidence \n";
             if ($multivote->is_regression()) {
                $this->assertEquals(round($combined_prediction_not_confidence,6), round($prediction,6));
             } else {
                $this->assertEquals($combined_prediction_not_confidence, $prediction);
             }
             print "check the confidence for the combined prediction\n";
             $this->assertEquals(round($combined_confidence, 6),round($confidence,6)); 
          }
          
       }

    }

}

?>
