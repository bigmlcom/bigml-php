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
if (!class_exists('basemodel')) {
  include('basemodel.php'); 
}
if (!class_exists('tree')) {
   include('tree.php');
}
if (!class_exists('path')) {
   include('path.php');
}   

function _ditribution_sum($x, $y) {
    return $x+$y;
}

function print_distribution($distribution, $out=STDOUT) {
  /*Prints distribution data*/
  $a = array();
  foreach ($distribution as $group) {
     array_push($a, $group[1]);
  }
  $total = array_reduce($a, "_ditribution_sum");
  foreach ($distribution as $group) {
    fwrite($out, "    " . $group[0] . ": " . number_format(round(($group[1]*1.0)/$total, 4)*100, 2) . "% (". strval($group[1]) ." instance" . ($group[1] == 1 ? ")\n" : "s)\n"));  
  } 

}

class Model extends BaseModel{
   /*
      A lightweight wrapper around a Tree model.
      Uses a BigML remote model to build a local version that can be used
      to generate predictions locally.
   */

   public $ids_map; 
   public $terms;
   public $tree;
   public $regression_ready=false;
   public $_max_bins;

   public function __construct($model, $api=null, $storage="storage") {

      if ($api == null) {
         $api = new BigML(null, null, null, $storage);
      }

      $this->ids_map = array();
      $this->terms = array();

      if (is_string($model)) {

         if (file_exists($model))
	 {
	    $model = json_decode(file_get_contents($model));
	 } else if (!($api::_checkModelId($model)) ) {
            error_log("Wrong model id");
            return null;
         } else {
            $model = $api::retrieve_resource($model, $api::ONLY_MODEL);
	 }
      } 

      if ($model == null || !property_exists($model, 'resource') ) {
         error_log("Cannot create the Model instance. Could not find the 'model' key in the resource");
         throw new Exception('Cannot create the Model instance. Could not find the model key in the resource');
      }

      if (property_exists($model, "object") && property_exists($model->object, "status") && $model->object->status->code != BigMLRequest::FINISHED ) {
         throw new Exception("The model isn't finished yet");
      }

      parent::__construct($model);
         
      if (property_exists($model, "object") && $model->object instanceof STDClass) {
         $model=$model->object;
      }

      if (property_exists($model, "model") && $model->model instanceof STDClass) {

         if ($model->status->code == BigMLRequest::FINISHED) {
	    $tree_info = array('max_bins' => 0);
            $this->tree = new Tree($model->model->root, $this->fields, $this->objective_id, $model->model->distribution->training, null, $this->ids_map, true, $tree_info);
	    if ($this->tree->regression) {
	       $this->_max_bins = $tree_info["max_bins"];
	    }
           
         } else {
            throw new Exception("The model isn't finished yet");
         }
      } else {
         throw new Exception("Cannot create the Model instance. Could not find the 'model' key in the resource:\n\n" . $model);
      }

      if ($this->tree->regression) {
         $this->regression_ready = true;
      }
   }


