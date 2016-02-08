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

if (!class_exists('predicate')) {
  include('predicate.php'); 
} 

/*
 Item object for the Association resource.
 This module defines each item in an Association resource.

*/
class Item {
  public $index;
  public $complement;
  public $complement_index;
  public $count;
  public $description;
  public $field_id;
  public $field_info;
  public $name;
  public $bin_end;
  public $bin_start;

  public function __construct($index, $item_info, $fields) {
     /*
       Object encapsulating an Association resource item as described in
       https://bigml.com/developers/associations

      */
     $this->index = $index;
     $this->complement =property_exists($item_info, "complement") ? $item_info->complement : false;
     $this->complement_index = property_exists($item_info, "complement_index") ? $item_info->complement_index : null;
     $this->count = $item_info->count;
     $this->description = property_exists($item_info, "description") ? $item_info->description : null;
     $this->field_id = $item_info->field_id;
     $this->field_info = $fields->{$this->field_id};
     $this->name = $item_info->name;
     $this->bin_end = property_exists($item_info, "bin_end") ? $item_info->bin_end : null;
     $this->bin_start = property_exists($item_info, "bin_start") ? $item_info->bin_start : null;
  }

  public function out_format($language="JSON") {
     # Transforming the item structure to a string in the required format
     if (in_array($language, array("JSON", "CSV") )) {
        $name="to_" . language;
        return $this->{$name};
     } 
  
  }

  public function to_csv() {
    # Transforming the item to CSV formats
    $output = array($this->complement, $this->complement_index, $this->count, $this->description, 
                    $this->field_info->name, $this->name,  $this->bin_end, $this->bin_start);

    return implode(", ", $output);

  }

  public function to_json() {
    #Transforming the item relevant information to JSON
    $item_dict = array(); 
    $item_dict = array('complement' => $this->complement, 
                    'count' =>  $this->count,
                    'description' =>  $this->description,
                    'field_id' => $this->field_id,
                    'name' => $this->name, 
                    'bin_end' => $this->bin_end,
                    'bin_start' => $this->bin_start);


    return json_encode($item_dict);
  }
  
  public function to_lisp_rule() {
    # Returns the LISP flatline expression to filter this item 
    $flatline = "";
    if (is_null($this->name)) {
       return "(missing? (f ". $this->field_id . "))";
    }

    $field_type = $this->field_info->optype;
    if ($field_type == "numeric") {
       $start = $this->complement ? $this->bin_end : $this->bin_start;
       $end = $this->complement ? $this->bin_start : $this->bin_end; 

       if (!is_null($start) && !is_null($end)) {
          if ($start < $end ) {
            $flatline = "(and (< " . $start . "(f " . $this->field_id .
                         " )) (<= (f " . $this->field_id . ") " . $end . "))";
          } else {
            $flatline = "(or (> (f ". $this->field_id .") " . 
                          $start . ") (<= (f " . $this->field_id .") " . $end . "))"; 
          }
       } else if (!is_null($start)) {
          $flatline = "(> (f " . $this->field_id . ") " . $start . ")";
       } else {
          $flatline = "(<= (f " . $this->field_id . ") " . $end . ")";
       }

    } else if ($field_type == "categorical") {
      $operator = ($this->complement) ? "!=" : "=";
      $flatline = "(" . $operator  . "(f " .$this->field_id . ") " .$this->name;
    } else if ($field_type == "text") {
      $operator = ($this->complement) ? "!=" : "=";
      $options = $this->field_info->term_analysis;
      $case_insensitive =  array_key_exists("case_sensitive", $options) ? !$options["case_sensitive"] : true;
      $case_insensitive = $case_sensitive ? 'true' : 'false';
      $language = array_key_exists("language", $options) ? $options['language'] : null;
      $language = is_null($language) ? '' : " " . $language;

      $flatline = "(" . $operator . " (occurrences (f " . $this->field_id .
                   ") " . $this->name . " " . $case_insensitive . $language .") 0)";
      

    } else if ($field_type == "items") {
      $operator =  ($this->complement) ? "!" : "";
      $flatline = "(" . $operator ."(contains-items? " . $this->field_id . " " . $this->name . "))";
    }

    return $flatline;
  }

  public function describe() {
    /*Human-readable description of a item_dict*/
    $description = "";
    if (is_null($this->name)) {
       return $this->field_info->name . ' is ' . $this->complement ? 'not' : '' . 'missing';
    }

    $field_name = $this->field_info->name;
    $field_type = $this->field_info->optype;
  
    if ($field_type == "numeric") {
       $start = $this->complement ? $this->bin_end : $this->bin_start;
       $end = $this->complement ? $this->bin_start : $this->bin_end;

       if (!is_null($start) && !is_null($end)) {
          if ($start < $end ) {
            $description = $start . " < " . $field_name . " <= " . $end; 
          } else {
            $description = $field_name . " > " . $start . " <= " . $end;
          }
       } else if (!is_null($start)) {
          $description = $field_name . " > " . $start;
       } else {
          $description = $field_name . " <= " . $end;
       }


    } else if ($field_type == "categorical") {
       $operator = ($this->complement) ? "!=" : "=";
       $description = $field_name . " " .$operator . " " .$this->name;
    } else if (in_array($field_type , array("text", "items"))) {
       $operator = ($this->complement) ? "excludes" : "includes";
       $description = $field_name . " " . $operator . " ".$this->name;
    } else {
       $description = $this->name;
    }
    return $description;
  }
  public function matches() {
    /*
     Checks whether the value is in a range for numeric fields or
     matches a category for categorical fields.
     */   
    $field_type = $this->field_info->optype; 
    if (is_null($value)) {
       return is_null($this->name);
    }
   
    if ($field_type == "numeric" and (!is_null($this->bin_end) or is_null($this->bin_start))){
       $result = ($this->bin_start <= $value) <= $this->bin_end;
       if (!is_null($this->bin_start) && !is_null($this->bin_end)) {
         $result = ($this->bin_start <= $value) <= $this->bin_end;
       } else if (!is_null($this->bin_end)) {
         $result = $value <= $this->bin_end; 
       } else {
         $result = $value >= $this->bin_start;
       }
    } else if ($field_type == "categorical") {
      $result = ($this->name == $value);
    } else if ($field_type = "text") {
      $all_forms = array_key_exists("term_forms", $this->field_info->summary) ?  
                   $this->field_info->summary : array();
      $term_forms =  array_key_exists($this->name, $all_forms) ?  $all_forms->{$this->name} : array();
      $terms = array_merge(array($this->name), $term_forms);
      $options = $this->field_info->term_analysis; 
      $result = term_matches($value, $terms, $options) > 0;
 
    } else if ($field_type = "items") {
      $options = $this->field_info->term_analysis;
    }
 

    if ($this->complement) {
      $result = !$result;
    }

    return $result;

  }

}
