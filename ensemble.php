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
        include('./multimodel.php');
    }

	if (!class_exists('multivote')) {
        include('./multivote.php');
    }
	
	if (!class_exists('model')) {
        include('./model.php');
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

					if ( !(is_string($model_id) && $api::_checkModelId($model_id)) ) {
						error_log("Failed to verify the list of models. Check your model id values");
						return null;
					}
					array_push($models, $model_id);
				}
				
				$this->distributions = null;	

			} else {
				if ($ensemble instanceof STDClass && property_exists($ensemble, "resource") && $api::_checkEnsembleId($ensemble->resource) && $ensemble->object->status->code == 5) {
					$models = $ensemble->object->models;
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
					array_push($models, $api::retrieve_resource($model_id, $api::ONLY_MODEL));
				}

				$this->multi_model = new MultiModel($models);
			}

		}

		function list_models() {
			/*
				Lists all the model/ids that compound the ensemble.
			*/
			return $this->model_ids;
		}

		function predict($input_data, $by_name=true, $method=MultiVote::PLURALITY_CODE, $with_confidence=false, $options=null) {
			/*
				Makes a prediction based on the prediction made by every model.
			 	The method parameter is a numeric key to the following combination
				methods in classifications/regressions:
					0 - majority vote (plurality)/ average: PLURALITY_CODE
					1 - confidence weighted majority vote / error weighted:
						CONFIDENCE_CODE
					2 - probability weighted majority vote / average:
						PROBABILITY_CODE
					3 - threshold filtered vote / doesn't apply:
						THRESHOLD_CODE
	
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
					$multi_model = new MultiModel($models);
					$votes_split = $multi_model->generate_votes($input_data, $by_name);

					foreach($votes_split->predictions as $prediction_info) {
						$prediction_info["order"] = $order;
						array_push($votes->predictions, $prediction_info); 
						$order+=1;
					}
				}

				return $votes->combine($method, $with_confidence, $options);

			} else {
				# When only one group of models is found you use the
            	# corresponding multimodel to predict
				$multi_model = $this->multi_model;
				$votes_split = $multi_model->generate_votes($input_data, $by_name);
				$votes = new MultiVote($votes_split->predictions);
				return $votes->combine($method, $with_confidence, $options);
			}		

		}

		function all_model_fields() {
			/*
				Retrieves the fields used as predictors in all the ensemble models
			*/

			$fields = array();
			foreach($this->model_ids as $model_id) {
				$local_model=new Model($model_id, $this->api);
				$new_array = array();
				foreach($local_model::$fields as $property => $value)  {	
					$new_array[$property] = $value; 
				}

				$fields = array_merge($fields, $new_array);
			}

			return $fields;
		}
	}

?>
