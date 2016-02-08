<?php
#
# Copyright 2015 BigML
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
if (!class_exists('item')) {
   include('item.php');
}   
if (!class_exists('associationrule')) {
   include('associationrule.php');
}   

define("NO_ITEMS", json_encode(array('numeric', 'categorical')));
#define("SEARCH_STRATEGY_ATTRIBUTES", json_encode(array(0 =>  "leverage", 
#                                                       1 => "confidence", 
#						       2 => "support", 
#						       3 => "lhs_coverage", 
#						       4=> "lift")));

define("RULE_HEADERS", json_encode(array("Rule ID", "Antecedent", "Consequent", "Antecedent Coverage %",
                                         "Antecedent Coverage", "Support %", "Support", "Confidence",
			                 "Leverage", "Lift", "p-value", "Consequent Coverage %",
					 "Consequent Coverage")));

define("ASSOCIATION_METRICS",  json_encode(array("lhs_cover", "support", "confidence",
                                                 "leverage", "lift", "p_value")));

define("METRIC_LITERALS", json_encode(array("confidence"=> "Confidence", "support"=> "Support",
                                            "leverage"=> "Leverage", "lhs_cover"=> "Coverage",
                                            "p_value"=> "p-value", "lift"=> "Lift")));

define("SCORES", json_encode(array("lhs_cover", "support", "confidence",
                                    "leverage", "lift")));

define("DEFAULT_K", 100);
define("DEFAULT_SEARCH_STRATEGY", "leverage");

class Association extends ModelFields{
  /*
    A lightweight wrapper around an Association rules object.
    Uses a BigML remote association resource to build a local version
    that can be used to extract associations information.
   */
   const DEFAULT_SEARCH_STRATEGY = "leverage";
   const DEFAULT_K = 100;

   public $resource_id = null;
   public $complement = null;
   public $discretization;
   public $field_discretizations;
   public $items;  
   public $k = null;
   public $max_lhs=null;
   public $min_coverage = null; 
   public $min_leverage = null; 
   public $min_strength = null; 
   public $min_support = null; 
   public $min_lift = null; 
   public $prune = null; 
   public $search_strategy;
   public $rules;
   public $significance_level = null; 

   public function __construct($association, $api=null) {
      $this->discretization=array();
      $this->field_discretizations=array();
      $this->items =array();
      $this->search_strategy = Association::DEFAULT_SEARCH_STRATEGY;
      $this->rules = array();

      #$SEARCH_STRATEGY_CODES = array("leverage"=> 0, "confidence"=> 1, "support"=> 2, "coverage" => 3, "lift" => 4);

      if (is_string($association)) {
         
         if (file_exists($association))
         {
            $association = json_decode(file_get_contents($association));
         } else if (!($api::_checkAssociationId($association)) ) {
            error_log("Wrong association id");
            return null;
         } else {
            $association = $api::retrieve_resource($association, $api::ONLY_MODEL);
         }

      }

      if ($association == null || !property_exists($association, 'resource') ) {
         error_log("Cannot create the Association instance. Could not find the 'association' key in the resource");
         throw new Exception('Cannot create the association instance. Could not find the association key in the resource');
      }

      if (property_exists($association, "object") && property_exists($association->object, "status") && $association->object->status->code != BigMLRequest::FINISHED ) {
         throw new Exception("The association isn't finished yet");
      }

      if (property_exists($association, "object") && $association->object instanceof STDClass) {
         $association=$association->object;
         if (property_exists($association, "associations") && $association->associations instanceof STDClass) {
            if ( property_exists($association, "status") && $association->status->code == BigMLRequest::FINISHED ) {

               $associations = $association->associations;
               $fields = $associations->fields;
               parent::__construct($fields); 
 
               $this->complement = property_exists($associations, "complement") ? $associations->complement : false;
               $this->discretization = property_exists($associations, "discretization") ? $associations->discretization : array();
               $this->field_discretizations = property_exists($associations, "field_discretizations") ? $associations->field_discretizations : array();

               $items = property_exists($associations, "items") ? $associations->items : array();
             
               foreach ($items as $index => $item) {
                  array_push($this->items, new Item($index, $item, $fields)); 
               }
               
               $this->k = property_exists($associations, "k") ? $associations->k : 100;
               $this->max_lhs = property_exists($associations, "max_lhs") ? $associations->max_lhs : 4;
               $this->min_coverage = property_exists($associations, "min_coverage") ? $associations->min_coverage : 0;
               $this->min_leverage = property_exists($associations, "min_leverage") ? $associations->min_leverage : -1;
               $this->min_strength = property_exists($associations, "min_strength") ? $associations->min_strength : 0; 
               $this->min_support = property_exists($associations, "min_support") ? $associations->min_support : 0;
               $this->min_lift = property_exists($associations, "min_lift") ? $associations->min_lift : 0;
               $this->prune = property_exists($associations, "prune") ? $associations->prune : true;
               #$this->search_strategy = $SEARCH_STRATEGY_CODES[property_exists($associations, "search_strategy") ? $associations->search_strategy : Association::DEFAULT_SEARCH_STRATEGY];
            
	       $this->search_strategy = property_exists($associations, "search_strategy") ? $associations->search_strategy : $DEFAULT_SEARCH_STRATEGY;

               $rules=property_exists($associations, "rules") ? $associations->rules : array();
               foreach ($rules as $rule) {
                   array_push($this->rules, new AssociationRule($rule));
               }

               $this->significance_level = property_exists($associations, "significance_level") ? $associations->significance_level : 0.05;
 
            } else {
               throw new Exception("The association isn't finished yet");
            }
        
         } else {
            throw new Exception("Cannot create the Association instance. Could not find the 'association' key in the resource:\n\n" . json_encode($association));
         }

      } else {
          throw new Exception("Cannot create the Association instance. Could not find the 'association' key in the resource:\n\n" . json_encode($association));
      }

   }

