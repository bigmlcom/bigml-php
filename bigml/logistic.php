<?php
#
# Copyright 2016 BigML
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

namespace BigML;

if (!class_exists('BigML\BigML')) {
   include('bigml.php');
}

if (!class_exists('BigML\ModelFields')) {
  include('modelfields.php');
}

if (!class_exists('BigML\Predicate')) {
  include('predicate.php');
}

if (!class_exists('BigML\Cluster')) {
  include('cluster.php');
}

define("EXPANSION_ATTRIBUTES", json_encode(array("categorical" => "categories",
                                                 "text" => "tag_cloud",
                                                 "items" => "items")));

define("OPTIONAL_FIELDS", json_encode(array('categorical', 'text', 'items')));

function transform_unique_terms($unique_terms) {
    /*
      Turns the associative array in $unique_terms into a list of
      lists, which is the expected input for category_probability.
    */

    $term_and_count = $unique_terms[0];

    foreach ($term_and_count as $field_id => $content) {
        $new_array = [];
        foreach ($content as $term => $count) {
            $new_array[] = array($term, $count);
        }
        $term_and_count[$field_id] = $new_array;
    }

    return array($term_and_count, $unique_terms[1]);
}

class LogisticRegression extends ModelFields {
   /*
   A lightweight wrapper around a logistic regression model.

   Uses a BigML remote logistic regression model to build a local version
   that can be used to generate predictions locally.
   */

   public $resource_id = null;
   public $input_fields;
   public $term_forms;
   public $tag_clouds;
   public $term_analysis;
   public $items;
   public $item_analysis;
   public $categories;
   public $coefficients;
   public $data_field_types;
   public $field_codings;
   public $bias = null;
   public $missing_coefficients;
   public $c = null;
   public $eps = null;
   public $lr_normalize = null;
   public $balance_fields = null;
   public $regularization = null;
   public $numeric_fields;

