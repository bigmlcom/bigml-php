<?php

# Copyright 2017 BigML
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

/*    A local Predictive Deepnet.

This module defines a Deepnet to make predictions locally or embedded
into your application without needing to send requests to BigML.io.
This module cannot only save you a few credits, but also enormously
reduce the latency for each prediction and let you use your models
offline.  You can also visualize your predictive model in IF-THEN rule
format and even generate a php function that implements the model.
Example usage (assuming that you have previously set up the
BIGML_USERNAME and BIGML_API_KEY environment variables and that you
own the model/id below): 

include bigml.php;
include deepnet.php;

$api = new BigML();
$deepnet = new Deepnet('deepnet/5026965515526876630001b2');
$deepnet->predict(array("petal length" => 3, "petal width" => 1));
 */

namespace BigML;

if (!class_exists('BigML\BigML')) {
   include('bigml.php');
}

if (!class_exists('BigML\BaseModel')) {
  include('basemodel.php');
}

if (!class_exists('BigML\ModelFields')) {
  include('modelfields.php');
}

include('laminar/math_ops.php');
include('laminar/preprocess.php');

use BigML\ModelFields;

function expand_terms($terms_list, $input_terms = []) {
    // Builds a list of occurrences for all the available terms

    $terms_occurrences = array_fill(0, count($terms_list), 0.0);
    foreach ($input_terms as $term => $occurrences) {
        $index = array_keys($terms_list, $term)[0];
        $terms_occurrences[$index] = $occurrences;
    }
    return $terms_occurrences;
}

class Deepnet extends ModelFields{
    /*
      A lightweight wrapper around a Deepnet model.  

      Uses a BigML remote model to build a local version that can be
      used to generate predictions locally.
    */

    public $resource_id = null;
    public $regression = false;
    public $network = null;
    public $networks = null;
    public $input_fields = [];
    public $class_names = [];
    public $preprocess = [];
    public $optimizer = null;
    public $missing_numerics = false;

    public function __construct($deepnet, $api=null, $storage="storage") {

//          The Deepnet constructor can be given as first argument:
//             - a deepnet structure
//             - a deepnet id
//             - a path to a JSON file containing a deepnet structure

        if ($api == null) {
            $api = new BigML(null, null, null, $storage);
        }
        
        if (is_string($deepnet)) {
            if (file_exists($deepnet)) {
                $deepnet = json_decode(file_get_contents($deepnet));
                $this->resource_id = $deepnet["resource"];
            } elseif (!($api->_checkDeepnetId($deepnet)) ) {
                error_log("Wrong deepnet id");
                return null;
            } else {
                $deepnet = $api->retrieve_resource($deepnet, 
                                                   BigML::ONLY_MODEL);
            }
        }

        if (property_exists($deepnet, "object") && 
            property_exists($deepnet->object, "status") && 
            $deepnet->object->status->code != BigMLRequest::FINISHED ) {
            throw new \Exception("The deepnet isn't finished yet");
        }

        if (property_exists($deepnet, "object") && 
            $deepnet->object instanceof \STDClass) {
            $deepnet = $deepnet->object;
            $this->input_fields = $deepnet->input_fields;
        }

        if (property_exists($deepnet, "deepnet") && 
            $deepnet->deepnet instanceof \STDClass) {

            if ($deepnet->status->code == BigMLRequest::FINISHED) {

                $objective_id = extract_objective($deepnet->objective_fields);
                $deepnet = $deepnet->deepnet;

                $this->fields = $deepnet->fields;
                parent::__construct($this->fields, 
                                    $objective_id, 
                                    null, null, true, true);

                $this->regression = ($this->fields->$objective_id->optype ==
                                     NUMERIC);
                if (!$this->regression) {
                    foreach ($this->fields->$objective_id->summary->categories as $category) {
                        $this->class_names[] = $category[0];
                    }
                    sort($this->class_names);
                }

                if (array_key_exists("missing_numerics", $deepnet)) {
                    $this->missing_numerics = $deepnet->missing_numerics;
                } else {
                    $this->missing_numerics = false;
                }

                if (array_key_exists("network", $deepnet)) {
                    $network = $deepnet->network;
                    $this->network = $network;
                    if (array_key_exists("networks", $network)) {
                        $this->networks = $network->networks;
                    } else {
                        $this->networks = [];
                    }
                    $this->preprocess = $network->preprocess;
                    if (array_key_exists("optimizer", $network)) {
                        $this->optimizer = $network->optimizer;
                    } else {
                        $this->optimizer = [];
                    }
                } else {
                    throw new \Exception("The deepnet isn't finished yet");
                }
            } else {
                throw new \Exception("Cannot create the Deepnet instance. Could not find the 'deepnet' key in the resource.\n\n ");
            }
        }
    }

