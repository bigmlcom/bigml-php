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

if (!class_exists('multimodel')) {
  include('multimodel.php');
}

if (!class_exists('multivote')) {
  include('multivote.php');
}

if (!class_exists('model')) {
  include('model.php');
}

if (!class_exists('basemodel')) {
  include('basemodel.php');
}

class Ensemble {
   /*
      A local predictive Ensemble.
      Uses a number of BigML remote models to build an ensemble local version
      that can be used to generate predictions locally.
   */
   public $ensemble_id;
   public $distributions;
   public $api;
   public $model_ids;
   public $models_splits;
   public $multi_model;
   public $fields;
   public $objective_id;

   public function __construct($ensemble, $api=null, $max_models=null, $storage="storage") {

      /*
         array from models
         ensemble object
      */
      if ($api == null) {
         $api = new BigML(null, null, null, $storage);
      }

      $this->api = $api;
      $this->ensemble_id = null;
      $models = array();

      if (is_array($ensemble)) {
         
         foreach($ensemble as $model_id) {

            if (!is_string($model_id) && is_a($model_id, "Model") ) {
               array_push($models, $model_id);
            } else if ($model_id instanceof STDClass) {
               array_push($models, $model_id);
            } else if ($api != null && $api::_checkModelId($model_id)) {
               $m = $api::get_model($model_id);
               if ($m != null) {
                 array_push($models, $m);
               } else {
                  error_log("Failed to verify the list of models. Check your model id values");
                  return null;
               }
                   
            } else {
               error_log("Failed to verify the list of models. Check your model id values");
               return null;
            }
         }
         
         $this->distributions = null;   

      } else {
         if (is_string($ensemble) && $api != null && $api::_checkEnsembleId($ensemble)) {
            $ensemble = $api::get_ensemble($ensemble);
         }

         if ($ensemble instanceof STDClass && property_exists($ensemble, "resource") && $api::_checkEnsembleId($ensemble->resource) && $ensemble->object->status->code == 5) {
            $models = $ensemble->object->models;
            $this->ensemble_id =$ensemble->resource;
            $this->resource_id = $ensemble->resource;
            $this->distributions = (property_exists($ensemble->object, "distributions") ) ? $ensemble->object->distributions : null;
         } else {
            error_log("Cannot find ensemble object");
            return null;
         }
      }

      $this->model_ids = $models;
      $this->fields = $this->all_model_fields();
      $number_of_models = count($models);
      $this->models_splits = array();

      if ($max_models == null) {
         $this->models_splits = array($models);
      } else {
          foreach (range(0, $number_of_models, $max_models) as $index) {
              array_push($this->models_splits, array_slice($models, $index, ($index+$max_models)));
          }
      }

      if (count($this->models_splits) == 1) {
         $models = array();
 
         foreach($this->models_splits[0] as $model_id) {
            if (!is_string($model_id) && is_a($model_id, "Model") ) {
              $mo = $model_id;
            } else {
              $mo = $api::retrieve_resource($model_id, $api::ONLY_MODEL);
            }
 
            $models[] = clone $mo;
         }
         $this->multi_model = new MultiModel($models, $this->api); 
      }

   }

   function list_models() {
      /*
         Lists all the model/ids that compound the ensemble.
      */
      return $this->model_ids;
   }

