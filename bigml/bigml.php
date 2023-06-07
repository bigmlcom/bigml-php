<?php
#
# Copyright 2012-2021 BigML
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

namespace BigML;

require_once("utils.php");

if (class_exists('BigML\BigML')) {
   return;
}

class BigML {

   const BIGML_ENDPOINT = "https://bigml.io";
   const ONLY_MODEL = "only_model=true;limit=-1;";
   /**
    * The BigML Access Username
    *
    * @var string
    * @access private
    */
   private $__username = null;
   /**
    * The BigML Access ApiKey
    *
    * @var string
    * @access private
    */
   private $__apiKey = null;

   /**
    * The BigML Virual Private domain
    *
    * @var string
    * @access private
    */
   private $domain = false;

   /**
    * More diagnostics
    *
    * @var boolean
    * @access private
    */
   private $debug = false;

   /**
    * The BigML API version
    *
    * @var string
    * @access private
    */
   private $version = "andromeda";

   /**
    * Local storage used for caching resources
    *
    * @var string
    * @access private
    */
   private $storage;

   /**
    * If you need to access an organization's project for which you
    * have been given privileges, set it here.
    * Otherwise, you can restrict this BigML instance to only
    * operate on one of your projects by specifying it here.
    *
    * @var string
    * @access private
    */
   private $project;

   /**
    * If an organization gave you access to their resources, set it here.
    *
    * @var string
    * @access private
    */
   private $org;

   /*
    * @param string $username
    * @param string $apiKey
    * @param boolean $devMode
    * @param string $domain
    * @param string $locale
    * @param string $version
    * @param string $project
    * @param string $org
    * @param string $debug
    * @return void
    */
   public function __construct($username = null,
                               $apiKey = null,
                               $devMode = false, /* deprecated */
                               $storage = null,
                               $domain = null,
                               $locale = null,
                               $version = "andromeda",
                               $project = null,
                               $org = null,
                               $debug = false
                               ) {

      if (is_array($username)) {
         $options = $username;
         if (array_key_exists("username", $options))
            $username = $options["username"];
         else
            $username = null;
         if (array_key_exists("apiKey", $options))
            $apiKey = $options["apiKey"];
         if (array_key_exists("storage", $options))
            $storage = $options["storage"];
         if (array_key_exists("domain", $options))
            $domain = $options["domain"];
         if (array_key_exists("locale", $options))
            $locale = $options["locale"];
         if (array_key_exists("version", $options))
            $version = $options["version"];
         if (array_key_exists("project", $options))
            $project = $options["project"];
         if (array_key_exists("organization", $options))
            $org = $options["organization"];
         if (array_key_exists("debug", $options))
            $debug = $options["debug"];
      }

      if ($username == null) {
         $username = getenv("BIGML_USERNAME");
      }

      if ($apiKey == null) {
         $apiKey = getenv("BIGML_API_KEY");
      }

      if ($username !== null && $apiKey !== null)
         $this->setAuth($username, $apiKey);

      $this->domain = $domain;
      $this->version= $version;
      $this->debug = $debug;

      if ($locale != null) {
         setlocale(LC_ALL, $$locale);
      }

      $this->storage = assign_dir($storage);

/*      if ($project == null) {
         $project = getenv("BIGML_PROJECT");
      }
*/      $this->project = $project;

/*      if ($org == null) {
         $org = getenv("BIGML_ORGANIZATION");
      }
*/      $this->org = $org;
   }

   /**
    * Set BigML username and key
    *
    * @param string $username Username
    * @param string $apiKey Api key
    * @return void
    */
   public function setAuth($username, $apiKey) {
      $this->__username = $username;
      $this->__apiKey = $apiKey;
   }

   public function setDebug($debug=false) {
      $this->debug = $debug;
   }

   public function setDevMode($devMode=false) {
      trigger_error('Deprecated BigML::setDevMode.',
                    E_USER_NOTICE);
   }

   /**
    * Check if BigML keys have been set
    *
    * @return boolean
    */
   public function hasAuth() {
      return ($this->__username !== null && $this->__apiKey !== null);
   }

   public function isDevMode() {
      trigger_error('Deprecated BigML::isDevMode.',
                    E_USER_NOTICE);
      return false;
   }

   public function getUsername() {
      return $this->__username;
   }

   public function getApiKey() {
      return $this->__apiKey;
   }

   public function getDomain() {
      return $this->domain;
   }

   public function getVersion() {
      return $this->version;
   }

   public function getStorage() {
      return $this->storage;
   }

   public function getProject() {
      return $this->project;
   }

   public function getOrganization() {
      return $this->org;
   }

   public function getDebug() {
      return $this->debug;
   }
   ##########################################################################
   #
   # Sources
   # https://bigml.com/developers/sources
   #
   ##########################################################################

   public function create_source($data, $options=array())
   {
      /*
        Creates a new source
        The source can be a local file path or a URL o stream source

      */
      if (filter_var($data, FILTER_VALIDATE_URL)) {
         return $this->_create_remote_source($data, $options);
      } elseif (is_file($data)) {
         return $this->_create_local_source($data, $options);
      } else {
         error_log("Wrong source file or url");
         return null;
      }
   }

   public function get_source($sourceId, $queryString=null)
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

