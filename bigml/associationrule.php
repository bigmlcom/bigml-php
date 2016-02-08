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

/*
  Association Rule object.

  This module defines each Rule in an Association Rule.
*/
class AssociationRule {
  public $rule_id;
  public $confidence;
  public $leverage;
  public $lhs;
  public $lhs_cover;
  public $p_value;
  public $rhs;
  public $rhs_cover;
  public $lift;
  public $support;

  public function __construct($rule_info) {
     /*
      Object encapsulating an association rule as described in
              https://bigml.com/developers/associations
      */
     $this->rule_id = $rule_info->id;
     $this->confidence = $rule_info->confidence;
     $this->leverage = $rule_info->leverage;
     $this->lhs = property_exists($rule_info, "lhs") ? $rule_info->lhs : array();
     $this->lhs_cover = property_exists($rule_info, "lhs_cover") ? $rule_info->lhs_cover : array();
     $this->p_value = $rule_info->p_value;
     $this->rhs =  property_exists($rule_info, "rhs") ? $rule_info->rhs : array();
     $this->rhs_cover =  property_exists($rule_info, "rhs_cover") ? $rule_info->rhs_cover : array();
     $this->support =  property_exists($rule_info, "support") ? $rule_info->support : array();
     $this->lift = $rule_info->lift;
  }

  public function out_format($language="JSON") {
     # Transforming the rule structure to a string in the required format
     if (in_array($language, array("JSON", "CSV") )) {
        $name="to_" . strtolower($language);
        return $this->{$name};
     } 
     return $this; 
  }

  public function to_csv() {
    /*Transforming the rule to CSV formats
       Metrics ordered as in ASSOCIATION_METRICS in association.php */
    $output = array($this->rule_id, 
                    json_encode($this->lhs),
		    json_encode($this->rhs), 
		    !is_null($this->lhs_cover) ? $this->lhs_cover[0] : null,
		    !is_null($this->lhs_cover) ? $this->lhs_cover[1] : null,
		    !is_null($this->support) ? $this->support[0] : null,
		    !is_null($this->support) ? $this->support[1] : null,
		    $this->confidence,
		    $this->leverage,
		    $this->lift,
		    $this->p_value,
		    !is_null($this->rhs_cover) ? $this->rhs_cover[0] : null,
		    !is_null($this->rhs_cover) ? $this->rhs_cover[1] : null
                    );

    return implode(",", $output);
  }
 
  public function to_json() {
    /*Transforming the rule to JSON*/
    return json_encode($this);
  }

  public function to_lisp_rule() {
   # Transforming the rule in a LISP flatline filter to select the
   # rows in the dataset that fulfill the rule
   $items = array();
   foreach ($this->lhs as $index) {
     $array_push($items, $item_list[$index]->to_lisp_rule());
   }
   $items = array();
   foreach ($this->rhs as $index) {
     $array_push($items, $item_list[$index]->to_lisp_rule());
   }

   return "(and $s)" . implode("",  $items);

  }
}
