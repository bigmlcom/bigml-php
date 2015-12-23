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

/*
  class for the Tree Prediction object

  This module defines an auxiliary Prediction object that is used in the
  Tree module to store all the available prediction info.
*/


class Prediction { 
   /*
     A Prediction object containing the predicted Node info or the
     subtree grouped prediction info for proportional missing strategy
   */
   public $output;
   public $path;
   public $confidence;
   public $distribution;
   public $count;
   public $distribution_unit;
   public $median;
   public $children;
   public $d_max;
   public $d_min;
   public $prediction;

   public function __construct($output, $path, $confidence, $distribution=null, 
                               $count=null, $distribution_unit=null, $median=null, 
			       $children=null, $d_max=null, $d_min=null) {
    
      $this->output = $output;
      $this->prediction = $output;
      $this->path  = $path;
      $this->confidence = $confidence;
      $this->distribution = ($distribution == null ? array() : $distribution);

      if ($count == null) {
         $this->count=0;
         foreach ($this->distribution as $key => $instances) {
	    $this->count+=$instances;
	 }
      } else {
        $this->count = $count;
      } 

      $this->distribution_unit = ($distribution_unit == null ? 'categorial' : $distribution_unit);
      $this->median = $median;
      $this->children = ($children == null ? array() : $children);
      $this->min = $d_min;
      $this->max = $d_max;

   }

}

?>
