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

function get_fields_structure($resource, $errors=false) {
    $DEFAULT_LOCALE = 'en-US'; 
    $DEFAULT_MISSING_TOKENS = array("", "N/A", "n/a", "NULL", "null", "-", "#DIV/0",
                                   "#REF!", "#NAME?", "NIL", "nil", "NA", "na",
			           "#VALUE!", "#NULL!", "NaN", "#N/A", "#NUM!", "?");
    /*
     Returns the field structure for a resource, its locale and missing_tokens
    */
      $resource_type = null;
      $field_errors = null;

      if ($resource instanceof STDClass && property_exists($resource, "resource")) {
         $resourceId = $resource->resource;
      } else if (is_string($resource)) {
         $resourceId = $resource;
      } else {
         $resourceId = null;
      }
       
      if (preg_match('/(source|dataset|model|prediction|cluster|anomaly|sample|correlation|statisticaltest|logisticregression|association)(\/)([a-z,0-9]{24}|[a-z,0-9]{27})$/i', $resourceId, $result)) {
         $resource_type = $result[1];
      } 

     if ($resource_type != null) {
        $fields=null;
        $resource_locale=null;
        $missing_tokens=null;

        if ($resource_type == "source") {
           $resource_locale = $resource->object->source_parser->locale;
           $missing_tokens = $resource->object->source_parser->missing_tokens;
        } else { 
           $resource_locale = property_exists($resource->object, "locale") ? 
	                         $resource->object->locale : $DEFAULT_LOCALE; 
           $missing_tokens = property_exists($resource->object, "missing_tokens") ?
	                         $resource->object->missing_tokens : $DEFAULT_MISSING_TOKENS;
        } 

        if (in_array($resource_type, array("model", "anomaly"))) {
           $fields=$resource->object->model->fields;
        } else if ($resource_type == "cluster") {
	   $fields=$resource->object->clusters->fields;
	} else if ($resource_type == "correlation") {
           $fields=$resource->object->correlations->fields;
        } else if ($resource_type == "statisticaltest") {
	   $fields=$resource->object->statistical_tests->fields;
	} else if ($resource_type == "logisticregression") {
           $fields=$resource->object->logistic_regression->fields;
	} else if ($resource_type == "associations") {
	   $fields=$resource->object->associations->fields;
	} else if ($resource_type == "sample") {
	   $fields = array();
	   foreach ($resource->object->sample as $key => $field) {
               array_push($fields, array($field->id, $field));
	   }
	} else {
           $fields=$resource->object->fields; 
        }

	$objective_column= null;
	if ($resource_type == "dataset") {
	  $objective_column = property_exists($resource->object, "objective_field") ?
	                         $resource->object->objective_field->id : null;

          if ($errors) {
	     $field_errors = property_exists($resource, "status") ? 
	                       property_exists($resource->status, "field_errors") ? 
			           $resource->status->field_errors : null : null;
	  }

	} else if (in_array($resource_type, array('model', 'logisticregression'))) {
	  $objective_id = property_exists($resource->object,"objective_fields") ? 
	                     $resource->object->objective_fields[0] : null;
          $objective_column = array_key_exists($objective_id, $fields) ? $fields[$objective_id]->column_number : null;

        }
        
	$result = array($fields,$resource_locale, $missing_tokens, $objective_column);
        if ($errors) {
	   array_push($result, $field_errors);
	}
        return $result;

     } else { 
        return $errors ? array(null, null, null, null, null) : array(null, null, null, null);
        throw new Exception("Unknown resource structure");
     }
     
}


class Fields {
  /*
   A class to deal with BigML auto-generated ids.
   */
   public $fields;
   public $fields_by_name;
   public $fields_by_column_number;
   public $missing_tokens;
   public $fields_columns;
   public $filtered_fields;
   public $row_ids;
   public $headers;
   public $objective_field;
   public $objective_field_present; 
   public $filtered_indexes;
   public $field_errors;

