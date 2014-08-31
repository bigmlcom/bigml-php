<?php
#
# Copyright 2012-2014 BigML
#
# Licensed under the Apache License, Version 2.0 (the "License"); you may
# not use this file except in compliance with the License. You may obtain
# a copy of the License at
#
#     http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
# WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
# License for the specific language governing permissions and limitations
# under the License.

# BigML.io PHP bindings.

# This is a simple binding to BigML.io, the BigML API.

class BigML {
   const BIGML_ENDPOINT = "https://bigml.io";
   const ONLY_MODEL = "only_model=true;limit=-1;"; 
   /**
      * The BigML Access Username 
      *
      * @var string
      * @access private
      * @static
    */
   private static $__username = null;
   /**
       * The BigML Access ApiKey 
       *
       * @var string
       * @access private
       * @static
     */
   private static $__apiKey = null;

   /**
       * The BigML Environment Mode
       *
       * @var string
       * @access private
       * @static
     */
     private static $devMode = false;

   /**
       * The BigML Virual Private domain
       *
       * @var string
       * @access private
       * @static
     */
   private static $domain = false;

   private static $debug = false;

   private static $version = "andromeda";

   private static $storage;

   /*   
    * @param string $username 
    * @param string $apiKey
    * @param boolean $devMode
    * @param string $domain
    * @return void
   */
   public function __construct($username=null, $apiKey=null, $devMode = false, $storage=null, $domain = null, $locale=null, $version="andromeda") {
      
      if ($username == null) {
         $username = getenv("BIGML_USERNAME");
      }

      if ($apiKey == null) {
         $apiKey = getenv("BIGML_API_KEY");
      }

      if ($username !== null && $apiKey !== null)
         self::setAuth($username, $apiKey);

      self::$devMode = $devMode;
      self::$domain = $domain;

      if ($locale != null) {
         setlocale(LC_ALL, $$locale);
      }

      self::$storage=assign_dir($storage);
   }

   /**
    * Set BigML username and key
    *
    * @param string $username Username
    * @param string $apiKey Api key
    * @return void
   */
   public static function setAuth($username, $apiKey) {
      self::$__username = $username;
      self::$__apiKey = $apiKey;
   }

   public static function setDebug($debug=false) {
      self::$debug = $debug;
   }

   /**
    * Check if BigML keys have been set
    *
    * @return boolean
   */
   public static function hasAuth() {
      return (self::$__username !== null && self::$__apiKey !== null);
   }

   public static function isDevMode() {
      return self::$devMode;
   }

   public static function getUsername() {
      return self::$__username;
   }

   public static function getApiKey() {
      return self::$__apiKey;
   }

   public static function getDomain() {
      return self::$domain;
   }

   public static function getVersion() {
      return self::$version;
   }

   public static function getStorage() {
      return self::$storage;
   }

   public static function getDebug() {
      return self::$debug;
   }
   ##########################################################################
   #
   # Sources 
   # https://bigml.com/developers/sources
   #
   ##########################################################################

   public static function create_source($data, $options=array()) 
   {
      /*
           Creates a new source
              The source can be a local file path or a URL o stream source
          
        */
      if (is_file($data)) {
         return self::_create_local_source($data, $options);
      } elseif (filter_var($data, FILTER_VALIDATE_URL)) {
         return self::_create_remote_source($data, $options);
      } else {
         error_log("Wrong source file or url");
         return null;
      } 
   }

