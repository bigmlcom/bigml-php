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

function term_matches($text, $forms_list, $options) {
  /*
    Counts the number of occurences of the words in forms_list in the text
    The terms in forms_list can either be tokens or full terms. The
    matching for tokens is contains and for full terms is equals.
  */

  $token_mode = property_exists($options, 'token_mode') ? $options->token_mode : Predicate::TM_TOKENS;
  $case_sensitive = property_exists($options, 'case_sensitive') ? $options->case_sensitive : false;
  $first_term = $forms_list[0];

  if ($token_mode == Predicate::TM_FULL_TERM) {
     return full_term_match($text, $first_term, $case_sensitive);
  }

   # In token_mode='all' we will match full terms using equals and
   # # tokens using contains

   if ($token_mode == Predicate::TM_ALL && count($forms_list) == 1) {
      if ( preg_match(Predicate::FULL_TERM_PATTERN, $first_term) ) {
         return full_term_match($text, $first_term, $case_sensitive);
      }
   }

  return term_matches_tokens($text, $forms_list, $case_sensitive);
}

function term_matches_tokens($text, $forms_list, $case_sensitive) {
  /*
    Counts the number of occurences of the words in forms_list in the text
  */
  $flags = get_tokens_flags($case_sensitive);
  $expression = "/(\b|_)" . join("(\b|_)|(\b|_)",$forms_list) . "(\b|_)/" . $flags;
  $total = preg_match_all($expression, $text, $matches);
  return $total;

}

function item_matches($text, $item, $options) {
  /*
   Counts the number of occurences of the item in the text
   The matching considers the separator or the separating regular expression.
  */
  $separator=property_exists($options, 'separator') ? $options->separator : ' ';
  $regexp=property_exists($options, 'separator_regexp') ? $options->separator_regexp : null;

  if (is_null($regexp)) {
     $regexp=preg_quote($separator, '/');
  }

  return count_items_matches($text, $item, $regexp);

}

function count_items_matches($text, $item, $regexp) {
  /*
   Counts the number of occurences of the item in the text
  */
  $expression="/(^|". $regexp  .")". $item ."($|". $regexp .")/u";

  #expression = ur'(^|%s)%s($|%s)' % (regexp, item, regexp)

  $total = preg_match_all($expression, $text, $matches);
  return $total;

}

function get_tokens_flags($case_sensitive) {
 /*
   Returns flags for regular expression matching depending on text analysis options
  */
  $flags = "u";
  if (!$case_sensitive) {
     $flags = "iu";
  }

  return $flags;
}

function full_term_match($text, $full_term, $case_sensitive) {
  /*
    Counts the match for full terms according to the case_sensitive option
  */
  if (!$case_sensitive) {
     $text = strtolower($text);
     $full_term = strtolower($full_term);
  }
  return ($text == $full_term) ? 1 : 0;
}

function plural($text, $num) {
    /*
      Pluralizer: adds "s" at the end of a string if a given number is > 1
    */
   return $text . $num>1 ? 's' : '';
}

function endsWith( $str, $sub ) {
    return ( substr( $str, strlen( $str ) - strlen( $sub ) ) == $sub );
}

function operatorFunction($operator) {
   $OPERATOR = array("<" => create_function('$ls, $rs', 'return $ls < $rs;'),
                            "<=" => create_function('$ls, $rs', 'return $ls <= $rs;'),
                            "=" => create_function('$ls, $rs', 'return $ls == $rs;'),
                            "!=" => create_function('$ls, $rs', 'return $ls != $rs;'),
                            "/=" => create_function('$ls, $rs', 'return $ls != $rs;'),
                            ">=" => create_function('$ls, $rs', 'return $ls >= $rs;'),
                            ">" =>  create_function('$ls, $rs', 'return $ls > $rs;'),
                            "in" => create_function('$ls, $rs', 'return in_array($rs,$ls);'));

   return $OPERATOR[$operator];
}

class Predicate {
   /*
      A predicate to be evaluated in a tree's node.
   */

   const TM_TOKENS = 'tokens_only';
   const TM_FULL_TERM = 'full_terms_only';
   const TM_ALL = 'all';
   const FULL_TERM_PATTERN = "/^.+\b.+$/u"; 

   private static $RELATIONS = array('<=' => 'no more than %s %s', 
                  '>=' => '%s %s at most',
                   '>' => 'more than %s %s',
                   '<' => 'less than %s %s');

   public $operator;
   public $field;
   public $value;
   public $term;
   public $missing;

   public function __construct($operator, $field, $value, $term=null) {
         $this->operator = $operator;
	 $this->missing = false;

         $this->field = $field;
         $this->value = $value;
         $this->term = $term;

         if (endsWith($this->operator, "*") ) {
            $this->operator = substr($this->operator, 0, -1);
            $this->missing = true;
         }
   }

