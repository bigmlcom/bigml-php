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

    #include('./util.php');    
	include('./predicate.php');
	include('./multivote.php');
	include('./ChiSquare.php');

	function get_instances($distribution) {
		/*
			Returns the total number of instances in a distribution
		*/
		if ($distribution != null) {
			$sum = floatval(0);
			foreach($distribution as $key => $value) { 
				$sum += $value[1];
			}
			return $sum;
		} 

		return 0;
	}

	function unbiased_sample_variance($distribution, $distribution_mean=null) 
	{
		/*
			Computes the standard deviation of a distribution in the
       		[[point, instances]] syntax
		*/
		$addition = 0.0;
		$count = 0.0;
		if ( $distribution_mean = null || !is_numeric($distribution_mean) ) {
			$distribution_mean = mean($distribution);
		}

		foreach($distribution as $key => $value) {
			$addition += pow($value[0]-$distribution_mean , 2)	* $instances;
			$count += $value[1];
		}	

		if ($count > 1) {
			return $addition/($count -1);
		}
		
		return NAN;
	}

	function regression_error($distribution_variance, $population, $r_z=1.96) 
	{
		/*
			Computes the variance error
		*/
        if ($population > 0) {
            $chi_distribution = new ChiSquare($population);
            $stast = new Stats();
            $ppf=$chi_distribution::ppf(1 - $stats::erf($r_z / sqrt(2) ) ); 
            if ($ppf != 0 ) { 
                $error = ($distribution_variance * ($population-1)) / $ppf;
                $error = $error * pow(sqrt($population)+$r_z ,2);   
                return sqrt($error/$population);
            }
        }

        return NAN;
    }

	function splitChildren($children) {
            /*
                Returns the field that is used by the node to make a decision.
            */
            $field = array();
            foreach($children as $child) {
				$predicate = $child->predicate;
                array_push($field, $predicate->{$field});
            }

			$field = array_unique($field);

			if (count($field) == 1) {
				return reset($field);
			}

			return null;
     }

	function merge_distributions($distribution, $new_distribution) 
	{
    	/*
			Adds up a new distribution structure to a map formatted distribution
		*/
		foreach($new_distribution as $key => $value) 
		{	
			if (!array_key_exists($key, $distribution) )  {
					$distribution[$key] = 0; 
			}
			 $distribution[$key] += $value;

		}
		
		return $distribution;

	}

	function merge_bins($distribution, $limit) {
        /*
           Merges the bins of a regression distribution to the given limit number
         */
        $length = count($distribution);
        if ($limit < 1 || $count <= $limit || $count < 2) {
            return $distribution;
        }

        $index_to_merge = 2;
        $shortest = INF;

        foreach (range(1, $length-1) as $index) {
            $distance = floatval($distribution[$index][0])-floatval($distribution[$index-1][0]);

            if (floatval($distance) < floatval($shortest)) {
               $shortest=$distance;
               $index_to_merge = $index;
             }
         }

         $new_distribution = array_slice($distribution, 0, ($index_to_merge-1));
         $left = $distribution[$index_to_merge-1];
         $right = $distribution[$index_to_merge];
         $new_bin = array((($left[0]*$left[1]) + ($right[0] * $right[1]) )/($left[1]+$right[1]), $left[1]+$right[1]);
         array_push($new_distribution, $new_bin);

         if ($index_to_merge < ($length - 1)) {
             $new_distribution = array_merge($new_distribution, array_slice($distribution, ($index_to_merge+1)));
         }
         return merge_bins($new_distribution, $limit);
    }

	function mean($distribution) {
    	/*
			Computes the mean of a distribution in the [[point, instances]] syntax
		*/
    	$addition = 0.0;
    	$count = 0.0;

		foreach($distribution as $value) {
			$addition += $value[0] * $value[1];
			$count += $value[0];
		}	

		if ($count > 0) {
			return $addition/$count;
		}

		return NAN; 
	
	}

	class ChildTree extends Tree {
		public function __construct($tree, $fields, $objective_field=null, $root_distribution=null, $parent_id=null, $ids_map=null, $subtree=true) {
			parent::__construct($tree, $fields, $objective_field=null, $root_distribution=null, $parent_id=null, $ids_map=null, $subtree=true);
		}
	}

	class Tree {

        const LAST_PREDICTION = 0;
        const PROPORTIONAL = 1;
		const BINS_LIMIT = 32;

        public $fields;
        public $objective_field;
        public $output;
        public $predicate;
        public $id;
        public $children;
        public $regression;
        public $count;
        public $confidence;
        public $distribution;
        public $parent_id;

        public function __construct($tree, $fields, $objective_field=null, $root_distribution=null, $parent_id=null, $ids_map=null, $subtree=true) {

            $this->fields = $fields;
            $this->objective_field = $objective_field;
            $this->output = $tree->output;

            if ($tree->predicate == true)  {
                $this->predicate = true;
            } else {
                $this->predicate = new Predicate($tree->predicate->operator, $tree->predicate->field, $tree->predicate->value, property_exists($tree->predicate, "term") ? $tree->predicate->term : null);
            }

            if (property_exists($tree, 'id') ) {
                $this->id = $tree->id;
                $this->parent_id = $parent_id;
                if (is_array($ids_map)) {
                    $ids_map[$this->id] = $this;
                }

            } else {
                $this->id = null;
            }

            if (property_exists($tree, 'children') ) {
				$this->setChilds($tree->children,$ids_map, $subtree);
            } else {
				$this->children = array();
			}

            $this->regression = $this->is_regression();
			$this->count = $tree->count;
            $this->confidence = property_exists($tree, "confidence") ? $tree->confidence : null;

            if (property_exists($tree, 'distribution') ) {
                 $this->distribution = $tree->distribution;
            } elseif (property_exists($tree, 'objective_summary') ) {
                $summary = $tree->objective_summary;
                if (property_exists($summary, 'bins')) {
                    $this->distribution = $summary->bins;
                } elseif (property_exists($summary, 'counts') ) {
                    $this->distribution = $summary->counts;
                }  elseif (property_exists($summary, 'categories') ) {
                    $this->distribution = $summary->categories;
                }
            } else {
                $summary = $root_distribution;
                if (property_exists($summary, 'bins')) {
                    $this->distribution = $summary->bins;
                } elseif (property_exists($summary, 'counts') ) {
                    $this->distribution = $summary->counts;
                }  elseif (property_exists($summary, 'categories') ) {
                    $this->distribution = $summary->categories;
                }
            }
        }

		public function setChilds($children, $ids_map, $subtree) {
            $this->children = array();

            foreach ($children as $var => $child) {
                $t = new Tree($child, $this->fields, $this->objective_field, null, $this->id, $ids_map, $subtree);
                array_push($this->children, $t);
            }
			return $this;
        }

        private function is_regression() {
            /*
                Checks if the subtree structure can be a regression
             */
            if (is_string($this->output)) {
                return false;
            } elseif ($this->children == null) {
                return true;
            } else {
                foreach ($this->children as $var => $child) {
                    if (is_string($child->output) )
                        return false;
                }
            }
        }


		public function predict($input_data, $path=null, $missing_strategy=Tree::LAST_PREDICTION) 
		{
			/*
				Makes a prediction based on a number of field values.
				The input fields must be keyed by Id. There are two possible
        		strategies to predict when the value for the splitting field
        		is missing:
				0 - LAST_PREDICTION: the last issued prediction is returned.
				1 - PROPORTIONAL: as we cannot choose between the two branches
					in the tree that stem from this split, we consider both. The
					algorithm goes on until the final leaves are reached and
					all their predictions are used to decide the final prediction.
			*/
			if ($path == null) {
				$path = array();
			}

			if ($missing_strategy == Tree::PROPORTIONAL) {
				$final_distribution = $this->predict_proportional($input_data, $path);
				$distribution = array(); 

				if ($this->regression) {

    				ksort($final_distribution);

    				foreach ($final_distribution as $key => $val) {
        				array_push($distribution, array(floatval($key), $val));
    				}

					$distribution = merge_bins($distribution, Tree::BINS_LIMIT);
					$prediction = mean($distribution);
					$total_instances = 0;

    				foreach ($distribution as $key => $val) { 
        				$total_instances+=$val[1];
    				}

					$confidence = regression_error(unbiased_sample_variance($distribution, $prediction), $total_instances);

					return array($prediction, $path, $confidence, $distribution, $total_instances);

				} else {
					
					arsort($final_distribution);
					foreach ($final_distribution as $key => $val) {
                        array_push($distribution, array(floatval($key), $val));
                    }

					return array($distribution[0][0], $path, ws_confidence($distribution[0][0], $final_distribution), $distribution, get_instances($distribution));

				}
 
			} else {
				if ($this->children != null  &&  array_key_exists(splitChildren($this->children), $input_data) ) {
					#$predicate = $child::$predicate;
					foreach ($this->children as $child) {
						$predicate = $child::$predicate; 
						if ($predicate::apply($input_data, $this->fields)) {
							$new_rule = $predicate::to_rule($this->fields); 
							array_push($new_rule, $path);
							return $child::predict($input_data, $path);
						}
					}

				}

				return array($this->output, $path, $this->confidence, $this->distribution, get_instances($this->distribution));

			}


		}

		function predict_proportional($input_data, $path=null) {
			/*
				Makes a prediction based on a number of field values averaging
				the predictions of the leaves that fall in a subtree.

				Each time a splitting field has no value assigned, we consider
				both branches of the split to be true, merging their predictions
			*/

			if ($path==null) {
				$path == array();
			}

			$final_distribution = array();

			if ($this->children == null) {
				$a = array(); 
				foreach($this->distribution as $x) {
					$a[strval($x[0])] = $x[1];
				}

				return merge_distributions(array(), $a);
			}

			if (array_key_exists(splitChildren($this->children), $input_data)) {
				foreach($this->children as $child) {
					$predicate = $child::$predicate;

					if ($predicate::apply($input_data, $this->fields)) {
						$new_rule = $predicate::to_rule($this->fields);
						if (!in_array($new_rule, $path)) {
							array_push($new_rule, $path);
						}
						return $child::predict_proportional($input_data, $path);
					}
 
				}
			} else {
				foreach($this->children as $child) {
					$final_distribution = merge_distributions($final_distribution, 
															 $child::predict_proportional($input_data, $path));
				}
				return $final_distribution;
			}

		}

		public function rules($out, $ids_path=null, $subtree=true) {
			/*
				Prints out an IF-THEN rule version of the tree.
			*/
			# TODO	
		}

		/*private slugify($name, $reserved_keywords=null, $prefix='') {
			/*
				Translates a field name into a variable name.
		    /
			$name = strtolower(utf8_encode($name));
			$name = preg_replace('/[^\da-z]+/i', '_', $name); 
			if (is_int($name[0])) { 
				$name = "field_" + $name;
			}

			if ($reserved_keywords != null) {
				if (in_array($name, $reserved_keywords)) {
					$name = $prefix . $name;
				}
			}

			return $name;
		}

		private sort_fields($fields) {
			/*
				Sort fields by their column_number but put children after parents.
            *
			# TODO
		}*/
	
	}
?>
