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

function print_importance($instance, $out=STDOUT) {
   /*
     Print a field importance structure
   */
   $count=1;
   $data = $instance->field_importance_data();
   $field_importance = $data[0];
   $fields = $data[1];

   foreach($field_importance as $item) {
     $field = $item[0];
     $importance = $item[1];
     fwrite($out,"    " . $count . ". " .  $fields->{$field}->name . ": " . number_format(strval(round($importance, 4, PHP_ROUND_HALF_UP)*100), 2)  . "%\n");
     fflush($out);
     $count+=1; 
   }   
}

class BaseModel extends ModelFields{
   /*
      A lightweight wrapper of the basic model information

      Uses a BigML remote model to build a local version that contains the
      main features of a model, except its tree structure.
   */
   public $resource_id;
   public $description;
   public $field_importance;
   public $locale = 'en_US.UTF-8';

   function objectToArray($d) {
      if (is_object($d)) {
         // Gets the properties of the given object
         // with get_object_vars function
         $d = get_object_vars($d);
      }
 
      if (is_array($d)) {
         /*
         * Return array converted to object
         * Using __FUNCTION__ (Magic constant)
         * for recursive call
         */
         #return array_map(__FUNCTION__, $d);
         #return array_values($d);
      } else {
         // Return array
         return $d;
      }
    }

    public function __construct($model, $api=null) {

      if (check_model_structure($model) ) {
         $this->resource_id = $model->resource;
      } else {
         if ($api == null) {
             $api = new BigML(null, null, null, $storage);
         }
 
         if (is_string($model)) {                                
            if (!($api::_checkModelId($model)) ) {
               error_log("Wrong model id");
               return null;
            }
            $model = $api::retrieve_resource($model, $api::ONLY_MODEL);
         } 

      } 
         
      if (property_exists($model, "object") && $model->object instanceof STDClass) {
         $model=$model->object;
      }

      if (property_exists($model, "model") && $model->model instanceof STDClass) {
         if ($model->status->code == BigMLRequest::FINISHED) {

            if (property_exists($model->model, "model_fields")) {
              
               foreach($model->model->model_fields as $key => $value) {
			      if (!property_exists($model->model->fields, $key)) {
                     throw new Exception("Some fields are missing to generate a local model " . $key .  "  Please, provide a model with the complete list of fields.");
                  }	
                  if (property_exists($model->model->fields->{$key}, "summary")) {
                     $model->model->model_fields->{$key}->summary = $model->model->fields->{$key}->summary;
                  }
                  $model->model->model_fields->{$key}->name = $model->model->fields->{$key}->name;
               }

             }
              
             parent::__construct($model->model->model_fields, extract_objective($model->objective_fields));
             $this->description = $model->description;
             $this->field_importance = property_exists($model->model, "importance") ? $model->model->importance : null;

             if ($this->field_importance  != null ) {
               $fields_importance=array();
               foreach($this->field_importance as $field => $value) {
                  if (property_exists($model->model->model_fields, $value[0]) ) {
                     array_push($fields_importance, $value);
                  } 
               }      
                  
               $this->field_importance = $fields_importance;
             }

             if (property_exists($model, "locale" && $model->locale != null ) ) {
               $this->locale = $model->locale;
             }

          } else {
             throw new Exception("The model isn't finished yet");
          }
       } else {
        throw new Exception("Cannot create the BaseModel instance. Could not  find the 'model' key in the resource:\n\n " . print_r($model));
       }

    }

    private function uniquify_varnames($fields) {
       /*
        Tests if the fields names are unique. If they aren't, a
        transformation is applied to ensure unicity.
       */
       $unique_names = array(); 
       $len=0;
       foreach($fields as $field) {
          array_push($unique_names, $field->name);
          $len+=1;
       }

       $unique_names = array_unique($unique_names);

       if (count($unique_names) < $len) {
         $fields = $this->transform_repeated_names($fields);
       }
       return $fields;
    }

    private function transform_repeated_names($fields) {
       /*
         If a field name is repeated, it will be transformed adding its
         column number. If that combination is also a field name, the field id will be added.
         The objective field treated first to avoid changing it
       */
       $unique_names =array($fields->{$this->objective_field}->name);
            
       foreach($fields as $field) { 
          $new_name = $field->name; 
          if (in_array($new_name, $unique_names) ) {
             $new_name = $field->name . strval($field->column_number);
             if (in_array($new_name, $unique_names) ) {
                $new_name = $field->name . "_" . strval($field->column_number);
             }   
             $field->name = $new_name;
          }
          array_push($unique_names, $new_name);
       }

       return $fields;
    }

    function resource() {
       /*
        Returns the model resource ID
       */
       return $this->resource_id;
    }

    function field_importance_data() {
       /*
        Returns field importance related info
       */ 
       return array($this->field_importance, $this->fields);
    }

    function print_importance($out=STDOUT) {
       /*
        Prints the importance data
       */
       print_importance($out);
    }

}

?>