   public function __construct($resource_or_fields, $missing_tokens=null, $data_locale=null, $verbose=null, $objective_field=null, $objective_field_present=null, $include=null, $errors=null) {
       # The constructor can be instantiated with resources or a fields
       # structure. The structure is checked and fields structure is returned
       # if a resource type is matched.

       $DEFAULT_MISSING_TOKENS = array("", "N/A", "n/a", "NULL", "null", "-", "#DIV/0",
                                       "#REF!", "#NAME?", "NIL", "nil", "NA", "na",
				       "#VALUE!", "#NULL!", "NaN", "#N/A", "#NUM!", "?");
       try {
           $resource_info = get_fields_structure($resource_or_fields, true);
       
           $this->fields = $resource_info[0];
           $resource_locale = $resource_info[1];
           $resource_missing_tokens= $resource_info[2];
	   $objective_column = $resource_info[3];
           $resource_errors = $resource_info[4]; 

           if (is_null($data_locale)) {
              $data_locale = $resource_locale;
           }
           if (is_null($missing_tokens)) {
              if (!empty($resource_missing_tokens)) {
                 $missing_tokens = $resource_missing_tokens;
              }
           }
	   if (is_null($errors)) {
	      $errors = $resource_errors;
	   }

       } catch  (Exception $e) {
          $this->fields = $resource_or_fields;
          if (is_null($data_locale)) { 
             $data_locale = "en_utf8";
          } 
          if (is_null($missing_tokens)) {
             $missing_tokens = $DEFAULT_MISSING_TOKENS;
          }
	  $objective_column= null;
       }
        
       if (is_null($this->fields)) {
          error_log("No fields structure was found.");
	  return;
       } 
       $this->fields_by_name = $this->invert_dictionary($this->fields, 'name');
       $this->fields_by_column_number  = $this->invert_dictionary($this->fields, 'column_number');

       find_locale($data_locale, $verbose); 

       $this->missing_tokens = $missing_tokens;

       $this->fields_columns=array();
       foreach ($this->fields_by_column_number as $key => $value) {
          array_push($this->fields_columns, $key);
       }

       sort($this->fields_columns);
       $this->filtered_fields = array();
       # Ids of the fields to be included
       if (is_null($include)) {
          foreach($this->fields as $key => $value)  {
	      array_push($this->filtered_fields, $key);
	  }
       } else { 
         $this->filtered_fields = $include;
       }

       # To be updated in update_objective_field
       $this->row_ids = null;
       $this->headers = null;
       $this->objective_field = null;
       $this->objective_field_present = null;
       $this->filtered_indexes = null;
       $this->field_errors = $errors;
 
       if (is_null($objective_field)  && !is_null($objective_column) ) {
           $objective_field = $objective_column;
	   $objective_field_present = true;
       }

       $this->update_objective_field($objective_field, $objective_field_present);

   }

   function field_column_number($key) {
      /*
        Returns a field column number.
       */
     try {
        return $this->fields->{$key}->column_number;
     } catch  (Exception $e) {
        return $this->fields->{$this->fields_by_name->{$key}}->column_number;
     } 
      
   }  
   
   function field_id($key) {
      /*
        Returns a field id.
       */

       if (is_string($key)) {
          try {
            return $this->fields_by_name[$key];
          } catch  (Exception $e) {
            exit("Error: field name '" . $key . "' does not exist ");
          }
       } else if (is_int($key)) {
          try {
            return $this->fields_by_column_number[$key]; 
          } catch  (Exception $e) {
             exit("Error: field column number '" . $key . "' does not exist ");
          }
       }

   }

   function field_name($key) {
      /*
        Returns a field name.
      */
      if (is_string($key)) {
        try {
          return $this->fields->{$key}->name;
        } catch  (Exception $e) {
          exit("Error: field id '" . $key . "' does not exist ");
        }
      } else if (is_int($key)) { 
        try {
          return $this->fields->{$this->fields_by_column_number[$key]}->$name;
        } catch  (Exception $e) {
          exit("Error: field column number '" . $key . "' does not exist ");
        } 
      }
   }

