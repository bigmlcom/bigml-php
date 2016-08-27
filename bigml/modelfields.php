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

/*
   A BasicModel resource.
   This module defines a BasicModel to hold the main information of the model
   resource in BigML. It becomes the starting point for the Model class, that
   is used for local predictions.
*/

function strip_affixes($value, $field) 
{
   /*
      Strips prefixes and suffixes if present
   */
   if (!mb_check_encoding($value,"UTF-8")) {
      $value = mb_convert_encoding($value, "UTF-8");
   }

   if (array_key_exists('prefix', $field) && substr( $field->prefix, 0, 6 ) === "prefix") {
      $value=substr($value, 6);
   }

   if (array_key_exists('suffix', $field) && endsWith($field->suffix, "suffix")) {
      $value=substr($value, 0, -6);
   }

   return $value;

}

function cast($input_data, $fields) {
   /*
      Checks expected type in input data values, strips affixes and casts
   */
   foreach($input_data as $key => $value) {
      if ($fields->{$key}->optype == 'categorical' &&  
         count($fields->{$key}->summary->categories) == 2 && (is_bool($value))) {# || in_array($value, array(0,1)))) {
         try {
	   $booleans = array();
	   $categories = array();
           foreach ($fields->{$key}->summary->categories as $index => $v) {
	      array_push($categories, $v[0]);
	   }
           foreach ($categories as $category) {
              $bool_key =  in_array(trim(strtolower($category)), array("true", "1")) ? '1' : '0'; 
	      $booleans[$bool_key] = $category; 
           }
           # converting boolean to the corresponding string	   
	   $input_data[$key] = $booleans[strval($value)];

	 } catch  (Exception $e) {
	   throw new Exception("Mismatch input data type in field \"". $fields->{$key}->name . 
	                        "\" for value " . json_encode($value) . ". String expected");
	 }

      } else if ( ($fields->{$key}->optype == 'numeric' && is_string($value)) || 
          ($fields->{$key}->optype != 'numeric' && !is_string($value))) {

         if ($fields->{$key}->optype == 'numeric') {
            $value = strip_affixes($value, $fields->{$key});
            if ($fields->{$key}->optype == "numeric") {
               $input_data[$key] = floatval($value); 
            } else {
               $input_data[$key] = utf8_encode($value);
            }
         } 
      } else if ($fields->{$key}->optype == 'numeric' && is_bool($value) ) {
          throw new Exception("Mismatch input data type in field \"". $fields->{$key}->name .
	                       "\" for value " . json_encode($value) . ". Numeric expected");
      }
   }
   return $input_data;
}

function extract_objective($objective_field) {
     /*
         Extract the objective field id from the model structure
     */
     if (is_array($objective_field) ) {
         return $objective_field[0];
     }
     return $objective_field;

 }

function check_model_structure($model) {
     /*
       Checks the model structure to see if it contains all the needed keys
     */
     return ($model instanceof STDClass &&
             property_exists($model, "resource") &&
             $model->resource != null &&
             ((property_exists($model, "object") && property_exists($model->object, "model")) ||
             property_exists($model, "model")));

}

function invert_dictionary($dictionary, $field='name') {
     /*Inverts a dictionary.
  
         Useful to make predictions using fields' names instead of Ids.
         It does not check whether new keys are duplicated though.
     **/
     $new_dictionary = array();
     foreach((array_keys(get_object_vars($dictionary))) as $key) {
         $field_value=$dictionary->{$key}->{$field};
         if (!mb_detect_encoding($field_value, 'UTF-8', true)) {
            $field = utf8_encode($field_value);
	 }
         $new_dictionary[strval($field_value)] = $key; 
     }
     return $new_dictionary;     
 }

class ModelFields { 
   /*
      A lightweight wrapper of the field information in the model or cluster
        objects
   */
   public $objective_id;
   public $fields;
   public $inverted_fields;
   public $missing_tokens;
   public $data_locale;

   const DEFAULT_LOCALE = 'en-US';