    function fill_array($input_data, $unique_terms) {

        // Filling the input array for the network with the data in
        // the input_data dictionary. Numeric missings are added as a
        // new field and texts/items are processed.

        $columns = [];

        foreach ($this->input_fields as $field_id) {
            // if the field is text or items, we need to expand the
            // field in one field per term and get its frequency
            if (isset($unique_terms[$field_id])) {
                $unique = $unique_terms[$field_id];
            } else {
                $unique = [];
            }

            if (array_key_exists($field_id, $this->tag_clouds)) {
                $terms_occurences = expand_terms($this->tag_clouds[$field_id], $unique);          
                $columns = array_merge($columns, $terms_occurences);
            } elseif (array_key_exists($field_id, $this->items)) {
                $terms_occurences = expand_terms($this->items[$field_id], $unique);
                $columns = array_merge($columns, $terms_occurences);
            } elseif (array_key_exists($field_id, $this->categories)) {
                $category = $unique;
                if ($category != []) {
                    $category = $category[0][0];
                }
                $columns[] = array($category);
            } else {
                // when missing_numerics is True and the field had
                // missings in the training data, then we add a new
                // "is missing?" element whose value is 1 or 0
                // according to whether the field is missing or not in
                // the input data

                if ($this->missing_numerics && $this->fields->$field_id->summary->missing_count > 0) {
                    if (array_key_exists($field_id, $input_data)) {
                        $columns = array_merge($columns, array($input_data[$field_id], 0.0));
                    } else {
                        $columns = array_merge($columns, array(0.0, 1.0));
                    }
                } else {
                    if (isset($input_data[$field_id])) {
                        $columns[] = $input_data[$field_id];
                    } else {
                        $columns[] = null;
                    }
                }
            }
        }

        return preprocess($columns, $this->preprocess);
    }

    function predict($input_data, $by_name=true, $add_unused_fields=false) {
        //Makes a prediction based on a number of field values

        //Checks and cleans input_data leaving the fields used in the model
        $new_data = $this->filter_input_data($input_data, $by_name, $add_unused_fields);
        if ($add_unused_fields) {
            $input_data = $new_data[0];
            $unused_fields = $new_data[1];
        } else {
            $input_data = $new_data;
        }

        //Strips affixes for numeric values and casts to the final field type
        cast($input_data, $this->fields);

        //Computes text and categorical field expansion
        $unique_terms = $this->get_unique_terms($input_data);

        $input_array = $this->fill_array($input_data, $unique_terms);

        if ($this->networks) {
            $prediction = $this->predict_list($input_array);
        } else {
            $prediction = $this->predict_single($input_array);
        }

        if ($add_unused_fields) {
            $prediction["unused_fields"] = $unused_fields;
        }

        return $prediction;
    }

    function predict_single($input_array) {
        //Makes a prediction with a single network

        if (!is_null($this->network->trees)) {
            $input_array = tree_transform($input_array, $this->network->trees);
        }

        return $this->to_prediction($this->model_predict($input_array, $this->network));
    }

    function predict_list($input_array) {
        if (!is_null($this->network->trees)) {
            $input_array_trees = tree_transform($input_array, $this->network->trees);
        }
        $youts = [];
        foreach ($this->networks as $model) {
            if ($model->trees) {
                $model_predict = $this->model_predict($input_array_trees, $model);
                if ($this->regression) {
                    $youts[] = array( array($model_predict));
                } else {
                    $youts[] = $model_predict;
                }
            } else {
                $model_predict = $this->model_predict($input_array, $model);
                if ($this->regression) {
                    $youts[] = array( array($model_predict));
                } else {
                    $youts[] = $model_predict;
                }
            }
        }
        
       return $this->to_prediction(sum_and_normalize($youts, $this->regression));
    }

    function model_predict($input_array, $model) {
        //Prediction with one model

        $layers = init_layers($model->layers);

        $y_out = propagate($input_array, $layers);

        if ($this->regression) {
            $moments = moments($model->output_exposition);
            $y_mean = $moments[0];
            $y_stdev = $moments[1];
            $y_out = destandardize($y_out, $y_mean, $y_stdev);

            return $y_out[0][0];
        }

        return $y_out;
    }

    function to_prediction($y_out) {
        //Structuring prediction in a dictionary output

        if ($this->regression) {
            return $y_out;
        }
        
        $y_sort = $y_out;
        arsort($y_sort[0]);

        $y_pairs = [];
        foreach ($y_sort[0] as $index => $value) {
            $y_pairs[] = array($index, $value);
        }
        $prediction = $y_pairs[0];

        $distribution = [];
        foreach ($this->class_names as $i => $category) {
            $distribution[] = array("category" => $category, "probability" => $y_out[0][$i]);
        }

        $prediction = array("prediction" => $this->class_names[$prediction[0]],
                            "probability" => $prediction[1],
                            "distribution" => $distribution);

        return $prediction;
    }

    function predict_probability($input_data, $by_name = true, $compact = false) {

        // Predicts a probability for each possible output class,
        // based on input values.  The input fields must be an array
        // keyed by field name or field ID.
        //
        // :param input_data: Input data to be predicted 
        //:param by_name: Boolean that is set to True if field_names
        // (as alternative to field ids) are used in the input_data
        // array
        // :param compact: If False, prediction is returned as an
        // array of arrays, one per class, with the keys "prediction"
        // and "probability" mapped to the name of the class and its
        // probability, respectively.  If True, returns an array of
        // probabilities ordered by the sorted order of the class
        // names.

        if ($this->regression) {
            return $this->predict($input_data, $by_name);
        } else {
            $distribution = $this->predict($input_data, $by_name)['distribution'];
            usort($distribution, function ($pred1, $pred2) 
                                 {return strnatcmp($pred1['category'], $pred2['category']);});

            if ($compact) {
                $output = [];
                foreach ($distribution as $category) {
                    $output[] = $category['probability'];
                }
                return $output;
            } else {
                return $distribution;
            }
        }
    }
}

?>