   function update_objective_field($objective_field, $objective_field_present, $headers=null) {
      /*
        Updates objective_field and headers info
        Permits to update the objective_field, objective_field_present and 
        headers info from the constructor and also in a per row basis.
       */
      # If no objective field, select the last column, else store its column
      if (is_null($objective_field)) {
         $this->objective_field = end($this->fields_columns);
      } else if (is_string($objective_field)) {
         try {
	    $this->objective_field = $this->field_column_number($objective_field);
	 } catch  (Exception $e) {
            $this->objective_field = end($this->fields_columns);
	 }
         $this->objective_field = $this->field_column_number($objective_field);
      } else {
         $this->objective_field = $objective_field;
      }

      # If present, remove the objective field from the included fields
      $objective_id = $this->field_id($this->objective_field);

      if (($key = array_search($objective_id, $this->filtered_fields)) !== false) {
         unset($this->filtered_fields[$key]);
      }

      $this->objective_field_present = $objective_field_present;

      if (is_null($headers)) {
         # The row is supposed to contain the fields sorted by column number
	 /*
	 [item[0] for item in
                            sorted(self.fields.items(),
                                   key=lambda x: x[1]['column_number'])
                            if objective_field_present or
                            item[1]['column_number'] != self.objective_field]
	 */
         $sorted_fields_items=array();
         $this->row_ids=array();
	 foreach($this->fields as $key => $field) {
           if ($objective_field_present || $field->column_number != $this->objective_field)
              $sorted_fields_items[$key] = $field->column_number;
	 }
         arsort($sorted_fields_items);
         foreach($sorted_fields_items as $key => $value) {
            array_push($this->row_ids, $key); 
         }

         $this->headers = $this->row_ids; 
      } else {
         $this->row_ids = array();
         foreach($headers as $header) {
            array_push($this->row_ids, $this->field_id($header));
         }
         $this->headers = $headers;
      }

      $this->filtered_indexes = array();
 
      foreach($this->filtered_fields as $field) {
        try {
           if (($key = array_search($field, $this->row_ids)) !== false) {
              array_push($this->filtered_indexes, $key);
           }
        } catch  (Exception $e) {
        }
      }

   }

   function len() {
      // Returns the number of fields.
      return count($this->fields); 
   }

   function pair($row, $headers=null, $objective_field=null, $objective_field_present=null) {
      /*
       Pairs a list of values with their respective field ids.
       objective_field is the column_number of the objective field.
       objective_field_present` must be True is the objective_field column is present in the row.
       */

       // Try to get objective field form Fields or use the last column
       if (is_null($objective_field)) {
          if (is_null($this->objective_field)) {
              $objective_field = end($this->fields_columns);
          } else {
              $objective_field = $this->objective_field; 
          }
       }
   
      // If objective fields is a name or an id, retrive column number
      if (is_string($objective_field)) {
         $objective_field = $this->field_column_number($objective_field);
      }

      // Try to guess if objective field is in the data by using headers or
      // comparing the row length to the number of fields
      if (is_null($objective_field_present)) {
         if (!is_null($headers)) {
            $objective_field_present = in_array($this->field_name($objective_field), $headers); 
         } else {
            $objective_field_present = count($row) == $this->len();
         }
      }
     
      // If objective field, its presence or headers have changed, update
      if ($objective_field != $this->objective_field  || $objective_field_present != $this->objective_field_present || (!is_null($headers) && $headers != $this->headers )) {
         $this->update_objective_field($objective_field, $objective_field_present, $headers);
      }

      $rows = array();

      foreach($row as $r) {
         array_push($rows, $this->normalize($r));
      }

      $this->to_input_data($rows); 

   }

   function list_fields($out=STDOUT) {
      /*
        Lists a description of the fields.
      */
      uasort($this->fields, array($this, "sort_field_items"));
     
      foreach($this->fields as $field) {
         $a = "[" . $field->name  . str_repeat(utf8_encode(' '), 32) . ":" . $field->optype . ":" . str_repeat(utf8_encode(' '), 16) . $field->column_number . str_repeat(utf8_encode(' '), 8) . "]\n";
         fwrite($out, $a);
         fflush($out);
      }

 
   }