   public static function get_source($sourceId, $queryString=null) 
   {
      /*
       Retrieves a remote source.

       The source parameter should be a string containing the
       source id or the dict returned by create_source.
       As source is an evolving object that is processed
       until it reaches the FINISHED or FAULTY state, thet function will
       return a dict that encloses the source values and state info
       available at the time it is called.
      */

	  $rest = self::get_resource_request($sourceId, "source", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();
   }   

   public static function list_sources($queryString=null)
   {
      /*
         Lists all your remote sources.
      */
      $rest = new BigMLRequest('LIST', 'source');

      if ($queryString!=null){
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public static function update_source($sourceId, $data, $waitTime=3000, $retries=10) {
      /*
         Updates a source
      */
      $rest = self::get_resource_request($sourceId, "source", "UPDATE", null, true, $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData(json_encode($data));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));

      return $rest->getResponse();
   }

   public static function delete_source($sourceId) {
      /*
         Deletes a source
      */
      $rest = self::get_resource_request($sourceId, "source", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   private function _check_resource_status($resource, $queryString=null) 
   {
      $rest = new BigMLRequest('GET', $resource);
      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      } 

      $item = $rest->getResponse();

      if ($item == null)
         return false;

      $obj = null;

      if (property_exists($item, "object")) 
          $obj = $item->object;


      if ($obj == null)
         $obj = $item->error;

      if ($obj == null)
         return false;

      $status = $obj->status;
      $code = $item->code;
      $statusCode = $status->code;
      $message = $status->message;

      return array('ready'=>$code!=null && $code==BigMLRequest::HTTP_OK && $statusCode!=null && $statusCode==BigMLRequest::FINISHED, 'code'=>$statusCode, 'message'=> $message, 'resource'=>$item);

   }

   public function _check_resource($resource, $queryString=null, $waitTime=3000, $retries=0)
   {
      
      $r = $resource;
      if (is_string($resource)) 
         $r = json_decode($resource);

      if ($r instanceof STDClass && property_exists($r, "resource")) {
         $resource = $r->resource;
      }

      if (preg_match('/(source|dataset|model|evaluation|ensemble|batchprediction|batchcentroid|prediction|cluster|centroid)(\/)([a-z,0-9]{24}|[a-z,0-9]{27})$/i', $resource, $result)) {
         $count = 0;
         $status = self::_check_resource_status($resource, $queryString); 
         while ($count<$retries && !$status["ready"]) {
            usleep($waitTime*1000); 
            $count++;
            $status=self::_check_resource_status($resource, $queryString);
         }

         return array('type'=> strtolower($result[1]), 'id'=> $resource, 'status'=> $status["code"], 'message' => $status["message"], 'resource' => $status["item"]);
      }

      return null;
   }

   ##########################################################################
   #
   # Datasets
   # https://bigml.com/developers/datasets
   #
   ##########################################################################

   public static function create_dataset($origin_resource, $args=array(), $waitTime=3000, $retries=10) {
      /* Uses a remote resource to create a new dataset 
       * The allowed remote resources can be:
       *   - source
       *   - dataset
       *  - list of dataset
       *  - cluster
       * In the case of using cluster id as origin_resources, a centroid must also be provided in the args argument. The first centroid is used otherwise.
       * If `wait_time` is higher than 0 then the dataset creation request is not sent until the `source` has been created successfuly.
       */
      if (is_array($origin_resource)) {
         $datasetIds = array();
         foreach ($origin_resource as $var => $datasetId) {
            $resource = self::_check_resource($datasetId, null, $waitTime, $retries);
            if ($resource == null || $resource['type'] != "dataset") {
               error_log("Wrong dataset id in List");
               return null;
            } elseif ($resource["status"] != BigMLRequest::FINISHED) {
               error_log($resource['message']);
               return null;
            }

            array_push($datasetIds, $resource["id"]);
         }

         if (sizeof($datasetIds) > 1) {
            $args["origin_datasets"] = $datasetIds;
         } else {
            $args["origin_dataset"] = $datasetIds[0];
         }

      } else {
         $resource = self::_check_resource($origin_resource, null, $waitTime, $retries);

         if ($resource == null || !in_array($resource['type'],array("dataset","source","cluster")) ) {
            error_log("Wrong dataset. A source, dataset, list of dataset ids or cluster id plus centroid id are needed to create a dataset");
            return null;
         } elseif ($resource["status"] != BigMLRequest::FINISHED) {
            error_log($resource['message']);
            return null;
         }

         if ($resource['type'] == "cluster") {
            if (!array_key_exists("centroid",$args)) {

			   $cluster = self::get_cluster($resource["id"]);

			   if ( $cluster == null || !property_exists($cluster, "object") || !property_exists($cluster->object, "cluster_datasets") ) {
                   error_log("Failed to generate the dataset. A centroid id is needed in the args argument to generate a dataset from a cluster.");
                   return null;
			   }

               $args['centroid'] = key(get_object_vars($cluster->object->cluster_datasets));
            }
         } elseif ($resource['type'] == "dataset") {
            $resource['type']="origin_dataset";
         }
          
         $args[$resource['type']] = $resource['id'];
      }
      $rest = new BigMLRequest('CREATE', 'dataset');
      $rest->setData(json_encode($args));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));
      return $rest->getResponse();
   }

   public static function get_dataset($datasetId, $queryString=null)
   {
      /*
         Retrieves a dataset.
         The dataset parameter should be a string containing the
         dataset id or the dict returned by create_dataset.
         As dataset is an evolving object that is processed
         until it reaches the FINISHED or FAULTY state, the function will
         return a dict that encloses the dataset values and state info
         available at the time it is called.
       */

      $rest = self::get_resource_request($datasetId, "dataset", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public static function list_datasets($queryString=null)
   {
     /*
      Lists all your datasets.
     */
      $rest = new BigMLRequest('LIST', 'dataset');

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public static function update_dataset($datasetId, $data, $waitTime=3000, $retries=10) {
      /*
        Update a dataset
      */
      $rest = self::get_resource_request($datasetId, "dataset", "UPDATE", null, true, $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData(json_encode($data));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public static function delete_dataset($datasetId) {
      /*
        Delete a dataset
      */
      $rest = self::get_resource_request($datasetId, "dataset", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Models
   # https://bigml.com/developers/models
   #
   ##########################################################################

   public static function create_model($datasetIds, $data=array(), $waitTime=3000, $retries=10) {
      /*
       Creates a model from a `dataset` or a list o `datasets`.
      */

      $datasets=array();
      if (!is_array($datasetIds)) {
         $datasetIds=array($datasetIds);
      }
   
      foreach ($datasetIds as $var => $datasetId) {
         $resource = self::_check_resource($datasetId, null, $waitTime, $retries);
         if ($resource == null || $resource['type'] != "dataset") {
            error_log("Wrong dataset id");
            return null;
         } elseif ($resource["status"] != BigMLRequest::FINISHED) {
            error_log($resource['message']);
            return null;
         }
         array_push($datasets, $resource["id"]);
      }
         
      $rest = new BigMLRequest('CREATE', 'model');

      if (sizeof($datasets) > 1) {
         $data["datasets"] = $datasets;   
      } else {
         $data["dataset"] = $datasets[0];
      }

      $rest->setData(json_encode($data));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public static function get_model($modelId, $queryString=null, $shared_username=null,  $shared_api_key=null)
   {
      /*
         Retrieves a model.
         The model parameter should be a string containing the
         model id or the dict returned by create_model.
         As model is an evolving object that is processed
         until it reaches the FINISHED or FAULTY state, the function will
         return a dict that encloses the model values and state info
         available at the time it is called.
         If this is a shared model, the username and sharing api key must
         also be provided.
      */
      $rest = self::get_resource_request($modelId, "model", "GET", $queryString, true, 3000, 0, $shared_username, $shared_api_key);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public static function list_models($queryString=null)
   {
      /*
         Lists all your models
      */
      $rest = new BigMLRequest('LIST', 'model');

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public static function update_model($modelId, $data, $waitTime=3000, $retries=10) {

      /*
         Updates a model
      */
      $rest = self::get_resource_request($modelId, "model", "UPDATE", null, true, $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData(json_encode($data));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public static function delete_model($modelId) {
      /*
        Deletes a model
      */
      $rest = self::get_resource_request($modelId, "model", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Ensembles
   # https://bigml.com/developers/ensembles
   #
   ##########################################################################

   public static function create_ensemble($datasetIds, $data=array(), $waitTime=3000, $retries=10) {

      /*
       Creates a ensemble from a `dataset` or a list o `datasets`.
      */

      $datasets= array();

      if (!is_array($datasetIds)) {
         $datasetIds=array($datasetIds);
      }

      foreach ($datasetIds as $var => $datasetId) {
         $resource = self::_check_resource($datasetId, null, $waitTime, $retries);
         if ($resource == null || $resource['type'] != "dataset") {
            error_log("Wrong dataset id");
            return null;
         } elseif ($resource["status"] != BigMLRequest::FINISHED) {
            error_log($resource['message']);
            return null;
         }
         array_push($datasets, $resource["id"]);
      }

      $rest = new BigMLRequest('CREATE', 'ensemble');

      if (sizeof($datasets) > 1) {
         $data["datasets"] = $datasets;
      } else {
         $data["dataset"] = $datasets[0];
      }

      $rest->setData(json_encode($data));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public static function get_ensemble($ensembleId, $queryString=null)
   {
      /*
         Retrieves an ensemble.

         The ensemble parameter should be a string containing the
         ensemble id or the dict returned by create_ensemble.
         As an ensemble is an evolving object that is processed
         until it reaches the FINISHED or FAULTY state, the function will
         return a dict that encloses the ensemble values and state info
         available at the time it is called. 
      */
      $rest = self::get_resource_request($ensembleId, "ensemble", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();

   }
   
   public static function list_ensembles($queryString=null)
   {
      /*
        Lists all your ensembles 
      */
      $rest = new BigMLRequest('LIST', 'ensemble');

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public static function update_ensemble($ensembleId, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a ensemble 
      */
      $rest = self::get_resource_request($ensembleId, "ensemble", "UPDATE", null, true, 3000, 10);
      if ($rest == null) return null;

      $rest->setData(json_encode($data));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public static function delete_ensemble($ensembleId) {
      /*
        Deletes a ensemble 
      */
      $rest = self::get_resource_request($ensembleId, "ensemble", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Predictions
   # https://bigml.com/developers/predictions
   #
   ##########################################################################
   public static function create_prediction($modelOrEnsembleId, $inputData=array(), $args=array(), $waitTime=3000, $retries=10) {
      /*
         Creates a new prediction.
         The model parameter can be:
           - a simple model
           - an ensemble
      */

      $resource = self::_check_resource($modelOrEnsembleId, null, $waitTime, $retries);

      if ($resource == null || !in_array($resource['type'],array("model","ensemble")) ) {
          error_log("Wrong model or ensemble id. A model or ensemble id is needed to create a prediction");
          return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
          error_log($resource['message']);
          return null;
      }

      $args = $args == null? array() : $args; 
      $args["input_data"] = $inputData == null? array() : $inputData;
      $args[$resource['type']] = $resource['id'];

      $rest = new BigMLRequest('CREATE', 'prediction');

      $rest->setData(json_encode($args));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));

      return $rest->getResponse();
   }

   public static function get_prediction($predictionId, $queryString=null)
   {
      /*
         Retrieves a prediction.
      */
      $rest = self::get_resource_request($predictionId, "prediction", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public static function list_predictions($queryString=null)
   {
      /*
         Lists all your predictions 
      */
      $rest = new BigMLRequest('LIST', 'prediction');

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public static function update_prediction($predictionId, $data, $waitTime=3000, $retries=10) 
   {
      /*
        Updates a  prediction 
      */
      $rest = self::get_resource_request($predictionId, "prediction", "UPDATE", null, true, $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData(json_encode($data));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public static function delete_prediction($predictionId) {
      /*
        Deletes a  prediction
      */
      $rest = self::get_resource_request($predictionId, "prediction", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }
   ##########################################################################
   #
   # Batch Predictions
   # https://bigml.com/developers/batch_predictions
   #
   ##########################################################################

   public static function create_batch_prediction($modelOrEnsembleId, $datasetId, $args=array(), $waitTime=3000, $retries=10) {

      /*
         Creates a new prediction.
         The model parameter can be:
            - a simple model
            - an ensemble
      */

      $args = $args == null? array() : $args;

      $resource = self::_check_resource($modelOrEnsembleId, null, $waitTime, $retries);

      if ($resource == null || !in_array($resource['type'],array("model","ensemble")) ) {
         error_log("Wrong model or ensemble id. A model or ensemble id is needed to create a batch prediction");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $args[$resource['type']] = $resource['id'];

      $resource = self::_check_resource($datasetId, null, $waitTime, $retries);
      if ($resource == null || $resource['type'] != "dataset" ) {
         error_log("Wrong dataset id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }   

      $args["dataset"] = $resource['id']; 

      $rest = new BigMLRequest('CREATE', 'batchprediction');

      $rest->setData(json_encode($args));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));
      return $rest->getResponse();
   }

   public static function get_batch_prediction($batchPredictionId, $queryString=null)
   {
      /*
        Retrieves a batch prediction.

        The batch_prediction parameter should be a string containing the
        batch_prediction id or the dict returned by create_batch_prediction.
        As batch_prediction is an evolving object that is processed
        until it reaches the FINISHED or FAULTY state, the function will
        return a dict that encloses the batch_prediction values and state
        info available at the time it is called.
      */
      $rest = self::get_resource_request($batchPredictionId, "batchprediction", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public static function download_batch_prediction($batchPredictionId, $filename=null){ 
      /*
         Retrieves the batch predictions file.

         Downloads predictions, that are stored in a remote CSV file. If
         a path is given in filename, the contents of the file are downloaded
         and saved locally. A file-like object is returned otherwise.
      */

      $rest = self::get_resource_request($batchPredictionId, "batchprediction", "DOWNLOAD"); 
      if ($rest == null) return null;

      $data = $rest->download();

      if ($data == false) { 
         error_log("Error downloading file");
         return null;
      }

      if ($filename != null) {
         file_put_contents($filename, $data);
      }

      return $data;
   }

   public static function list_batch_predictions($queryString=null)
   {
      /*
         Lists all your batch predictions 
      */
      $rest = new BigMLRequest('LIST', 'batchprediction');

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public static function update_batch_prediction($batchPredictionId, $data, $waitTime=3000, $retries=10) {
      /*
         Updates a batch prediction 
      */
      $rest = self::get_resource_request($batchPredictionId, "batchprediction", "UPDATE", null, true, $waitTime, $retries); 
      if ($rest == null) return null;

      $rest->setData(json_encode($data));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public static function delete_batch_prediction($batchPredictionId) {
      /*
         Deletes a batch prediction 
      */
      $rest = self::get_resource_request($batchPredictionId, "batchprediction", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }
   
   ##########################################################################
   #
   # Evaluations 
   # https://bigml.com/developers/evaluations
   #
   ##########################################################################
   public static function create_evaluation($modelId, $datasetId, $args=array(), $waitTime=3000, $retries=10) {

      /*
         Creates a new evaluation.
      */

      $args = $args == null? array() : $args;

      $resource = self::_check_resource($modelId, null, $waitTime, $retries);

      if ($resource == null || !in_array($resource['type'],array("model","ensemble"))) {
         error_log("Wrong model or ensemble id. A model or ensemble id is needed to create a evaluation");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $args[$resource['type']] = $resource['id'];

      $resource = self::_check_resource($datasetId, null, $waitTime, $retries);
      if ($resource == null || $resource['type'] != "dataset" ) {
         error_log("Wrong dataset id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $args[$resource['type']] = $resource['id'];

      $rest = new BigMLRequest('CREATE', 'evaluation');

      $rest->setData(json_encode($args));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));
      return $rest->getResponse();
   }

   public static function get_evaluation($evaluationId, $queryString=null)
   {
      /*
         Retrieves a evaluation
         The evaluation parameter should be a string containing the
         evaluation id or the dict returned by create_evaluation.
         As evaluation is an evolving object that is processed
         until it reaches the FINISHED or FAULTY state, the function will
         return a dict that encloses the evaluation values and state info
         available at the time it is called. 
      */
      $rest = self::get_resource_request($evaluationId, "evaluation", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public static function list_evaluations($queryString=null)
   {
      /*
        Lists all your evaluations.
      */
      $rest = new BigMLRequest('LIST', 'evaluation');

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public static function update_evaluation($evaluationId, $data, $waitTime=3000, $retries=10) {
      /*
         Updates a evaluation
      */
      $rest = self::get_resource_request($evaluationId, "evaluation", "UPDATE", null, true, $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData(json_encode($data));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public static function delete_evaluation($evaluationId) {
      /*
         Deletes a evaluation
      */
      $rest = self::get_resource_request($evaluationId, "evaluation", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }
   ##########################################################################
   #
   # Clusters 
   # https://bigml.com/developers/clusters
   #
   ##########################################################################
   public static function create_cluster($datasetIds, $data=array(), $waitTime=3000, $retries=10) {
      /*
         Creates a cluster from a `dataset` or a list of `datasets`
      */

      $datasets= array();

      if (!is_array($datasetIds)) {
         $datasetIds=array($datasetIds);
      }

      foreach ($datasetIds as $var => $datasetId) {
         $resource = self::_check_resource($datasetId, null, $waitTime, $retries);
         if ($resource == null || $resource['type'] != "dataset") {
            error_log("Wrong dataset id");
            return null;
         } elseif ($resource["status"] != BigMLRequest::FINISHED) {
            error_log($resource['message']);
            return null;
         }
         array_push($datasets, $resource["id"]);
      }

      $rest = new BigMLRequest('CREATE', 'cluster');

      if (sizeof($datasets) > 1) {
         $data["datasets"] = $datasets;
      } else {
         $data["dataset"] = $datasets[0];
      }

      $rest->setData(json_encode($data));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

    public static function get_cluster($clusterId, $queryString=null, $shared_username=null, $shared_api_key=null)
    {
      /*
         Retrieves a cluster.

         The model parameter should be a string containing the
         cluster id or the dict returned by create_cluster.
         As cluster is an evolving object that is processed
         until it reaches the FINISHED or FAULTY state, the function will
         return a dict that encloses the cluster values and state info
         available at the time it is called.

         If this is a shared cluster, the username and sharing api key must
         also be provided.
      */
      $rest = self::get_resource_request($clusterId, "cluster", "GET", $queryString, true, 3000, 0, $shared_username, $shared_api_key);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public static function list_clusters($queryString=null)
   {
      /*
         List all your clusters
      */
      $rest = new BigMLRequest('LIST', 'cluster');

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public static function update_cluster($clusterId, $data, $waitTime=3000, $retries=10) {
      /*
         Updates a cluster
      */
      $rest = self::get_resource_request($clusterId, "cluster", "UPDATE", null, true,  $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData(json_encode($data));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }      

   public static function delete_cluster($clusterId) {
   	  /*
        Deletes a cluster
      */
      $rest = self::get_resource_request($clusterId, "cluster", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Centroids
   # https://bigml.com/developers/centroids
   #
   ##########################################################################

   public static function create_centroid($clusterId, $inputData=array(), $args=array(), $waitTime=3000, $retries=10) {
      /*
         Creates a new centroid
      */
      $args = $args == null? array() : $args;

      $resource = self::_check_resource($clusterId);
      if ($resource == null || $resource['type'] != "cluster") {
         error_log("Wrong cluster id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $args["cluster"] = $resource["id"];
      $args["input_data"] = $inputData == null? array() : $inputData;

      $rest = new BigMLRequest('CREATE', 'centroid');

      $rest->setData(json_encode($args));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));
      return $rest->getResponse();
   }

   public static function get_centroid($centroId, $queryString=null)
   {
      /*
         Retrieves a centroid.
      */
      $rest = self::get_resource_request($centroId, "centroid", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public static function list_centroids($queryString=null)
   {
      /*
         List all your centroids.
      */
      $rest = new BigMLRequest('LIST', 'centroid');

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public static function update_centroid($centroId, $data, $waitTime=3000, $retries=10) {
      /*
         Updates a centroid.
      */
      $rest = self::get_resource_request($centroId, "centroid", "UPDATE",null, true, $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData(json_encode($data));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public static function delete_centroid($centroId) {
      /*  
        Deletes a centroid.
      */
      $rest = self::get_resource_request($centroId, "centroid", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Batch Centroids
   # https://bigml.com/developers/batch_centroids
   #
   ##########################################################################
   public static function create_batch_centroid($clusterId, $datasetId, $args=array(), $waitTime=3000, $retries=10) {
      /*
         Creates a new batch centroid.
      */
      $args = $args == null? array() : $args;

      $resource = self::_check_resource($clusterId, null, $waitTime, $retries);

      if ($resource == null || $resource['type'] != "cluster" ) {
         error_log("Wrong cluster id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $args[$resource['type']] = $resource['id'];

      $resource = self::_check_resource($datasetId, null, $waitTime, $retries);
      if ($resource == null || $resource['type'] != "dataset" ) {
          error_log("Wrong dataset id");
          return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
          error_log($resource['message']);
          return null;
      }

      $args[$resource['type']] = $resource['id'];

      $rest = new BigMLRequest('CREATE', 'batchcentroid');

      $rest->setData(json_encode($args));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));

      return $rest->getResponse();
   }

   public static function get_batchcentroid($batchcentroId, $queryString=null)
   {
      /*
         Retrieves a batch centroid.

         The batch_centroid parameter should be a string containing the
         batch_centroid id or the dict returned by create_batch_centroid.
         As batch_centroid is an evolving object that is processed
         until it reaches the FINISHED or FAULTY state, the function will
         return a dict that encloses the batch_centroid values and state
        info available at the time it is called.
      */
      $rest = self::get_resource_request($batchcentroId, "batchcentroid", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public static function download_batch_centroid($batchCentroId, $filename=null) {
      /*
         Retrieves the batch centroid file.

         Downloads centroids, that are stored in a remote CSV file. If
         a path is given in filename, the contents of the file are downloaded
         and saved locally. A file-like object is returned otherwise.
   	 */
      $rest = self::get_resource_request($batchCentroId, "batchcentroid", "DOWNLOAD");
      if ($rest == null) return null;
      $data = $rest->download();

      if ($data == false) {
         error_log("Error downloading file");
         return null;
      }

      if ($filename != null) {
         file_put_contents($filename, $data);
      }

      return $data;
   }


   public static function list_batch_centroids($queryString=null)
   {
      /*
         Lists all your batch centroids
      */
      $rest = new BigMLRequest('LIST', 'batchcentroid');

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public static function update_batchcentroid($batchcentroId, $data, $waitTime=3000, $retries=10) {
      /*
         Updates a batch centroid.
      */
      $rest = self::get_resource_request($batchcentroId, "batchcentroid", "UPDATE",null, true, $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData(json_encode($data));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public static function delete_batchcentroid($batchcentroId) {
      /*
         Deletes a batch centroid.
      */
      $rest = self::get_resource_request($batchcentroId, "batchcentroid", "DELETE",null);
	  if ($rest == null) return null;

      return $rest->getResponse();
   }

   private function _create_local_source($file_name, $options=array()) {
      $rest = new BigMLRequest('CREATE', 'source');
      $options['file'] = '@' . realpath($file_name);
      $rest->setData($options);
      $rest->setHeader('Content-Type', 'multipart/form-data');
      return $rest->getResponse();
   }

   private function _create_remote_source($file_url, $options=array()) {
      $rest = new BigMLRequest('CREATE', 'source');
      $options['remote'] = $file_url;
      $rest->setData(json_encode($options));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($options)));
      return $rest->getResponse();
   }

   public static function create_inline_source($data_string, $options=array()) {
      $rest = new BigMLRequest('CREATE', 'source');
      $options['data'] = $data_string;
      $rest->setData(json_encode($options));
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($options)));
      return $rest->getResponse();
   }

   private function _checkSourceId($stringID) {
      return preg_match("/^source\/[a-f,0-9]{24}$/i", $stringID) ? true : false;
   }

   private function _checkDatasetId($stringID) {
      return preg_match("/^(public\/)?dataset\/[a-f,0-9]{24}$|^shared\/dataset\/[a-f,0-9]{27}$/i", $stringID) ? true : false;
   }

   public function _checkModelId($stringID) {
      return preg_match("/^(public\/)?model\/[a-f,0-9]{24}$|^shared\/model\/[a-f,0-9]{27}$/i", $stringID) ? true : false;
   }

   private function _checkPredictionId($stringID) {
      return preg_match("/^prediction\/[a-f,0-9]{24}$/i", $stringID) ? true : false;
   }

   private function _checkEvaluationId($stringID) {
      return preg_match("/^evaluation\/[a-f,0-9]{24}$/i", $stringID) ? true : false;
   }

   public function _checkEnsembleId($stringID) {
      return preg_match("/^ensemble\/[a-f,0-9]{24}$/i", $stringID) ? true : false;
   }

   private function _checkBatchPredictionId($stringID) {
      return preg_match("/^batchprediction\/[a-f,0-9]{24}$/i", $stringID) ? true : false;
   }

   public function _checkClusterId($stringID) {
      return preg_match("/^(public\/)?cluster\/[a-f,0-9]{24}$|^shared\/cluster\/[a-f,0-9]{27}$/i", $stringID) ? true : false;
   }

   private function _checkCentroId($stringID) {
      return preg_match("/^centroid\/[a-f,0-9]{24}$/i", $stringID) ? true : false;
   }

   private function _checkBatchCentroId($stringID) {
      return preg_match("/^batchcentroid\/[a-f,0-9]{24}$/i", $stringID) ? true : false;
   }

   public function get_fields($resource) {
      /*
         Retrieve fields used by a resource.
         Returns a dictionary with the fields that uses
           the resource keyed by Id.
      */
      $resource_id = null;

      if ($resource instanceof STDClass && property_exists($resource, "resource")) {
         $resource_id = $resource->resource;
         } else {
         error_log("Wrong resource object");
         return null;
      }

      return self::_get_fields_key($resource, $resource_id);

   }

   private function _get_fields_key($resource, $resource_id) {
      /*
         Returns the fields key from a resource dict
      */
      if (in_array($resource->code, array(BigMLRequest::HTTP_OK, BigMLRequest::HTTP_ACCEPTED)) )  {

         if (self::_checkModelId($resource_id)) {
            return $resource->object->model->model_fields;   
         } else {
            return $resource->object->fields;
         }
      }
      return null;
   }

   public function retrieve_resource($resource_id, $query_string=null)
   {
      /*
         Retrieves resource info either from a local repo or from the remote server
      */
      if (self::$storage != null) {
         $stored_resource = self::$storage . DIRECTORY_SEPARATOR . str_replace('/','_',$resource_id);

         if (file_exists($stored_resource)) {
            $resource = json_decode(file_get_contents($stored_resource));

            if (property_exists($resource, "object") && property_exists($resource->object, "status") && $resource->object->status->code != BigMLRequest::FINISHED ) {
               # get resource again
               try {
                  $rest = new BigMLRequest('GET', $resource->resource);
                  if ($query_string!=null) {
                     $rest->setQueryString($query_string);
                  }
                  return $rest->getResponse();
               } catch  (Exception $e) {
                  error_log("The resource isn't finished yet");
                  return $resource;
               }
            } else {
               return $resource;
            }

          }
      }

      $resource = self::_check_resource($resource_id, $query_string);

      if ($resource == null) {
         error_log("Wrong resource id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $rest = new BigMLRequest('GET', $resource["id"]);

      if ($query_string!=null) {
         $rest->setQueryString($query_string);
      }

      return $rest->getResponse();
   }

   public function pprint($resource, $out=STDOUT) 
   {
      /*
         Pretty prints a resource or part of it.
      */
      if ($resource instanceof STDClass && property_exists($resource, "resource") && property_exists($resource, "object")) {

         $resource_id = $resource->resource;
         if (preg_match('/(source|dataset|model|evaluation|ensemble|cluster)(\/)([a-f,0-9]{24}|[a-f,0-9]{27})$/i', $resource_id, $result)) {
            fwrite($out, $resource->object->name . "(" . $resource->object->size  . " bytes)\n");
            fflush($out);
         } elseif (self::_checkPredictionId($resource_id)) {

            $objective_field_name = $resource->object->fields->{$resource->object->objective_fields[0]}->name;
            $input_data=array();

            foreach($resource->object->input_data as $key => $value) {
               $name = (property_exists($resource->object->fields, $key) && property_exists($resource->object->fields->{$key}, "name")) ? $resource->object->fields->{$key}->name : $key;
               $input_data[$name] = $value;
            }

            $prediction = $resource->object->prediction->{$resource->object->objective_fields[0]};
            $str = $objective_field_name . " for " . json_encode($input_data) . " is " . $prediction . "\n";
            fwrite($out, $str); 
            fflush($out);
         } 
         
      } else {
         fwrite($out, print_r($resource));
      } 
      
   }

   public function status($resourceId) {

      $resource = self::_check_resource($resourceId);

      if ($resource == null) {
         error_log("Wrong resource id");
         return null;
      }
   
      return array('message' => $resource['message'], 'code'=>$resource['code']);   

   }

   private static function check_resource_type($resourceId, $resourceType) {
     
      $resource = null;
      if ($resourceId instanceof STDClass && property_exists($resourceID, "resource")) {
         $resource = $resourceId->resource;
      } else if (is_string($resourceId)) {
         $resource = $resourceId;
      } else {
         error_log("Wrong ". $resourceType . " id");
         return null;
      }
 
      if (preg_match('/('.$resourceType. ')(\/)([a-z,0-9]{24}|[a-z,0-9]{27})$/i', $resource, $result)) {
         return $resource; 
      } else {
         error_log("Wrong ". $resourceType . " id");
         return null;
      }
   }

   private static function get_resource_request($resourceId, $resourceType, $operation, $queryString=null, $checkStatus=false, $waitTime=3000, $retries=0, $shared_username=null, $shared_api_key=null) {

     $resource=self::check_resource_type($resourceId,  $resourceType);

     if ($resource == null) {
        error_log("Wrong ". $resourceType . " id");
        return null;
     }

     if ($checkStatus) {
        $resource = self::_check_resource($resourceId, $queryString, $waitTime, $retries);
        if ($resource["status"] != BigMLRequest::FINISHED) {
           error_log($resource['message']);
           return null;
        }
        $resource = $resource["id"];
     }

     /*if ($resource == null || $resource['type'] != $resourceType) {
        error_log("Wrong ". $resourceType . " id");
        return null;
     }

     if ($checkStatus && $resource["status"] != BigMLRequest::FINISHED) {
        error_log($resource['message']);
        return null;
     }*/

     $rest = new BigMLRequest($operation, $resource, $shared_username, $shared_api_key);

     if ($queryString!=null) {
        $rest->setQueryString($queryString);
     }

     return $rest;
   }

 }

 final class BigMLRequest {

   # HTTP Status Codes from https://bigml.com/developers/status_codes
   const HTTP_OK = 200;
   const HTTP_CREATED = 201;
   const HTTP_ACCEPTED = 202;
   const HTTP_NO_CONTENT = 204;
   const HTTP_BAD_REQUEST = 400;
   const HTTP_UNAUTHORIZED = 401;
   const HTTP_PAYMENT_REQUIRED = 402;
   const HTTP_FORBIDDEN = 403;
   const HTTP_NOT_FOUND = 404;
   const HTTP_METHOD_NOT_ALLOWED = 405;
   const HTTP_TOO_MANY_REQUESTS = 429;
   const HTTP_LENGTH_REQUIRED = 411;
   const HTTP_INTERNAL_SERVER_ERROR = 500;

   # Resource status codes
   const WAITING = 0;
   const QUEUED = 1;
   const STARTED = 2;
   const IN_PROGRESS = 3;
   const SUMMARIZED = 4;
   const FINISHED = 5;
   const UPLOADING = 6;
   const FAULTY = -1;
   const UNKNOWN = -2;
   const RUNNABLE = -3;

   public $status_codes = array("0"=> "WAITING", "1"=> "QUEUED",  "2" => "STARTED", "3" => "IN_PROGRESS", "4" => "SUMMARIZED", "5" => "FINISHED", "6" => "UPLOADING", "-1" => "FAULTY", "-2" => "UNKNOWN", "-3" => "RUNNABLE");

   private $endpoint;
   private $uri;
   private $method;
   private $version;
   private $headers = array(
      'Host' => '', 'Date' => '',  'Content-Type' => ''
   );

   public  $response;
   private $parameters = array();
   private $data = array(); 
   private $queryString;

   private $response_code;

   function __construct($method='GET', $uri = 'source', $shared_username=null, $shared_api_key=null) {

      $this->endpoint=(BigML::getDomain() != null) ? BigML::getDomain() : BigML::BIGML_ENDPOINT;
      $this->endpoint.=(BigML::isDevMode() == true) ? '/dev' : '' ; 
      $this->method = $method;
      $this->version = BigML::getVersion();
      $this->uri = $uri;
 
      #$this->uri = $uri !== '' ? '/'.str_replace('%2F', '/', rawurlencode($uri)) : '/';

      $this->headers['Host'] = str_replace('https://','', $endpoint);
      $this->headers['Date'] = gmdate('D, d M Y H:i:s T');
      #$this->headers['Content-Type'] = 'application/json';
      #$this->resource = $this->uri;

      if ($shared_username != null && $shared_api_key != null) {
         $this->setParameter("username", $shared_username);
		 $this->setParameter("api_key", $shared_api_key);
	  } else {
        if (BigML::hasAuth()) {
           $this->setParameter("username", BigML::getUsername());
           $this->setParameter("api_key", BigML::getApiKey());
        } else {
           error_log("Cannot find BIGML AUTH");
        }

      }
      # set download uri
      $this->uri .= ($method == "DOWNLOAD" ? '/download' : '');

      # set Response
      $this->setResponse();
   }

   public function setQueryString($qs) {
      $this->queryString = $qs;
   }

   public function setParameter($key, $value)
   {
      $this->parameters[$key] = $value;
   }

   public function setHeader($key, $value)
   {   
      $this->headers[$key] = $value;
   }

   public function setData($data) 
   {   
      $this->data = $data;
   }

   public function download() {
      // Set Parameters
      if (sizeof($this->parameters) > 0)
      {
         $query = substr($this->uri, -1) !== '?' ? '?' : '&';

         foreach ($this->parameters as $var => $value) {
             if ($value == null || $value == '') { 
		$query .= $var.'&';
      		} else {
		$query .= $var.'='.rawurlencode($value).'&';
	}	
}
         $query = substr($query, 0, -1);
         $this->uri .= $query;
      }

      if ($this->queryString != null) {
         $this->uri .= (substr($this->queryString, 0, 1) !== "&" ) ? '&': '';
         $this->uri .= $this->queryString;
      }   

      // Set Url
      $url = $this->endpoint.'/'.$this->version.'/'.$this->uri;

      $data = file_get_contents($url);
      return $data;
   }

   public function getResponse() 
   {
      // Set Parameters
      if (sizeof($this->parameters) > 0)
      {
         $query = substr($this->uri, -1) !== '?' ? '?' : '&';
         foreach ($this->parameters as $var => $value) {
            if ($value == null || $value == '') { 
      $query .= $var.'&';
   } else {
      $query .= $var.'='.rawurlencode($value).'&';
   }
} 
         $query = substr($query, 0, -1);
         $this->uri .= $query;
      }

      if ($this->queryString != null) {
         $this->uri .= (substr($this->queryString, 0, 1) !== "&" ) ? '&': '';
         $this->uri .= $this->queryString;
      }

      // Set Url
      $url = $this->endpoint.'/'.$this->version.'/'.$this->uri;

      try {
	 if (BigML::getDebug() != null && BigML::getDebug() == true)
             echo "URL: " . $url . "\n";

         $curl = curl_init();
         curl_setopt($curl, CURLOPT_URL, $url);

         curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
         curl_setopt($curl, CURLOPT_HEADER, true);

         if ($this->method == "CREATE") {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $this->data);
         } elseif ($this->method == "UPDATE") {
	    #curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($curl, CURLOPT_POSTFIELDS, $this->data);
         } elseif ($this->method == "DELETE") {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
         } elseif ($this->method == "DOWNLOAD") { 
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($curl, CURLOPT_BINARYTRANSFER, true );
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10 );
            curl_setopt($curl, CURLOPT_FILE, $this->data);

         }
         // Set Headers
         $headers = array();
         foreach ($this->headers as $header => $value)
             if (strlen($value) > 0) $headers[] = $header.': '.$value;

         curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

         $response = curl_exec($curl);
         $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

         if ($code == $this->response_code) {

            $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $header = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->parseJsonResponse($body, $code, $header);

         } else if (in_array(intval($code), array(BigMLRequest::HTTP_BAD_REQUEST, BigMLRequest::HTTP_UNAUTHORIZED, BigMLRequest::HTTP_NOT_FOUND, BigMLRequest::HTTP_TOO_MANY_REQUESTS))) {
            $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $header = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            #$this->response->error = json_decode($response, true);
			$this->response["code"] = $code; 
            $error_message = $this->error_message(json_decode($body), $this->method);
            $this->response["error"]["status"]["message"] = $error_message["message"];
            $this->response["error"]["status"]["code"] = $code;

            if ($error_message["code"] != null)
               $this->response["error"]["status"]["code"] = $error_message["code"];

         } else {
            error_log("Unexpected error ". $code);
            $this->response["code"] = $HTTP_INTERNAL_SERVER_ERROR;
         }

         curl_close($curl);

      } catch (Exception $e) {
         error_log("Unexpected exception error"); 
      }

      return json_decode(json_encode($this->response));
   }

   private function parseJsonResponse($response, $code, $headers) {
      $r =json_decode($response,true);

      if ($this->method ==  "LIST") { 
         $this->response["meta"] = $r["meta"];
         $this->response["resources"] = $r["objects"];
         $this->response["error"] = null;
         $this->response["code"] = $code;
      } else if ($this->method ==  "DELETE") {
         $this->response["code"] = $code;
      } else {

         $headers = explode("\n", $headers);
         foreach($headers as $header) {
             if (stripos($header, 'Location:') !== false) {
               $cad = explode("Location:", $header);
               $this->response["location"] =trim($cad[1]);
               break;
             }
         }

         $this->response["code"] = $code;
         $this->response["resource"] = $r["resource"];
         $this->response["error"] = null;
         $this->response["object"] = $r;

         maybe_save($this->response, BigML::getStorage(), $code, $url);
      }
   }

   private function setResponse() {

      if ($this->method ==  "LIST") {
         $this->response = array("code"=> BigMLRequest::HTTP_INTERNAL_SERVER_ERROR, 
                                 "meta"=>null,
                                 "resources" => null, 
                                 "error"=>array("status"=> array("code" => BigMLRequest::HTTP_INTERNAL_SERVER_ERROR ,
                                                                 "message"=> "The resource couldn't be listed")));

         $this->response_code = BigMLRequest::HTTP_OK;

      } elseif ( in_array($this->method, array("CREATE", "GET", "UPDATE")) ) {

         $this->response = array(); 
         $this->response["code"] = BigMLRequest::HTTP_INTERNAL_SERVER_ERROR;
         $this->response["resource"] = null;
         $this->response["location"]= null; 
         $this->response["object"] = null;
         $this->response["error"] = array();;
         $this->response["error"]["status"] = array(); 
         $this->response["error"]["status"]["code"] = BigMLRequest::HTTP_INTERNAL_SERVER_ERROR;
         $this->response["error"]["status"]["message"] = "The resource couldn't be " . $this->method == "CREATE" ? "created" : $this->method == "UPDATE" ? "updated": "retrieved";

         if ($this->method == "CREATE") {
            $this->response_code = BigMLRequest::HTTP_CREATED;
         } elseif ($this->method == "UPDATE") {
            $this->response_code = BigMLRequest::HTTP_ACCEPTED;
         } else {
            $this->response_code = BigMLRequest::HTTP_OK;
         }

         #$this->response_code = $this->method == "CREATE" ? BigMLRequest::HTTP_CREATED : $this->method == "UPDATE" ? BigMLRequest::HTTP_ACCEPTED : BigMLRequest::HTTP_OK;
      } elseif ($this->method == "DELETE") {
         $this->response = array("code"=> BigMLRequest::HTTP_INTERNAL_SERVER_ERROR, "error"=> null);
         $this->response_code = BigMLRequest::HTTP_NO_CONTENT;
      } 
   }

   private function error_message($resource, $method, $resource_type='resource') {
      /*
         Error message for each type of resource
      */
      $error = null;
      $error_info = null;
      $error_response = array("message"=> null, "code"=>null);

      if ($resource instanceof STDClass) {
         if (property_exists($resource, "error")) {
            $error_info = $resource->error;
         } elseif (property_exists($resource, "code") && property_exists($resource, "status")) {
            $error_info = $resource;
         }
      } 


      if ($error_info != null && property_exists($error_info, "code") )  {
         $code = $error_info->code;
         $error_response["code"] = $code;

         if (property_exists($error_info, "status") && property_exists($error_info->status, "message") ) {
            $error = $error_info->status->message;
            $extra = null;
            if (property_exists($error_info->status, "extra")) {
               $extra = $error_info->status->extra;
            }
            if ($extra != null) {
               if ($extra instanceof STDClass) {
                  $error = $error . ":"; 
                  $error_response["code"] = $extra->error;
                  foreach(get_object_vars($extra) as $key => $value) {
                      $error = $error . $key . ": " . json_encode($value) . " ";
                  }
                  $error = $error . "\n"; 
               } else {
                 $error = $error . ": " . $extra;
               }
            }
         }

         if ($code == BigMLRequest::HTTP_NOT_FOUND && strtolower($method) == 'get') {
            $alternate_message = '';
            if (BigML::getDomain() != null && BigML::getDomain()!= BigML::BIGML_ENDPOINT) {
               $alternate_message = "The " . $resource_type . " was not created in " . BigML::getDomain() . "\n"; 
            }   

            $error = $error . "\nCouldn\'t find a " . $resource_type . " matching the given id. The most probable causes are:\n\n" . $alternate_message . " A typo in the " . $resource_type . "'s id.\n The " . $resource_type . " id cannot be accessed with your credentials.\n \nDouble-check your " . $resource_type . " and credentials info and retry."; 

         } elseif ($code == BigMLRequest::HTTP_UNAUTHORIZED) {
            $error = $error. '\nDouble-check your credentials, please.';
         } elseif ($code == BigMLRequest::HTTP_BAD_REQUEST) { 
             $error = $error.'\nDouble-check the arguments for the call, please.';
         } elseif ($code == BigMLRequest::HTTP_TOO_MANY_REQUESTS) {
            $error = $error.'\nToo many requests. Please stop requests for a while before resuming.';   
         } elseif ($code == BigMLRequest::HTTP_PAYMENT_REQUIRED) {
            $error = $error.'\nYou\'ll need to buy some more credits to perform the chosen action';
         }

		 $error_response["message"] = $error;
         return $error_response;
      }

      $error_response["message"] = "Invalid" . $resource_type . "structure:\n\n" . $resource;
      return $error_response;

   }

}

function assign_dir($path) {
   /*
      Silently checks the path for existence or creates it.
       Returns either the path or Null.
   */
   if ($path == null || !is_string($path)) {
      return null;
   }
      
   return check_dir($path);
}

function check_dir($path) {
   /*
      Creates a directory if it doesn't exist
   */
   if (file_exists($path)){
      if (!is_dir($path)) {
         throw new Exception("The given path is not a directory");
      }
   } elseif (count($path) > 0) {
      if(!mkdir($path, 0777, true)) {
         throw new Exception("Cannot create a directory");
      }   
   }
   return $path;
}

function maybe_save($resource, $path, $code, $location)
{
   /*
      Builds the resource dict response and saves it if a path is provided.
      The resource is saved in a local repo json file in the given path
   */
   if ($path != null &&  $resource["resource"] != null) {
      #json_encode($resource);
      $resource_file_name = $path . DIRECTORY_SEPARATOR . str_replace('/','_',$resource["resource"]);

      $fp = fopen($resource_file_name, 'w');
      fwrite($fp, json_encode($resource));
      fclose($fp);

     }
}

function compareFiles($file_a, $file_b)
{
   if (filesize($file_a) == filesize($file_b) && 
       md5_file($file_a) == md5_file($file_b) ) {
      return true;
   }
   return false;
}

?>
