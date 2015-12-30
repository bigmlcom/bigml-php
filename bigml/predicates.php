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

if (!class_exists('predicate')) {
   include('predicate.php');       
} 

/*

Predicates structure for the BigML local AnomalyTree

This module defines an auxiliary Predicates structure that is used in the
AnomalyTree to save the node's predicates info.

*/

class Predicates {
   /*
    A list of predicates to be evaluated in an anomaly tree's node.
   */

   public $predicates;

   public function __construct($predicates_list) {
        $this->predicates = array();

        foreach ($predicates_list as $predicate) {
	    if ( is_bool($predicate) &&  $predicate == true) {
               array_push($this->predicates, true);
            } else {
               array_push($this->predicates, new Predicate($predicate->op, $predicate->field, $predicate->value, property_exists($predicate, "term") ? $predicate->term : null));
            }
        }  
   }

   function to_rule($fields, $label='name') {
      /*
       Builds rule string from a predicates list
      */
      $values = array();
      foreach ($this->predicates as $predicate) {

         if (!is_bool($predicate)) {
            array_push($values, $predicate->to_rule($fields, $label)); 
         }
      }

      return join(" and ", $values); 

   }

   function apply($input_data, $fields) {
      /*
       Applies the operators defined in each of the predicates to
       the provided input data
      */
      foreach ($this->predicates as $predicate)  {
          if ( is_object($predicate)) {
             if ($predicate->apply($input_data, $fields) == false) 
                return false;
          } 
      }

      return true;

   }

}

?>