   public function __construct($fields, $objective_id=null, $data_locale=null, $missing_tokens=null) {
      
      if ($fields instanceof STDClass) {
         $this->objective_id = $objective_id;
         $fields = $this->uniquify_varnames($fields);
         $this->inverted_fields = invert_dictionary($fields);
         $this->fields = $fields;
         $this->data_locale = $data_locale;
         $this->missing_tokens = $missing_tokens;
         if ($this->data_locale == null) {
           $this->data_locale = ModelFields::DEFAULT_LOCALE; 
         } 
         if ($this->missing_tokens == null) {
           $this->missing_tokens = $DEFAULT_MISSING_TOKENS = array("", "N/A", "n/a", "NULL", "null", "-", "#DIV/0",
	                                                           "#REF!", "#NAME?", "NIL", "nil", "NA", "na",
						                   "#VALUE!", "#NULL!", "NaN", "#N/A", "#NUM!", "?");
         }
      }
   }

   private function uniquify_varnames($fields) {
      /*
      Tests if the fields names are unique. If they aren't, a
      transformation is applied to ensure unicity.
      */
      $unique_names = array();
      $len=0;
      foreach($fields as $field) {
          array_push($unique_names, $field->name);
          $len+=1;
      }

      $unique_names = array_unique($unique_names);

      if (count($unique_names) < $len) {
         $fields = $this->transform_repeated_names($fields);
      }

      return $fields;
   }

   private function transform_repeated_names($fields) {
      /*
        If a field name is repeated, it will be transformed adding its
        column number. If that combination is also a field name, the field id will be added.
        The objective field treated first to avoid changing it
      */
      if ($this->objective_id != null) {
         $unique_names =array($fields->{$this->objective_id}->name);
      } else {
         $unique_names = array();
      }

      foreach($fields as $field_id => $field) {
         $new_name = $field->name;
         if (in_array($new_name, $unique_names) ) {
             $new_name = $field->name . strval($field->column_number);

             if (in_array($new_name, $unique_names) ) {
                $new_name = $new_name . "_" . strval($field_id);
             }  
             $field->name = $new_name;
         }
         array_push($unique_names, $new_name);
      }

      return $fields;
   }

   function normalize($value) {
     /* 
      Transforms to unicode and cleans missing tokens
      */

      if (!mb_detect_encoding($value, 'UTF-8', true)) {
         $value = utf8_encode($value);
      }
      if (in_array(strval($value), $this->missing_tokens)) {
         return null;
      } else {
         return $value;
      }
   }

   public function filter_input_data($input_data, $by_name=true, $add_unused_fields=false) 
   {
      /*
         Filters the keys given in input_data checking against model fields
	 If `add_unused_fields` is set to true, it also
	 provides information about the ones that are not used.

      */
      $unused_fields = array();
      $new_input = array();

      if (is_array($input_data)) {
         foreach($input_data as $key => $value) {
             $value = $this->normalize($value);
             if (is_null($value)) {
               unset($input_data[$key]); 
             }
 
         }
         $new_input_data = array();
         if ($by_name) {
            # We no longer check that the input data keys match some of
            # the dataset fields. We only remove the keys that are not
            # used as predictors in the model


            foreach($input_data as $key => $value) { 
                if (!mb_detect_encoding($key, 'UTF-8', true)) {
		   $key = utf8_encode($key);
		}
               if (array_key_exists($key, $this->inverted_fields) && (is_null($this->objective_id) || $this->inverted_fields[$key] != $this->objective_id))
               {
                  $new_input[$this->inverted_fields[$key]] = $value;
               } else {
	          array_push($unused_fields, $key);
	       }
            }
         } else {
            foreach($input_data as $key => $value) {
               if (array_key_exists($key, $this->fields) && (is_null($this->objective_id) || $key != $this->objective_id) ) {
                  $new_input[$key] = $value;
               } else {
	         array_push($unused_fields, $key);   
               } 
            }   
         }

         $result =  $add_unused_fields ? array($new_input, $unused_fields) : $new_input;

         return $result;

      } else {
         error_log("Failed to read input data in the expected array {field=>value} format");
	 return $add_unused_fields ? array(array(), array()) : array();
      }

   }

   protected function clean_empty_fields($var) {
      $k = $var != null;
      if (is_int($var) && $var == 0) return true;
      return ($var != null);
   }

}

function isAssoc($arr)
{
  return array_keys($arr) !== range(0, count($arr) - 1);
}

?>