   public function __construct($logistic_regression, $api=null, $storage="storage") {
      $this->input_fields=array();
      $this->term_forms=array();
      $this->tag_clouds = array();
      $this->term_analysis= array();
      $this->items=array();
      $this->item_analysis=array();
      $this->categories=array();
      $this->data_field_types=array();
      $this->numeric_fields = array();
      $this->field_codings = array();
      $this->bias = null;
      $this->missing_numerics = null;
      $this->c = null;
      $this->eps = null;
      $this->lr_normalize = null;
      $this->balance_fields = null;
      $this->regularization = null;
      $old_coefficients = false;

      if ($api == null) {
         $api = new BigML(null, null, null, $storage);
      }

      if (is_string($logistic_regression)) {

         if (file_exists($logistic_regression))
         {
            $logistic_regression = json_decode(file_get_contents($logistic_regression));
         } elseif (!($api->_checkLogisticRegressionId($logistic_regression)) ) {
            error_log("Wrong logistic regression id");
            return null;
         } else {
            $logistic_regression = $api->retrieve_resource($logistic_regression,
                                                           BigML::ONLY_MODEL);
         }
      }

      if ($logistic_regression == null || !property_exists($logistic_regression, 'resource') ) {
         error_log("Cannot create the Model instance. Could not find the 'logistic_regression' key in the resource");
         throw new \Exception('Cannot create the logistic regression instance. Could not find the logistic regression key in the resource');
      }

      if (property_exists($logistic_regression, "object") && property_exists($logistic_regression->object, "status") && $logistic_regression->object->status->code != BigMLRequest::FINISHED ) {
         throw new \Exception("The logistic_regression isn't finished yet");
      }

      if (property_exists($logistic_regression, "object") && $logistic_regression->object instanceof \STDClass) {
         $logistic_regression=$logistic_regression->object;
      }

      if (property_exists($logistic_regression, "logistic_regression") && $logistic_regression->logistic_regression instanceof \STDClass) {

         if ($logistic_regression->status->code == BigMLRequest::FINISHED) {

            $this->input_fields=property_exists($logistic_regression, "input_fields") ? $logistic_regression->input_fields : array();
            $this->dataset_field_types=property_exists($logistic_regression, "dataset_field_types") ? $logistic_regression->dataset_field_types : array();
            $objective_field = $logistic_regression->objective_fields;

            $logistic_regression_info= $logistic_regression->logistic_regression;
            $fields = property_exists($logistic_regression_info, "fields") ? $logistic_regression_info->fields : array();

            if (is_null($this->input_fields) or empty($this->input_fields)) {
	       $this->input_fields=array();
	       $fields_sorted_by_column_number=array();
	       foreach ($fields as $field_id => $field) {
	         $a[$field_id]=$field->column_number;
	       }
	       asort($fields_sorted_by_column_number);
	       foreach ($fields_sorted_by_column_number as $key => $value) {
	          array_push($this->input_fields, $key);
	       }
	    }

            $this->coefficients=array();
	    if (property_exists($logistic_regression_info, "coefficients")) {
              $j=0;
	      foreach ($logistic_regression_info->coefficients as $key => $coefficient) {
	         $this->coefficients[$coefficient[0]] = $coefficient[1];
                 if ($j == 0 and !is_array($coefficient[1])) {
                    $old_coefficients=true;
                 }
                 $j+=1;
	      }
	    }

            $this->bias = property_exists($logistic_regression_info, "bias") ? $logistic_regression_info->bias : 0;
            $this->c = property_exists($logistic_regression_info, "c") ? $logistic_regression_info->c : null;
            $this->eps = property_exists($logistic_regression_info, "eps") ? $logistic_regression_info->eps : null;
            $this->lr_normalize = property_exists($logistic_regression_info, "normalize") ? $logistic_regression_info->normalize : null;
	    $this->balance_fields = property_exists($logistic_regression_info, "balance_fields") ? $logistic_regression_info->balance_fields : null;
            $this->regularization = property_exists($logistic_regression_info, "regularization") ? $logistic_regression_info->regularization : null;
            $this->field_codings = property_exists($logistic_regression_info, "field_codings") ? $logistic_regression_info->field_codings : array();
	    $this->missing_numerics =  property_exists($logistic_regression_info, "missing_numerics") ? $logistic_regression_info->missing_numerics : false;

            $objective_id = extract_objective($objective_field);
            foreach ($fields as $field_id => $field) {
               if ($field->optype == 'text') {
                  $this->term_forms[$field_id] = $field->summary->term_forms;

                  $this->tag_clouds[$field_id] = array();
                  foreach ($field->summary->tag_cloud as $tag => $value) {
                    array_push($this->tag_clouds[$field_id], $value[0]);
                  }

                  $this->term_analysis[$field_id] = $field->term_analysis;

               } else if ($field->optype == 'items') {
                  $this->items[$field_id] = array();
                  foreach ($field->summary->items as $item => $value) {
                     array_push( $this->items[$field_id], $value[0]);
                  }
                  $this->item_analysis[$field_id] = $field->item_analysis;

               } else if ($field->optype == 'categorical') {
	          $this->categories[$field_id] = array();
                  foreach($field->summary->categories as $key => $value) {
                     array_push($this->categories[$field_id], $value[0]);
                  }
               }

               if ($this->missing_numerics && $field->optype == "numeric") {
	           $this->numeric_fields[$field_id] = true;
	       }
            }

            parent::__construct($fields, $objective_id);
            $this->field_codings = property_exists($logistic_regression_info, "field_codings") ? $logistic_regression_info->field_codings : array();
            $this->format_field_codings();

            foreach ($this->field_codings as $field_id => $field_coding) {
                if ( array_key_exists($field_id, $fields) && array_key_exists($field_id,$this->inverted_fields) ) {
                   $this->field_codings[$this->inverted_fields[$field_id]] = $this->field_codings[$field_id];
                   unset($this->field_codings[$field_id]);
                }
            }

            if ($old_coefficients) {
               $this->map_coefficients();
            }

         } else {
            throw new \Exception("The logistic regression isn't finished yet");
         }
      } else {
         throw new \Exception("Cannot create the Model instance. Could not find the 'logistic regression' key in the resource:\n\n" . $logistic_regression);
      }

   }
    
