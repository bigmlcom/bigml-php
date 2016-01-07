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

function reverse($operator) {
    /*Reverses the unequality operators*/
    $REVERSE_OP = array('<' => '>', '>'=> '<');
    return $REVERSE_OP[substr($operator, 0, 1)] . substr($operator, 1); 

}

function merge_categorical_rules($list_of_predicates, $fields, $label='name', $missing_flag=null) {
  /* Summarizes the categorical predicates for the same field*/
  $equal = array();
  $not_equal = array();

  foreach ($list_of_predicates as $predicate) {
     if (substr($predicate->operator, 0, 1) == "!") {
        array_push($not_equal, $predicate); 
     } else {
        array_push($equal, $predicate);
     }
  }

  $rules = array();
  $rules_not = array();

  if ($equal) {
     array_push($rules,trim($equal[0]->to_rule($fields, $label, false)));
     foreach (array_slice($equal, 1) as $predicate) {
         if (!in_array($predicate->value,$rules) ){
	    array_push($rules, $predicate->value);
	 }
     }
  }
 
  $rule = implode(" and ", $rules);

  if (!empty($not_equal) && empty($rules)) {
     array_push($rules_not, trim($not_equal[0]->to_rule($fields, $label, false)));
     foreach (array_slice($not_equal, 1) as $predicate) {
        if (!in_array($predicate->value,$rules_not) ){
	   array_push($rules_not, $predicate->value);
	}
     }
  }

  if (!empty($rules_not)) {
     $connector = (!is_null($rule) && $rule != "") ? " and "  : ""; 
     $rule = $rule . $connector .  implode(" or ", $rules_not); 
  }

  if (!is_null($missing_flag)) {
     $rule = $rule . " or missing"; 
  }

  return $rule;

}

function merge_text_rules($list_of_predicates, $fields, $label='name') {
   /*Summarizes the text predicates for the same field */
   $contains = array();
   $not_contains = array();

   foreach ($list_of_predicates as $predicate) {
      if (($predicate->operator == '<' && $predicate->value <= 1) or 
          ($predicate->operator == '<=' && $predicate->value == 0) ) {
     
         array_push($not_contains, $predicate);
      } else {
        array_push($contains, $predicate);
      } 
   }

   $rules = array();
   $rules_not = array();

   if (!empty($contains)) {
      array_push($rules, $contains[0]->to_rule($fields, $label));
      foreach (array_slice($contains, 1) as $predicate) {
         if (!in_array($predicate->term, $rules)) {
	    array_push($rules, $predicate->term);
	 }
      }
   }

   $rule = implode(" and ", $rules);

   if (!empty($not_contains)) {
      if ($empty($rules) ) {
         array_push($rules_not, trim($not_contains[0]->to_rule($fields, $label)));
      }  else {
         array_push($rules_not, " and " . trim($not_contains[0]->to_rule($fields, $label)) );
      } 

      foreach (array_slice($not_contains, 1) as $predicate) {
         if (!$in_array($predicate->term, $rules_not)) {
            array_push($rules_not, $predicate->term);
	 }
      }
   }

   $rule = $rule . implode("or ", $rules_not);
   return $rule;

} 

function merge_numeric_rules($list_of_predicates, $fields, $label='name', $missing_flag=null) {
  /* Summarizes the numeric predicates for the same field */
  $minor = array(null, -INF);
  $major = array(null, INF);
  $equal = null;

  foreach ($list_of_predicates as $predicate) {
     if (substr($predicate->operator, 0, 1) == ">"  && $predicate->value > $minor[1]) {
        $minor = array($predicate, $predicate->value);
     } 

     if (substr($predicate->operator, 0, 1) == "<" && $predicate->value < $major[1]) {
        $major = array($predicate, $predicate->value);
     }

     if (in_array(substr($predicate->operator, 0, 1), array("!", "=", "/", "i")) ) {
        $equal = $predicate;
	break;
     } 
  }

  if (!is_null($equal) ) {
     return  $equal->to_rule($fields, $label, $missing_flag);
  }

  $rule = "";

  $field_id = $list_of_predicates[0]->field;
  $name = $fields->{$field_id}->{$label};

  if (!is_null($minor[0]) && !is_null($major[0])) {
     $predicate = $minor[0];
     $value = $minor[1];
     $rule = $value . " " . reverse($predicate->operator) . " " . $name;
 
     $predicate = $major[0];
     $value = $major[1];

     $rule = $rule ." " .  $predicate->operator . " " . $value . " ";

     if (!is_null($missing_flag)) {
       $rule = $rule . " or missing";
     }

  } else {
     $predicate = !is_null($minor[0]) ? $minor[0] : $major[0];
     $rule = $predicate->to_rule($fields, $label, $missing_flag); 
  }

  return $rule;
}


