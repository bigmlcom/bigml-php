<?php
#
# Copyright 2012-2017 BigML
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


# Tree structure for the BigML local boosted Model

# This module defines an auxiliary Tree structure that is used in the local 
# boosted Ensemble to predict locally or embedded into your application 
# without needing to send requests to BigML.io.

namespace BigML;

if (!class_exists('BigML\Predicate')) {
   include('predicate.php'); 
}

if (!class_exists('BigML\Prediction')) {
   include('prediction.php'); 
}

if (!class_exists('BigML\Tree')) {
   include('tree.php');
}

class BoostedTree {
        /*
        A boosted tree-like predictive model
        */

        public $fields;
        public $output;
        public $predicate;
        public $weight;
        public $lambda;
        public $objective_class;

        public function __construct($tree, $fields, $boosting_info) {
                        
            $this->fields = $fields;
            $this->output = $tree->output;

            $this->weight = $boosting_info->weight;
            $this->lambda = $boosting_info->lambda;

            if (isset($boosting_info->objective_class)) {
                $this->objective_class = $boosting_info->objective_class;
            }

            if ($tree->predicate === true) {
                $this->predicate = true;
            } else {
                if (isset($tree->predicate->term)) {
                    $this->predicate = new Predicate($tree->predicate->operator, 
                                                     $tree->predicate->field, 
                                                     $tree->predicate->value, 
                                                     $tree->predicate->term);
                } else {
                    $this->predicate = new Predicate($tree->predicate->operator, 
                                                     $tree->predicate->field, 
                                                     $tree->predicate->value, 
                                                     null);
                }
            }

            $this->id = $tree->id;
            $children = [];
            if (isset($tree->children)) {
                    foreach ($tree->children as $child) {
                        array_push($children, new BoostedTree($child, $fields, $boosting_info));
                    }
            }
            $this->children = $children;
            $this->count = $tree->count;
            $this->g_sum = $tree->g_sum;
            $this->h_sum = $tree->h_sum;

        }

        public function predict($input_data, $path=null, $missing_strategy=Tree::LAST_PREDICTION) {
            /*
              Makes a prediction based on a number of field values.

              The input fields must be keyed by Id. There are two possible
              strategies to predict when the value for the splitting field
              is missing:
                  0 - LAST_PREDICTION: the last issued prediction is returned.
                  1 - PROPORTIONAL: we consider all possible outcomes and create
                                    an average prediction.
              */

            if ($path == null) {
                $path = [];
            }
            if ($missing_strategy == Tree::PROPORTIONAL) {
                return $this->predict_proportional($input_data, $path);
            } else {
                if ($this->children) {
                    foreach ($this->children as $child) {
                        if ($child->predicate->apply($input_data, $this->fields)) {
                            array_push($path, $child->predicate->to_rule($this->fields));
                            return $child->predict($input_data, $path);

                        }
                    }
                }

                return new Prediction($this->output, 
                                      $path, 
                                      null, 
                                      null, 
                                      $this->count,
                                      null,
                                      null,
                                      $this->children,
                                      null,
                                      null);
            }
        }

        public function predict_proportional($input_data, $path=null, $missing_found=false) {
            /*
              Makes a prediction based on a number of field values considering all
              the predictions of the leaves that fall in a subtree.

              Each time a splitting field has no value assigned, we consider both 
              branches of the split to be true, merging their predictions. The
              function returns the merged distribution and the last node reached 
              by a unique path.
            */

            if ($path == null) {
                $path = [];
            }

            if (!$this->children) {
                return array($this->g_sum, $this->h_sum, $this->count, $path);
            }

            $b = false;
            if (isset($this->fields->children)) {
                $child_type = $this->fields->children[0]->predicate->field['optype'];
                $b = ($child_type == 'text' OR $child_type == 'items');
            }

            if (one_branch($this->children, $input_data) OR $b) {
                foreach ($this->children as $child) {
                    if ($child->predicate->apply($input_data, $this->fields)) {
                        $new_rule = $child->predicate->to_rule($this->fields);
                        if (!in_array($new_rule, $path) AND !$missing_found) {
                            array_push($path, $new_rule);
                        }
                        return $child->predict_proportional($input_data, $path, $missing_found);
                    }
                }
           } else {
                $missing_found = true;
                $g_sums = 0.0;
                $h_sums = 0.0;
                $population = 0;
                foreach ($this->children as $child) {
                    $child_prediction = $child->predict_proportional($input_data, $path, $missing_found);
                    $g_sum = $child_prediction->g_sum;
                    $h_sum = $child_prediction->h_sum;
                    $count = $child_prediction->count;
                    
                    $g_sums += $g_sum;
                    $h_sums += $h_sum;
                    $population += $count;
                }
                return array($g_sums, $h_sums, $population, $path);
            }
        }
}            
?>