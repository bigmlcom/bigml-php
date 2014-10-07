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

if (!class_exists('modelfields')) {
   include('modelfields.php'); 
}

if (!class_exists('anomalytree')) {
   include('anomalytree.php'); 
} 

/*

A local Predictive Anomaly Detector.

This module defines an Anomaly Detector to score anomlies in a dataset locally
or embedded into your application without needing to send requests to
BigML.io.

This module cannot only save you a few credits, but also enormously
reduce the latency for each prediction and let you use your models
offline.

*/
class Anomaly extends ModelFields {
   /*
      A lightweight wrapper around an anomaly detector.
      Uses a BigML remote anomaly detector model to build a local version that
      can be used to generate anomaly scores locally.
    */ 
    public $sample_size;
    public $mean_depth;
    public $expected_mean_depth;
    public $iforest;
    public $top_anomalies;

    public function __construct($anomaly, $api=null, $storage="storage") {
       if ($api == null) {
          $api = new BigML(null, null, null, $storage);
       }

       if (is_string($anomaly)) {
          if (!($api::_checkAnomalyId($anomaly)) ) {
             error_log("Wrong anomaly id");
             return null;
          }

          $anomaly = $api::retrieve_resource($anomaly, $api::ONLY_MODEL);
       }

       if ($anomaly == null || !property_exists($anomaly, 'resource') ) {
          error_log("Cannot create the Anomaly instance. Could not find the 'model' key in the resource");
          throw new Exception('Cannot create the Anomaly instance. Could not find the model key in the resource');
       }

       if (property_exists($anomaly, "object") && property_exists($anomaly->object, "status") && $anomaly->object->status->code != BigMLRequest::FINISHED ) {
          throw new Exception("The model isn't finished yet");
       }
 
       if (property_exists($anomaly, "object") && $anomaly->object instanceof STDClass)  {
          $anomaly = $anomaly->object;
          $this->sample_size = $anomaly->sample_size;
       }

       if (property_exists($anomaly, "model") && $anomaly->model instanceof STDClass) {
          parent::__construct($anomaly->model->fields);
          
          if ( property_exists($anomaly->model, "top_anomalies") && is_array($anomaly->model->top_anomalies) ) {
             $this->mean_depth = $anomaly->model->mean_depth;
             $this->expected_mean_depth->null;

             if ($this->mean_depth == null || $this->sample_size == null) {
                error_log("The anomaly data is not complete. Score will not be available");
                throw new Exception('The anomaly data is not complete. Score will not be available');
             } else {
               $default_depth = 2*(0.5772156649 + log($this->sample_size - 1) - (floatval($this->sample_size-1)/$this->sample_size));
               $this->expected_mean_depth = min(array($this->mean_depth, $default_depth));
             }

             $this->iforest = array();

             foreach ($anomaly->model->trees as $anomaly_tree) {
                array_push($this->iforest, new AnomalyTree($anomaly_tree->root, $this->fields));
             }
             
             $this->top_anomalies = $anomaly->model->top_anomalies;
             
          } else {
             error_log("Cannot create the Anomaly instance. Could not find the 'top_anomalies' key in the resource");
             throw new Exception("Cannot create the Anomaly instance. Could not find the 'top_anomalies' key in the resource");
          }
 

       }


    }
 
    function anomaly_score($input_data, $by_name=true)
    {
      /*
       Returns the anomaly score given by the iforest

       To produce an anomaly score, we evaluate each tree in the iforest
       for its depth result (see the depth method in the AnomalyTree
       object for details). We find the average of these depths
       to produce an `observed_mean_depth`. We calculate an
       `expected_mean_depth` using the `sample_size` and `mean_depth`
       parameters which come as part of the forest message.
       We combine those values as seen below, which should result in a
       value between 0 and 1.
      */

      //Checks and cleans input_data leaving the fields used in the model

      $input_data = $this->filter_input_data($input_data, $by_name);

      // Strips affixes for numeric values and casts to the final field type
      $input_data = cast($input_data, $this->fields);
      $depth_sum = 0;

      foreach ($this->iforest as $tree) {
         $depth = $tree->depth($input_data);
         $depth_sum += $depth[0];
      }

      $observed_mean_depth = floatval($depth_sum)/len($this->iforest);

      return pow(2, -$observed_mean_depth/$this->expected_mean_depth);

    }

}

?>