function merge_rules($list_of_predicates, $fields, $label='name') {
   /* Summarizes the predicates referring to the same field */
   if (!empty($list_of_predicates)) {
      $field_id = $list_of_predicates[0]->field;
      $field_type = $fields->{$field_id}->optype;
      $missing_flag = null;
      $name = $fields->{$field_id}->{$label};

      $last_predicate = end($list_of_predicates);
      if ($last_predicate->operator == "=" and is_null($last_predicate->value)) {
         return $name . " is missing";
      } 

      if (in_array(substr($last_predicate->operator, 0, 1), array("!", "/")) && is_null($last_predicate->value)) {

          if (count($list_of_predicates)  == 1 ) {
	     return $name . "is not missing";
	  }

	  $list_of_predicates =  array_slice($list_of_predicates, 0, -1);
	  $missing_flag = false;
      }

      if ($last_predicate->missing) {
         $missing_flag = true;
      }

      if ($field_type == 'numeric') {
         return merge_numeric_rules($list_of_predicates, $fields, $label, $missing_flag); 
      } 

      if ($field_type == 'text') {
         return merge_text_rules($list_of_predicates, $fields, $label);
      }

      if ($field_type == 'categorical') {
         return merge_categorical_rules($list_of_predicates, $fields, $label, $missing_flag);
      }

      $predicate_array = array();
      foreach ($list_of_predicates as $predicate) {
         array_push($predicate_array, trim($predicate->to_rule($fields, $label)));
      }

      return implode(" and ", $predicate_array);
   }

}

class Path {
   public $predicates;

   public function __construct($predicates=null) {
      /* Path instance constructor accepts only lists of Predicate objects */
      if ($predicates == null) {
         $this->predicates = array(); 
      } else if (is_array($predicates) && is_a($predicates[0], "Predicate") ) {
        $this->predicates = $predicates;
      } else {
         error_log("The Path constructor accepts a list of Predicate objects. Please check the arguments for the  constructor");
	 throw new Exception("The Path constructor accepts a list of Predicate objects. Please check the arguments for the  constructor");
      }
   }

   public function to_rules($fields, $label='name', $format=0) {
      /* Builds rules string from a list lf predicates in different formats */
      if ($format == 0) { # EXTENDED
         return $this->to_extended_rules($fields, $label);
      } else if ($format == 1) { # BRIEF
         return $this->to_brief_rules($fields, $label);
      } else {
         error_log("Invalid format. The list of valid formats are 0 (extended) or 1 (brief).");
	 throw new Exception("Invalid format. The list of valid formats are 0 (extended) or 1 (brief)");
      }
   }

   public function to_extended_rules($fields, $label='name') {
      /*Builds rules string in ordered and extended format*/
      $list_of_rules=array();
      foreach ($this->predicates as $predicate) {
         array_push($list_of_rules, trim($predicate->to_rule($fields, $label)));
      }
   
      return implode(" and ", $list_of_rules); 

   }

   public function to_brief_rules($fields, $label='name') {
     /*Builds rules string in brief format (grouped and unordered)*/
     $group_of_rules = array();
     $list_of_fields = array();
     foreach ($this->predicates as $predicate) {
          if (!array_key_exists($predicate->field, $group_of_rules)) {
	     $group_of_rules[$predicate->field] = array();
	     array_push($list_of_fields, $predicate->field);
	  }
	  array_push($group_of_rules[$predicate->field], $predicate);
     }

     $lines=array();
     foreach ($list_of_fields as $field) {
       array_push($lines, merge_rules($group_of_rules[$field], $fields, $label));
     }
     return implode(" and ", $lines);

   }

   public function append($predicate) {
      $this->predicates.append($predicate);
   }
}


?>
