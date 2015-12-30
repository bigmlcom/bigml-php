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

if (!class_exists('predicates')) {
   include('predicates.php');       
} 

/*

Tree structure for the BigML local Anomaly Detector

This module defines an auxiliary Tree structure that is used in the local
Anomaly Detector to score anomalies locally or embedded into your application
without needing to send requests to BigML.io.

*/

class AnomalyTree {
   /*
    An anomaly tree-like predictive model.
   */

   public $fields;
   public $predicates;
   public $id;
   public $children;

   public function __construct($tree, $fields) {

       $this->fields = $fields;
       if ( is_bool ($tree->predicates) &&  $tree->predicates == true) {
          $this->predicates = new Predicates(array(true));
       } else {
          $this->predicates = new Predicates($tree->predicates);
          $this->id = null;
       } 
      
       $this->children = array();
       if (property_exists($tree, "children")) {
          foreach ($tree->children as $child) {
            array_push($this->children, new AnomalyTree($child, $this->fields));
	  }
       }
   }

   function list_fields($out) {
      /*
       Lists a description of the model's fields.
      */
           
      $a = "<" . $this->fields->{$this->objective_id}->name . str_repeat(utf8_encode(' '), 32) . ": " . $this->fields->{$this->objective_id}->optype . ">";
      fwrite($out, $a);
      fflush($out); 
 
      foreach($this->sort_fields($this->fields) as $key => $val) {
         if ($key != $this->objective_id) {
            $a = '<' . $val->name . str_repeat(utf8_encode(' '), 32) . ': ' . $val->optype . '>';
	    fwrite($out, $a);
            fflush($out); 
         } 
      }

      return $this->fields;

   }

   function depth($input_data, $path=null, $depth=0) {
      /*
       Returns the depth of the node that reaches the input data instance
       when ran through the tree, and the associated set of rules.

       If a node has any children whose
       predicates are all true given the instance, then the instance will
       flow through that child.  If the node has no children or no
       children with all valid predicates, then it outputs the depth of the
       node.
      */
      if ($path == null) {
         $path = array();
      }

      # root node: if predicates are met, depth becomes 1, otherwise is 0
      if ($depth == 0) {
         if (!$this->predicates->apply($input_data, $this->fields)) {
            return array($depth, $path);
         }
         $depth+=1;
      }

      if ($this->children != null) {
         foreach ($this->children as $child) {
            if ($child->predicates->apply($input_data, $this->fields)) {
               array_push($path, $child->predicates->to_rule($this->fields));
               return $child->depth($input_data, $path, $depth+1); 
            }
         }
      }
      return array($depth, $path);

   }

   private function sort_fields($fields) {
      /*
         Sort fields by their column_number but put children after parents.
      */
      $fathers = array();
      $children = array();

      $new_array_childs = array();
      $new_array_fathers = array();

      foreach($this->fields as $key => $value) {

         if (property_exists($value, "auto_generated") ) {
            $new_array_childs[$key] = $value->column_number;
         } else {
            $new_array_fathers[$key] = $value->column_number;
         }
      }

      arsort($new_array_childs);
      asort($new_array_fathers);

      $fathers_keys = array();

      foreach($new_array_childs as $key => $value) {
         array_push($children, array($key, $fields->{$key}));
      }

      foreach($new_array_fathers as $key => $value) {
         array_push($fathers, array($key, $fields->{$key}));
         array_push($fathers_keys, $key);
      }

      foreach($children as $child => $value) {
          $index = array_search($value[1]->parent_ids[0], $fathers_keys);
         if ($index >=0) {
            $fathers = array_slice($fathers, 0, $index, true) + array(array($value, $child)) + array_slice($fathers, $index+1, count($fathers), true);
         } else {
            array_push($fathers, array($value, $child));
         }
      }

      return $fathers;
   }
}

?>
