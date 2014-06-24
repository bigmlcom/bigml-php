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
include('basemodel.php'); 
 #include('./predicate.php');
include('tree.php');

class Model extends BaseModel{
   /*
      A lightweight wrapper around a Tree model.
      Uses a BigML remote model to build a local version that can be used
      to generate predictions locally.
   */

   public $ids_map; 
   public $terms;
   public $tree;
   public $regression_ready=false;

   public function __construct($model, $api=null, $storage="storage") {

      if ($api == null) {
         $api = new BigML(null, null, null, $storage);
      }

      if (is_string($model)) {
         if (!($api::_checkModelId($model)) ) {
            error_log("Wrong model id");
            return null;
         }
         $model = $api::retrieve_resource($model, $api::ONLY_MODEL);
      } 


      if ($model == null || !property_exists($model, 'resource') ) {
         error_log("Cannot create the Model instance. Could not find the 'model' key in the resource");
         throw new Exception('Cannot create the Model instance. Could not find the model key in the resource');
      }

      if (property_exists($model, "object") && property_exists($model->object, "status") && $model->object->status->code != BigMLRequest::FINISHED ) {
         throw new Exception("The model isn't finished yet");
      }

      parent::__construct($model);
         
      if (property_exists($model, "object") && $model->object instanceof STDClass) {
         $model=$model->object;
      }

      if (property_exists($model, "model") && $model->model instanceof STDClass) {

         if ($model->status->code == BigMLRequest::FINISHED) {

            $this->ids_map = array();
            $this->terms = array();
            $this->tree = new Tree($model->model->root, $this->fields, $this->objective_id, $model->model->distribution->training, null, $this->ids_map);
            #self::$tree = clone $tree;
           
         } else {
            throw new Exception("The model isn't finished yet");
         }
      } else {
         throw new Exception("Cannot create the Model instance. Could not find the 'model' key in the resource:\n\n" . $model);
      }

      if ($this->tree->regression) {
         $this->regression_ready = true;
      }
   }


   public function predict($input_data, $by_name=true,$print_path=false, $out=STDOUT, $with_confidence=false, $missing_strategy=Tree::LAST_PREDICTION) 
   {
      /*
         Makes a prediction based on a number of field values.
         By default the input fields must be keyed by field name but you can use
        `by_name` to input them directly keyed by id.

      */

      # Checks if this is a regression model, using PROPORTIONAL
      # missing_strategy
      $tree = $this->tree;

      if ($tree->regression && $missing_strategy==Tree::PROPORTIONAL && !$this->regression_ready) {
         throw new Exception("You needed to use proportional missing strategy, 
                         for regressions. Please install them before, using local predictions for the model."); 
      }
      # Checks and cleans input_data leaving the fields used in the model
      $input_data = $this->filter_input_data($input_data, $by_name);

      # Strips affixes for numeric values and casts to the final field type
      $input_data = cast($input_data, $this->fields);
      $prediction_info = $tree->predict($input_data, null, $missing_strategy);

      $prediction = $prediction_info[0];
      $path = $prediction_info[1];
      $confidence = $prediction_info[2]; 
      $distribution = $prediction_info[3];
      $instances = $prediction_info[4];

      # Prediction path   
      if ($print_path == true) {
         fwrite($out, join(" AND ", $path) . ' => ' . $prediction . "\n");
         fclose($out);
      }         

      if ($with_confidence == true) {
         return array($prediction, $confidence, $distribution, $instances);
      }

      return $prediction;

   }

   function to_prediction($value_as_string, $data_locale="UTF-8") {
      /*
         Given a prediction string, returns its value in the required type
      */

      if (!mb_check_encoding($value_as_string, 'UTF-8')) {
         $value_as_sring = utf8_encode($value_as_string);
      }

      $tree = $this->tree;
      $objective_id = $tree->objective_id;

      if ($this->fields[$objective_id]['optype'] == 'numeric' ) {
         if ($data_locale==null) {
            $data_locale = $this->locale;
         }
         find_locale($data_locale);
         $datatype = $this->fields[$objective_id]['datatype'];

         if ($datatype == "double" || $datatype == "float") {
            return floatval($value_as_string);
         } else {
            return intval($value_as_string);
         }
      }

      return $value_as_string;
   }

   function find_locale($data_locale="en_US.UTF-8", $verbose=false)
   {
      try {
         setlocale(LC_ALL, $data_locale);
      } catch  (Exception $e) {
         error_log("Error find Locale"); 
      }
   }

   function rules($out=STDOUT, $filter_id=null, $subtree=true)
   {
      /*
         Returns a IF-THEN rule set that implements the model.
         `out` is file descriptor to write the rules.
      */ 
      $ids_path = $this->get_ids_path($filter_id);
      return $this->tree->rules($out, $ids_path, $subtree);

   }

   function get_ids_path($filter_id) 
   {
      /*
       Builds the list of ids that go from a given id to the tree root
      */
      $ids_path = null;
      if ($filter_id != null && $this->tree->id != null) {
         if (array_key_exists($filter_id, $this->ids_map)) {
            throw new Exception("The given id does not exist."); 
         } else {
            $ids_path = array($filter_id);
            $last_id = $filter_id;

            while ($this->ids_map[$last_id]->parent_id != null) {
               array_push($this->ids_map[$last_id]->parent_id, $ids_path);
               $last_id = $this->ids_map[$last_id]->parent_id;
            }
         } 
      }
      return $ids_path;
   }

}

?>