   public function predict($input_data, $by_name=true,$print_path=false, $out=STDOUT, $with_confidence=false, $missing_strategy=Tree::LAST_PREDICTION,
                           $add_confidence=false, $add_path=false,$add_distribution=false,$add_count=false, $add_median=false, $add_next=false,
                           $add_min=false, $add_max=false, $add_unused_fields=false, $multiple=null)
   {
      /*
         Makes a prediction based on a number of field values.
         By default the input fields must be keyed by field name but you can use
        `by_name` to input them directly keyed by id.

         input_data: Input data to be predicted
         by_name: Boolean, true if input_data is keyed by names
        print_path: Boolean, if true the rules that lead to the prediction
                    are printed
        out: output handler
        with_confidence: Boolean, if true, all the information in the node
                         (prediction, confidence, distribution and count)
                         is returned in a list format
        missing_strategy: LAST_PREDICTION|PROPORTIONAL missing strategy for
                          missing fields
        add_confidence: Boolean, if true adds confidence to the dict output
        add_path: Boolean, if true adds path to the dict output
        add_distribution: Boolean, if true adds distribution info to the
                          dict output
        add_count: Boolean, if true adds the number of instances in the
                       node to the dict output
        add_median: Boolean, if true adds the median of the values in
                    the distribution
        add_next: Boolean, if true adds the field that determines next
                  split in the tree
        add_min: Boolean, if true adds the minimum value in the prediction's
                 distribution (for regressions only)
        add_max: Boolean, if true adds the maximum value in the prediction's
                 distribution (for regressions only)
        add_unused_fields: Boolean, if true adds the information about the
                           fields in the input_data that are not being used
                           in the model as predictors.
        multiple: For categorical fields, it will return the categories
                  in the distribution of the predicted node as a
                  list of arrays:
                    array(array('prediction' => 'Iris-setosa',
                      'confidence'=> 0.9154
                      'probability'=> 0.97
                      'count'=> 97),
                     array('prediction'=> 'Iris-virginica',
                      'confidence'=> 0.0103
                      'probability'=> 0.03,
                      'count'=> 3))
                  The value of this argument can either be an integer
                  (maximum number of categories to be returned), or the
                  literal 'all', that will cause the entire distribution
                  in the node to be returned.

      */

      # Checks if this is a regression model, using PROPORTIONAL
      # missing_strategy
      $tree = $this->tree;

      if ($tree != null && $tree->regression && $missing_strategy==Tree::PROPORTIONAL && !$this->regression_ready) {
         throw new Exception("You needed to use proportional missing strategy, 
                         for regressions. Please install them before, using local predictions for the model."); 
      }
    
      # Checks and cleans input_data leaving the fields used in the model
      $new_data = $this->filter_input_data($input_data, $by_name, $add_unused_fields);

      if ($add_unused_fields) {
         $input_data = $new_data[0];
         $unused_fields = $new_data[1];
      } else {
         $input_data = $new_data;
      } 


      # Strips affixes for numeric values and casts to the final field type
      $input_data = cast($input_data, $this->fields);

      $prediction = $tree->predict($input_data, null, $missing_strategy);

      # Prediction path   
      if ($print_path == true) {
         fwrite($out, join(" AND ", $prediction->path) . ' => ' . $prediction->output . "\n");
         fclose($out);
      }         

      $output = $prediction;

      if ($with_confidence == true) {
         $output = array($prediction->output, $prediction->confidence, $prediction->distribution, $prediction->count, $prediction->median);
      }

      if ($multiple != null && !$tree->regression) {
         $output = array();
         $total_instances = floatval($prediction->count);
	 
	 $index =0;
	 foreach ($prediction->distribution as $index => $data) {
	    $category = $data[0];
	    $instances = $data[1];

            if ((is_string($multiple) && $multiple == 'all') or 
	       ( is_int($multiple) && $index < $multiple  ) ) {

               $prediction_dict = array('prediction' => $category,
	                                'confidence' => ws_confidence($category, $prediction->distribution),
		                        'probability' => $instances / $total_instances,
		                        'count' => $instances);

	       array_push($output, $prediction_dict);

	    } 

	 }

      } else {
         
	 if ($add_confidence || $add_path || $add_distribution || $add_count || 
	     $add_median || $add_next || $add_min || $add_max || $add_unused_fields) {

             $output = (object) array('prediction'=> $prediction->output);

	     if ($add_confidence) {
	        $output->confidence = $prediction->confidence;
	     }

	     if ($add_path) {
	        $output->path = $prediction->path;
	     }
             
	     if ($add_distribution) {
	        $output->distribution = $prediction->distribution;
		$output->distribution_unit = $prediction->distribution_unit;
	     }

	     if ($add_count) {
	        $output->count = $prediction->count;
	     }

	     if ($tree->regression && $add_median) {
	        $output->median = $prediction->median;
	     }

	     if ($add_next) {
                $field = (count($prediction->children) == 0 ? null : $prediction->children[0]->predicate->field);

		if ($field != null && array_key_exists($field, $this->fields) ) {
		   $field = $this->fields->{$field}->name;
		}

		$output->next = $field;
		
	     }

	     if ($tree->regression && $add_min) {
	        $output->min = $prediction->min;
	     }

             if ($tree->regression && $add_max) {
	        $output->max = $prediction->max;
	     }

             if ($add_unused_fields) {
                $output->unused_fields = $unused_fields;
             }

	 }

      }
      return $output;

   }

   function to_prediction($value_as_string, $data_locale="UTF-8") {
      /*
         Given a prediction string, returns its value in the required type
      */

      if (!mb_check_encoding($value_as_string, 'UTF-8')) {
         $value_as_sring = utf8_encode($value_as_string);
      }

      $tree = $this->tree;
      $objective_id = $tree->objective_id;

      if ($this->fields->{$objective_id}->optype == 'numeric' ) {
         if ($data_locale==null) {
            $data_locale = $this->locale;
         }
         find_locale($data_locale);
         $datatype = $this->fields->{$objective_id}->datatype;

         if ($datatype == "double" || $datatype == "float") {
            return floatval($value_as_string);
         } else {
            return intval($value_as_string);
         }
      }

      return $value_as_string;
   }

   function find_locale($data_locale="en_US.UTF-8", $verbose=false)
   {
      try {
         setlocale(LC_ALL, $data_locale);
      } catch  (Exception $e) {
         error_log("Error find Locale"); 
      }
   }

   function rules($out=STDOUT, $filter_id=null, $subtree=true)
   {
      /*
         Returns a IF-THEN rule set that implements the model.
         `out` is file descriptor to write the rules.
      */ 
      $ids_path = $this->get_ids_path($filter_id);
      return $this->tree->rules($out, $ids_path, $subtree);

   }

   function get_ids_path($filter_id) 
   {
      /*
       Builds the list of ids that go from a given id to the tree root
      */
      $ids_path = null;
      if (!is_null($filter_id) && !is_null($this->tree->id)) {
         if (array_key_exists($filter_id, $this->ids_map)) {
            throw new Exception("The given id does not exist."); 
         } else {
            $ids_path = array($filter_id);
            $last_id = $filter_id;

            while (!is_null($this->ids_map[$last_id]->parent_id)) {
               array_push($ids_path, $this->ids_map[$last_id]->parent_id);
               $last_id = $this->ids_map[$last_id]->parent_id;
            }
         } 
      }
      return $ids_path;
   }

   function add_to_groups($groups, $output, $path, $count, $confidence,
                             $impurity=null) {
         /*  Adds instances to groups array */
         $group = $output;
         if (!array_key_exists(strval($output), $groups)) {
            $groups[strval($group)] = array('total'=> array(array(), 0, 0),
                                    'details' => array());
         }
         array_push($groups[strval($group)]['details'], array($path, $count, $confidence, $impurity));

         $groups[strval($group)]['total'][2] += $count;
         return $groups;
   }

   function depth_first_search($tree, $path, $groups) {
         /* Search for leafs values and instances */
         if (is_a($tree->predicate, 'Predicate')) {
            array_push($path, $tree->predicate);
            if ($tree->predicate->term) {
               $term = $tree->predicate->term;
               if (!array_key_exists($tree->predicate->field, $this->terms)) {
                  $this->terms->{$tree->predicate->field} = array();
               }

               if (!array_key_exists($term, $this->terms->{$tree->predicate->field})) {
                  array_push($term, $this->terms->{$tree->predicate->field});
               }
            }
         }

         if (count($tree->children) == 0) {
            $groups = $this->add_to_groups($groups, $tree->output, $path, $tree->count, $tree->confidence, $tree->impurity);
            return array($tree->count, $groups);
         } else {
            $children = $tree->children;
            $children = array_reverse($children);

            $children_sum = 0;
            foreach ($children as $child) {
               $data = $this->depth_first_search($child, $path, $groups);
               $children_sum += $data[0];
               $groups = $data[1];
            }
            if ($children_sum < $tree->count) {
               $groups = $this->add_to_groups($groups, $tree->output, $path, $tree->count - $children_sum,
                             $tree->confidence, $tree->impurity);
            }
            return array($tree->count, $groups);
         }
   }

   function group_prediction() {
      /*
        Groups in categories or bins the predicted data
        dict - contains a dict grouping counts in 'total' and 'details' lists.
               'total' key contains a 3-element list.
                       - common segment of the tree for all instances
                       - data count
                       - predictions count
               'details' key contains a list of elements. Each element is a
                         3-element list:
                        - complete path of the tree from the root to the leaf
                        - leaf predictions count
                        - confidence
      */
      $groups = array();
      $tree = $this->tree;
      $distribution = $tree->distribution;

      foreach ($distribution as $group) {
         $groups[strval($group[0])] = array('total' => array(array(), $group[1], 0), 
                                    'details' => array());
      }

      $path = array();

      $result = $this->depth_first_search($tree, $path, $groups);
      return $result[1]; 

   }

   function get_data_distribution() {
     /*
     Returns training data distribution
     */
     $tree = $this->tree;
     $distribution = $tree->distribution;

     $order_array = array();
     foreach ($distribution as $k => $row)
     {
      $order_array[$k] = $row[0];
     }
     array_multisort($order_array, SORT_ASC, $distribution);

     return $distribution;

   }

   function get_prediction_distribution($groups=null) {
     /*Returns model predicted distribution*/
     if ($groups == null) { 
        $groups = $this->group_prediction();
     }
     $predictions=array();
     foreach ($groups as $key => $group) {
       if ($group["total"][2] > 0) {
          $predictions[strval($key)] =  $group["total"][2];
       }
     }
     # remove groups that are not predicted
     ksort($predictions);
     return $predictions;

     #return sorted(predictions, key=lambda x: x[0])

   }

   function extract_common_path($groups) {
      /* Extracts the common segment of the prediction path for a group */
      foreach ($groups as $key => $group) {
        $details = $group['details'];
        $common_path = array();

        if (count($details) > 0 ) {
           $mcd_len = null;
           foreach ($details as $x) {
              if (is_null($mcd_len) or count($x[0]) < $mcd_len) {
                 $mcd_len = count($x[0]);
              }
           }
           foreach (range(0, $mcd_len-1) as $i) {
              $test_common_path = $details[0][0][$i];
              foreach ($details as $subgroup) {
                 if ($subgroup[0][$i] != $test_common_path ) {
                     $i = $mcd_len;
                     break;
                 }  
              }
              if ($i < $mcd_len) { 
                 array_push($common_path, $test_common_path);
              } 
           }     
        }
        $groups[$key]["total"][0] = $common_path;
        if (count($details) > 0 ) {

           uksort($details,  function($x, $y) use ($details) {
               if ($details[$x][1] == $details[$y][1]) {
                  return $x<$y?-1:$x!=$y;
               }

               return $details[$y][1]-$details[$x][1];

           });

           $groups[$key]["details"]=$details;
        }

      } 
      return $groups;
   }

   function confidence_error($value, $impurity=null, $tree) {
     /*Returns confidence for categoric objective fields
       and error for numeric objective fields*/
     
     if (is_null($value)) {
        return "";
     }
    
     $impurity_literal = "";
     if (!is_null($impurity) && $impurity > 0) {
        $impurity_literal = "; impurity: " . strval(round($impurity, 4));
     }
     $objective_type = $this->fields->{$tree->objective_id}->optype;

     if ($objective_type == 'numeric') {
        return " [Error: " . $value . "]";
     } else {
        return " [Confidence: " . number_format(round($value*100, 2, PHP_ROUND_HALF_DOWN), 2) . $impurity_literal . "%]";
     }
 
 
   }

   function summarize($out=STDOUT, $format=1) {
      /* Prints summary grouping distribution as class header and details */

      $distribution = $this->get_data_distribution();
      fwrite($out, "Data distribution:\n");
      print_distribution($distribution, $out); 
      fwrite($out, "\n\n");

      $groups = $this->group_prediction();
      $predictions = $this->get_prediction_distribution($groups);
      fwrite($out, "Predicted distribution:\n");

      $a_to_print = array();
      foreach ($predictions as $key => $value) {
          array_push($a_to_print, array($key, $value));
      }

      $tree = $this->tree;
      print_distribution($a_to_print, $out);
      fwrite($out, "\n\n");

      if ($this->field_importance) {
         fwrite($out, "Field importance:\n");
         print_importance($this, $out);
      }

      $groups = $this->extract_common_path($groups);
      fwrite($out, "\n\nRules summary:");
      foreach ($a_to_print as $x) {
            $group = $x[0];
            $details = $groups[$group]["details"];
 
            $path = new Path($groups[$group]["total"][0]);
            $data_per_group = ($groups[$group]["total"][1] * 1.0) / $tree->count;
            $pred_per_group = ($groups[$group]["total"][2] * 1.0) / $tree->count; 
 
            fwrite($out, "\n\n" . $group .  " : (data " . number_format(round($data_per_group, 4)*100, 2) . 
                          "% / prediction " . number_format(round($pred_per_group, 4)*100, 2) . "%) " . 
                          $path->to_rules($this->fields, "name", $format));

            if (count($details) == 0) {
               fwrite($out, "\n     The model will never predict this class\n");
            } else if (count($details) == 1) {
               $subgroup = $details[0];
               fwrite($out, $this->confidence_error($subgroup[2], $subgroup[3], $tree) . "\n");
            } else {
               fwrite($out, "\n");
               foreach ($details as $key => $subgroup) { 
                  $pred_per_sgroup = $subgroup[1] * 1.0 / $groups[$group]["total"][2];
                  $path = new Path($subgroup[0]);
                  $path_chain = (!is_null($path->predicates) or $path->predicates == false) ? $path->to_rules($this->fields, 'name', $format) : "(root node)";
                  fwrite($out, "    · " . number_format(round($pred_per_sgroup, 4) * 100,2) . "%: " . $path_chain . $this->confidence_error($subgroup[2], $subgroup[3], $tree) . "\n");
               }
            }


      }
 
      fclose($out);      
   } 
}

?>