   public function predict_probability($input_data, $by_name=true, $compact=false) {
         // Predicts a probability for each possible output class,
         // based on input values.  The input fields must be a dictionary
         // keyed by field name or field ID.
 
         // :param input_data: Input data to be predicted
         // :param by_name: Boolean that is set to True if field_names (as
         //                 alternative to field ids) are used in the
         //                 input_data dict
         // :param compact: If False, prediction is returned as a list of maps, one
         //                 per class, with the keys "prediction" and "probability"
         //                 mapped to the name of the class and it's probability,
         //                 respectively.  If True, returns a list of probabilities
         //                 ordered by the sorted order of the class names.

       $distribution = $this->predict($input_data, $by_name)['distribution'];
       
       $cmp = function($a, $b) {
           return strcmp($a["category"], $b["category"]);
       };

       usort($distribution, $cmp);

       if ($compact) {
           $output = [];
           foreach ($distribution as $category) {
               $output[] = $category["probability"];
           } 
           return $output;
       } else {
           return $distribution;
       }
   }

   public function predict($input_data, $by_name=true, $add_unused_fields=false) {
      /* "Returns the class prediction and the probability distribution */
      # By default the input fields must be keyed by field name but you can use
      # `by_name` to input them directly keyed by id.
      # input_data: Input data to be predicted
      # by_name: Boolean, true if input_data is keyed by names
      # add_unused_fields: Boolean, if True adds the information about the
      #                    fields in the input_data that are not being used
      #                    in the model as predictors.

      # Checks and cleans input_data leaving the fields used in the model

      $new_data = $this->filter_input_data($input_data, $by_name, $add_unused_fields);

      if ($add_unused_fields) {
         $input_data = $new_data[0];
	 $unused_fields = $new_data[1];
      } else {
         $input_data = $new_data;
      }

      # In case that missing_numerics is False, checks that all numeric
      # fields are present in input data.
      if ($this->missing_numerics == false) {

         foreach ($fields as $field_id => $field) {
           if (!in_array($field->optype, json_decode(OPTIONAL_FIELDS, true)) and
               !array_key_exists($field_id, $input_data) ) {
               throw new \Exception("Failed to predict. Input data must contain values for all numeric fields to get a logistic regression prediction.");
           }
         }

      }

      #Strips affixes for numeric values and casts to the final field type
      cast($input_data, $this->fields);

      if (!is_null($this->balance_fields) && $this->balance_fields) {

         foreach ($input_data as $field => $value) {
            if ($this->fields->{$field}->optype == 'numeric'){
	       $mean = $this->fields->{$field}->summary->mean;
	       $stddev = $this->fields->{$field}->summary->standard_deviation;
           # if stddev is not positive, we only substract the mean
           if ($stddev > 0) {
	           $input_data[$field] = ($input_data[$field] - $mean) / $stddev;
           } else {
               $input_data[$field] = $input_data[$field] - $mean;
           }
	    }
	 }

      }

      #Compute text and categorical field expansion
      $unique_terms = $this->get_unique_terms($input_data, true, "pair_count");
      $input_data = $unique_terms[1];
      $unique_terms = $unique_terms[0];

      $probablities=array();
      $total=0;

      foreach (array_keys($this->coefficients) as $category) {
         $probability = $this->category_probability($input_data, $unique_terms, $category);
         $order = array_search($category, $this->categories[$this->objective_id]);
         $probabilities[$category] = array("category" => $category,
                                           "probability" => $probability,
                                           "order" => $order);

         $total += $probabilities[$category]["probability"];

      }
      foreach ($probabilities as $category => $value) {
         $probabilities[$category]["probability"] = $probabilities[$category]["probability"]/$total;
      }

      uasort($probabilities, array($this, "sort_probabilities_items"));

      foreach ($probabilities as $prediction => $probability) {
        unset($probabilities[$prediction]['order']);
      }

      reset($probabilities);
      $result=array('prediction'=> key($probabilities),
                    'probability'=> $probabilities[key($probabilities)]["probability"],
		    'distribution' => array());
      foreach ($probabilities as $category => $probability) {
         array_push($result["distribution"], array("category"=> $category, "probability" => $probabilities[$category]["probability"]));
      }

      if ($add_unused_fields) {
         $result["unused_fields"]=$unused_fields;
      }
      return $result;
   }