   function preferred_fields() {
     /*
      Returns fields where attribute preferred is set to True or where
      it isn't set at all.
     */
     $result = array();
     foreach($this->fields as $key => $value) {
        if (!array_key_exists("preferred", $value) || ($value->preferred != null)) {
           $result[$key] = $value;
        }
     }   
     return $result; 
   }

   function validate_input_data($input_data, $out=STDOUT) {
      /*
       Validates whether types for input data match types in the
       fields definition.
      */
      if (is_array($input_data)) {
         foreach($input_data as $name => $value) {
           if (array_key_exists($name, $this->fields_by_name)) {
              $a = "[" . $name  . str_repeat(utf8_encode(' '), 32) . ":" . gettype($input_data->{$name}) . str_repeat(utf8_encode(' '), 16) . ":" . $this->fields->{$this->fields_by_name{$name}}->optype . str_repeat(utf8_encode(' '), 16) . ":";

              if (in_array(gettype($input_data->{$name}), php_map_type($this->fields->{$this->fields_by_name->{$name}}->optype) )) {
                $a = $a . "OK\n"; 
              } else {
                $a = $a . "WRONG\n";
              }

              fwrite($out, $a);
              fflush($out);    
           } else {
              fwrite($out, "Field " . $name . " does not exist\n");
           }
         }
      } else {
         fwrite($out, "input data must be a array");
      }

   }

   function missing_counts() {
      /*
       Returns the ids for the fields that contain missing values
      */
      $summaries = array();
      foreach($this->fields as $key => $value) {
          $field_data = array();
          if (property_exists($value, "summary")) {
             $field_data = $value->summary; 
          }
          
          array_push($summaries, array($key, $field_data));
         
      } 
     
      if (count($summaries) == 0) {
          throw new Exception("The structure has not enough information to extract the fields containing missing values. Only datasets and models have such information. You could retry the get remote call with 'limit=-1' as query string.");
      }
     
      $hash_result = array();

      foreach($summaries as $summary) {

	 if (property_exists($summary[1], "missing_count") && $summary[1]->missing_count > 0) {
            $hash_result[$summary[0]] = $summary[1]->missing_count;
         }
      }
      return $hash_result;
   }

   function stats($field_name) {
      /*
       Returns the summary information for the field
       */
       
      $field_id = $this->field_id($field_name);

      if (array_key_exists("summary", $this->fields->{$field_id})) {
         return $this->fields->{$field_id}->summary;
      }

      return array();
   }

   function normalize($value) {
     /* 
      Transforms to unicode and cleans missing tokens
      */

      if (!mb_detect_encoding($str, 'UTF-8', true)) {
         $value = utf8_encode($value);
      }

      if (in_array($value, $this->missing_tokens))
         return null;
      else
         return $value;
   }

   function to_input_data($row) {
     /*
      Builds dict with field, value info only for the included headers
      */
 
     $pair = array();

     foreach($this->filtered_indexes as $index) {
        $pair[$this->headers[$index]]= $row[$index];
     }

     return $pair;
   }


   private function sort_field_items($a, $b) {
      if ($a[1]->column_number < $b[1]->column_number) {
         return 1;
      } else {
         return -1;
      } 
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

}

function find_locale($data_locale="en_US.UTF-8", $verbose=false)
{
  try {
   setlocale(LC_ALL, $data_locale);
  } catch  (Exception $e) {
   error_log("Error find Locale");
  }
}


function php_map_type($value) {
    /*
     Maps a BigML type to equivalent Php types.
    */
    $PHP_TYPE_ARRAY = array("categorical" => array("string"), "numeric" => array("integer", "double"), "text" => array("string"));
       
    if (in_array($value, $PHP_TYPE_ARRAY)) {
       return $PHP_TYPE_ARRAY->{$value};
    } else {
      return array("str");
    }

} 

?>
