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

define("OPTIONAL_FIELDS_CENTROID", json_encode(array('categorical', 'text','items','datetime')));
define("GLOBAL_CLUSTER_LABEL", "Global");

function parse_items($text, $regexp) {
  /*Returns the list of parsed items*/
  if (is_null($text)) {
    return array();
  }
  
  $regex_string = "/". $regexp . "/";
  return preg_split($regex_string, $text);
  
}

function parse_terms($text, $case_sensitive=true) {
   /*
      Returns the list of parsed terms
   */
   if ($text == null) {
      return array();
   }


   preg_match_all("/(\b|_)([^\b_\s]+?)(\b|_)/u", $text, $matches);

   $results = array();

   if (!empty($matches[0])) {

      foreach ($matches[0] as $valor) { 
        array_push($results, ($case_sensitive) ? $valor : strtolower($valor)); 
      }

   }
   return $results;
}

function get_unique_terms($terms, $term_forms, $tag_cloud) {
  /* 
     Extracts the unique terms that occur in one of the alternative forms in
     term_forms or in the tag cloud.
   */

  $extend_forms = array();

  $tag_keys=array();  
  foreach ($tag_cloud as $key => $value) {
       array_push($tag_keys, $value[0]);
  }
 
  foreach ($term_forms as $term => $forms) {

     foreach ($forms as $form) {
         $extend_forms[$form] = $term;
     }
  }

  $terms_set=array();

  foreach ($terms as $term) {
      if (in_array($term, $tag_keys)) {
        array_push($terms_set, $term); 
      } else if (array_key_exists($term, $extend_forms)) {
        array_push($terms_set, $extend_forms[$term]); 
      }
  }
   
  return array_unique($terms_set); 
 
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
   public $item_analysis;
   public $items;
   public $cluster_global;
   public $total_ss;
   public $within_ss;
   public $between_ss;
   public $ratio_ss;
   public $critical_value;
   public $k;

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

	    $the_clusters = $cluster->clusters;
	    $cluster_global = array_key_exists("global", $the_clusters) ? $the_clusters->global : null;
	    $clusters = $the_clusters->clusters;

            $this->centroids = array();

            foreach($clusters as $centroid) {
               array_push($this->centroids, new Centroid($centroid));
            }
            $this->cluster_global=$cluster_global;
	    if (!is_null($cluster_global)) {
	      $this->cluster_global=new Centroid($cluster_global);
	      $this->cluster_global->name = GLOBAL_CLUSTER_LABEL;
	      $this->cluster_global->count = $this->cluster_global->distance->population;
	    }

	    $this->total_ss = $the_clusters->total_ss;
	    $this->within_ss = $the_clusters->within_ss;
            
	    if (!is_null($this->within_ss)) {
	       $this->within_ss = 0;
	       foreach($this->centroids as $centroid) {
	          $this->within_ss+=$centroid->distance->sum_squares;
	       }
	    }

	    $this->between_ss = $the_clusters->between_ss;
	    $this->ratio_ss = $the_clusters->ratio_ss;
	    $this->critical_value = array_key_exists("critical_value", $cluster) ? $cluster->critical_value : null;
	    $this->k = $cluster->k;

            $this->scales = $cluster->scales;
            $this->term_forms = array(); 
            $this->tag_clouds = array();
            $this->term_analysis = array();
	    $this->item_analysis = array();
	    $this->items=array();

            $fields = $cluster->clusters->fields;
            $summary_fields = $cluster->summary_fields;

            foreach($summary_fields as $field_id) {
               unset($fields->{$field_id});
            }

            foreach($fields as $field_id => $field) {

               if ($field->optype == 'text' ) {
                  $this->term_forms[$field_id]=$field->summary->term_forms;
                  $this->tag_clouds[$field_id]=$field->summary->tag_cloud;
                  $this->term_analysis[$field_id]=$field->term_analysis;
               } else if ($field->optype == 'items' ) {
	          $this->items[$field_id]= $field->summary->items;
		  $this->item_analysis[$field_id]=$field->item_analysis;
	       }

            }

            parent::__construct($fields);

            foreach($this->scales as $field_id=>$field) {
            
               if (!property_exists($this->fields, $field_id) )  {
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
      foreach($this->fields as $field_id=>$field) {
         if (!in_array($field->optype, json_decode(OPTIONAL_FIELDS_CENTROID)) && !array_key_exists($field_id, $input_data) ) {
            throw new Exception("Failed to predict a centroid. Input data must contain values for all numeric fields to find a centroid.");
         } 
      }
      #Strips affixes for numeric values and casts to the final field type
      $input_data = cast($input_data, $this->fields);
      $unique_terms = array();
      $get_unique = $this->get_unique_terms($input_data);
      $unique_terms = $get_unique[0];
      $input_data =  $get_unique[1];

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

   function is_g_means() {
      return !is_null($this->critical_value);
   }

   function get_unique_terms($input_data) {
      $unique_terms = array();
      foreach($this->term_forms as $field_id => $field) {
         if ( array_key_exists($field_id, $input_data) ) {
            $input_data_field = (array_key_exists($field_id, $input_data)) ?  $input_data[$field_id] : '';

            if (is_string($input_data_field)) {
               $case_sensitive = (array_key_exists('case_sensitive', $this->term_analysis[$field_id])) ? $this->term_analysis[$field_id]->case_sensitive : true;
               $token_mode = (array_key_exists('token_mode', $this->term_analysis[$field_id])) ? $this->term_analysis[$field_id]->token_mode : 'all';
               
               if ($token_mode != Predicate::TM_FULL_TERM) {
                  $terms = parse_terms($input_data_field, $case_sensitive);
               } else {
                  $terms = array();
               }
            
               if ($token_mode != Predicate::TM_TOKENS) {
                  array_push($terms, ($case_sensitive) ? $input_data_field : strtolower($input_data_field) );
               }


               $unique_terms[$field_id] = get_unique_terms($terms,
                                                        $this->term_forms[$field_id],
                                                        array_key_exists($field_id, $this->tag_clouds) ? $this->tag_clouds[$field_id] : array());

            } else {
              $unique_terms[$field_id] = $input_data_field;
            }  
            unset($input_data[$field_id]); 
  
         }
      }

      # the same for items fields
      foreach($this->item_analysis as $field_id => $field){

         if ( array_key_exists($field_id, $input_data) ) {
	    $input_data_field = (array_key_exists($field_id, $input_data)) ?  $input_data[$field_id] : ''; 
	 }
       
         if (is_string($input_data_field)) {
           $separator = (array_key_exists('separator', $this->item_analysis[$field_id])) ? $this->item_analysis[$field_id]->separator : ' ';
	   $regexp = (array_key_exists('separator_regexp', $this->item_analysis[$field_id])) ? $this->item_analysis[$field_id]->separator_regexp : null;

	   if (is_null($regexp)) {
	      $regexp='' . preg_quote($separator);
	   }

	   $terms = parse_items($input_data_field, $regexp);
	   $unique_terms[$field_id] = get_unique_terms($terms,
	                                               array(),
						       array_key_exists($field_id, $this->items) ? $this->items[$field_id] : array());
	 } else {
	   $unique_terms[$field_id] = $input_data_field; 
	 }

	 unset($input_data[$field_id]);
  
      }

      return array($unique_terms,$input_data);
   }

} 

?>