   public function  association_set($input_data, $k=Association::DEFAULT_K, $score_by=null, $by_name=true) {
     /*
      Returns the Consequents for the rules whose LHS best match
           the provided items. Cosine similarity is used to score the match.

            @param inputs dict map of input data: e.g.
                               {"petal length": 4.4,
                                "sepal length": 5.1,
                                "petal width": 1.3,
                                "sepal width": 2.1,
                                "species": "Iris-versicolor"}
            @param k integer Maximum number of item predictions to return
                             (Default 100)
            @param max_rules integer Maximum number of rules to return per item
	    @param score_by Code for the metric used in scoring
	           (default search_strategy)
                Leverage
                Confidence
                Support
                Coverage
                Lift

            @param by_name boolean If True, input_data is keyed by field
                                   name, field_id is used otherwise. 
     */ 
     $predictions = array();
     if ($score_by != null && !in_array($score_by, json_decode($SCORES)) ) {
        throw new Exception("The available values of score_by are " . $SCORES); 
     }

     $input_data = $this->filter_input_data($input_data, $by_name);
     # retrieving the items in input_data
     $item_indexes = array();
     foreach ($this->get_items(null, null, $input_data) as $item) {
        array_push($item_indexes, $item->index); 
     }
 
     if (is_null($score_by)) {
       $score_by = $this->search_strategy;
     } 

     foreach ($this->rules as $rule) {
       $field_type = $this->fields[$this->items[$rule->rhs[0]]->field_id]["optype"];
       # if the rhs corresponds to a non-itemized field and this field
       # is already in input_data, don't add rhs    
       if (in_array($field_type, json_decode(NO_ITEMS)) and
          (in_array($this->items[$rule->rhs[0]]["field_id"], $input_data)) ) {
           continue;
       }
       
       # if an itemized content is in input_data, don't add it to the
       # prediction
       if (!in_array($field_type, json_decode(NO_ITEMS))) {
          continue;
       }
       $cosine=0;
       foreach ($item_indexes as $index) {
          if (in_array($index, $rule->lhs)) {
             $cosine+=1;
          }
       }

       if ($cosine > 0){
          $cosine = ($cosine / floatval(sqrt(count($item_indexes)) * sqrt($count($rule->lhs)) ) );

          $rhs = $rule.rhs; 
          if (!in_array($rhs, $predictions) ) {
             $predictions[$rhs] = array("score" => 0);
          }
          $predictions[$rhs]["score"] += $cosine*$rule->{$score_by};

       } 

     }

     # choose the best k predictions
     $k=is_null($k) ? count(array_keys($predictions)) : $k;
     $order_array=array();
     foreach ($predictions as $key => $row) {
         $order_array[$key] = $row[1]["score"];
     } 

     array_multisort($order_array, SORT_DESC, $predictions);

     $final_predictions = array();
     foreach ($predictions as $rhs => $prediction) {
        $prediction["item"] = json_encode($this->items[$rhs[0]]);
        array_push($final_predictions, $prediction);
     }

     return $final_predictions; 
 

   }

