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

namespace BigML;

include "predicate.php";

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

function parse_terms($text, $case_sensitive=true) {
    //Returns the list of parsed terms

    if (is_null($text)) {
        return [];
    }
    $expression = '/\b([^\b_\s]+?)\b/u';
    preg_match_all( $expression, $text, $matches);
    if ($case_sensitive) {
        return $matches[0];
    } else {
        return array_map('strtolower', $matches[0]);
    }
}

function parse_items($text, $regexp) {
    //Returns the list of parsed items

    if (is_null($text)) {
        return [];
    }
    return mb_split($regexp, $text);
}

function check_model_structure($model) {
     /*
       Checks the model structure to see if it contains all the needed keys
     */
     return ($model instanceof \STDClass &&
             property_exists($model, "resource") &&
             $model->resource != null &&
             ((property_exists($model, "object") && property_exists($model->object, "model")) ||
             property_exists($model, "model")));

}

function get_unique_terms($terms, $term_forms, $tag_cloud) {
    //Extracts the unique terms that occur in one of the alternative
    //forms in term_forms or in the tag cloud.

    $extend_forms = [];
    foreach ($term_forms as $term => $forms) {
        foreach ($forms as $form) {
            $extend_forms[$form] = $term;
        }
        $extend_forms[$term] = $term;
    }

    $terms_set = [];
    foreach ($terms as $term) {

        if (in_array($term, $tag_cloud)) {
            if (!array_key_exists($term, $terms_set)) {
                $terms_set[$term] = 0;
            }
            $terms_set[$term] += 1;
        } elseif (array_key_exists($term, $extend_forms)) {
            $term = $extend_forms[$term];
            if (!array_key_exists($term, $terms_set)) {
                $terms_set[$term] = 0;
            }
            $terms_set[$term] += 1;
        }
    }
    return $terms_set;
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

   public function __construct($fields, $objective_id=null, $data_locale=null, $missing_tokens=null, $terms=false, $categories=false, $numerics=false) {
      
      if ($fields instanceof \STDClass) {
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
         if ($terms) {

             //adding text and items information to handle terms
             //expansion
             $this->term_forms = [];
             $this->tag_clouds = [];
             $this->term_analysis = [];
             $this->items = [];
             $this->item_analysis = [];
         }
         if ($categories) {
             $this->categories = [];
         }
         if ($terms OR $categories) {
             $this->add_terms($categories, $numerics);
         }
      }
   }

   private function add_terms($categories=false, $numerics=false) {
       //Adds the terms information of text and items fields

       foreach ($this->fields as $field_id => $field) {
           if ($field->optype == "text") {
               $this->term_forms[$field_id] = $field->summary->term_forms;
               foreach ($field->summary->tag_cloud as $tag) {
                   $this->tag_clouds[$field_id][] = $tag[0];
               }
               $this->term_analysis[$field_id] = $field->term_analysis;
           }
           if ($field->optype == "items") {
               foreach ($field->summary->items as $item) {
                   $this->items[$field_id][] = $item[0];
               }
               $this->item_analysis[$field_id] = $field->item_analysis;
           }
           if ($categories && $field->optype == "categorical") {
               foreach ($field->summary->categories as $category) {
                   $this->categories[$field_id][] = $category[0];
               }
           }
           if ($numerics && $this->missing_numerics && $field->optype == "numeric") {
               $this->numeric_fields[$field_id] = true;
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

      if (array_keys($input_data) !== range(0, count($input_data) -1)) {
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

                if (array_key_exists($key, $this->inverted_fields) && (is_null($this->objective_id) || $this->inverted_fields[$key] != $this->objective_id)) {
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

    public function get_unique_terms($input_data, $with_data = false, $term_format = "map_count") {
        // Parses the input data to find the list of unique terms in
        // the tag cloud
        //
        //:param input_data: Input data to be predicted 
        //:param with_data: Boolean that is set to True to append
        //                  input data to the returned array
        //:param term_format: String that determines the format of the
        //                    returned terms.  
        //                    If set to "map_count", it returns a list
        //                    of maps between terms and their counts
        //                    as used in deepnets ("love" => 2, "day"
        //                    => 1).
        //                    If "pair_count", it returns a list of
        //                    lists as used in logistic regression
        //                    (("love", 2), ("day", 1)).
        //                    If "list", it returns just the list of
        //                    terms as used in clustering ("love",
        //                    "day").

        $unique_terms = [];
        foreach ($this->term_forms as $field_id => $contents) {            
           if (array_key_exists($field_id, $input_data)) {
                if (!is_null($input_data[$field_id])) {
                    $input_data_field = $input_data[$field_id];
                } else {
                    $input_data_field = '';
                }
                if (is_string($input_data_field)) {
                    if (!is_null($this->term_analysis[$field_id]->case_sensitive)) {
                        $case_sensitive = $this->term_analysis[$field_id]->case_sensitive;
                    } else {
                        $case_sensitive = true;
                    }
                    if (!is_null($this->term_analysis[$field_id]->token_mode)) {
                        $token_mode = $this->term_analysis[$field_id]->token_mode;
                    } else {
                        $token_mode = "all";
                    }
                    if ($token_mode != Predicate::TM_FULL_TERM) {
                        $terms = parse_terms($input_data_field, $case_sensitive);
                    } else {
                        $terms = [];
                    }
                    if ($case_sensitive) {
                        $full_term = $input_data_field;
                    } else {
                        $full_term = strtolower($input_data_field);
                    }

                    /* We add full_term if needed. Note that when
                       there's only one term in the input_data,
                       full_term and term are equal. Then
                       full_term will not be added to avoid
                       duplicated counters for the term. */
                    if ($token_mode == Predicate::TM_FULL_TERM OR 
                        ($token_mode == Predicate::TM_ALL && 
                         $terms[0] != $full_term)) {
                        $terms[] = $full_term;
                    }

                    if (is_array($this->tag_clouds[$field_id][0])) {
                        foreach($this->tag_clouds[$field_id] as $value) {
                            $tag_cloud[] = $value[0];
                        }
                    } elseif (!is_null($this->tag_clouds[$field_id])) {
                        $tag_cloud = $this->tag_clouds[$field_id];
                    } else {
                        $tag_cloud = [];
                    }

                    $unique_terms[$field_id] = get_unique_terms(
                        $terms, $this->term_forms[$field_id],
                        $tag_cloud);

                } else {
                    $unique_terms[$field_id] = array(array($input_data_field, 1));
                }
                unset($input_data[$field_id]);
            }
        }

        //the same for items fields
        foreach ($this->item_analysis as $field_id => $contents) {
            if (array_key_exists($field_id, $input_data)) {
                if (!is_null($input_data[$field_id])) {
                    $input_data_field = $input_data[$field_id];
                } else {
                    $input_data_field = '';
                }
                if (is_string($input_data_field)) {
                    //parsing the items in input_data
                    if (!is_null($this->item_analysis[$field_id]->separator)) {
                        $separator = $this->item_analysis[$field_id]->separator;
                    } else {
                        $separator = ' ';
                    }

                    if (isset($this->item_analysis[$field_id]->separator_regexp)) {
                        $regexp = $this->item_analysis[$field_id]->separator_regexp;
                    } else {
                        $regexp = preg_quote($separator);
                    }

                    $terms = parse_items($input_data_field, $regexp);

                    if (is_array($this->items[$field_id][0])) {
                        foreach ($this->items[$field_id] as $item) {
                            $tag_cloud[] = $item[0];
                        }
                    } elseif (!is_null($this->items[$field_id])) {
                        $tag_cloud = $this->items[$field_id];
                    } else {
                        $tag_cloud = [];
                    }
                    $unique_terms[$field_id] = get_unique_terms($terms, [], $tag_cloud);
                } else {
                    $unique_terms[$field_id] = array(array($input_data_field, 1));
                }
                unset($input_data[$field_id]);
            }
        }

        $final_terms = [];
        if ($term_format == "pair_count") {
            foreach ($unique_terms as $field_id => $terms) {
                $list = [];
                foreach ($terms as $value => $count) {
                     $list[] = array($value, $count);
                }
                $final_terms[$field_id] = $list;
            }
            $unique_terms = $final_terms;
        }

        if (property_exists($this, 'categories') && $this->categories) {
            foreach ($this->categories as $field_id => $contents) {
                if (array_key_exists($field_id, $input_data)) {
                    if (!is_null($input_data[$field_id])) {
                        $input_data_field = $input_data[$field_id];
                    } else {
                        $input_data_field = '';
                    }
                    $unique_terms[$field_id] = array(array($input_data_field, 1));
                    unset($input_data[$field_id]);
                }
            }
        }

        $final_terms = [];
        if ($term_format == "list") {
            foreach ($unique_terms as $field_id => $terms) {
                $final_terms[$field_id] = array_keys($terms);
            }
            $unique_terms = $final_terms;
        } 

        if ($with_data) {
            $unique_terms = array($unique_terms, $input_data);
        } 

        return $unique_terms;
    }        
}

function isAssoc($arr)
{
  return array_keys($arr) !== range(0, count($arr) - 1);
}

?>
