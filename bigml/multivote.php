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

	function ws_confidence($prediction, $distribution, $ws_z=1.96, $ws_n=null) {
		/*
			Wilson score interval computation of the distribution for the prediction
			expected arguments:
				prediction: the value of the prediction for which confidence is computed
				distribution: a distribution-like structure of predictions and the associated weights. (e.g.
							 'Iris-setosa', 10], ['Iris-versicolor', 5]])

				ws_z: percentile of the standard normal distribution
				ws_n: total number of instances in the distribution. If absent, the number is computed as the sum of weights in the
					  provided distribution
		*/

		$ws_p = floatval($distribution[strval($prediction)]);

		if ($ws_p < 0)
		{
			throw new Exception("The distribution weight must be a positive value");
		}

		$ws_norm = floatval(array_sum($distribution));

		if ($ws_norm != 1.0) {
			$ws_p = $ws_p/$ws_norm;
		}

		if ($ws_n == null) {
			$ws_n = $ws_norm;
		} else {
			$ws_n = floatval($ws_n);
		}

		if ($ws_n < 1) {
			throw new Exception("The total of instances in the distribution must be a positive integer");
		}

		$ws_z = floatval($ws_z);
		$ws_z2 = $ws_z * $ws_z;
		$ws_factor = floatval($ws_z2)/floatval($ws_n);
		$ws_sqrt = sqrt( ( ($ws_p * (1.0-$ws_p)) + ($ws_factor/4.0) )/$ws_n  );

		return ($ws_p + ($ws_factor/2) - ($ws_z*$ws_sqrt) )/(1+$ws_factor);
	
	}

	class MultiVote {
		/*
			A multiple vote prediction
			Uses a number of predictions to generate a combined prediction.	
		*/

		const PLURALITY = 'plurality';
    	const CONFIDENCE = 'confidence weighted';
    	const PROBABILITY = 'probability weighted';
    	const THRESHOLD = 'threshold';
    	const PLURALITY_CODE = 0;
    	const CONFIDENCE_CODE = 1;
    	const PROBABILITY_CODE = 2;
    	const THRESHOLD_CODE = 3;
    	const DEFAULT_METHOD = 0;
   
    	public $COMBINATION_WEIGHTS = array(MultiVote::PLURALITY => null, MultiVote::CONFIDENCE => 'confidence', MultiVote::PROBABILITY => 'probability', MultiVote::THRESHOLD => null);
    	public $COMBINER_MAP = array(MultiVote::PLURALITY_CODE=>MultiVote::PLURALITY, MultiVote::CONFIDENCE_CODE => MultiVote::CONFIDENCE, MultiVote::PROBABILITY_CODE => MultiVote::PROBABILITY, MultiVote::THRESHOLD_CODE=>MultiVote::THRESHOLD);
    	public $WEIGHT_KEYS = array(MultiVote::PLURALITY => null, MultiVote::CONFIDENCE=> array('confidence'), MultiVote::PROBABILITY=>array('distribution', 'count'), MultiVote::THRESHOLD=> null);

		public $predictions; 

		public function __construct($predictions) {
			/*
				Init method, builds a MultiVote with a list of predictions
				The constuctor expects a list of well formed predictions like:
				{'prediction': 'Iris-setosa', 'confidence': 0.7}
				Each prediction can also contain an 'order' key that is used
				to break even in votations. The list order is used by default.
			*/
			$this->predictions = array();

			if (is_array($predictions) ) {
				/*foreach($predictions as $prediction) {
					 array_push($this->predictions, new Model($prediction)); 
				}*/
				$this->predictions = $predictions;
			} else {
				array_push($this->predictions, $predictions);
			}

			$has_order = true;
	
			foreach($this->predictions as $prediction) {

				if (!array_key_exists($order, $prediction) ) {
					$has_order = false;
					break;
				}
            }	

			if (!$has_order) {
				$new_predictions = array();
				$i = 0;
				foreach($this->predictions as $prediction) {
					$prediction["order"] = $i; 
					array_push($new_predictions, $prediction);
					$i+=1;
				}
				$this->predictions = $new_predictions;
			}
		}

		public function is_regression() {
			/*
				Returns True if all the predictions are numbers
			*/
			foreach($this->predictions as $prediction) {
				if (!is_numeric($prediction["prediction"]) ) {
					return false;
				}	
			}
			return true;
		}

		public function avg($instance, $with_confidence=false) {
            /*
                Returns the average of a list of numeric values.
                If with_confidence is True, the combined confidence (as the
                average of confidences of the multivote predictions) is also
                returned
            */

            if (property_exists($instance, 'predictions') && $instance->predictions != null && $with_confidence==true) {

                foreach($instance->predictions as $prediction) {
					if (!array_key_exists('confidence', $prediction)) {
						throw new Exception('Not enough data to use the selected prediction method. Try creating your model anew.');
                    }
                }
            }

			$total = count($instance->predictions);
			$result = 0.0;
			$confidence = 0.0;

			foreach($instance->predictions as $prediction) { 
				$result += $prediction["prediction"];
				if ($with_confidence) {
					$confidence += $prediction["confidence"];
				}	
			}

            if ($with_confidence) {
                return ($total > 0) ? array($result / $total, $confidence / $total) : array(NAN,0);
            }

            return ($total > 0) ? $result / $total : NAN;

        }
	
		public function error_weighted($instance, $with_confidence=false) {
            /*
                Returns the prediction combining votes using error to compute weight
                If with_confidences is true, the combined confidence (as the
                error weighted average of the confidences of the multivote
                predictions) is also returned
            */
            if (property_exists($instance, 'predictions') && $instance->predictions != null && $with_confidence) {

                foreach($instance->predictions as $prediction) {
					if (!array_key_exists('confidence', $prediction)) {
						throw new Exception('Not enough data to use the selected prediction method. Try creating your model anew.');
                    }
                }

            }

			$result = 0.0;
			$top_range = 10;
			$combined_error = 0.0;
			$normalization_factor = $instance->normalize_error($instance, $top_range);

            if ($normalization_factor  == 0) {
                if ($with_confidence) {
                    return array(NAN, 0);
                } else  {
                    return NAN;
                }
            }

			if ($with_confidence) {
				$combined_error = 0.0;
			}

			foreach($instance->predictions as $prediction) {
				$result += $prediction["prediction"] * $prediction["_error_weight"];

				if ($with_confidence) {
					$combined_error += ($prediction["confidence"] * $prediction["_error_weight"]);
				}

				unset($prediction['_error_weight']);
			}

            if ($with_confidence) {
                return array($result / $normalization_factor, $combined_error / $normalization_factor);
            } else {
                return $result / $normalization_factor;
            }  

        }

		public function normalize_error($instance, $top_range) {
            /*
                Normalizes error to a [0, top_range] and builds probabilities
            */
			$error_values = array();
			if (property_exists($instance, 'predictions') ) {

				foreach($instance->predictions as $prediction) {
					if (!array_key_exists('confidence', $prediction)) {
						throw new Exception('Not enough data to use the selected prediction method. Try creating your model anew.');
					}
					array_push($error_values, $prediction["confidence"]);
				}
			}

            $max_error = max($error_values);
            $min_error = min($error_values);
			$error_range = 1.0 * ($max_error - $min_error);
            $normalize_factor = 0;

            if ($error_range > 0) {
                # Shifts and scales predictions errors to [0, top_range].
                # Then builds e^-[scaled error] and returns the normalization
                # factor to fit them between [0, 1]
                foreach($instance->predictions as $prediction) {
                    $delta = ($min_error - $prediction["confidence"]);
                    $prediction["_error_weight"] = exp(($delta/$error_range)*$top_range);
                    $normalize_factor+=$prediction["_error_weight"];
                }
            } else {
				$new_predictions = array();
                foreach($instance->predictions as $prediction) {
                    $prediction["_error_weight"] = 1;
					array_push($new_predictions, $prediction);
                }
				$instance->predictions = $new_predictions;
				$normalize_factor = count($instance->predictions);
            }

            return $normalize_factor;
        }

		public function next_order() {
			/*
				Return the next order to be assigned to a prediction
				Predictions in MultiVote are ordered in arrival sequence when
				added using the constructor or the append and extend methods.
				This order is used to break even cases in combination
				methods for classifications.
			*/
			return ($this->predictions != null) ? $this->predictions[-1]["order"] + 1 : 0; 
			
		}

		public function combine($method=MultiVote::DEFAULT_METHOD, $with_confidence=false, $options=null) {
			/*
				Reduces a number of predictions voting for classification and
				averaging predictions for regression.

           		method will determine the voting method (plurality, confidence
           		weighted, probability weighted or threshold).
           		If with_confidence is true, the combined confidence (as a weighted
           		average of the confidences of votes for the combined prediction)
           		will also be given.
			*/

			# there must be at least one prediction to be combined
			if ($this->predictions ==  null) {
				throw new Exception('No predictions to be combined.');
			}


			$method = (array_key_exists(strval($method),  $this->COMBINER_MAP)) ? $this->COMBINER_MAP[strval($method)] : $this->COMBINER_MAP[MultiVote::DEFAULT_METHOD];
	
			$keys = array_key_exists($method, $this->WEIGHT_KEYS) ? $this->WEIGHT_KEYS[$method] : null;
			if ($keys != null ) {
				foreach($keys as $key) {
					foreach($this->predictions as $prediction) {
						if (!array_key_exists($key, $prediction)) {
							throw new Exception('Not enough data to use the selected prediction method. Try creating your model anew.');
						}
					}
				}
			}

			if ($this->is_regression()) {
				foreach($this->predictions as $prediction) {
					if ($prediction["confidence"] == null) {
						$prediction["confidence"] = 0;
					}
				}

				if ($method == MultiVote::CONFIDENCE) {
					return $this->error_weighted($this, $with_confidence);
				} else {
					return $this->avg($this, $with_confidence); 
				}
			} else {

				$predictions = $this;
				if ($method == MultiVote::THRESHOLD) {

					if ($options == null) {
						$options = array();
					}

					$predictions = $this->single_out_category($options);	
				} elseif ($method == MultiVote::PROBABILITY) {

					$predictions = new MultiVote(array());
					$predictions->predictions = $this->probability_weight();
	
				}
				return $predictions->combine_categorical( (array_key_exists($method,  $this->COMBINATION_WEIGHTS)) ? 
														  $this->COMBINATION_WEIGHTS[$method] : null, 
														 $with_confidence);
			
			}
		}


		function single_out_category($options) {
			/*
				Singles out the votes for a chosen category and returns a prediction
				for this category iff the number of votes reaches at least the given
				threshold.
			*/
			if ($options == null) {
				throw new Exception("No category and threshold information was found. Add threshold and category info");
			}

			foreach($options as $option) {
				if (!array_key_exists("threshold", $option) || !array_key_exists("category", $option) ) {
					throw new Exception("No category and threshold information was found. Add threshold and category info");
				}
 
			}

			$length = count($this->predictions);

			if ($options["threshold"] > $length) {
				throw new Exception("You cannot set a threshold value larger than ". length . "The ensemble has not enough models to use this threshold value.");
			}

			if ($options["threshold"] < 1) {
				throw new Exception("The threshold must be a positive value");
			}

			$category_predictions=array();
			$rest_of_predictions=array();

			foreach($this->predictions as $prediction) {

				if ($prediction["prediction"] == $options["category"]) {
					array_push($category_predictions, $prediction);	
				} else {
					array_push($rest_of_predictions, $prediction);
				}
			}

			if (count($category_predictions) >= $options["threshold"]) {
				return new MultiVote($category_predictions);
			}

			return new MultiVote($rest_of_predictions);

		}

		function probability_weight() {
			/*
				Reorganizes predictions depending on training data probability
			*/
			$predictions = array();
			foreach($this->predictions as $prediction) { 

				if (!array_key_exists("distribution", $prediction) || !array_key_exists("count", $prediction) ) {
					throw new Exception("Probability weighting is not available because distribution information is missing.");
				} 

				$total = $prediction["count"];

				if ($total < 1 || !is_numeric($total)) {
					throw new Exception("Probability weighting is not available because distribution seems to have ". $total . " as number of instances in a node");
				}

				$order = $prediction["order"];

				foreach($prediction["distribution"] as $key => $value) {
					array_push($predictions, array("prediction" => $value[0],
												   "probability" => floatval($value[1]) / $total,
												   "count" => $value[1],
												   "order" => $order));
				}

			}
			return $predictions;

		}

		function combine_categorical($weight_label=null, $with_confidence=false) {
			/*
				Returns the prediction combining votes by using the given weight:
				weight_label can be set as:
					None:          plurality (1 vote per prediction)
					'confidence':  confidence weighted (confidence as a vote value)
					'probability': probability weighted (probability as a vote value)
				
				If with_confidence is true, the combined confidence (as a weighted
				average of the confidences of the votes for the combined
				prediction) will also be given.
			*/
			$mode = array();

			$weight = 0;
			if ($weight_label == null) {
				$weight = 1;
			}

			foreach($this->predictions as $prediction) {

				if ($weight_label != null) {
				
					if (!in_array($weight_label, array_values($this->COMBINATION_WEIGHTS))) {
						throw new Exception("Wrong weight_label value.");
					}

					if (!array_key_exists($weight_label, $prediction)) {
						throw new Exception("Not enough data to use the selected prediction method. Try creating your model anew"); 
					} else {
						$weight = $prediction[$weight_label];
					}
				}

				$category = $prediction["prediction"];
				if (array_key_exists(strval($category),  $mode) ) {
					$mode[strval($category)] = array("count" => $mode[strval($category)]["count"] + $weight,
											 "order" => $mode[strval($category)]["order"]
											);

				} else {
					$mode[strval($category)] = array("count" => $weight, "order" => $prediction["order"]);
				}
				
			}
			function sort_mode_items($a, $b) {
            	$retval = $b["count"] - $a["count"];
            
            	if (!$retval) $retval= $a["order"] - $b["order"];

            	return $retval;
        	}

			uasort($mode, "sort_mode_items");
			reset($mode);
	
    		$prediction = key($mode);

			if ($with_confidence) {

				if (!array_key_exists('confidence', $this->predictions[0])) {
					return $this->weighted_confidence($prediction, $weight_label);
				} else {
					$combined_distribution = $this->combine_distribution();
					$distribution = $combined_distribution[0];
					$count = $combined_distribution[1];
					$combined_confidence = ws_confidence($prediction, $distribution, 1.96, $count); 

					return array($prediction, $combined_confidence); 
				}
	
			}
			return $prediction;
		}

		function combine_distribution($weight_label='probability') {
			/*
				Builds a distribution based on the predictions of the MultiVote
				Given the array of predictions, we build a set of predictions with
				them and associate the sum of weights (the weight being the
				contents of the weight_label field of each prediction)
			*/

			$distribution = array();
			$total = 0;

			foreach($this->predictions as $prediction) {
				if (!array_key_exists($weight_label, $prediction)) {
				 	throw new Exception("Not enough data to use the selected prediction method. Try creating your model anew.");
				}

				if (!array_key_exists($distribution, $prediction["prediction"]) ) {	
					$distribution[$prediction["prediction"]] = 0.0; 
				}

				$distribution[$prediction["prediction"]] += $prediction[$weight_label];	
				$total += $prediction["count"];
			}		

			if ($total > 0) {
				$new_distribution = array();
				foreach($distribution as $key => $value) {
					array_push($new_predictions, array($key,$value)); 
				}
  
			} else {
				$distribution = array();
			}
				
			return array($distribution, $total);
		}

		function weighted_confidence($combined_prediction, $weight_label) {
			/*
				Compute the combined weighted confidence from a list of predictions
			*/
			$predictions = array();	
			$check_confidence_and_weight_label = true;

			foreach($this->predictions as $prediction) {
				if ($prediction["prediction"] == $combined_prediction) {
					array_push($predictions, $prediction);
					if ($check_confidence_and_weight_label==true && (!array_key_exists($weight_label, $prediction) || !array_key_exists('confidence', $prediction)) ) {
						$check_confidence_and_weight_label=false;
					} 
				}
			}

			if (($weight_label != null && !is_string($weight_label)) || (!$check_confidence_and_weight_label) ) {
				throw new Exception("Not enough data to use the selected prediction method. Lacks " . $weight_label . " information.");
			}
	
			$final_confidence = 0.0;
			$total_weight = 0.0;
			$weight = 1;
			
			foreach($predictions as $prediction) {
				if ($weight_label != null) {
					$weight = $prediction[$weight_label];
				}

				$final_confidence += $weight*$prediction["confidence"];
				$total_weight += $weight;
			}

			$final_confidence = ($total_weight > 0) ? $final_confidence/$total_weight : NAN;

			return array($combined_prediction, $final_confidence);
		}

	}
?>