   public function category_probability($input_data, $unique_terms, $category) {
     /* Computes the probability for a concrete category */
     $probability=0;
     $norm2 = 0;
     # the bias term is the last in the coefficients list
     $bias = $this->coefficients[$category][count($this->coefficients[$category]) - 1][0];

     foreach ($input_data as $field_id => $value) {
       $coefficients = $this->get_coefficients($category, $field_id);
       $probability += $coefficients[0] * $input_data[$field_id];
       $norm2 += pow($input_data[$field_id], 2);
     }

     foreach ($unique_terms as $field_id => $value) {
       if ( in_array($field_id, $this->input_fields) ) {
         $coefficients = $this->get_coefficients($category, $field_id);
         foreach($unique_terms[$field_id] as $term_value) {
            $term =  $term_value[0];
            $occurrences=$term_value[1];
            try {
              $one_hot = true;
              if ( array_key_exists($field_id, $this->tag_clouds) ) {
                $index = array_search($term, $this->tag_clouds[$field_id]);
              } else if (array_key_exists($field_id, $this->items)) {
                $index = array_search($term, $this->items[$field_id]);
              } else if (array_key_exists($field_id, $this->categories) and
                        (!array_key_exists($field_id, $this->field_codings) or array_keys($this->field_codings[$field_id])[0] == "dummy" ) ) {

                $index = array_search($term, $this->categories[$field_id]);
              } else if (array_key_exists($field_id, $this->categories) ) {
                $one_hot = false;
                $index = array_search($term, $this->categories[$field_id]);
                $coeff_index = 0;
                foreach(array_values($this->field_codings[$field_id])[0] as $contribution) {
                      $probability += $coefficients[$coeff_index] * $contribution[$index] * $occurrences;
                      $coeff_index+=1;
                }

              }

              if ($one_hot) {
                $probability += $coefficients[$index]*$occurrences;
              }
              $norm2 += pow($occurrences, 2);

            } catch (Exception $e) {
              continue;
            }
         }

       }
     }

     foreach ($this->numeric_fields as $field_id => $value) {
         if ( in_array($field_id, $this->input_fields) ) {
            $coefficients = $this->get_coefficients($category, $field_id);
            if (!array_key_exists($field_id, $input_data)) {
              $probability += $coefficients[1];
	      $norm2 += 1;
            }
	 }

     }

     foreach ($this->tag_clouds as $field_id => $value) {
         if ( in_array($field_id, $this->input_fields) ) {
             $coefficients = $this->get_coefficients($category, $field_id);
             if (!array_key_exists($field_id, $unique_terms) or !$unique_terms[$field_id]) {
	       $probability += $coefficients[count($value)];
	       $norm2 += 1;
             }
         }
     }

     foreach ($this->items as $field_id => $value) {
        if ( in_array($field_id, $this->input_fields) ) {
           $coefficients = $this->get_coefficients($category, $field_id);
           if (!array_key_exists($field_id, $unique_terms) or !$unique_terms[$field_id]) {
	      $norm2 += 1;
	      $probability += $coefficients[count($value)];
           }
        }
     }

     foreach ($this->categories as $field_id => $value) {
        if ( in_array($field_id, $this->input_fields) ) {
           $coefficients = $this->get_coefficients($category, $field_id);
           if (!array_key_exists($field_id, $unique_terms) or !$unique_terms[$field_id]) {
	      $norm2 += 1;
              if (!array_key_exists($field_id, $this->field_codings) or array_keys($this->field_codings->{$field_id})[0] == "dummy" )  {
                $probability += $coefficients[count($value)];
              } else {
                # codings are given as arrays of coefficients. The
                # last one is for missings and the previous ones are
                # one per category as found in summary
                $coeff_index = 0;
                foreach($this->field_codings[$field_id] as $key => $value) {
                   foreach ($value[0] as $contribution) {
                      $probability += $coefficients[$coeff_index] * end($contribution);
                      $coeff_index+=1;
                   }
                   break;
                }

              }
           }
        }
     }

     $probability += $bias;
     if ($this->bias != 0) {
       $norm2 += 1;
     }

     if (!is_null($this->lr_normalize) and $this->lr_normalize) {
       try {
         $probability /= sqrt($norm2);
       } catch (Exception $e) {
         $probability = INF;
       }
     }

     try {
       $probability = 1 / (1+ exp(-$probability));
     } catch (Exception $e) {
       $probability = ($probability < 0) ? 0 : 1;
     }

     return $probability;

   }

