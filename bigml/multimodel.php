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

if (!class_exists('model')) {
   include('model.php');
}

function read_votes($votes_files, $model, $data_locale=null)
{
   /*
    Reads the votes found in the votes' files.
    Returns a list of MultiVote objects containing the list of predictions.
    votes_files parameter should contain the path to the files where votes are stored
    In to_prediction parameter we expect the method of a local model object
    that casts the string prediction values read from the file to their real type. 
   */

   $votes = array();
   foreach (range(0, (count($votes_files)-1)) as $order) {

       $votes_file = $votes_files[$order];
       $index = 0;

       ini_set('auto_detect_line_endings',TRUE);
       $handle = fopen($votes_file,'r');
       while ( ($row = fgetcsv($handle) ) !== FALSE ) {
           $prediction = $model->to_prediction($row[0], $data_locale);
           if ($index > (count($votes) -1) ) {
              array_push($votes, new MultiVote(array()));
           }

           $distribution = null;
           $instances = null;
           $confindence = null;

           if (count($row) > 2)  {
              $distribution = json_decode($row[2]);
              $instances = intval($row[3]);
              try {
                 $confidence = floatval($row[1]);
              } catch  (Exception $e) {
                 $confidence = 0;
              }

           }

           $votes[$index]->append_row( array($prediction, $confidence, $order, $distribution, $instances));
           $index+=1;
       }
       ini_set('auto_detect_line_endings',FALSE);
   }

   return $votes;
}

function get_predictions_file_name($model, $path) {
   /*
      Returns the file name for a multimodel predictions file
   */
   if (property_exists($model, 'resource')) {
      $model = $model["resource"];
   }

   return $path . DIRECTORY_SEPARATOR . str_replace(DIRECTORY_SEPARATOR, "_", $model) . '_predictions.csv';

}

class MultiModel{
   /*
     A multiple local model.

     Uses a number of BigML remote models to build a local version that can be
     used to generate predictions locally.
   */
   public $models;

   public function __construct($models, $api=null, $storage="storage") { 
      #$this->models = array();

      if ($api == null) {
         $api = new BigML(null, null, null, $storage);
      }

      if (is_array($models)) {
         foreach($models as $mo) {
            if (!is_string($mo) && is_a($mo, "Model") ) {
               $m = $mo;
            } else {
               $m = new Model($mo, $api);
            }
            $this->models[] = clone $m;
         }

      } else {
         array_push($this->models, new Model($models, $api, $storage));
      }
   }

   public function predict($input_data, $by_name=true, $method=MultiVote::PLURALITY_CODE, 
                           $with_confidence=false, $options=null, $missing_strategy=Tree::LAST_PREDICTION,
			   $add_confidence=false, $add_distribution=false, $add_count=false, 
			   $add_median=false, $add_min=false, $add_max=false, $add_unused_fields=false) {

      /*
         Makes a prediction based on the prediction made by every model. The method parameter is a numeric key to the following combination methods in classifications/regressions:
         0 - majority vote (plurality)/ average: PLURALITY_CODE
         1 - confidence weighted majority vote / error weighted: CONFIDENCE_CODE
         2 - probability weighted majority vote / average: PROBABILITY_CODE
         3 - threshold filtered vote / doesn't apply: THRESHOLD_COD
      */

      $votes = self::generate_votes($input_data, $by_name, $missing_strategy, $add_median, $add_min, $add_max, $add_unused_fields); 

      $result = $votes->combine($method, $with_confidence, $add_confidence, $add_distribution, 
                             $add_count, $add_median, $add_min, $add_max, $options);

      if ($add_unused_fields) {
         $unused_fields = array_unique(array_keys($input_data));
         foreach($votes->predictions as $index => $prediction) {
            $unused_fields = array_intersect($unused_fields, array_unique($prediction->unused_fields));
         }
  
         if (!is_array($result)) {          
            $result = array("prediction" => $result);
         }
  
         $result['unused_fields'] = $unused_fields;
  
      } 

      return $result;

   }