   function predict($input_data, $by_name=true, $method=MultiVote::PLURALITY_CODE, $with_confidence=false, 
                    $add_confidence=false, $add_distribution=false, $add_count=false, $add_median=false, $add_unused_fields=false,
		    $add_min=false, $add_max=false, $options=null, $missing_strategy=Tree::LAST_PREDICTION, $median=false) {

      /*
         Makes a prediction based on the prediction made by every model.
  
         :param input_data: Test data to be used as input
	 :param by_name: Boolean that is set to true if field_names (as
	                 alternative to field ids) are used in the
			 input_data dict
	 :param method: numeric key code for the following combination
                        methods in classifications/regressions:

            0 - majority vote (plurality)/ average: PLURALITY_CODE
            1 - confidence weighted majority vote / error weighted:
               CONFIDENCE_CODE
            2 - probability weighted majority vote / average:
               PROBABILITY_CODE
            3 - threshold filtered vote / doesn't apply:
               THRESHOLD_CODE

	 The following parameter causes the result to be returned as a list
          :param add_confidence: Adds confidence to the prediction
          :param add_distribution: Adds the predicted node's distribution to the prediction
	  :param add_count: Adds the predicted nodes' instances to the prediction
	  :param add_median: Adds the median of the predicted nodes' distribution
	                     to the prediction
          :param add_min: Boolean, if true adds the minimum value in the
	                          prediction's distribution (for regressions only)
          :param add_max: Boolean, if true adds the maximum value in the
                        prediction's distribution (for regressions only)
          :param add_unused_fields: Boolean, if true adds the information about
                                  the fields in the input_data that are not
                                  being used in the model as predictors.
          :param options: Options to be used in threshold filtered votes.
          :param missing_strategy: numeric key for the individual model's
                                 prediction method. See the model predict
                                 method.
          :param median: Uses the median of each individual model's predicted
                       node as individual prediction for the specified
                       combination method.				  
      */

      if (count($this->models_splits) > 1) {
         $votes = new MultiVote(array()); 
         $models = array();
         $api = $this->api;
         $order = 0;

         foreach($this->models_splits as $model_split) {
            $models = array();
            foreach($model_split as $model_id) {
               array_push($models, $api::retrieve_resource($model_id, $api::ONLY_MODEL));
            }

            $multi_model = new MultiModel($models, $this->api);
            $votes_split = $multi_model->generate_votes($input_data, $by_name, $missing_strategy, ($add_median || $median), $add_min, $add_max, $add_unused_fields);
          
	    if ($median) {
               foreach($votes_split->predictions as $prediction) {
                  $prediction['prediction'] = $prediction['median'];
	       }
	    }

            $votes->extend($votes_split->predictions);
         }

         #return $votes->combine($method, $with_confidence, $options);

      } else {
         # When only one group of models is found you use the
         # corresponding multimodel to predict
         $votes_split = $this->multi_model->generate_votes($input_data, $by_name, $missing_strategy, ($add_median || $median),$add_min, $add_max, $add_unused_fields);

         $votes = new MultiVote($votes_split->predictions);
         if ($median) {
            $new_predictions=array();
	    foreach($votes->predictions as $prediction) {
	      $prediction->prediction = $prediction->median;
              array_push($new_predictions, $prediction);
	    }
            $votes->predictions = $new_predictions;
	 }
      }

      $result= $votes->combine($method, $with_confidence, $add_confidence, 
	                        $add_distribution,$add_count, $add_median, 
				$add_min, $add_max, $options);

      if ($add_unused_fields) {
         $unused_fields = array_unique(array_keys($input_data));
         foreach($votes->predictions as $index => $prediction) {
            $unused_fields = array_intersect($unused_fields, array_unique($prediction->unused_fields)); 
         }

         if (!is_array($result)) {
            $result = array("prediction" => $result);
         }
 
         $result['unused_fields'] = $unused_fields;
 
      }

      return $result;
   }

   function all_model_fields() {
      /*
         Retrieves the fields used as predictors in all the ensemble models
      */

      $fields = array();
      $models= array();

      foreach($this->model_ids as $model_id) {
         if (!is_string($model_id) && is_a($model_id, "Model") ) {
           $local_model = $model_id;
         } else {
           $local_model=new Model($model_id, $this->api);
         }
         $new_array = array();
         foreach($local_model->fields as $property => $value)  {
            $new_array[$property] = $value; 
         }

         $fields = array_merge($fields, $new_array);
      }

      return $fields;
   }

   function field_importance_data()
   {
      /*
       Computes field importance based on the field importance information
       of the individual models in the ensemble.
      */
      $field_importance = array();
      $field_names = array();
      $check_importance = false;

      if ($this->distributions != null && is_array($this->distributions)) 
      {
         $check_importance = true;
         $importances = array();
         foreach($this->distributions as $distribution) {
	    if (array_key_exists('importance', $distribution)) {
               array_push($importances, $distribution->importance); 
            } else {
	       $check_importance = false;
	    }
         }

        if ($check_importance == true) {

           foreach($importances as $model_info) {

               foreach ($model_info as $field_info) {

                  $field_id= $field_info[0];

                  if (!array_key_exists($field_id, $field_importance)) {
                     $field_importance[$field_id] = 0.0;
                     $field_names[$field_id] = array('name' => $this->fields[$field_id]->name); 
                  }

                  $field_importance[$field_id]+=$field_info[1];

               }
           }
        }
      }
        if ($this->distributions == null || !is_array($this->distributions) || $check_importance=false) 
        {
           # Old ensembles, extracts importance from model information
           foreach($this->model_ids as $model_id) {
              if (!is_string($model_id) && is_a($model_id, "Model") ) {
                 $local_model = $model_id; 
              } else {
                 $local_model = new BaseModel($model_id, $this->api);
              }
              foreach($local_model->field_importance as $field_info) {
                 $field_id = $field_info[0];
                 if (!array_key_exists($field_id, $field_importance)) { 
                    $field_importance[$field_id] = 0.0;
                    $field_names[$field_id] = array('name' => $this->fields[$field_id]->name);
                 }
                 $field_importance[$field_id]+=$field_info[1];
              }
           }  
        }

        $number_of_models = count($this->model_ids);

        foreach($field_importance as $key=>$value) { 
           $field_importance[$key] /= $number_of_models; 
        }
         
        arsort($field_importance);
        ksort($field_names);
        return array($field_importance, $field_names);
      
   }

}

?>