   public function map_coefficients() {
     /*
        Maps each field to the corresponding coefficients subarray
     */

     $field_ids=array();

     foreach ($this->input_fields as $field_id) {
        if ($field_id != $this->objective_id)
           array_push($field_ids, $field_id);
     }

     $shift = 0;
     foreach ($field_ids as $field_id) {
        $optype = $this->fields->{$field_id}->optype;

        if (in_array($optype, array_keys(json_decode(EXPANSION_ATTRIBUTES, true)))) {
          # text and items fields have one coefficient per
          # text plus a missing terms coefficient plus a bias
          # coefficient
          # categorical fields too, unless they use a non-default
          # field coding.

          if ($optype != 'categorical' or
              !array_key_exists($field_id, $this->field_codings) or
              array_keys($this->field_codings->{$field_id})[0] == "dummy") {
             $length = count($this->fields->{$field_id}->summary->{json_decode(EXPANSION_ATTRIBUTES, true)[$optype]});
             $length += 1;
          } else {
             $length = count(array_values($this->field_codings->{$field_id})[0]);
          }

        } else {
           # numeric fields have one coefficient and an additional one
           # if self.missing_numerics is True
           $length = ($this->missing_numerics) ? 2 : 1;
        }
        $this->fields->{$field_id}->coefficients_length = $length;
        $shift += $length;
     }

     $this->group_coefficients();
   }

   public function get_coefficients($category, $field_id) {
     /* Returns the set of coefficients for the given category and fieldIds */
     $coeff_index = array_search($field_id, $this->input_fields);
     return $this->coefficients[$category][$coeff_index];
   }

   public function group_coefficients() {
     /* Groups the coefficients of the flat array in old formats to the
       grouped array, as used in the current notation
     */
     $coefficients = clone $this->coefficients;
     $this->flat_coefficients = $coefficients;
     foreach ($this->coefficients as $key => $category) {
        $this->coefficients[$category] = array();
        foreach ($this->input_fields as $field_id => $value) {
           $shift = $this->fields->{$field_id}->coefficients_shift;
           $length =  $this->fields->{$field_id}->coefficients_length;
           $coefficients_group = array_slice($coefficients[$category], $shift, $length + $shift);
           array_push($this->coefficients[$category],
                      array($coefficients[$category][count($coefficients[$category]) -1]));
        }
     }

   }

   public function format_field_codings() {
     /*  Changes the field codings format to the dict notation */
     if (is_array($this->field_codings)) {
        $this->field_codings_list = array_slice($this->field_codings, 0);
        $field_codings = array_slice($this->field_codings, 0);
        $this->field_codings=array();

        foreach ($field_codings as $index => $element) {
          $field_id = $element->field;
          if ($element->coding == "dummy") {
            $this->field_codings[$field_id] = array($element->coding => $element->dummy_class);
          } else {
            $this->field_codings[$field_id] = array($element->coding => $element->coefficients);
          }
        }
     }

   }

   private function sort_probabilities_items($a, $b) {
      if ($a["probability"] < $b["probability"]) {
         return 1;
      } else if ($a["probability"] > $b["probability"]) {
         return -1;
      } else {
          if ($a["order"] < $b["order"]) {
            return -1;
          } else if ($a["order"] > $b["order"]) {
	    return 1;
	  }
          return 0;
      }
   }
}