   public function get_items($field=null, $names=null, $input_map=null, $filter_function=null) {
     /* Returns the items array, previously selected by the field
        corresponding to the given field name or a user-defined function */

     $items=array();
     $field_id = null;
     if ($field != null) {
        if (in_array($field, $this->fields)) {
           $field_id = $field;
        } else if (in_array($field, $this->inverted_fields)) {
           $field_id = $this->inverted_fields[$field];
        } else {
           throw new Exception("Failed to find a field name or ID corresponding to " . $field);
        }
     }

     $is_true = true;
     foreach ($this->items as $item) {
          $item_array = array_unique(array($this->field_filter($item, $field, $field_id), 
                                           $this->names_filter($item, $names),
                                           $this->input_map_filter($item, $input_map),
                                           $this->filter_function_set($item, $filter_function)));

          if (count($item_array) == 1 and $item_array[0] == true) {
             array_push($items, $item);
          }

     }

     return $items;
 
   }

   private function names_filter($item, $names) {
     /*Checking if an item by name */
     if (is_null($names)) {
         return true;
     }
      
     return in_array($item->name, $names);
   
   }

   private function field_filter($item, $field, $field_id) {
      /*Checking if an item is associated to a fieldInfo */
      if (is_null($field)) {
         return true;
      }

      return $item->field_id == $field_id;
   } 

   private function input_map_filter($item, $input_map) {
     if (is_null($input_map)) {
        return true;
     }
     $value = $input_map[$item->field_id];
     return $item->matches($value);

   }

   private function check_minimun_value($rule, $min_name, $min_value) {
     # Check minimum min_name 
     if (is_null($min_value)) {
        return true;
     }

     return $rule->{$min_name} >= $min_value;
   }

   private function filter_function_set($rule, $filter_function) {
     # Checking filter function if set
     if (is_null($filter_function)) {
        return true;
     }
     return $filter_function($rule);# php 5.3 or higher 
   }

   private function item_list_set($rule, $item_list) {
     # Checking if any of the items list is in a rule
     if (is_null($item_list)) {
        return true;
     } 
     $items = array(); 
     if (is_a($item_list[0], "item") ){
        foreach ($item_list as $item) {
           array_push($items, $item->index);
        }
     } else if (is_string($item_list[0])) {
        foreach ($this->get_items(null, $item_list) as $item) {
           array_push($items, $item->index);
        } 
     }

     foreach ($rule->lhs as $item_index) {
       if (in_array($item_index, $items)) {
          return true;
       }
     }

     foreach ($rule->rhs as $item_index) {
       if (in_array($item_index, $items)) {
          return true;
       }
     }

     return false;

   }
 
   function get_rules($min_leverage=null, $min_strength=null, $min_support=null, 
                      $min_p_value=null, $item_list=null, $filter_function=null) {
     /*
        Returns the rules array, previously selected by the leverage,
        strength, support or a user-defined filter function (if set)

        @param float min_leverage   Minum leverage value
        @param float min_strength   Minum strength value
        @param float min_support   Minum support value
        @param float min_p_value   Minum p_value value
        @param List item_list   List of Item objects. Any of them should be
                                   in the rules
        @param function filter_function   Function used as filter 
     */ 

     $rules = array();
     $names_min = array('leverage' => $min_leverage, 'strength' => $min_strength, 
                         'support'=> $min_support, 'p_value' => $min_p_value);
     foreach ($this->rules as $rule) {
         $is_true = true;
         foreach ($names_min as $name => $value) {
             if ($this->check_minimun_value($rule, $name, $value) == false) {
                $is_true=false;
                break;
             }

             if ($is_true && (!$this->item_list_set($rule, $item_list) or !$this->filter_function_set($rule, $filter_function))) {
                $is_true=false;
             }
         }

         if ($is_true){
           array_push($rules, $rule);
         } 
     }  

     return $rules;
  
   } 

