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

class BaseModel extends ModelFields{
   /*
      A lightweight wrapper of the basic model information

      Uses a BigML remote model to build a local version that contains the
      main features of a model, except its tree structure.
   */
   public static $resource_id;
   public static $description;
   public static $field_importance;
   public static $locale = 'en_US.UTF-8';

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

    public function __construct($model) {
      if (check_model_structure($model) ) {
         self::$resource_id = $model->resource;
      } 
         
      if (property_exists($model, "object") && $model->object instanceof STDClass) {
         $model=$model->object;
      }

      if (property_exists($model, "model") && $model->model instanceof STDClass) {
         if ($model->status->code == BigMLRequest::FINISHED) {
            if (property_exists($model->model, "model_fields")) {

               $model_fields = get_object_vars($model->model->model_fields);
               while($element = current($model_fields)) {
                  if (!property_exists($model->model->fields, key($model_fields))) {
                     throw new Exception("Some fields are missing to generate a local model " . key($model_fields) .  "  Please, provide a model with the complete list of fields.");
                  }
                  #$k = key($model_fields);
                  $field_info = $model->model->fields->{key($model_fields)};
                  if (property_exists($field_info, "summary")) {
                     $model->model->model_fields->{key($model_fields)}->summary = $field_info->summary;
                  }
                  $model->model->model_fields->{key($model_fields)}->name = $field_info->name;
                  next($model_fields);
               }
               
             }
   
             parent::__construct($model->model->model_fields, extract_objective($model->objective_fields));
             self::$description = $model->description;
             self::$field_importance = property_exists($model->model, "importance") ? $model->model->importance : null;

             if (self::$field_importance  != null ) {
               $fields_importance= array();
               foreach(self::$field_importance as $field) { 
                  if ( property_exists(self::$fields, $field[0]) ) {
                     array_push($fields_importance, $field[0]);
                  } 
               }      
                  
               self::$field_importance = $fields_importance;
             }

             if (property_exists($model, "locale" && $model->locale != null ) ) {
               self::$locale = $model->locale;
             }

          } else {
             #error_log("The model isn't finished yet");
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
         $fields = self::transform_repeated_names($fields);
       }
       return $fields;
    }

    private function transform_repeated_names($fields) {
       /*
         If a field name is repeated, it will be transformed adding its
         column number. If that combination is also a field name, the field id will be added.
         The objective field treated first to avoid changing it
       */
       $unique_names =array($fields->{self::$objective_field}->name);
            
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

}

?>
