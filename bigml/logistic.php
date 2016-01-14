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

if (!class_exists('modelfields')) {
  include('modelfields.php'); 
}

if (!class_exists('predicate')) {
  include('predicate.php'); 
}

if (!class_exists('cluster')) {
  include('cluster.php');
}

define("EXPANSION_ATTRIBUTES", json_encode(array("categorical" => "categories", 
                                                 "text" => "tag_cloud",
                                                 "items" => "items")));

define("OPTIONAL_FIELDS", json_encode(array('categorical', 'text', 'items')));


function logistic_get_unique_terms($terms, $term_forms, $tag_cloud) {
  /* 
     Extracts the unique terms that occur in one of the alternative forms in
     term_forms or in the tag cloud.
   */

  $extend_forms = array();

  foreach ($term_forms as $term => $forms) {

     foreach ($forms as $form => $value) {
         $extend_forms[$value] = $term;
     }
  }

  $terms_set=array();

  foreach ($terms as $key => $term) {
      if (in_array($term, $tag_cloud)) {
          if (!array_key_exists($term, $terms_set)) {
             $terms_set[$term] = 0;
          }

          $terms_set[$term] +=1;

      } else if (array_key_exists($term, $extend_forms)) {
        $term = $extend_forms[$term];
         if (!array_key_exists($term, $terms_set)) {
            $terms_set[$term] = 0;
         }
         $terms_set[$term] +=1;
      }

  }

  $result = array();

  foreach ($terms_set as $key => $value) {
    array_push($result, array($key, $value)); 
  }

  return $result;

}


class LogisticRegression extends ModelFields {
   /*
   A lightweight wrapper around a logistic regression model.

   Uses a BigML remote logistic regression model to build a local version
   that can be used to generate predictions locally.
   */

   public $resource_id = null;
   public $term_forms;
   public $tag_clouds;
   public $term_analysis;
   public $items;
   public $item_analysis;
   public $categories;
   public $coefficients;
   public $data_field_types;
   public $bias = null;
   public $missing_coefficients; 
   public $c = null;
   public $eps = null;
   public $lr_normalize = null;
   public $regularization = null;
   public $numeric_fields;