   function rules_csv($filename, $args) {
     /* Stores the rules in CSV format in the user-given file. The rules
        can be previously selected using the arguments in get_rules
      */
      $rules = $this->get_rules($args);
      $rules_new = array();
      foreach ($rules as $rules) {
         array_push($rules_new, $rule->to_csv());
      }

      if (is_null($filename)) {
         error_log("A valid file name is required to store the rules.");
         throw new Exception('A valid file name is required to store the rules.');
      }

      $file = fopen("$filename", "w");
      fwrite($file, implode(",",json_decode($RULE_HEADERS)) . "\n");
      foreach ($rules_new as $rule) {
        $tmp_array();
        foreach ($rule as $item) {
	  array_push($tmp_array, $item); 
	}
        fwrite($file, implode(",", $tmp_array) . "\n"); 
      }
      fclose($file);

   }

   function describe($rule_row) {
      /* Transforms the lhs and rhs index information to a human-readable
         rule text.*/
   
      foreach(range(1,2) as $index) {
         $description = array();
	 foreach($rule_row[$index] as $item_index) {
	    $item=$this->items[$item_index];
	    $item_description = (count(array_keys($this->fields)) == 1 && !$item->complement) ? $item->name : $item->describe();
	    array_push($description, $item->description);
	 }
         $description_str = implode(" & ", $description);
         $rule_row[$index] = $description_str; 
      }
      return $rule_row;
   }   

   function summarize($out=STDOUT, $limit=10, $args) {
     /* Prints a summary of the obtained rules*/
     $rules = $this->get_rules($args);
     fwrite($out, "Total number of rules: " . count($rules) . "\n");
     $INDENT = utf8_encode('    ');
 
     foreach (json_decode($ASSOCIATION_METRICS) as $metric) {
        fwrite($out, "\n\nTop " . $limit . " by " . json_decode($METRIC_LITERALS[$metric]) . "\n\n");
        $order_array = array();
        $top_rules = $rules;
        foreach ($rules as $k => $row) {
          $order_array[$k] = $row->x;
        }

        array_multisort($order_array, SORT_DESC, $toprules);
        $top_rules = array_slice($toprules, 0, $limit*2); 

        $out_rules = array();
        $ref_rules = array();

        $counter = 0;
        foreach ($top_rules as $rule) {
           $rule_row = $this->describe($rule->to_csv());
           $metric_string = $this->get_metric_string($rule);
           $operator = "->";
           $rule_id_string = "Rule " . $rule->rule_id; 

           foreach ($top_rules as $item) {
               if ($rule->rhs == $item->lhs && 
                   $rule->lhs == $item->rhs 
                   && $metric_string == get_metric_string($item, true)) {
                   $rule_id_string = "Rules " . $rule->rule_id . ", " . $item->rule_id . ":";
                   $operator = "<->";
               }
           }

           $out_rule = $rule_row[1] . " " . $operator . " " . 
                       $rule_row[2] . "[" . $metric_string . "]";


           $reverse_rule = $rule_row[2] . " " . $operator . " " . 
                           $rule_row[1] . "[" . $metric_string . "]";
 
           if ($operator == "->" or !in_array($reverse_rule, $ref_rules)) {
              array_push($ref_rules, $out_rule);
              $out_rule = str_repeat($INDENT,2) . $rule_id_string . $out_rule;

              array_push($out_rules, $out_rule);
              $counter+=1;
              if ($counter > $limit) {
                 break;
              } 
           }
        }

        fwrite($out, implode("\n", $out_rules));
     }
     fwrite($out, "\n");

   }


   function get_metric_string($rule, $reverse=false) {
    /* Returns the string that describes the values of metrics for a rule. */
    $metric_values=array();
    foreach (json_decode($ASSOCIATION_METRICS) as $metric) {
       if ($reverse && $metric == "lhs_cover") {
         $metric_key = "rhs_cover";
       } else {
         $metric_key = $metric;
       }

       $metric_value=$rule->metric_key;

       if (is_array($metric_value)) {
          array_push($metric_values, json_decode($METRIC_LITERALS[$metric]) . "=" . 
                                       number_format(round($metric_value[0], 4)*100, 2) . "% (" . $metric_value[1] . ")");

       } else if ($metric == "confidence") {
          array_push($metric_values, json_decode($METRIC_LITERALS[$metric]) . "=" . 
                                      number_format(round($metric_value[0], 4)*100, 2) . "%");
       } else {
          array_push($metric_values, json_decode($METRIC_LITERALS[$metric]) . "=" . $metric_value);
       }
    }

    return implode("; ", $metric_values);

    
   }

}