   public function batch_predict($input_data_list, $output_file_path=null, $by_name=true, $reuse=false, 
                                 $missing_strategy=Tree::LAST_PREDICTION, $headers=null, $to_file=true, $use_median=false) {
      /*
         Makes predictions for a list of input data.

         The predictions generated for each model are stored in an output
         file. The name of the file will use the following syntax:
         model_[id of the model]__predictions.csv
         For instance, when using model/50c0de043b563519830001c2 to predict,
         the output file name will be
         model_50c0de043b563519830001c2__predictions.csv

      */
      $add_headers = (is_array($input_data_list[0]) && $headers != null and count($headers)== $count($input_data_list[0]) );
    
      if (!$add_headers && !(array_keys($input_data_list[0]) !== range(0, count($input_data_list[0]) - 1)) ) {
          error_log("Input data list is not a dictionary or the headers and input data information are not consistent");
          throw new Exception("Input data list is not a dictionary or the headers and input data information are not consistent");
      }

      $order = 0;
      if (!$to_file) {
         $votes = array();
      }

      foreach($this->models as $model) {
         $order+=1;
         $out = null;
         if ($to_file) {
            $output_file = get_predictions_file_name($model->resource_id, $output_file_path);
	     if ($reuse) {
	        try {
	          $predictions_file = fopen($output_file, "r");
		  fclose($predictions_file);
		  continue;
		} catch  (Exception $e) {
		}
	     } 
  
             if (!file_exists($output_file_path)) {
	        error_log("Cannot find " . $output_file_path . " directory.");
		throw new Exception("Cannot find " . $output_file_path . " directory.");
             }

	     $fp = fopen($output_file, 'w');
	 }

         if ($out != null) {
	   // TODO
	   $fp = $out;
	 }
         $index = 0; 
         foreach($input_data_list as $input_data) {
	    if ($add_headers) {
	       // TODO
	    }

	    $prediction = $model->predict($input_data, $by_name, false, STDOUT, true, $missing_strategy);
           
	    if ($use_median && $model->tree->regression) {
	      $prediction[0] = array_slice($prediction, -1);
	    }

            $prediction = array_slice($prediction, 0, -1);
            if ($to_file) {
              $res = array();
               foreach ($prediction as $value) {
                  if (is_array($value)) {
                     $value = json_encode($value);
                  }

                  array_push($res, $value);
              }
	      fputcsv($fp, $res); 
	    } else {
	      $prediction_row = array_slice($prediction, 0, 2);
	      array_push($prediction_row, $order);
	      $prediction_row = array_merge($prediction_row, array_slice($prediction, 2));

	      if (count($votes) <= $index) {
	         array_push($votes, new MultiVote(array()));
	      }

	      $votes[$index]->append_row($prediction_row);

	    }
            $index+=1; 
	 }

         if ($to_file) {
	   fclose($fp);
	 }

         if ($out != null) {
	   fclose($out);
	 }

      }

      if (!$to_file) {
         return $votes;
      }
   }

   function generate_votes($input_data, $by_name=true, $missing_strategy=Tree::LAST_PREDICTION, $add_median=false, 
                           $add_min=false, $add_max=false, $add_unused_fields=false) {
      /*
         Generates a MultiVote object that contains the predictions
         made by each of the models.
      */
      
      $votes = new MultiVote(array());
      $order = 0;

      
      foreach ($this->models as $model) {
         $prediction_info = $model->predict($input_data, $by_name, false, STDOUT, false, 
                                            $missing_strategy, true, false, true, true, $add_median,
                                            false, $add_min, $add_max, $add_unused_fields, null);
         $votes->append($prediction_info);
      }

      return $votes;
   }

   function batch_votes($predictions_file_path, $data_locale=null) {
      /*
         Adds the votes for predictions generated by the models.
         Returns a list of MultiVote objects each of which contains a list
         of predictions.
      */
      $votes_files = array();
      foreach($this->models as $model) {
         array_push($votes_files, get_predictions_file_name($model->resource_id, $predictions_file_path));
      }
      return read_votes($votes_files, $this->models[0], $data_locale); 
   }
}
?>
