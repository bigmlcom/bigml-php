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

if (!class_exists('modelfields')) {
   include('modelfields.php');
}
if (!class_exists('centroid')) {
   include('centroid.php'); 
}

function parse_terms($text, $case_sensitive=true) {
   /*
      Returns the list of parsed terms
   */
   if ($text == null) {
      return array();
   }


   reg_match_all("/(\b|_)([^\b_\s]+?)(\b|_)'/u", $text, $matches);

   $results = array();

   foreach ($matches as $valor) { 
      array_push($results, ($case_sensitive) ? $valor[1] : strtolower($valor[1])); 
   }

   return $results();
}

class Cluster extends ModelFields {
   /*
      A lightweight wrapper around a cluster model.

       Uses a BigML remote cluster model to build a local version that can be used
       to generate centroid predictions locally.
   */

   public $centroids;
   public $scales;
   public $term_forms;
   public $tag_clouds;
   public $term_analysis;

   public function __construct($cluster, $api=null, $storage="storage") {

      if ($api == null) {
         $api = new BigML(null, null, null, $storage);
      }

      if (is_string($cluster)) {
         if (!($api::_checkClusterId($cluster)) ) {
            error_log("Wrong cluster id"); 
            return null;
         }

         $cluster = $api::retrieve_resource($cluster, $api::ONLY_MODEL);
      } 
      
      if (property_exists($cluster, "object") && property_exists($cluster->object, "status") && $cluster->object->status->code != BigMLRequest::FINISHED ) {
         throw new Exception("The cluster isn't finished yet");
      }

      if (property_exists($cluster, "object") && $cluster->object instanceof STDClass) {
          $cluster = $cluster->object;
      }

      if (property_exists($cluster, "clusters") && $cluster->clusters instanceof STDClass) {

         if ($cluster->status->code == BigMLRequest::FINISHED) {

            
            $clusters = $cluster->clusters->clusters;
            $this->centroids = array();

            foreach($clusters as $centroid) {
               array_push($this->centroids, new Centroid($centroid));
            }

            $this->scales = $cluster->scales;
            $this->term_forms = array(); 
            $this->tag_clouds = array();
            $this->term_analysis = array();

            $fields = $cluster->clusters->fields;

            foreach($fields as $field_id => $field) {

               if ($field->optype == 'text' ) {
                  $this->term_forms[$field_id]=$field->summary->term_forms;
                  $this->tag_clouds[$field_id]=$field->summary->tag_cloud;
                  $this->term_analysis[$field_id]=$field->term_analysis;
               }

            }

            parent::__construct($fields);

            foreach($this->scales as $field_id=>$field) {
            
               if (!property_exists(self::$fields, $field_id) )  {
                  throw new Exception("Some fields are missing  to generate a local cluster. Please, provide a cluster with the complete list of fields.");   
               }
            }

         } else {
            throw new Exception("The cluster isn't finished yet");
         }

      } else {
         throw new Exception("Cannot create the Cluster instance. Could not  find the 'clusters' key in the resource:\n\n " .$cluster);
      }
   }


   function centroid($input_data, $by_name=true) {
      /*
         Returns the id of the nearest centroid
      */

      # Checks and cleans input_data leaving the fields used in the model
      $input_data = $this->filter_input_data($input_data, $by_name);

      # Checks that all numeric fields are present in input data
      foreach(self::$fields as $field_id=>$field) {
         if (!in_array($field->optype, array('categorical', 'text')) && !array_key_exists($field_id, $input_data) ) {
            throw new Exception("Failed to predict a centroid. Input data must contain values for all numeric fields to find a centroid.");
         } 
      }

      #Strips affixes for numeric values and casts to the final field type
      $input_data = cast($input_data, self::$fields);
      $unique_terms = array();

      foreach($this->term_forms as $field_id => $field) {

         if ( array_key_exists($field_id, $input_data) ) {

            $case_sensitive = (array_key_exists('case_sensitive', $this->term_analysis[$field_id])) ? $this->term_analysis[$field_id]->case_sensitive : true; 
            $token_mode = (array_key_exists('token_mode', $this->term_analysis[$field_id])) ? $this->term_analysis[$field_id]->token_mode : 'all'; 
            $input_data_field = (array_key_exists($field_id, $input_data)) ?  $input_data[$field_id] : '';

            if ($token_mode != Predicate::TM_FULL_TERM) {
               $terms = parse_terms($input_data_field, $case_sensitive);
            } else {
               $terms = array();
            }

            if ($token_mode != Predicate::TM_TOKENS) {
               array_push($terms, (case_sensitive) ? $input_data_field : strtolower($input_data_field) );
            }

            $unique_terms[$field_id] = get_unique_terms($terms, 
                                                        $this->term_forms[$field_id], 
                                                        array_key_exists($field_id, $this->tag_clouds) ? $this->tag_clouds[$field_id] : array());

            unset($input_data[$field_id]);
         }

      }

      $nearest = array('centroid_id'=> null, 'centroid_name'=> null, 'distance' => INF);
      
      foreach($this->centroids as $centroid) {
         $distance2 = $centroid->distance2($input_data, $unique_terms, $this->scales, $nearest["distance"]);

         if ($distance2 != null) {
            $nearest = array('centroid_id' => $centroid->centroid_id,
                             'centroid_name' => $centroid->name,
                             'distance' => $distance2);   
         }
      }

      $nearest["distance"] = sqrt($nearest["distance"]);
      return $nearest;
   }

} 

?>
