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
function is_assoc($array){
    return !ctype_digit( implode('', array_keys($array)) );
}

function ws_confidence($prediction, $distribution, $ws_z=1.96, $ws_n=null) {
   /*
      Wilson score interval computation of the distribution for the prediction
      expected arguments:
         prediction: the value of the prediction for which confidence is computed
         distribution: a distribution-like structure of predictions and the associated weights. (e.g.
                   array(('Iris-setosa', 10), ('Iris-versicolor', 5))

         ws_z: percentile of the standard normal distribution
         ws_n: total number of instances in the distribution. If absent, the number is computed as the sum of weights in the
              provided distribution
   */
   if (!is_assoc($distribution)) {
      $new_distribution = array();
      foreach($distribution as $item) { 
         $new_distribution[$item[0]]=$item[1];
      }
      $distribution = $new_distribution;
   }

   $ws_p = $distribution[$prediction];
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

function merge_distributions($distribution, $new_distribution)
{
   /*
     Adds up a new distribution structure to a map formatted distribution
   */
   foreach($new_distribution as $key => $value)
   {
      if (!array_key_exists($key, $distribution) )  {
            $distribution[$key] = 0;
      }
       $distribution[$key] += $value;

   }

   return $distribution;

}

function merge_bins($distribution, $limit) {
   /*
      Merges the bins of a regression distribution to the given limit number
   */
   $length = count($distribution);
   if ($limit < 1 || $length <= $limit || $length < 2) {
      return $distribution;
   }

   $index_to_merge = 2;
   $shortest = INF;

   foreach (range(1, $length-1) as $index) {
      $distance = floatval($distribution[$index][0])-floatval($distribution[$index-1][0]);

      if (floatval($distance) < floatval($shortest)) {
         $shortest=$distance;
         $index_to_merge = $index;
      }
   }

   $new_distribution = array_slice($distribution, 0, ($index_to_merge-1));
   $left = $distribution[$index_to_merge-1];
   $right = $distribution[$index_to_merge];
   $new_bin = array((($left[0]*$left[1]) + ($right[0] * $right[1]) )/($left[1]+$right[1]), $left[1]+$right[1]);
   array_push($new_distribution, $new_bin);

   if ($index_to_merge < ($length - 1)) {
       $new_distribution = array_merge($new_distribution, array_slice($distribution, ($index_to_merge+1)));
   }

   return merge_bins($new_distribution, $limit);
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
   const BINS_LIMIT = 32;

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
         $this->predictions = $predictions;
      } else {
         array_push($this->predictions, $predictions);
      }

      $has_order = true;

      foreach($this->predictions as $prediction) {

         if (!array_key_exists('order', $prediction) ) {
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
         if (is_object($prediction)) {
           if (!is_numeric($prediction->prediction)) return false;
         } else {
           if (!is_numeric($prediction["prediction"])) return false;
         }
      }
      return true;
   }
   
   private function sort_joined_distribution_items($a, $b) {
      if ($a[0] < $b[0]) {
         return 1;
      } else if ($a[0] > $b[0]) {
         return -1;
      } else { 
         return 0;
      }   
   }

   public function grouped_distribution() {
     /*
        Returns a distribution formed by grouping the distributions of
	each predicted node.
     */
     $joined_distribution = array();
     $distribution_unit = 'counts';

     foreach($this->predictions as $prediction) {

        $joined_distribution = merge_distributions($joined_distribution, 
	                                           array($prediction['distribution'][0], 
						         $prediction['distribution'][1]));

        uasort($joined_distribution, array($this, "sort_joined_distribution_items"));
        $distribution = array();
        foreach($joined_distribution as $dis) {
           array_push($distribution, array($dis)); 
        }

        if ($distribution_unit == 'counts') {
           if (count($distribution) > MultiVote::BINS_LIMIT) {
             $distribution_unit = 'bins';
           } else {
             $distribution_unit = 'counts';
           } 
        }

        $distribution = merge_bins($distribution, MultiVote::BINS_LIMIT);

     }

     return array("distribution" => $distribution, "distribution_unit" => $distribution_unit); 

   }

   public function avg($instance, $with_confidence=false, $add_confidence=false, $add_distribution=false,
                          $add_count=false, $add_median=false, $add_min=false, $add_max=false) {
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
      $median_result=0.0;
      $instances = 0;
      $d_min = INF;
      $d_max = INF;
   
      #foreach($this->predictions as $prediction) {
      foreach($instance->predictions as $prediction) { 
         $result += $prediction->prediction;

         if ($add_median) {
            $median_result += $prediction->median;
         }

         if ($with_confidence or $add_confidence) {
            $confidence += $prediction->confidence;
         }  

         if ($add_count) {
           $instances += $prediction->count;
         }

         if ($add_min && $d_min > $prediction->min) {
            $d_min = $prediction->min;
         }

         if ($add_max && $d_max < $prediction->max) {
           $d_max = $prediction->max;
         }
      }

      if ($with_confidence) {
          return ($total > 0) ? array($result / $total, $confidence / $total) : array(NAN,0);
      }

      if ($add_confidence or $add_distribution or $add_count or
                $add_median or $add_min or $add_max) {

          $output =  array('prediction' => ($total > 0) ? $result/$total : NAN);

          if ($add_confidence) {
             $ouput["confidence"] = ($total > 0) ? $confidence/$total : 0;
          }
        
          if ($add_distribution) {
             $grouped_dis = $this->grouped_distribution();
             $output["distribution"] = $grouped_dis["distribution"];
             $output["distribution_unit"] = $grouped_dis["distribution_unit"]; 
          }
 
          if ($add_count) {
             $output["count"] = $instances;
          }
          
          if ($add_median) {
             $output["median"] = ($total > 0) ? $median_result/$total : NAN;
          }

          if ($add_min) {
             $output["min"] = $d_min;
          }

          if ($add_max) {
             $output["max"] = $d_max;
          }

          return $output;
      }

      return ($total > 0) ? $result / $total : NAN;

   }

   public function error_weighted($with_confidence=false, $add_confidence=false, $add_distribution=false,
                          $add_count=false, $add_median=false, $add_min=false, $add_max=false) {
      /*
         Returns the prediction combining votes using error to compute weight
         If with_confidences is true, the combined confidence (as the
         error weighted average of the confidences of the multivote
         predictions) is also returned
      */
      if (property_exists($this, 'predictions') && $this->predictions != null && $with_confidence) {

         foreach($this->predictions as $prediction) {
            if (!array_key_exists('confidence', $prediction)) {
               throw new Exception('Not enough data to use the selected prediction method. Try creating your model anew.');
            }
         }

      }

      $result = 0.0;
      $median_result=0.0;
      $top_range = 10;
      $combined_error = 0.0;
      $instances = 0;
      $d_min = INF;
      $d_max = INF;
      $normalization_factor = $this->normalize_error($top_range);
 

      if ($normalization_factor  == 0) {
         if ($with_confidence) {
            return array(NAN, 0);
         } else  {
            return NAN;
         }
      }

      if ($with_confidence or $add_confidence) {
         $combined_error = 0.0;
      }

      foreach($this->predictions as $prediction) {

         $result += $prediction->prediction * $prediction->_error_weight;
 
         if ($add_median) {
	   $median_result += ($prediction->median * $prediction->_error_weight); 
	 }

         if ($add_count) {
           $instances += $prediction->count; 
         }

         if ($add_min && $d_min > $prediction->min) {
           $d_min = $prediction->min;
         }        
         
         if ($add_max && $d_max < $prediction->max) {
           $d_max = $prediction->max;
         }

         if ($with_confidence or $add_confidence) {
            $combined_error += ($prediction->confidence * $prediction->_error_weight);
         }

         unset($prediction->_error_weight);
      }

      if ($with_confidence) {
          return array($result / $normalization_factor, $combined_error / $normalization_factor);
      } 
 
      if ($add_confidence or $add_distribution or $add_count or
          $add_median or $add_min or $add_max) {
          $output = array('prediction' => $result / $normalization_factor);
          if ($add_confidence) {
              $output["confidence"] = $combined_error / $normalization_factor;
          }

          if ($add_distribution) {
             $grouped_dis = $this->grouped_distribution();
             $output["distribution"] = $grouped_dis["distribution"];
             $output["distribution_unit"] = $grouped_dis["distribution_unit"];
          }
          
          if ($add_count) {
            $output["count"] = $instances;
          }

          if ($add_median) {
            $output["median"] =  $median_result / $normalization_factor ;
          }

          if ($add_min){
             $output["min"] = $d_min;
          } 
         
          if ($add_max){
             $output["max"] = $d_max;
          }

          return $output;
      }

      return $result / $normalization_factor; 
   }

   public function normalize_error($top_range) {
      /*
         Normalizes error to a [0, top_range] and builds probabilities
      */
      $error_values = array();
      if (property_exists($this, 'predictions') ) {

         foreach($this->predictions as $prediction) {
            if (!array_key_exists('confidence', $prediction)) {
               throw new Exception('Not enough data to use the selected prediction method. Try creating your model anew.');
            }
            array_push($error_values, $prediction->confidence);
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
		 $new_predictions = array();
         foreach($this->predictions as $prediction) {
            $delta = ($min_error - $prediction->confidence);
            $prediction->_error_weight = exp(($delta/$error_range)*$top_range);
            $normalize_factor+=$prediction->_error_weight;
			array_push($new_predictions, $prediction);
         }
		 $this->predictions = $new_predictions;
      } else {
         $new_predictions = array();
         foreach($this->predictions as $prediction) {
            $prediction->_error_weight = 1;
            array_push($new_predictions, $prediction);
         }
         $this->predictions = $new_predictions;
         $normalize_factor = count($this->predictions);
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
      return ($this->predictions != null) ? is_object(end($this->predictions)) ? end($this->predictions)->order +1 : end($this->predictions)["order"] + 1 : 0; 
      
   }

   public function combine($method=MultiVote::DEFAULT_METHOD, $with_confidence=false, 
                           $add_confidence=false, $add_distribution=false,
			   $add_count=false, $add_median=false, $add_min=false, $add_max=false, $options=null) {
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
         $new_predictions = array();
         foreach($this->predictions as $prediction) {
            if ($prediction->confidence == null) {
               $prediction->confidence = 0;
            }
            $new_predictions[] = $prediction;
         }

         $this->predictions=$new_predictions;
         if ($method == MultiVote::CONFIDENCE) {
            return $this->error_weighted($with_confidence, $add_confidence, 
                                         $add_distribution, $add_count, $add_median, $add_min, $add_max);
         } else {
            return $this->avg($this, $with_confidence, $add_confidence,
                              $add_distribution, $add_count, $add_median, $add_min, $add_max); 
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
                                        $with_confidence, $add_confidence, $add_distribution, $add_count);
      }
   }

   public function append($prediction_info) {
       /*Adds a new prediction into a list of predictions

           prediction_info should contain at least:
           - prediction: whose value is the predicted category or value

           for instance:
               {'prediction': 'Iris-virginica'}

           it may also contain the keys:
           - confidence: whose value is the confidence/error of the prediction
           - distribution: a list of [category/value, instances] pairs
                           describing the distribution at the prediction node
           - count: the total number of instances of the training set in the
                    node
       */
       if ($prediction_info != null) {
          $order = $this->next_order();
          $prediction_info->order = $order;
          array_push($this->predictions, $prediction_info);
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

         $total = $prediction->count;

         if ($total < 1 || !is_numeric($total)) {
            throw new Exception("Probability weighting is not available because distribution seems to have ". $total . " as number of instances in a node");
         }

         $order = $prediction->order;

         foreach($prediction->distribution as $key => $value) {
            $prediction = new stdClass();
            $prediction->prediction= $value[0];
            $prediction->probability= floatval($value[1]) / $total;
            $prediction->count =$value[1];
            $prediction->order = $order;

            array_push($predictions, $prediction);
         }

      }
      return $predictions;

   }

   function combine_categorical($weight_label=null, $with_confidence=false, $add_confidence=false, 
                                $add_distribution=false, $add_count=False) {
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
      $instances = 0;

      foreach($this->predictions as $prediction) {
         if ($weight_label != null) {
         
            if (!in_array($weight_label, array_values($this->COMBINATION_WEIGHTS))) {
               throw new Exception("Wrong weight_label value.");
            }

            if (!array_key_exists($weight_label, $prediction)) {
               throw new Exception("Not enough data to use the selected prediction method. Try creating your model anew"); 
            } else {
               $weight = is_object($prediction) ? $prediction->{$weight_label} : $prediction[$weight_label];
            }
         }
         if (is_object($prediction)) { 
           $category = $prediction->prediction;
         } else {
           $category = $prediction["prediction"];
         }
 
	 if ($add_count) {
	    $instances += $prediction->count;
	 }
	 

         if (array_key_exists(strval($category),  $mode) ) {
            $mode[strval($category)] = array("count" => $mode[strval($category)]["count"] + $weight,
                               "order" => $mode[strval($category)]["order"]
                              );

         } else {
            $mode[strval($category)] = array("count" => $weight, "order" => is_object($prediction) ? $prediction->order : $prediction["order"]);
         }
         
      }

      uasort($mode, array($this, "sort_mode_items"));
      
      reset($mode);

      $prediction = key($mode);

      if ($with_confidence or $add_confidence) {
         if (array_key_exists('confidence', $this->predictions[0])) {
            return $this->weighted_confidence($prediction, $weight_label);
         } else {
            $combined_distribution = $this->combine_distribution();
            $distribution = $combined_distribution[0];
            $count = $combined_distribution[1];
            $combined_confidence = ws_confidence($prediction, $distribution, 1.96, $count); 
         }

      }
 
      if ($with_confidence) {
         return array($prediction, $combined_confidence); 
      }

      if ($add_confidence or $add_distribution or $add_count) {
         $output = array("prediction" => $prediction);
	 if ($add_confidence) {
	   $output["confidence"] = $combined_confidence;
	 }

	 if ($add_distribution) {
            $grouped_dis = $this->grouped_distribution();
            $output["distribution"] = $grouped_dis["distribution"];
            $output["distribution_unit"] = $grouped_dis["distribution_unit"];
	 }

      }


      return $prediction;
   }

   private function sort_mode_items($a, $b) {
      if ($a["count"] < $b["count"]) {
         return 1;
      } else if ($a["count"] > $b["count"]) {
         return -1;
      }  else {
         if ($a["order"] < $b["order"]) {
            return -1;
         }
         return 0;
      }    
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

         if (!array_key_exists($prediction->prediction, $distribution) ) {   
            $distribution[$prediction->prediction] = 0.0; 
         }

         $distribution[$prediction->prediction] += $prediction->$weight_label; 
         $total += $prediction->count;
      }      

      if ($total > 0) {
         $new_distribution = array();
         foreach($distribution as $key => $value) {
            array_push($new_distribution, array($key,$value)); 
         }
         $distribution = $new_distribution; 
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
         if ($prediction->prediction == $combined_prediction) {
            array_push($predictions, $prediction);
            if ($check_confidence_and_weight_label==true && (!array_key_exists($weight_label, $prediction) || !array_key_exists('confidence', $prediction)) ) {
               $check_confidence_and_weight_label=false;
            } 
         }
      }

      if ($weight_label != null && (!is_string($weight_label) || (!$check_confidence_and_weight_label)) ) {
         throw new Exception("Not enough data to use the selected prediction method. Lacks " . $weight_label . " information.");
      }

      $final_confidence = 0.0;
      $total_weight = 0.0;
      $weight = 1;
      
      foreach($predictions as $prediction) {
         if ($weight_label != null) {
            $weight = $prediction->$weight_label;
         }

         $final_confidence += $weight*$prediction->confidence;
         $total_weight += $weight;
      }

      $final_confidence = ($total_weight > 0) ? $final_confidence/$total_weight : NAN;

      return array($combined_prediction, $final_confidence);
   }
   
   function extend($predictions_info) {
      /*Given a list of predictions, extends the list with another list of
           predictions and adds the order information. For instance,
           predictions_info could be:

                [{'prediction': 'Iris-virginica', 'confidence': 0.3},
                 {'prediction': 'Iris-versicolor', 'confidence': 0.8}]
           where the expected prediction keys are: prediction (compulsory),
           confidence, distribution and count.
       */
       if (is_array($predictions_info) ) {
         $order = next_order();
         $i=0;
         foreach($predictions_info as $prediction) {
	    if (is_array($prediction)) {
	      $prediction['order'] = $order+$i;
	      array_push($this->predictions, $prediction);
	    } else {
	       error_log("WARNING: failed to add the prediction.\n Only dict like predictions are expected\n");
	    }
            $i+=1;
         } 
       } else {
         error_log("WARNING: failed to add the predictions.\nOnly a list of dict-like predictions are expected."); 
       }
   }

   function append_row($prediction_row, $prediction_headers=array('prediction', 'confidence', 'order', 'distribution',
                         'count')) {
    			 
      /*Adds a new prediction into a list of predictions

           prediction_headers should contain the labels for the prediction_row
           values in the same order.

           prediction_headers should contain at least the following string
           - 'prediction': whose associated value in prediction_row
                           is the predicted category or value

           for instance:
               prediction_row = ['Iris-virginica']
               prediction_headers = ['prediction']

           it may also contain the following headers and values:
           - 'confidence': whose associated value in prediction_row
                           is the confidence/error of the prediction
           - 'distribution': a list of [category/value, instances] pairs
                             describing the distribution at the prediction node
           - 'count': the total number of instances of the training set in the
                      node
      */			

      if (is_array($prediction_row) && is_array($prediction_headers) && count($prediction_row) == count($prediction_headers) && in_array("prediction", $prediction_headers)) { 
         $order =  $this->next_order();
         try {
           $index = array_search("order", $prediction_headers);
           $prediction_row[$index] = $order;
         } catch  (Exception $e) {
           array_push($prediction_headers, "order");
           array_push($prediction_row, "order");
         }
         $prediction_info = array();
         $i=0;
         foreach($prediction_row as $prediction) {
            $prediction_info[$prediction_headers[$i]] = $prediction_row[$i];
            $i+=1;
         }

         array_push($this->predictions, $prediction_info);

      } else {
        error_log("WARNING: failed to add the prediction.\n The row must have label 'prediction' at least.");
      }
 
   }

}
?>