      $rest = $this->get_resource_request($sourceId, "source", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function list_sources($queryString=null)
   {
      /*
        Lists all your remote sources.
      */
      $rest = new BigMLRequest('LIST', 'source', $this);

      if ($queryString!=null){
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_source($sourceId, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a source
      */
      $rest = $this->get_resource_request($sourceId,
                                         "source",
                                         "UPDATE",
                                         null,
                                         true,
                                         $waitTime,
                                         $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));

      return $rest->getResponse();
   }

   public function delete_source($sourceId) {
      /*
        Deletes a source
      */
      $rest = $this->get_resource_request($sourceId, "source", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   private function _check_resource_status($resource, $queryString=null)
   {
      $rest = new BigMLRequest('GET', $resource, $this);
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

      $code = $item->code;
      if (substr( $resource, 0, 7 ) == 'project') {
         $status = "";
         $statusCode = "";
         $message = "";
      } else {
         $status = $obj->status;
         $statusCode = $status->code;
         $message = $status->message;
      }
      $ready = $code == BigMLRequest::HTTP_OK &&
             $statusCode == BigMLRequest::FINISHED;

      return array('ready' => $ready,
                   'code' => $statusCode,
                   'message' => $message,
                   'resource' => $item);

   }

   public function _check_resource($resource,
                                   $queryString = null,
                                   $waitTime = 3000,
                                   $retries = 0)
   {
      $r = $resource;
      if (is_string($resource))
         $r = json_decode($resource);

      if ($r instanceof \STDClass && property_exists($r, "resource")) {
         $resource = $r->resource;
      }

      if (preg_match('/(source|dataset|model|evaluation|ensemble|batchprediction|batchcentroid|prediction|cluster|centroid|anomaly|anomalyscore|sample|project|correlation|statisticaltest|association|logisticregression|library|execution|script|topicmodel|deepnet)(\/)([a-z,0-9]{24}|[a-z,0-9]{27})$/i', $resource, $result)) {
         $count = 0;
         $status = $this->_check_resource_status($resource, $queryString);
         while ($count<$retries && !$status["ready"]) {
            $waitTime = 1.1 * $waitTime;
            usleep($waitTime*1000);
            $count++;
            $status = $this->_check_resource_status($resource, $queryString);
         }

         return array('type'=> strtolower($result[1]),
                      'id'=> $resource,
                      'status'=> $status["code"],
                      'message' => $status["message"],
                      'resource' => $status["resource"]);
      }

      return null;
   }

   ##########################################################################
   #
   # Datasets
   # https://bigml.com/developers/datasets
   #
   ##########################################################################

   public function create_dataset($origin_resource, $args=array(), $waitTime=3000, $retries=10) {
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
            $resource = $this->_check_resource($datasetId, null, $waitTime, $retries);
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
         $resource = $this->_check_resource($origin_resource, null, $waitTime, $retries);

         if ($resource == null || !in_array($resource['type'],array("dataset","source","cluster")) ) {
            error_log("Wrong dataset. A source, dataset, list of dataset ids or cluster id plus centroid id are needed to create a dataset");
            return null;
         } elseif ($resource["status"] != BigMLRequest::FINISHED) {
            error_log($resource['message']);
            return null;
         }

         if ($resource['type'] == "cluster") {
            if (!array_key_exists("centroid",$args)) {

               $cluster = $this->get_cluster($resource["id"]);

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

      $rest = new BigMLRequest('CREATE', 'dataset', $this);
      $rest->setData($args);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));
      return $rest->getResponse();
   }

   public function get_dataset($datasetId, $queryString=null)
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

      $rest = $this->get_resource_request($datasetId, "dataset", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function list_datasets($queryString=null)
   {
      /*
        Lists all your datasets.
      */
      $rest = new BigMLRequest('LIST', 'dataset', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_dataset($datasetId, $data, $waitTime=3000, $retries=10) {
      /*
        Update a dataset
      */
      $rest = $this->get_resource_request($datasetId, "dataset", "UPDATE", null, true, $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_dataset($datasetId) {
      /*
        Delete a dataset
      */
      $rest = $this->get_resource_request($datasetId, "dataset", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function download_dataset($datasetId, $filename=null){
      /*
        Downloads dataset contents to a csv file or file object
      */

      $rest = $this->get_resource_request($datasetId, "dataset", "DOWNLOAD");
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

   public function error_counts($dataset, $raise_on_error=true, $waitTime=3000, $retries=10) {

      if (!is_string($dataset)) {
         if ($dataset instanceof \STDClass && property_exists($dataset, "resource")) {
            $dataset = $dataset->resource;
         } else {
            error_log("Wrong dataset id");
            return null;
         }
      }

      $resource = $this->_check_resource($dataset, null, $waitTime, $retries);

      if ($resource == null || $resource['type'] != "dataset") {
         error_log("Wrong dataset id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $errors_dict = array();
      $errors = array();

      try {
         $errors = $resource["resource"]->object->status->field_errors;
      } catch  (\Exception $e) {
      }

      foreach ($errors as $field_id => $value) {
         $errors_dict[$field_id] = $value->total;
      }

      return $errors_dict;

   }
   ##########################################################################
   #
   # Models
   # https://bigml.com/developers/logisticregressions
   #
   ##########################################################################

   public function create_logistic_regression($datasetIds, $data=array(), $waitTime=3000, $retries=10) {
      /* Creates a logistic regression from a `dataset`
         of a list o `datasets` */

      $datasets=array();
      if (!is_array($datasetIds)) {
         $datasetIds=array($datasetIds);
      }

      foreach ($datasetIds as $var => $datasetId) {
         $resource = $this->_check_resource($datasetId, null, $waitTime, $retries);
         if ($resource == null || $resource['type'] != "dataset") {
            error_log("Wrong dataset id");
            return null;
         } elseif ($resource["status"] != BigMLRequest::FINISHED) {
            error_log($resource['message']);
            return null;
         }
         array_push($datasets, $resource["id"]);
      }

      $rest = new BigMLRequest('CREATE', 'logisticregression', $this);

      if (sizeof($datasets) > 1) {
         $data["datasets"] = $datasets;
      } else {
         $data["dataset"] = $datasets[0];
      }

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function get_logistic_regression($logisticregression, $queryString=null, $shared_username=null,  $shared_api_key=null)
   {
      /*
        Retrieves a logisticregression.
        The logisticregression parameter should be a string containing the
        logisticregression id or the dict returned by create_logistic_regression.
        As model is an evolving object that is processed
        until it reaches the FINISHED or FAULTY state, the function will
        return a dict that encloses the model values and state info
        available at the time it is called.
        If this is a shared logistic regression, the username and sharing api key must
        also be provided.
      */
      $rest = $this->get_resource_request($logisticregression, "logisticregression", "GET", $queryString, true, 3000, 0, $shared_username, $shared_api_key);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function list_logistic_regressions($queryString=null)
   {
      /*
        Lists all your logistic regressions
      */
      $rest = new BigMLRequest('LIST', 'logisticregression', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_logistic_regression($logisticregressionId, $data, $waitTime=3000, $retries=10) {

      /*
        Updates a logistic regression
      */
      $rest = $this->get_resource_request($logisticregressionId, "logisticregression", "UPDATE", null, true, $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_logistic_regression($logisticregressionId) {
      /*
        Deletes a logistic regression
      */
      $rest = $this->get_resource_request($logisticregressionId, "logisticregression", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Models
   # https://bigml.com/developers/models
   #
   ##########################################################################

   public function create_model($datasetIds, $data=array(), $waitTime=3000, $retries=10) {
      /*
        Creates a model from a `dataset` or a list o `datasets`.
      */

      $datasets=array();
      if (!is_array($datasetIds)) {
         $datasetIds=array($datasetIds);
      }

      foreach ($datasetIds as $var => $datasetId) {
         $resource = $this->_check_resource($datasetId, null, $waitTime, $retries);
         if ($resource == null || !in_array($resource['type'], array("dataset","cluster"))) {
            error_log("Wrong dataset id");
            return null;
         } elseif ($resource["status"] != BigMLRequest::FINISHED) {
            error_log($resource['message']);
            return null;
         }
         array_push($datasets, $resource["id"]);
      }

      $rest = new BigMLRequest('CREATE', 'model', $this);

      if (sizeof($datasets) > 1) {
         $data["datasets"] = $datasets;
      } else {

         if ($resource['type'] == "cluster") {
            if (!array_key_exists("centroid",$data)) {
               $cluster = $this->get_cluster($datasets[0]);
               if ( $cluster == null || !property_exists($cluster, "object") || !property_exists($cluster->object, "cluster_datasets") ) {
                  error_log("Failed to generate the dataset. A centroid id is needed in the args argument to generate a dataset from a cluster.");
                  return null;
               }

               $data['centroid'] = key(get_object_vars($cluster->object->cluster_models));
               $data["cluster"] =  $datasets[0];
            }
         } else {
            $data["dataset"] = $datasets[0];
         }
      }

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function get_model($modelId, $queryString=null, $shared_username=null,  $shared_api_key=null)
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
      $rest = $this->get_resource_request($modelId, "model", "GET", $queryString, true, 3000, 0, $shared_username, $shared_api_key);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function list_models($queryString=null)
   {
      /*
        Lists all your models
      */
      $rest = new BigMLRequest('LIST', 'model', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_model($modelId, $data, $waitTime=3000, $retries=10) {

      /*
        Updates a model
      */
      $rest = $this->get_resource_request($modelId, "model", "UPDATE", null, true, $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_model($modelId) {
      /*
        Deletes a model
      */
      $rest = $this->get_resource_request($modelId, "model", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Ensembles
   # https://bigml.com/developers/ensembles
   #
   ##########################################################################

   public function create_ensemble($datasetIds, $data=array(), $waitTime=3000, $retries=10) {

      /*
        Creates a ensemble from a `dataset` or a list o `datasets`.
      */

      $datasets= array();

      if (!is_array($datasetIds)) {
         $datasetIds=array($datasetIds);
      }

      foreach ($datasetIds as $var => $datasetId) {
         $resource = $this->_check_resource($datasetId, null, $waitTime, $retries);
         if ($resource == null || $resource['type'] != "dataset") {
            error_log("Wrong dataset id");
            return null;
         } elseif ($resource["status"] != BigMLRequest::FINISHED) {
            error_log($resource['message']);
            return null;
         }
         array_push($datasets, $resource["id"]);
      }

      $rest = new BigMLRequest('CREATE', 'ensemble', $this);

      if (sizeof($datasets) > 1) {
         $data["datasets"] = $datasets;
      } else {
         $data["dataset"] = $datasets[0];
      }

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function get_ensemble($ensembleId, $queryString=null)
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
      $rest = $this->get_resource_request($ensembleId, "ensemble", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();

   }

   public function list_ensembles($queryString=null)
   {
      /*
        Lists all your ensembles
      */
      $rest = new BigMLRequest('LIST', 'ensemble', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_ensemble($ensembleId, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a ensemble
      */
      $rest = $this->get_resource_request($ensembleId, "ensemble", "UPDATE", null, true, 3000, 10);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_ensemble($ensembleId) {
      /*
        Deletes a ensemble
      */
      $rest = $this->get_resource_request($ensembleId, "ensemble", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Predictions
   # https://bigml.com/developers/predictions
   #
   ##########################################################################
   public function create_prediction($modelOrEnsembleId, $inputData, $args=array(), $waitTime=3000, $retries=10) {
      /*
        Creates a new prediction.
        The model parameter can be:
        - a simple model
        - an ensemble
      */

      $resource = $this->_check_resource($modelOrEnsembleId, null, $waitTime, $retries);

      if ($resource == null || !in_array($resource['type'],array("model","ensemble", "logisticregression","deepnet")) ) {
         error_log("Wrong model, ensemble or logistic regression id. A model, ensemble or logistic regression id is needed to create a prediction");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $args = $args == null? array() : $args;
      $args["input_data"] = new \StdClass();

      if ($inputData != null) {
         $args["input_data"] = $inputData;
      }

      $args[$resource['type']] = $resource['id'];

      $rest = new BigMLRequest('CREATE', 'prediction', $this);
      $rest->setData($args);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));

      return $rest->getResponse();
   }

   public function get_prediction($predictionId, $queryString=null)
   {
      /*
        Retrieves a prediction.
      */
      $rest = $this->get_resource_request($predictionId, "prediction", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function list_predictions($queryString=null)
   {
      /*
        Lists all your predictions
      */
      $rest = new BigMLRequest('LIST', 'prediction', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_prediction($predictionId, $data, $waitTime=3000, $retries=10)
   {
      /*
        Updates a  prediction
      */
      $rest = $this->get_resource_request($predictionId, "prediction", "UPDATE", null, true, $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_prediction($predictionId) {
      /*
        Deletes a  prediction
      */
      $rest = $this->get_resource_request($predictionId, "prediction", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }
   ##########################################################################
   #
   # Batch Predictions
   # https://bigml.com/developers/batch_predictions
   #
   ##########################################################################

   public function create_batch_prediction($modelOrEnsembleId, $datasetId, $args=array(), $waitTime=3000, $retries=10) {

      /*
        Creates a new prediction.
        The model parameter can be:
        - a simple model
        - an ensemble
      */

      $args = $args == null? array() : $args;

      $resource = $this->_check_resource($modelOrEnsembleId, null, $waitTime, $retries);

      if ($resource == null || !in_array($resource['type'],array("model","ensemble", "logisticregression")) ) {
         error_log("Wrong model , ensemble or logistic regression id. A model, ensemble or logistic regression id is needed to create a batch prediction");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $args[$resource['type']] = $resource['id'];

      $resource = $this->_check_resource($datasetId, null, $waitTime, $retries);
      if ($resource == null || $resource['type'] != "dataset" ) {
         error_log("Wrong dataset id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $args["dataset"] = $resource['id'];

      $rest = new BigMLRequest('CREATE', 'batchprediction', $this);

      $rest->setData($args);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));
      return $rest->getResponse();
   }

   public function get_batch_prediction($batchPredictionId, $queryString=null)
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
      $rest = $this->get_resource_request($batchPredictionId, "batchprediction", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function download_batch_prediction($batchPredictionId, $filename=null){
      /*
        Retrieves the batch predictions file.

        Downloads predictions, that are stored in a remote CSV file. If
        a path is given in filename, the contents of the file are downloaded
        and saved locally. A file-like object is returned otherwise.
      */

      $rest = $this->get_resource_request($batchPredictionId, "batchprediction", "DOWNLOAD");
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

   public function source_from_batch_prediction($batchPredictionId, $args=array()) {

      $rest = $this->get_resource_request($batchPredictionId, "batchprediction", "DOWNLOAD");
      if ($rest == null) return null;
      $url = $rest->download_url();
      return $this->_create_remote_source($url, $args);
   }

   public function list_batch_predictions($queryString=null)
   {
      /*
        Lists all your batch predictions
      */
      $rest = new BigMLRequest('LIST', 'batchprediction', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_batch_prediction($batchPredictionId, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a batch prediction
      */
      $rest = $this->get_resource_request($batchPredictionId, "batchprediction", "UPDATE", null, true, $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_batch_prediction($batchPredictionId) {
      /*
        Deletes a batch prediction
      */
      $rest = $this->get_resource_request($batchPredictionId, "batchprediction", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Evaluations
   # https://bigml.com/developers/evaluations
   #
   ##########################################################################
   public function create_evaluation($modelId, $datasetId, $args=array(), $waitTime=3000, $retries=10) {

      /*
        Creates a new evaluation.
      */

      $args = $args == null? array() : $args;

      $resource = $this->_check_resource($modelId, null, $waitTime, $retries);

      if ($resource == null || !in_array($resource['type'],array("model","ensemble", "logisticregression"))) {
         error_log("Wrong model, ensemble or logistic_regression id. A model,  ensemble or logistic regression id is needed to create a evaluation");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $args[$resource['type']] = $resource['id'];

      $resource = $this->_check_resource($datasetId, null, $waitTime, $retries);
      if ($resource == null || $resource['type'] != "dataset" ) {
         error_log("Wrong dataset id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $args[$resource['type']] = $resource['id'];

      $rest = new BigMLRequest('CREATE', 'evaluation', $this);

      $rest->setData($args);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));
      return $rest->getResponse();
   }

   public function get_evaluation($evaluationId, $queryString=null)
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
      $rest = $this->get_resource_request($evaluationId, "evaluation", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function list_evaluations($queryString=null)
   {
      /*
        Lists all your evaluations.
      */
      $rest = new BigMLRequest('LIST', 'evaluation', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_evaluation($evaluationId, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a evaluation
      */
      $rest = $this->get_resource_request($evaluationId, "evaluation", "UPDATE", null, true, $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_evaluation($evaluationId) {
      /*
        Deletes a evaluation
      */
      $rest = $this->get_resource_request($evaluationId, "evaluation", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }
   ##########################################################################
   #
   # Clusters
   # https://bigml.com/developers/clusters
   #
   ##########################################################################
   public function create_cluster($datasetIds, $data=array(), $waitTime=3000, $retries=10) {
      /*
        Creates a cluster from a `dataset` or a list of `datasets`
      */

      $datasets= array();

      if (!is_array($datasetIds)) {
         $datasetIds=array($datasetIds);
      }

      foreach ($datasetIds as $var => $datasetId) {
         $resource = $this->_check_resource($datasetId, null, $waitTime, $retries);
         if ($resource == null || $resource['type'] != "dataset") {
            error_log("Wrong dataset id");
            return null;
         } elseif ($resource["status"] != BigMLRequest::FINISHED) {
            error_log($resource['message']);
            return null;
         }
         array_push($datasets, $resource["id"]);
      }

      $rest = new BigMLRequest('CREATE', 'cluster', $this);

      if (sizeof($datasets) > 1) {
         $data["datasets"] = $datasets;
      } else {
         $data["dataset"] = $datasets[0];
      }

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function get_cluster($clusterId, $queryString=null, $shared_username=null, $shared_api_key=null)
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
      $rest = $this->get_resource_request($clusterId, "cluster", "GET", $queryString, true, 3000, 0, $shared_username, $shared_api_key);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function list_clusters($queryString=null)
   {
      /*
        List all your clusters
      */
      $rest = new BigMLRequest('LIST', 'cluster', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_cluster($clusterId, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a cluster
      */
      $rest = $this->get_resource_request($clusterId, "cluster", "UPDATE", null, true,  $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_cluster($clusterId) {
      /*
        Deletes a cluster
      */
      $rest = $this->get_resource_request($clusterId, "cluster", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Centroids
   # https://bigml.com/developers/centroids
   #
   ##########################################################################

   public function create_centroid($clusterId, $inputData=array(), $args=array(), $waitTime=3000, $retries=10) {
      /*
        Creates a new centroid
      */
      $args = $args == null? array() : $args;

      $resource = $this->_check_resource($clusterId);
      if ($resource == null || $resource['type'] != "cluster") {
         error_log("Wrong cluster id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $args["cluster"] = $resource["id"];
      $args["input_data"] = $inputData == null?  new \StdClass() : $inputData;

      $rest = new BigMLRequest('CREATE', 'centroid', $this);

      $rest->setData($args);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));
      return $rest->getResponse();
   }

   public function get_centroid($centroId, $queryString=null)
   {
      /*
        Retrieves a centroid.
      */
      $rest = $this->get_resource_request($centroId, "centroid", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function list_centroids($queryString=null)
   {
      /*
        List all your centroids.
      */
      $rest = new BigMLRequest('LIST', 'centroid', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_centroid($centroId, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a centroid.
      */
      $rest = $this->get_resource_request($centroId, "centroid", "UPDATE",null, true, $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_centroid($centroId) {
      /*
          Deletes a centroid.
      */
      $rest = $this->get_resource_request($centroId, "centroid", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Batch Centroids
   # https://bigml.com/developers/batch_centroids
   #
   ##########################################################################
   public function create_batch_centroid($clusterId, $datasetId, $args=array(), $waitTime=3000, $retries=10) {
      /*
        Creates a new batch centroid.
      */
      $args = $args == null? array() : $args;

      $resource = $this->_check_resource($clusterId, null, $waitTime, $retries);

      if ($resource == null || $resource['type'] != "cluster" ) {
         error_log("Wrong cluster id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $args[$resource['type']] = $resource['id'];

      $resource = $this->_check_resource($datasetId, null, $waitTime, $retries);
      if ($resource == null || $resource['type'] != "dataset" ) {
         error_log("Wrong dataset id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $args[$resource['type']] = $resource['id'];

      $rest = new BigMLRequest('CREATE', 'batchcentroid', $this);

      $rest->setData($args);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));

      return $rest->getResponse();
   }

   public function get_batchcentroid($batchcentroId, $queryString=null)
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
      $rest = $this->get_resource_request($batchcentroId, "batchcentroid", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function download_batch_centroid($batchCentroId, $filename=null) {
      /*
        Retrieves the batch centroid file.

        Downloads centroids, that are stored in a remote CSV file. If
        a path is given in filename, the contents of the file are downloaded
        and saved locally. A file-like object is returned otherwise.
      */
      $rest = $this->get_resource_request($batchCentroId, "batchcentroid", "DOWNLOAD");
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


   public function list_batch_centroids($queryString=null)
   {
      /*
        Lists all your batch centroids
      */
      $rest = new BigMLRequest('LIST', 'batchcentroid', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_batchcentroid($batchcentroId, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a batch centroid.
      */
      $rest = $this->get_resource_request($batchcentroId, "batchcentroid", "UPDATE",null, true, $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_batchcentroid($batchcentroId) {
      /*
        Deletes a batch centroid.
      */
      $rest = $this->get_resource_request($batchcentroId, "batchcentroid", "DELETE",null);
      if ($rest == null) return null;

      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Anomaly detector
   # https://bigml.com/developers/anomaly
   #
   ##########################################################################
   public function get_anomaly($anomalyId, $queryString=null)
   {
      /*
        Retrieves an anomaly detector.
        The anomaly parameter should be a string containing the
        anomaly id or the dict returned by create_anomaly.
        As the anomaly detector is an evolving object that is processed
        until it reaches the FINISHED or FAULTY state, the function will
        return a dict that encloses the model values and state info
        available at the time it is called.

        If this is a shared anomaly detector, the username and sharing api
        key must also be provided.
      */
      $rest = $this->get_resource_request($anomalyId, "anomaly", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function create_anomaly($datasetIds, $data=array(), $waitTime=3000, $retries=10) {
      /*
        Creates a anomaly from a `dataset` or a list of `datasets`
      */

      $datasets= array();

      if (!is_array($datasetIds)) {
         $datasetIds=array($datasetIds);
      }

      foreach ($datasetIds as $var => $datasetId) {
         $resource = $this->_check_resource($datasetId, null, $waitTime, $retries);
         if ($resource == null || $resource['type'] != "dataset") {
            error_log("Wrong dataset id");
            return null;
         } elseif ($resource["status"] != BigMLRequest::FINISHED) {
            error_log($resource['message']);
            return null;
         }
         array_push($datasets, $resource["id"]);
      }

      $rest = new BigMLRequest('CREATE', 'anomaly', $this);

      if (sizeof($datasets) > 1) {
         $data["datasets"] = $datasets;
      } else {
         $data["dataset"] = $datasets[0];
      }

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function list_anomalies($queryString=null)
   {
      /*
        List all your anomalies
      */
      $rest = new BigMLRequest('LIST', 'anomaly', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_anomaly($anomalyId, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a anomaly
      */
      $rest = $this->get_resource_request($anomalyId, "anomaly", "UPDATE", null, true,  $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_anomaly($anomalyId) {
      /*
        Deletes a anomaly
      */
      $rest = $this->get_resource_request($anomalyId, "anomaly", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }


   ##########################################################################
   #
   # Anomaly scores
   # https://bigml.com/developers/anomalyscore
   #
   ##########################################################################
   public function get_anomaly_score($anomalyscoreId, $queryString=null)
   {
      /*
        Retrieves an anomaly score.
      */
      $rest = $this->get_resource_request($anomalyscoreId, "anomalyscore", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function create_anomaly_score($anomalyId, $inputData=array(), $args=array(), $waitTime=3000, $retries=10) {
      /*
        Creates a new centroid
      */
      $args = $args == null? array() : $args;

      $resource = $this->_check_resource($anomalyId);
      if ($resource == null || $resource['type'] != "anomaly") {
         error_log("Wrong anomaly id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $args["anomaly"] = $resource["id"];
      $args["input_data"] = $inputData == null?  new \StdClass() : $inputData;

      $rest = new BigMLRequest('CREATE', 'anomalyscore', $this);

      $rest->setData($args);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));
      return $rest->getResponse();
   }

   public function list_anomaly_scores($queryString=null)
   {
      /*
        List all your anomalies score
      */
      $rest = new BigMLRequest('LIST', 'anomalyscore', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_anomaly_score($anomalyscoreId, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a anomaly score
      */
      $rest = $this->get_resource_request($anomalyscoreId, "anomalyscore", "UPDATE", null, true,  $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_anomaly_score($anomalyscoreId) {
      /*
        Deletes a anomaly score
      */
      $rest = $this->get_resource_request($anomalyscoreId, "anomalyscore", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Batch Anomaly Scores
   # https://bigml.com/developers/batch_anomalyscores
   #
   ##########################################################################
   public function create_batch_anomaly_score($anomalyId, $datasetId, $args=array(), $waitTime=3000, $retries=10) {
      /*
        Creates a new batch centroid.
      */
      $args = $args == null? array() : $args;

      $resource = $this->_check_resource($anomalyId, null, $waitTime, $retries);

      if ($resource == null || $resource['type'] != "anomaly" ) {
         error_log("Wrong anomaly id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $args[$resource['type']] = $resource['id'];

      $resource = $this->_check_resource($datasetId, null, $waitTime, $retries);
      if ($resource == null || $resource['type'] != "dataset" ) {
         error_log("Wrong dataset id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $args[$resource['type']] = $resource['id'];

      $rest = new BigMLRequest('CREATE', 'batchanomalyscore', $this);

      $rest->setData($args);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));

      return $rest->getResponse();
   }

   public function get_batch_anomaly_score($batch_anomaly_score_Id, $queryString=null)
   {
      /*
        Retrieves a batch anomaly score.

        The batch_anomaly_score parameter should be a string containing the
        batch_anomaly_score id or the dict returned by
        create_batch_anomaly_score.
        As batch_anomaly_score is an evolving object that is processed
        until it reaches the FINISHED or FAULTY state, the function will
        return a dict that encloses the batch_anomaly_score values and state
        info available at the time it is called.
      */
      $rest = $this->get_resource_request($batch_anomaly_score_Id, "batchanomalyscore", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function download_batch_anomaly_score($batch_anomaly_score_Id, $filename=null){
      /*
        Retrieves the batch anomaly score file.

        Downloads anomaly scores, that are stored in a remote CSV file. If
        a path is given in filename, the contents of the file are downloaded
        and saved locally. A file-like object is returned otherwise.
      */

      $rest = $this->get_resource_request($batch_anomaly_score_Id, "batchanomalyscore", "DOWNLOAD");
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

   public function list_batch_anomaly_score($queryString=null)
   {
      /*
        Lists all your batch anomaly scores.
      */
      $rest = new BigMLRequest('LIST', 'batchanomalyscore', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_batch_anomaly_score($batch_anomaly_score_Id, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a batch anomaly scores.
      */
      $rest = $this->get_resource_request($batch_anomaly_score_Id, "batchanomalyscore", "UPDATE", null, true, $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_batch_anomaly_score($batch_anomaly_score_Id) {
      /*
        Deletes a batch prediction
      */
      $rest = $this->get_resource_request($batch_anomaly_score_Id, "batchanomalyscore", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Samples
   #
   ##########################################################################
   public function create_sample($datasetId, $args=array(), $waitTime=3000, $retries=10) {
      /*
        Creates a new sample.
      */

      $args = $args == null? array() : $args;

      $resource = $this->_check_resource($datasetId, null, $waitTime, $retries);
      if ($resource == null || $resource['type'] != "dataset" ) {
         error_log("Wrong dataset id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $args[$resource['type']] = $resource['id'];

      $rest = new BigMLRequest('CREATE', 'sample', $this);

      $rest->setData($args);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));
      return $rest->getResponse();
   }

   public function get_sample($sampleId, $queryString=null)
   {
      /*
        Retrieves an sample.
      */
      $rest = $this->get_resource_request($sampleId, "sample", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function list_samples($queryString=null)
   {
      /*
        Lists all your samples.
      */
      $rest = new BigMLRequest('LIST', 'sample', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_sample($sampleId, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a sample
      */
      $rest = $this->get_resource_request($sampleId, "sample", "UPDATE", null, true,  $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_sample($sampleId) {
      /*
        Deletes a sample
      */
      $rest = $this->get_resource_request($sampleId, "sample", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Projects
   #
   ##########################################################################

   public function create_project($args=array(), $waitTime=3000, $retries=10) {
      /*
        Creates a project
      */

      $args = $args == null? array() : $args;

      $rest = new BigMLRequest('CREATE', 'project', $this);

      $rest->setData($args);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));
      return $rest->getResponse();
   }

   public function get_project($projectId)
   {
      /*
        Retrieves an project.
      */
      $rest = $this->get_resource_request($projectId, "project", "GET");
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function list_projects($queryString=null)
   {
      /*
        Lists all your samples.
      */
      $rest = new BigMLRequest('LIST', 'project', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_project($projectId, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a project
      */
      $rest = $this->get_resource_request($projectId, "project", "UPDATE", null, true,  $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_project($projectId) {
      /*
        Deletes a project
      */
      $rest = $this->get_resource_request($projectId, "project", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function delete_all_project_by_name($test_name) {
      /*
        Delete a list from projects by name
      */

      $projects = $this->list_projects("name=". $test_name);

      if (!is_null($projects)) {
         foreach ($projects->resources as $project) {
            $this->delete_project($project->resource);
         }
      }
   }

   ##########################################################################
   #
   # Correlations
   # https://bigml.com/developers/correlations
   #
   ##########################################################################

   public function create_correlation($datasetId, $data=array(), $waitTime=3000, $retries=10) {
      /*
        Creates a correlation from a `dataset`.
      */

      $resource = $this->_check_resource($datasetId, null, $waitTime, $retries);
      if ($resource == null || $resource['type'] != "dataset") {
         error_log("Wrong dataset id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $rest = new BigMLRequest('CREATE', 'correlation', $this);

      $data["dataset"] = $resource["id"];

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function get_correlation($correlation, $queryString=null)
   {
      /*
        Retrieves an correlation.
      */
      $rest = $this->get_resource_request($correlation, "correlation", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();

   }

   public function list_correlations($queryString=null)
   {
      /*
        Lists all your correlations.
      */
      $rest = new BigMLRequest('LIST', 'correlation', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_correlation($correlationId, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a correlation
      */
      $rest = $this->get_resource_request($correlationId, "correlation", "UPDATE", null, true,  $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_correlation($correlationId) {
      /*
        Deletes a correlation
      */
      $rest = $this->get_resource_request($correlationId, "correlation", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Associations
   # https://bigml.com/developers/associations
   #
   ##########################################################################

   public function create_association($datasetId, $data=array(), $waitTime=3000, $retries=10) {
      /*
        Creates a association from a `dataset`
      */

      $resource = $this->_check_resource($datasetId, null, $waitTime, $retries);
      if ($resource == null || $resource['type'] != "dataset") {
         error_log("Wrong dataset id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $rest = new BigMLRequest('CREATE', 'association', $this);

      $data["dataset"] = $resource["id"];

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function get_association($association, $queryString=null)
   {
      /*
        Retrieves an association
      */
      $rest = $this->get_resource_request($association, "association", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();

   }

   public function list_associations($queryString=null)
   {
      /*
        Lists all your associations.
      */
      $rest = new BigMLRequest('LIST', 'association', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_association($association, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a association
      */
      $rest = $this->get_resource_request($association, "association", "UPDATE", null, true,  $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_association($association) {
      /*
        Deletes a association
      */
      $rest = $this->get_resource_request($association, "association", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Statisticaltests
   # https://bigml.com/developers/statisticaltests
   #
   ##########################################################################

   public function create_statistical_test($datasetId, $data=array(), $waitTime=3000, $retries=10) {
      /*
        Creates a statistical test from a `dataset`
      */

      $resource = $this->_check_resource($datasetId, null, $waitTime, $retries);
      if ($resource == null || $resource['type'] != "dataset") {
         error_log("Wrong dataset id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $rest = new BigMLRequest('CREATE', 'statisticaltest', $this);

      $data["dataset"] = $resource["id"];

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function get_statistical_test($statisticaltest, $queryString=null)
   {
      /*
        Retrieves an statistical test
      */
      $rest = $this->get_resource_request($statisticaltest, "statisticaltest", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();

   }

   public function list_statistical_tests($queryString=null)
   {
      /*
        Lists all your statistical tests.
      */
      $rest = new BigMLRequest('LIST', 'statisticaltest', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_statistical_test($statisticaltest, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a statistical test
      */
      $rest = $this->get_resource_request($statisticaltest, "statisticaltest", "UPDATE", null, true,  $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_statistical_test($statisticaltest) {
      /*
        Deletes a statistical test
      */
      $rest = $this->get_resource_request($statisticaltest, "statisticaltest", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   private function _create_local_source($file_name, $options=array()) {
      $rest = new BigMLRequest('CREATE', 'source', $this);

      if (phpversion() < 5.5) {
         $options['file'] = '@' . realpath($file_name);
      } else {
         $options['file'] = new \CurlFile(realpath($file_name));
      }

      if ($options != null ){
         foreach ($options as $key => $value) {
            if (is_array($value) ){
               $options[$key] = json_encode($value);
            }
         }
      }

      $rest->setData($options, false);
      $rest->setHeader('Content-Type', 'multipart/form-data');
      return $rest->getResponse();
   }
   ##########################################################################
   #
   # whizzml scripts REST calls
   # https://bigml.com/developers/scripts
   #
   ##########################################################################

   public function create_script($source_code, $args=array(), $waitTime=3000, $retries=10) {
      /*
        Creates a whizzml script from its source code. The `source_code`
        parameter can be a:
        {script ID}: the ID for an existing whizzml script
        {string} : the string containing the source code for the script
      */
      $args = $args == null? array() : $args;
      if (is_null($source_code)) {
         throw new \Exception('A valid code string or a script id must be provided.');
      }

      #$resource = $this->_check_resource($source_code, null, $waitTime, $retries);
      $resource = null;
      if ($resource != null) {
         if ($resource['type'] != "script") {
            throw new \Exception('A valid code string or a script id must be provided.');
         }

         if ($resource["status"] != BigMLRequest::FINISHED) {
            throw new \Exception($resource['message']);
         }

         $args["origin"] = $resource["id"];

      } else if (is_string($source_code)) {
         $args["source_code"] = $source_code;
      } else {
         throw new \Exception('A valid code string or a script id must be provided.');
      }

      $rest = new BigMLRequest('CREATE', 'script', $this);
      $rest->setQueryString("full=false");
      $rest->setData($args);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));
      return $rest->getResponse();

   }

   public function get_script($script, $queryString=null)
   {
      /*
        Retrieves a script.
        The script parameter should be a string containing the
        script id or the dict returned by create_script.
        As script is an evolving object that is processed
        until it reaches the FINISHED or FAULTY state, the function will
        return a dict that encloses the script content and state info
        available at the time it is called.
      */
      $rest = $this->get_resource_request($script, "script", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();

   }

   public function list_scripts($queryString=null)
   {
      /*
        Lists all your scripts
      */
      $rest = new BigMLRequest('LIST', 'script', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_script($script, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a script
      */
      $rest = $this->get_resource_request($script, "script", "UPDATE", null, true,  $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_script($script) {
      /*
        Deletes a script
      */
      $rest = $this->get_resource_request($script, "library", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # whizzml execution REST calls
   # https://bigml.com/developers/executions
   #
   ##########################################################################

   public function create_execution($script, $args=array(), $waitTime=3000, $retries=10) {
      /*
        Creates a whizzml execution from a script or list of scripts
      */

      $args = $args == null? array() : $args;
      $scriptsIds = array();
      if (is_array($script)) {
         foreach ($script as $var => $scriptId) {
            $resource = $this->_check_resource($scriptId, null, $waitTime, $retries);
            if ($resource == null || $resource['type'] != "script") {
               throw new \Exception('A script id is needed to create a script execution.');
            } elseif ($resource["status"] != BigMLRequest::FINISHED) {
               throw new \Exception($resource['message']);
            }
            array_push($scriptsIds, $resource["id"]);
         }
      } else {
         $resource = $this->_check_resource($script, null, $waitTime, $retries);
         if ($resource == null || $resource['type'] != "script") {
            throw new \Exception('A script id is needed to create a script execution.');
         } elseif ($resource["status"] != BigMLRequest::FINISHED) {
            throw new \Exception($resource['message']);
         }
         array_push($scriptsIds, $resource["id"]);
      }

      if (sizeof($scriptsIds) > 1) {
         $args["scripts"] = $scriptsIds;
      } else {
         $args["script"] = $scriptsIds[0];
      }

      $rest = new BigMLRequest('CREATE', 'execution', $this);

      $rest->setQueryString("full=false");
      $rest->setData($args);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));
      return $rest->getResponse();

   }

   public function get_execution($execution, $queryString=null)
   {
      /*
        Retrieves an execution
        The execution parameter should be a string containing the
        execution id or the dict returned by create_execution.
        As execution is an evolving object that is processed
        until it reaches the FINISHED or FAULTY state, the function will
        return a dict that encloses the execution contents and state info
        available at the time it is called.
      */
      $rest = $this->get_resource_request($execution, "execution", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();

   }

   public function list_executions($queryString=null)
   {
      /*
        Lists all your executions
      */
      $rest = new BigMLRequest('LIST', 'execution', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_execution($execution, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a execution
      */
      $rest = $this->get_resource_request($execution, "execution", "UPDATE", null, true,  $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_execution($execution) {
      /*
        Deletes a execution
      */
      $rest = $this->get_resource_request($execution, "execution", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # whizzml libraries REST calls
   # https://bigml.com/developers/libraries
   #
   ##########################################################################

   public function create_library($source_code, $args=array(), $waitTime=3000, $retries=10) {
      /*
        Creates a whizzml library from its source code. The `source_code`
        parameter can be a:
        {library ID}: the ID for an existing whizzml library
        {string} : the string containing the source code for the library
      */

      $args = $args == null? array() : $args;

      if (is_null($source_code)) {
         throw new \Exception('A valid code string or a library id must be provided.');
         return null;
      }


      $resource = $this->_check_resource($source_code, null, $waitTime, $retries);

      if ($resource != null) {
         if ($resource['type'] != "library") {
            throw new \Exception('A valid code string or a library id must be provided.');
         }

         if ($resource["status"] != BigMLRequest::FINISHED) {
            throw new \Exception($resource['message']);
         }

         $args["origin"] = $resource["id"];

      } else if (is_string($source_code)) {
         $args["source_code"] = $source_code;
      } else {
         throw new \Exception('A valid code string or a library id must be provided.');
      }

      $rest = new BigMLRequest('CREATE', 'library', $this);
      $rest->setQueryString("full=false");
      $rest->setData($args);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($args)));
      return $rest->getResponse();
   }

   public function get_library($library, $queryString=null)
   {
      /*
        Retrieves an library
        The library parameter should be a string containing the
        library id or the dict returned by create_script.
        As library is an evolving object that is processed
        until it reaches the FINISHED or FAULTY state, the function will
        return a dict that encloses the library content and state info
        available at the time it is called.
      */
      $rest = $this->get_resource_request($library, "library", "GET", $queryString);
      if ($rest == null) return null;
      return $rest->getResponse();

   }

   public function list_libraries($queryString=null)
   {
      /*
        Lists all your libraries
      */
      $rest = new BigMLRequest('LIST', 'library', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_library($library, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a library
      */
      $rest = $this->get_resource_request($library, "library", "UPDATE", null, true,  $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_library($library) {
      /*
        Deletes a library
      */
      $rest = $this->get_resource_request($library, "library", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Topic Models
   # https://bigml.com/developers/topicmodels
   #
   ##########################################################################
   public function create_topicmodel($datasetIds, $data=array(), $waitTime=3000, $retries=10) {
      /*
        Creates a topic model from a `dataset` or a list of `datasets`
      */

      $datasets= array();

      if (!is_array($datasetIds)) {
         $datasetIds=array($datasetIds);
      }

      foreach ($datasetIds as $var => $datasetId) {
         $resource = $this->_check_resource($datasetId, null, $waitTime, $retries);
         if ($resource == null || $resource['type'] != "dataset") {
            error_log("Wrong dataset id");
            return null;
         } elseif ($resource["status"] != BigMLRequest::FINISHED) {
            error_log($resource['message']);
            return null;
         }
         array_push($datasets, $resource["id"]);
      }

      $rest = new BigMLRequest('CREATE', 'topicmodel', $this);

      if (sizeof($datasets) > 1) {
         $data["datasets"] = $datasets;
      } else {
         $data["dataset"] = $datasets[0];
      }

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function get_topicmodel($topicmodelId, $queryString=null, $shared_username=null, $shared_api_key=null)
   {
      /*
        Retrieves a topic model.

        The model parameter should be a string containing the
        topic model id or the dict returned by create_topicmodel.
        As topic model is an evolving object that is processed
        until it reaches the FINISHED or FAULTY state, the function will
        return a dict that encloses the topic model values and state info
        available at the time it is called.

        If this is a shared topic model, the username and sharing api key must
        also be provided.
      */
      $rest = $this->get_resource_request($topicmodelId, "topicmodel", "GET", $queryString, true, 3000, 0, $shared_username, $shared_api_key);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function list_topicmodels($queryString=null)
   {
      /*
        List all your topic models
      */
      $rest = new BigMLRequest('LIST', 'topicmodel', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_topicmodel($topicmodelId, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a topic model
      */
      $rest = $this->get_resource_request($topicmodelId, "topicmodel", "UPDATE", null, true,  $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_topicmodel($topicmodelId) {
      /*
        Deletes a topic model
      */
      $rest = $this->get_resource_request($topicmodelId, "topicmodel", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   ##########################################################################
   #
   # Deepnets
   # https://bigml.com/developers/deepnets
   #
   ##########################################################################
   public function create_deepnet($datasetIds, $data=array(), $waitTime=3000, $retries=10) {
      /*
        Creates a deepnet from a `dataset` or a list of datasets
      */

      $datasets= array();

      if (!is_array($datasetIds)) {
         $datasetIds=array($datasetIds);
      }

      foreach ($datasetIds as $var => $datasetId) {
         $resource = $this->_check_resource($datasetId, null, $waitTime, $retries);
         if ($resource == null || $resource['type'] != "dataset") {
            error_log("Wrong dataset id");
            return null;
         } elseif ($resource["status"] != BigMLRequest::FINISHED) {
            error_log($resource['message']);
            return null;
         }
         array_push($datasets, $resource["id"]);
      }

      $rest = new BigMLRequest('CREATE', 'deepnet', $this);

      if (sizeof($datasets) > 1) {
         $data["datasets"] = $datasets;
      } else {
         $data["dataset"] = $datasets[0];
      }

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function get_deepnet($deepnetId, $queryString=null, $shared_username=null, $shared_api_key=null)
   {
      /*
        Retrieves a deepnet.

        The deepnetId parameter should be a string containing the
        deepnet id or the dict returned by create_deepnet.
        As a deepnet is an evolving object that is processed
        until it reaches the FINISHED or FAULTY state, the function will
        return a dict that encloses the deepnet values and state info
        available at the time it is called.

        If this is a shared deepnet, the username and sharing api key must
        also be provided.
      */
      $rest = $this->get_resource_request($deepnetId, "deepnet", "GET", $queryString, true, 3000, 0, $shared_username, $shared_api_key);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   public function list_deepnets($queryString=null)
   {
      /*
        List all your deepnets
      */
      $rest = new BigMLRequest('LIST', 'deepnet', $this);

      if ($queryString!=null) {
         $rest->setQueryString($queryString);
      }

      return $rest->getResponse();
   }

   public function update_deepnet($deepnetId, $data, $waitTime=3000, $retries=10) {
      /*
        Updates a deepnet
      */
      $rest = $this->get_resource_request($deepnetId, "deepnet", "UPDATE", null, true,  $waitTime, $retries);
      if ($rest == null) return null;

      $rest->setData($data);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($data)));
      return $rest->getResponse();
   }

   public function delete_deepnet($deepnetId) {
      /*
        Deletes a deepnet
      */
      $rest = $this->get_resource_request($deepnetId, "deepnet", "DELETE", null);
      if ($rest == null) return null;
      return $rest->getResponse();
   }

   private function _create_remote_source($file_url, $options=array()) {
      $rest = new BigMLRequest('CREATE', 'source', $this);
      $options['remote'] = $file_url;
      $rest->setData($options);
      $rest->setHeader('Content-Type', 'application/json');
      $rest->setHeader('Content-Length', strlen(json_encode($options)));
      return $rest->getResponse();
   }

   public function create_inline_source($data_string, $options=array()) {
      $rest = new BigMLRequest('CREATE', 'source', $this);
      $options['data'] = $data_string;
      $rest->setData($options);
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

   public function _checkBatchPredictionId($stringID) {
      return preg_match("/^batchprediction\/[a-f,0-9]{24}$/i", $stringID) ? true : false;
   }

   public function _checkClusterId($stringID) {
      return preg_match("/^(public\/)?cluster\/[a-f,0-9]{24}$|^shared\/cluster\/[a-f,0-9]{27}$/i", $stringID) ? true : false;
   }

   public function _checkCentroId($stringID) {
      return preg_match("/^centroid\/[a-f,0-9]{24}$/i", $stringID) ? true : false;
   }

   public function _checkBatchCentroId($stringID) {
      return preg_match("/^batchcentroid\/[a-f,0-9]{24}$/i", $stringID) ? true : false;
   }

   public function _checkAnomalyId($stringID) {
      return preg_match("/^anomaly\/[a-f,0-9]{24}$/i", $stringID) ? true : false;
   }

   public function _checkAssociationId($stringID) {
      return preg_match("/^association\/[a-f,0-9]{24}$/i", $stringID) ? true : false;
   }

   public function _checkLogisticRegressionId($stringID) {
      return preg_match("/^logisticregression\/[a-f,0-9]{24}$/i", $stringID) ? true : false;
   }

   public function _checkTopicmodelId($stringID) {
      return preg_match("/^topicmodel\/[a-f,0-9]{24}$/i", $stringID) ? true : false;
   }

   public function _checkDeepnetId($stringID) {
      return preg_match("/^deepnet\/[a-f,0-9]{24}$/i", $stringID) ? true : false;
   }

   public function get_fields($resource) {
      /*
        Retrieve fields used by a resource.
        Returns a dictionary with the fields that uses
        the resource keyed by Id.
      */
      $resource_id = null;

      if ($resource instanceof \STDClass && property_exists($resource, "resource")) {
         $resource_id = $resource->resource;
      } else {
         error_log("Wrong resource object");
         return null;
      }

      return $this->_get_fields_key($resource, $resource_id);

   }

   private function _get_fields_key($resource, $resource_id) {
      /*
        Returns the fields key from a resource dict
      */
      if (in_array($resource->code, array(BigMLRequest::HTTP_OK, BigMLRequest::HTTP_ACCEPTED)) )  {

         if ($this->_checkModelId($resource_id)) {
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
      if ($this->storage != null) {
         $stored_resource = $this->storage .
                          DIRECTORY_SEPARATOR .
                          str_replace('/','_',$resource_id);
         if (file_exists($stored_resource)) {
            $resource = json_decode(file_get_contents($stored_resource));
            if (property_exists($resource, "object") &&
                property_exists($resource->object, "status") &&
                $resource->object->status->code != BigMLRequest::FINISHED ) {
               # get resource again
               try {
                  $rest = new BigMLRequest('GET', $resource->resource, $this);
                  if ($query_string!=null) {
                     $rest->setQueryString($query_string);
                  }
                  return $rest->getResponse();
               } catch  (\Exception $e) {
                  error_log("The resource isn't finished yet");
                  return $resource;
               }
            } else {
               return $resource;
            }

         }
      }

      $resource = $this->_check_resource($resource_id, $query_string);

      if ($resource == null) {
         error_log("Wrong resource id");
         return null;
      } elseif ($resource["status"] != BigMLRequest::FINISHED) {
         error_log($resource['message']);
         return null;
      }

      $rest = new BigMLRequest('GET', $resource["id"], $this);

      if ($query_string!=null) {
         $rest->setQueryString($query_string);
      }

      return $rest->getResponse();
   }

   public function pprint($resource)
   {
      /*
        Pretty prints a resource or part of it.
      */
      if ($resource instanceof \STDClass && property_exists($resource, "resource") && property_exists($resource, "object")) {

         $resource_id = $resource->resource;
         if (preg_match('/(source|dataset|model|evaluation|ensemble|cluster)(\/)([a-f,0-9]{24}|[a-f,0-9]{27})$/i', $resource_id, $result)) {
            print_r($resource->object->name . "(" . $resource->object->size  . " bytes)\n");
         } elseif ($this->_checkPredictionId($resource_id)) {

            $objective_field_name = $resource->object->fields->{$resource->object->objective_fields[0]}->name;
            $input_data=array();

            foreach($resource->object->input_data as $key => $value) {
               $name = (property_exists($resource->object->fields, $key) && property_exists($resource->object->fields->{$key}, "name")) ? $resource->object->fields->{$key}->name : $key;
               $input_data[$name] = $value;
            }

            $prediction = $resource->object->prediction->{$resource->object->objective_fields[0]};
            $str = $objective_field_name . " for " . json_encode($input_data) . " is " . $prediction . "\n";
            print_r($str);
         }

      } else {
         print_r($resource);
      }

   }

   public function status($resourceId) {

      $resource = $this->_check_resource($resourceId);

      if ($resource == null) {
         error_log("Wrong resource id");
         return null;
      }

      return array('message' => $resource['message'], 'code'=>$resource['code']);

   }

   private function check_resource_type($resourceId, $resourceType) {
      $resource = null;
      if ($resourceId instanceof \STDClass && property_exists($resourceId, "resource")) {
         $resource = $resourceId->resource;
      } else if (is_string($resourceId)) {
         $resource = $resourceId;
      } else {
         error_log("Wrong ". $resourceType . " id");
         return null;
      }
      if (preg_match('/((shared|public)\/)?('.$resourceType. ')(\/)([a-z,0-9]{24}|[a-z,0-9]{27})$/i', $resource, $result)) {
         return $resource;
      } else {
         error_log("Wrong ". $resourceType . " id");
         return null;
      }
   }

   private function get_resource_request($resourceId, $resourceType, $operation, $queryString=null, $checkStatus=false, $waitTime=3000, $retries=0, $shared_username=null, $shared_api_key=null) {
      $resource=$this->check_resource_type($resourceId,  $resourceType);

      if ($resource == null) {
         error_log("Wrong ". $resourceType . " id");
         return null;
      }

      if ($checkStatus) {
         $resource = $this->_check_resource($resourceId, $queryString, $waitTime, $retries);
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

      $rest = new BigMLRequest($operation, $resource, $this, $shared_username, $shared_api_key);

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

   private $bigml;

   private $response_code;

   public function __construct($method = 'GET',
                                $uri = 'source',
                                $bigml = null,
                                $shared_username = null,
                                $shared_api_key = null) {

      $this->endpoint = ($bigml->getDomain() != null) ?
                      $bigml->getDomain() : BigML::BIGML_ENDPOINT;
      $this->headers['Host'] = str_replace('https://','', $this->endpoint);

      $this->method = $method;
      $this->version = $bigml->getVersion();
      $this->uri = $uri;

      $this->headers['Date'] = gmdate('D, d M Y H:i:s T');

      if ($shared_username != null && $shared_api_key != null) {
         $this->setParameter("username", $shared_username);
         $this->setParameter("api_key", $shared_api_key);
      } else {
         if ($bigml->hasAuth()) {
            $this->setParameter("username", $bigml->getUsername());
            $this->setParameter("api_key", $bigml->getApiKey());
         } else {
            error_log("Cannot find BIGML AUTH");
         }

         if ($bigml == null) {
            $bigml = new BigML();
         }
         $this->bigml = $bigml;

         if ($bigml->getOrganization() != null) {
            $this->setParameter("organization", $bigml->getOrganization());
         } else {
            if ($bigml->getProject() != null) {
               $this->setParameter("project", $bigml->getProject());
            }
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

   public function setData($data, $json=true)
   {
      if ($json) {
         $this->data = json_encode($data);
      } else {
         $this->data = $data;
      }
   }

   public function download_url() {
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

      return $url;
   }
   public function download($counter=0, $retries=3, $wait_time=30) {
      try {
         $data = file_get_contents($this->download_url());
         $headers = $this->parseHeaders($http_response_header);

         if ($headers["reponse_code"] == BigMLRequest::HTTP_OK) {
            if ($counter < $retries) {
               $download_status = json_decode($data);
               if ($download_status != null && (is_object($download_status) or is_array($download_status)) ) {
                  if ($download_status->status->code != 5) {
                     sleep($wait_time);
                     $counter+=1;
                     return $this->download($counter, $retries, $wait_time);
                  } else {
                     return $this->download($retries+1, $retries, $wait_time);
                  }
               }
            } else if ($counter == $retries) {
               error_log("The maximum number of retries for the download has been exceeded " .
                         "You can retry your  command again in a while.");
            }

         } else if (in_array(intval($headers["reponse_code"]), array(BigMLRequest::HTTP_BAD_REQUEST, BigMLRequest::HTTP_UNAUTHORIZED, BigMLRequest::HTTP_NOT_FOUND, BigMLRequest::HTTP_TOO_MANY_REQUESTS))) {
            error_log("request error");
         } else {
            error_log("Unexpected exception error");
         }

         return $data;

      } catch (\Exception $e) {
         error_log("Unexpected exception error");
      }
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
      $url = $this->endpoint.'/'.$this->uri;
      if (!is_null($this->version) && ($this->version != ""))
         $url = $this->endpoint.'/'.$this->version.'/'.$this->uri;

      try {
         if ($this->bigml &&
             $this->bigml->getDebug() != null &&
             $this->bigml->getDebug() == true) {
            echo $this->method . "------------------------------------\n";
            echo "URL: " . $url . "\n";
            }

         $curl = curl_init();
         curl_setopt($curl, CURLOPT_URL, $url);

         curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
         curl_setopt($curl, CURLOPT_HEADER, true);
         curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false );
         curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false );

         if ($this->method == "CREATE") {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $this->data);
            if ($this->bigml &&
                $this->bigml->getDebug() != null &&
                $this->bigml->getDebug() == true) {
               echo "Data: ";
               var_dump($this->data);
            }
         } elseif ($this->method == "UPDATE") {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($curl, CURLOPT_POSTFIELDS, $this->data);
            if ($this->bigml &&
                 $this->bigml->getDebug() != null &&
                 $this->bigml->getDebug() == true) {
                echo "Data: ";
                var_dump($this->data);
            }
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

         if ($this->bigml &&
             $this->bigml->getDebug() != null &&
             $this->bigml->getDebug() == true) {
            curl_setopt($curl, CURLOPT_VERBOSE, true);
         }

         $response = curl_exec($curl);
         if ($this->bigml &&
             $this->bigml->getDebug() != null &&
             $this->bigml->getDebug() == true) {
            print_r($response);
         }
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
            $this->response["code"] = $code;
            $error_message = $this->error_message(json_decode($body), $this->method);
            $this->response["error"]["status"]["message"] = $error_message["message"];
            $this->response["error"]["status"]["code"] = $code;
            error_log($this->response["error"]["status"]["message"]);
            if ($error_message["code"] != null)
               $this->response["error"]["status"]["code"] = $error_message["code"];

         } else {
            error_log("Unexpected error ". $code);
            print_r($response);
            $this->response["code"] = BigMLRequest::HTTP_INTERNAL_SERVER_ERROR;
         }

         curl_close($curl);
      } catch (\Exception $e) {
         print("got exception");

         error_log("Unexpected exception error");
      }

      return json_decode( json_encode($this->response));
   }

   private function parseJsonResponse($response, $code, $headers) {
      $location = null;

      $r =json_decode($response,true);

      if ($this->method ==  "LIST") {
         $this->response["meta"] = $r["meta"];
         $this->response["resources"] = $r["objects"];
         $this->response["error"] = null;
         $this->response["code"] = $code;
      } else if ($this->method ==  "DELETE") {
         $this->response["code"] = $code;
      } else {

         $headers_arr = explode("\n", $headers);
         foreach($headers_arr as $header) {
            $cad = explode(": ", $header);
            if (strtolower($cad[0]) === 'location') {
               $this->response["location"] = trim($cad[1]);
               $location = $this->response["location"];
               break;
            }
         }

         $this->response["code"] = $code;
         $this->response["resource"] = $r["resource"];
         $this->response["error"] = null;
         $this->response["object"] = $r;

         if ($this->bigml) {
            maybe_save($this->response, $this->bigml->getStorage(),
                       $code, $location);
         }
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
         $this->response["error"]["status"]["message"] = "The resource couldn't be " . $this->method == "CREATE" ? "created" : ($this->method == "UPDATE" ? "updated": "retrieved");

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

      if ($resource instanceof \STDClass) {
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
               if ($extra instanceof \STDClass) {
                  $error = $error . ":";
                  $error_response["code"] = $extra->error;
                  foreach(get_object_vars($extra) as $key => $value) {
                     $error = $error . $key . ": " . json_encode($value) . " ";
                  }
                  $error = $error . "\n";
               } else {
                  $error = $error . ": " . $extra[0];
               }
            }
         }

         if ($code == BigMLRequest::HTTP_NOT_FOUND && strtolower($method) == 'get') {
            $alternate_message = '';
            if ($this->bigml &&
                $this->bigml->getDomain() != null &&
                $this->bigml->getDomain()!= BigML::BIGML_ENDPOINT) {
               $alternate_message = "The " . $resource_type .
                                  " was not created in " .
                                  $this->bigml->getDomain() . "\n";
            }

            $error = $error . "\nCouldn\'t find a " . $resource_type . " matching the given id. The most probable causes are:\n\n" . $alternate_message . " A typo in the " . $resource_type . "'s id.\n The " . $resource_type . " id cannot be accessed with your credentials.\n \nDouble-check your " . $resource_type . " and credentials info and retry.";

         } elseif ($code == BigMLRequest::HTTP_UNAUTHORIZED) {
            $error = $error. '\nDouble-check your credentials, and the general  domain your account is registered with (currently using '. $this->bigml->getDomain() . '), please.';
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

   private function parseHeaders( $headers )
   {
      $head = array();
      foreach( $headers as $k=>$v )
      {
         $t = explode( ':', $v, 2 );
         if( isset( $t[1] ) )
            $head[ trim($t[0]) ] = trim( $t[1] );
         else
         {
            $head[] = $v;
            if( preg_match( "#HTTP/[0-9\.]+\s+([0-9]+)#",$v, $out ) )
               $head['reponse_code'] = intval($out[1]);
         }
      }
      return $head;
   }

}

?>