   function is_full_term($fields) {
      /*
         Returns a boolean showing if a term is considered as a full_term
      */
      if ($this->term != null) {
         if ($fields->{$this->field}->optype == 'items') {
	    return false;
	 }

         $options = $fields->{$this->field}->term_analysis;
         $token_mode = property_exists($options, 'token_mode') ? $options->token_mode : Predicate::TM_TOKENS;

         if ($token_mode == Predicate::TM_FULL_TERM ) {
            return true;
         } elseif ($token_mode == Predicate::TM_ALL)  {
            return preg_match(Predicate::FULL_TERM_PATTERN, $this->term);
         }

      }
      return false;
   }


   function to_rule($fields, $label='name', $missing=null) {
      /*
       Builds rule string from a predicate
      */

      if (is_null($missing)){
        $missing = $this->missing; 
      }
      if (!is_null($label)) {
        $name=$fields->{$this->field}->{$label};
	if (!mb_detect_encoding($fields->{$this->field}->{$label}, 'UTF-8', true)) {
	    $name = utf8_encode($fields->{$this->field}->{$label});
        }
      } else {
        $name = "";
      }

      $full_term = $this->is_full_term($fields);
      $relation_missing = '';

      if ($missing) {
          $relation_missing =' or missing';
      }

      $value = $this->value;
      if (is_array($this->value)) {
          $value = implode(',', $this->value);
      }

      if ($this->term != null ) {

         $relation_suffix = '';
         $relation_literal = '';
         if ( ($this->operator == '<' && $this->value <= 1) || ($this->operator == '<=' && $this->value ==0) ) {
            $relation_literal = $full_term ? 'is not equal to' : 'does not contain';
         } else {
            $relation_literal = $full_term ? 'is equal to' : 'contains';
            if (!$full_term) {
               if ($this->operator != '>' || $this->value != 0) {
                  $relation_suffix = $this->RELATIONS[$this->operator] . $this->value . plural('time', $this->value);
               }
            }
         }
         return $name . " " . $relation_literal . " " . $this->term . " " . $relation_suffix . $relation_missing;
      }

      if (is_null($this->value)) {
         if ($this->operator == "=")
             return $name . " is missing";
         else
             return $name . " is not missing";
      }

      return $name . " " . $this->operator . " ". $value . $relation_missing;
 
   }

   function apply($input_data, $fields) {
      /*
         Applies the operators defined in the predicate as strings toi the provided input data
      */

      // for missing operators
      if (!array_key_exists($this->field, $input_data) ) {
        // text and item fields will treat missing values by following the
	// doesn't contain branch
	if (is_null($this->term)){
           return ( $this->missing || ($this->operator == '=' && is_null($this->value)) ); 
	}
      } else if ($this->operator == "!=" && is_null($this->value)) {
        return true;
      }

      $op = operatorFunction($this->operator);

      if ($this->term != null ) {

         if ($fields->{$this->field}->optype == 'text') {
            $term_forms = property_exists($fields->{$this->field}->summary, 'term_forms') && !empty($fields->{$this->field}->summary->term_forms) ? 
                           property_exists($fields->{$this->field}->summary->term_forms, $this->term) ? $fields->{$this->field}->summary->term_forms->{$this->term} 
                           : array() 
                           : array();
            $terms = array($this->term);
	    $terms = array_merge($terms, $term_forms);
            $options = $fields->{$this->field}->term_analysis;
	    return $op(term_matches(array_key_exists($this->field, $input_data) ? $input_data[$this->field] : "", $terms, $options), $this->value);
	 } else {
	    $options = $fields->{$this->field}->item_analysis;
	    return $op(item_matches(array_key_exists($this->field, $input_data) ? $input_data[$this->field] : "", $this->term, $options), $this->value);
	 } 
      } 

      if ($this->operator == "in") {
          return $op($this->value, $input_data[$this->field]);
      } else {
          return $op($input_data[$this->field], $this->value);
      }  
   }

   function to_list_rule($fields) {
     /*
       Builds rule string in LISP from a predicate
      */
     if (!is_null($this->term)) {
        if ($fields[$this->field]['optype'] == 'text') {
           $options = $fields[$this->field]["term_analysis"];
           $case_insensitive =  array_key_exists("case_sensitive", $options) ? !$options["case_sensitive"] : true;
           $case_insensitive = $case_sensitive ? 'true' : 'false';
           $language = array_key_exists("language", $options) ? $options['language'] : null;
	   $language = is_null($language) ? '' : " " . $language;

           return  "(" . $operator . " (occurrences (f " . $this->field_id .
                    ") " . $this->term . " " . $case_insensitive . $language .") ". $this->value . ")";

	} else if ($fields[$this->field]['optype'] == 'items') {
	}

     }

     if (is_null($this->value)) {
        $negation = ($this->operator == "=") ? "" : "not ";
	return "(" . $negation . " (missing? " . $this->field . ")";
     }

     $rule = "(" . $this->operator ." (f " . $this->field . ") " . $this->value .")";

     if ($this->missing) {
       $rule = "(or (missing? " . $this->field .") ". $rule .")";
     }

     return rule;
   }

}

?>