   public function __construct($logistic_regression, $api=null, $storage="storage") {
      $this->term_forms=array();
      $this->tag_clouds = array();
      $this->term_analysis= array();
      $this->items=array();
      $this->item_analysis=array();
      $this->categories=array();
      $this->data_field_types=array();
      $this->numeric_fields = array(); 

      if ($api == null) {
         $api = new BigML(null, null, null, $storage);
      }

      if (is_string($logistic_regression)) {

         if (file_exists($logistic_regression))
         {
            $logistic_regression = json_decode(file_get_contents($logistic_regression));
         } else if (!($api::_checkModelId($logistic_regression)) ) {
            error_log("Wrong logistic regression id");
            return null;
         } else {
            $logistic_regression = $api::retrieve_resource($logistic_regression, $api::ONLY_MODEL);
         }
      }

      if ($logistic_regression == null || !property_exists($logistic_regression, 'resource') ) {
         error_log("Cannot create the Model instance. Could not find the 'logistic_regression' key in the resource");
         throw new Exception('Cannot create the logistic regression instance. Could not find the logistic regression key in the resource');
      }

      if (property_exists($logistic_regression, "object") && property_exists($logistic_regression->object, "status") && $logistic_regression->object->status->code != BigMLRequest::FINISHED ) {
         throw new Exception("The logistic_regression isn't finished yet");
      }

      if (property_exists($logistic_regression, "object") && $logistic_regression->object instanceof STDClass) {
         $logistic_regression=$logistic_regression->object;
      }

      if (property_exists($logistic_regression, "logistic_regression") && $logistic_regression->logistic_regression instanceof STDClass) {

         if ($logistic_regression->status->code == BigMLRequest::FINISHED) {

            $this->dataset_field_types=property_exists($logistic_regression, "dataset_field_types") ? $logistic_regression->dataset_field_types : array();
            $objective_field = $logistic_regression->objective_fields;

            $logistic_regression_info= $logistic_regression->logistic_regression;
            $fields = property_exists($logistic_regression_info, "fields") ? $logistic_regression_info->fields : array();

            $this->coefficients=array();
	    if (property_exists($logistic_regression_info, "coefficients")) {
	      foreach ($logistic_regression_info->coefficients as $key => $coefficient) {
	         $this->coefficients[$coefficient[0]] = $coefficient[1];
	      } 
	    }

            $this->bias = property_exists($logistic_regression_info, "bias") ? $logistic_regression_info->bias : 0;
            $this->c = property_exists($logistic_regression_info, "c") ? $logistic_regression_info->c : null;
            $this->eps = property_exists($logistic_regression_info, "eps") ? $logistic_regression_info->eps : null; 
            $this->normalize = property_exists($logistic_regression_info, "normalize") ? $logistic_regression_info->normalize : null;
            $this->regularization = property_exists($logistic_regression_info, "regularization") ? $logistic_regression_info->regularization : null;
	    $this->missing_numerics =  property_exists($logistic_regression_info, "missing_numerics") ? $logistic_regression_info->missing_numerics : false;

            $objective_id = extract_objective($objective_field);
            foreach ($fields as $field_id => $field) {
               if ($field->optype == 'text') {
                  $this->term_forms[$field_id] = $field->summary->term_forms;
                  
                  $this->tag_clouds[$field_id] = array();
                  # TODO revisar
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
 
            $this->map_coefficients();
 
         } else {
            throw new Exception("The logistic regression isn't finished yet");
         }
      } else {
         throw new Exception("Cannot create the Model instance. Could not find the 'logistic regression' key in the resource:\n\n" . $logistic_regression);
      }

   }

   public function predict($input_data, $by_name=true) {
      /* "Returns the class prediction and the probability distribution */

      # Checks and cleans input_data leaving the fields used in the model

      $input_data = $this->filter_input_data($input_data, $by_name);
      foreach ($this->fields as $field_id => $field) {
         if (!in_array($field->optype, json_decode(OPTIONAL_FIELDS)) && !array_key_exists($field_id, $input_data)) {
	    throw new Exception("Failed to predict. Input data must contain values for all numeric 
	                         fields to get a logistic regression prediction");
	 }
      }

      #Strips affixes for numeric values and casts to the final field type
      cast($input_data, $this->fields);

      #Compute text and categorical field expansion
      $unique_terms = $this->get_unique_terms($input_data);

      $probablities=array();
      $total=0;

      foreach ($this->categories[$this->objective_id] as $category) {
         $coefficients = $this->coefficients[$category];
	 $probabilities[$category] = $this->category_probability($input_data, $unique_terms, $coefficients);
	 $total += $probabilities[$category];
      }
  
      foreach ($probabilities as $key => $value) {
         $probabilities[$key] =  $value/$total;
      }

      arsort($probabilities);

      reset($probabilities);
      $result=array('prediction'=> key($probabilities),
                    'probability'=> $probabilities[key($probabilities)],
		    'distribution' => array());

      foreach ($probabilities as $category => $probability) {
         array_push($result["distribution"], array("category"=> $category, "probability" => $probability));
      }

      return $result;
   }
 
   public function category_probability($input_data, $unique_terms, $coefficients) {
     /* Computes the probability for a concrete category */
     $probability=0;
     foreach ($input_data as $field_id => $value) {
       $shift = $this->fields->{$field_id}->coefficients_shift;
       $probability += $coefficients[$shift]* $input_data[$field_id];
     }

     foreach ($unique_terms as $field_id => $value) {
          $shift = $this->fields->{$field_id}->coefficients_shift;
          $term = reset($unique_terms[$field_id]);
	  $ocurrences = end($unique_terms[$field_id]);
          try { 
	    if ( array_key_exists($field_id, $this->tag_clouds) ) {
	      $index = array_search($term, $this->tag_clouds[$field_id]);
	    } else if (array_key_exists($field_id, $this->items)) {
	      $index = array_search($term, $this->items[$field_id]); 
	    } else if (array_key_exists($field_id, $this->categories)) {
	      $index = array_search($term, $this->categories[$field_id]); 
	    }
	    $probability+=$coefficients[$shift+$index] * $occurrences;

	  } catch (Exception $e) {
	    continue;
	  }   
     }
 
     foreach ($this->numeric_fields as $field_id => $value) {
         if (!array_key_exists($field_id, $input_data)) {
	    $shift = $this->fields->{$field_id}->coefficients_shift +1;
	    $probability += $coefficients[$shift];
	 }

     }

     foreach ($this->tag_clouds as $field_id => $value) {
          $shift = $this->fields->{$field_id}->coefficients_shift;
          if (!array_key_exists($field_id, $unique_terms) or !$unique_terms[$field_id]) {
	     $probability += $coefficients[$shift+ count($this->tag_clouds[$field_id])]; 
	  } 
     }

     foreach ($this->items as $field_id => $value) {
	  $shift = $this->fields->{$field_id}->coefficients_shift;
          if (!array_key_exists($field_id, $unique_terms) or !$unique_terms[$field_id]) {
	    $probability += $coefficients[$shift+ count($this->items[$field_id])];
	  }
     }

     foreach ($this->categories as $field_id => $value) {
	   if (($field_id != $this->objective_id) && !array_key_exists($field_id, $unique_terms)) {
	      $shift = $this->fields->{$field_id}->coefficients_shift; 
	      $probability += $coefficients[$shift+ count($value)];
	   }
     }

     $probability += end($coefficients);
     $probability = 1 / (1 + exp(-$probability)); 

     return $probability;

   }

   public function get_unique_terms($input_data) {
      /* Parses the input data to find the list of unique terms in the
         tag cloud */

      $unique_terms = array();
      foreach($this->term_forms as $field_id => $field) {
         if (array_key_exists($field_id, $input_data) ) {
            $input_data_field = (array_key_exists($field_id, $input_data)) ?  $input_data[$field_id] : '';

            if (is_string($input_data_field)) {
               $case_sensitive = (array_key_exists('case_sensitive', $this->term_analysis[$field_id])) ? $this->term_analysis[$field_id]->case_sensitive : true;
               $token_mode = (array_key_exists('token_mode', $this->term_analysis[$field_id])) ? $this->term_analysis[$field_id]->token_mode : 'all';

               if ($token_mode != Predicate::TM_FULL_TERM) {
                  $terms = parse_terms($input_data_field, $case_sensitive);
               } else {
                  $terms = array();
               }

               if ($token_mode != Predicate::TM_TOKENS) {
                  array_push($terms, ($case_sensitive) ? $input_data_field : strtolower($input_data_field) );
               }

               $unique_terms[$field_id] = logistic_get_unique_terms($terms,
                                                        $this->term_forms[$field_id],
                                                        array_key_exists($field_id, $this->tag_clouds) ? $this->tag_clouds[$field_id] : array());

            } else {
              $unique_terms[$field_id] = $input_data_field;
            }
            unset($input_data[$field_id]);

         }
      } 

      # the same for items fields
      foreach($this->item_analysis as $field_id => $value){
         if ( array_key_exists($field_id, $input_data) ) {
            $input_data_field = (array_key_exists($field_id, $input_data)) ?  $input_data[$field_id] : '';
         }

         if (is_string($input_data_field)) {
           $separator = (property_exists($this->item_analysis[$field_id], 'separator')) ? $value->separator : ' ';
           $regexp = (property_exists($this->item_analysis[$field_id], 'separator_regexp')) ? $value->separator_regexp : null;

           if (is_null($regexp)) {
              $regexp='' . preg_quote($separator);
           }

           $terms = parse_items($input_data_field, $regexp);
           $unique_terms[$field_id] = get_unique_terms($terms,
                                                       array(),
                                                       array_key_exists($field_id, $this->items) ? $this->items[$field_id] : array());
         } else {
           $unique_terms[$field_id] = $input_data_field;
         }

         unset($input_data[$field_id]);

      }

      foreach ($this->categories as $field_id => $value) {
         if (array_key_exists($field_id, $input_data)) {
	     $input_data_field = (array_key_exists($field_id, $input_data)) ?  $input_data[$field_id] : '';
	     $unique_terms[$field_id]=array(array($input_data_field, 1));
	     unset($input_data[$field_id]);
	 }    
      }

      return $unique_terms;
   }


   public function map_coefficients() {
     /*
        Maps each field to the corresponding coefficients subarray
     */

     $field_ids=array();

     foreach ($this->fields as $field_id => $row) {
        if ($field_id != $this->objective_id) 
           $field_ids[$field_id] = $row->column_number;
     } 

     asort($field_ids);
     
     $shift = 0;
     foreach ($field_ids as $field_id => $value) {
        $optype = $this->fields->{$field_id}->optype;

        if (in_array($optype, array_keys(json_decode(EXPANSION_ATTRIBUTES, true)))) {
          # text, items and categorical fields have one coefficient per
          # text/class plus a missing terms coefficient plus a bias
          # coefficient
          $length = count($this->fields->{$field_id}->summary->{json_decode(EXPANSION_ATTRIBUTES, true)[$optype]});
          $length += 1;
        } else {
           # numeric fields have one coefficient and an additional one
           # if self.missing_numerics is True
           $length = ($this->missing_numerics) ? 2 : 1;
        }
        $this->fields->{$field_id}->coefficients_shift = $shift;
        $shift += $length; 
     }
 
   }
}
