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

namespace BigML;

function cosine_distance2($terms, $centroid_terms, $scale) {
   /*
      Returns the distance defined by cosine similarity
   */
   # Centroid values for the field can be an empty list.
    # Then the distance for an empty input is 1
    # (before applying the scale factor).

   if ($terms == null && $centroid_terms == null) {
      return 0;
   }

   if ($terms == null || $centroid_terms == null) {
      return pow($scale, 2);
   }

   $input_count = 0;
   foreach($centroid_terms as $term) {
      if (in_array($term, $terms) ) {
         $input_count +=1;
      }         
   }    

   $cosine_similarity = $input_count / sqrt(count($terms) * count($centroid_terms));
   $similarity_distance = $scale * (1-$cosine_similarity);
   return pow($similarity_distance, 2);

}   

class Centroid {
   /*
      A Centroid.
   */
   public $center;
   public $count;
   public $centroid_id;
   public $name;
   public $distance;

   public function __construct($centroid_info) 
   {
      $this->center = (property_exists($centroid_info, "center"))  ? $centroid_info->center : array();
      $this->count = (property_exists($centroid_info, "count")) ? $centroid_info->count : 0;
      $this->centroid_id = (property_exists($centroid_info, "id")) ? $centroid_info->id : null;
      $this->name = (property_exists($centroid_info, "name"))  ? $centroid_info->name : null;
      $this->distance = (property_exists($centroid_info, "distance"))  ? $centroid_info->distance : array();
   }

   public function distance2($input_data, $term_sets, $scales, $stop_distances2=null) {
      /*
         Squared Distance from the given input data to the centroid
      */

      $distance2 = 0.0;

      foreach($this->center as $field_id => $value) {
         if (is_array($value)) {
            $terms = (!array_key_exists($field_id, $term_sets) ) ? array() : $term_sets[$field_id]; 
            $distance2 += cosine_distance2($terms, $value, $scales->{$field_id});
         } elseif (is_string($value)) {
            if (!array_key_exists($field_id, $input_data) || strval($input_data[$field_id]) != strval($value)) {
               $distance2 += 1 * pow($scales->{$field_id}, 2);
            }     
         } else {
            $distance2 += pow( ($input_data[$field_id] - $value) * ($scales->{$field_id}), 2);
         }

         if ($stop_distances2 != null && $distance2 >= $stop_distances2) {
            return null;
         }
      }
      return $distance2;   
   }
}
?>
