<?php
#
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

/* A local Predictive Topic Model.  

 This module allows you to download and use Topic models for local
 predicitons.  Specifically, the function topic_model.distribution
 allows you to pass in input text and infers a generative distribution
 over the topics in the learned topic model.  

 Example usage (assuming that you have previously set up the
 BIGML_USERNAME and BIGML_API_KEY environment variables and that you
 own the topicmodel/id below): 

 if (!class_exists('bigml')) {
   include '../bigml/bigml.php';
 }

 if (!class_exists('TopicModel')) {
   include '../bigml/topicmodel.php';
 }

 $api = new BigML(); 
 $topic_model = new TopicModel('topicmodel/5026965515526876630001b2'); 
 $topic_distribution = topic_model.distribution({"text": "A sample string"}));
*/

if (!class_exists('bigml')) {
   include('bigml.php');
}

if (!class_exists('basemodel')) {
  include('basemodel.php');
}

if (!class_exists('modelfields')) {
  include('modelfields.php');
}

require_once '../vendor/autoload.php';

define("MAX_TERM_LENGTH", 30);
define("MIN_UPDATES", 16);
define("MAX_UPDATES", 512);
define("SAMPLES_PER_TOPIC", 128);

function code_to_name($lang) {
    switch ($lang) {
        case "da":
            $stemmer = new Wamania\Snowball\Danish();
            return $stemmer;
        case "nl":
            $stemmer = new Wamania\Snowball\Dutch();
            return $stemmer;
        case "en":
            $stemmer = new Wamania\Snowball\English();
            return $stemmer;
        case "fr":
            $stemmer = new Wamania\Snowball\French();
            return $stemmer;
        case "de":
            $stemmer = new Wamania\Snowball\German();
            return $stemmer;
        case "it":
            $stemmer = new Wamania\Snowball\Italian();
            return $stemmer;
        case "nn":
            $stemmer = new Wamania\Snowball\Norwegian();
            return $stemmer;
        case "pt":
            $stemmer = new Wamania\Snowball\Portuguese();
            return $stemmer;
        case "ro":
            $stemmer = new Wamania\Snowball\Romanian();
            return $stemmer;
        case "ru":
            $stemmer = new Wamania\Snowball\Russian();
            return $stemmer;
        case "es":
            $stemmer = new Wamania\Snowball\Spanish();
            return $stemmer;
        case "sv":
            $stemmer = new Wamania\Snowball\Swedish();
            return $stemmer;
        default:
            throw new Exception("Your language is not currently supported.");
    }        
}

class TopicModel extends ModelFields{
    /*
      A lightweight wrapper around a Topic model.  Uses a BigML remote
      Topic Model to build a local version that can be used to
      generate topic distributions for input documents locally.
    */

    public $resource_id;
    public $stemmer;
    public $seed;
    public $case_sensitive = false;
    public $bigrams = false;
    public $ntopics;
    public $temp;
    public $phi;
    public $term_to_index;
    public $topics = [];

    public function __construct($topicmodel, $api=null, $storage="storage") {

      if ($api == null) {
         $api = new BigML(null, null, null, $storage);
      }

      if (is_string($topicmodel)) {
          if (!($api::_checkTopicmodelId($topicmodel)) ) {
              error_log("Wrong topic model id");
              return null;
          }
          
          $topicmodel = $api::retrieve_resource($topicmodel, $api::ONLY_MODEL);
      }

      if (property_exists($topicmodel, "object") && property_exists($topicmodel->object, "status") && $topicmodel->object->status->code != BigMLRequest::FINISHED ) {
          throw new Exception("The topic model isn't finished yet");
      }

      if (property_exists($topicmodel, "object") && $topicmodel->object instanceof STDClass) {
          $topicmodel = $topicmodel->object;
      }

      if (property_exists($topicmodel, "topic_model") && $topicmodel->topic_model instanceof STDClass) {

          if ($topicmodel->status->code == BigMLRequest::FINISHED) {

              $model = $topicmodel->topic_model;

              $this->topics = $model->topics;

              if (property_exists($model, "language") and !is_null($model->language)) {
                  $lang = $model->language;
                  $this->stemmer = code_to_name($lang);
              }
              
              $term_to_index = [];
              foreach ($model->termset as $index => $term) {
                  $term_to_index[$this->stem($term)] = $index;
              }
              $this->term_to_index = $term_to_index;

              $this->seed = abs($model->hashed_seed);
              $this->case_sensitive = $model->case_sensitive;
              $this->bigrams = $model->bigrams;

              $this->ntopics = count($model->term_topic_assignments[0]);

              $this->alpha = $model->alpha;
              $this->ktimesalpha = $this->ntopics * $this->alpha;

              $this->temp = array_fill(0, $this->ntopics, 0);

              $assignments = $model->term_topic_assignments;
              $beta = $model->beta;
              $nterms = count($this->term_to_index);

              $sums = [];
              foreach (range(0, $this->ntopics - 1) as $index) {
                  $sum_n = 0;
                  foreach ($assignments as $n) {
                      $sum_n += $n[$index];
                  }
                  $sums[] = $sum_n;
              }

              $this->phi = array_fill(0, $this->ntopics, array_fill(0, $nterms, 0));

              foreach (range(0, $this->ntopics - 1) as $k) {
                  $norm = $sums[$k] + $nterms * $beta;
                  foreach (range(0, $nterms - 1) as $w) {
                      $this->phi[$k][$w] = ($assignments[$w][$k] + $beta) / $norm;
                  }
              }

              $fields = $model->fields;
              parent::__construct($fields);

          } else {
              throw new Exception("The topic model isn't finished yet");
          }
      } else {
          throw new Exception("Cannot create the Topic Model instance. Could not find the 'topic_model' key in the resource.\n\n ");
      }
    }

    public function distribution($input_data, $by_name=True) {
        //Returns the distribution of topics given the input text

        //Checks and cleans input_data leaving the fields used in the model
        $input_data = $this->filter_input_data($input_data, $by_name);

        return $this->distribution_for_text(implode("\n\n", array_values($input_data))); 
    }

    public function distribution_for_text($text) {
        //Returns the topic distribution of the given 'text', which can
        //either be a string or a list of strings

        if (is_string($text)) {
            $astr = $text;
        } else {
            //List of strings
            $astr = implode("\n\n", $text);
        }

        $doc = $this->tokenize($astr);

        $topics_probability = $this->infer($doc);
        
        $distribution = [];
        foreach ($topics_probability as $index => $probability) {
            $distribution[] = array("name" => $this->topics[$index]->name, "probability" => $probability );
        }
        return $distribution;
    }

    public function stem($term) {
        // Returns the stem of the given term, if the stemmer is defined

        if (is_null($this->stemmer)) {
            return $term;
        } else {
            $stemmer = $this->stemmer;
            return $stemmer->stem($term);
        }
    }

    public function append_bigram($out_terms, $first, $second) {
        // Takes two terms and appends the index of their concatenation to the
        // provided list of output terms

        if ($this->bigrams && !is_null($first) && !is_null($second)) {
            $bigram = $this->stem($first . " " . $second);
            if (array_key_exists($bigram, $this->term_to_index)) {
                $out_terms[] = $this->term_to_index[$bigram];                
            }
        }

        return $out_terms;
    }

    public function tokenize($astr) {
        /* Tokenizes the input string `astr` into a list of integers,
           one for each term present in the `self.term_to_index`
           dictionary.  Uses word stemming if applicable. */

        $out_terms = [];

        $last_term = Null;
        $term_before = Null;

        $space_was_sep = False;
        $saw_char = False;

        if (mb_detect_encoding($astr, 'UTF-8, ISO-8859-1') == 'UTF-8') {
            $text = $astr;
        } elseif (mb_detect_encoding($astr, 'UTF-8, ISO-8859-1') == 'ISO-8859-1') {
            $text = utf8_encode($astr);
        } else {
            throw new Exception("Your input text encoding is not supported.");
        }

        $index = 0;
        $length = mb_strlen($text, 'UTF-8');

        while ($index < $length) {
            $out_terms = $this->append_bigram($out_terms, $term_before, $last_term);

            $char = mb_substr($text, $index, 1, 'UTF-8');
            $buf = '';
            $saw_char = False;

            if ( !(preg_match('/^[\pL\pN]+/u', $char)) ) {
                $saw_char = True;
            }

            while ( !(preg_match('/^[\pL\pN]+/u', $char)) && $index < $length) {
                $next_char = $this->next_char($text, $index, $length);
                $char = $next_char[0];
                $index = $next_char[1];
            }

            while ($index < $length 
                   && (preg_match('/^[\pL\pN]+/u', $char) OR $char == "'") 
                   && strlen(utf8_decode($buf)) < MAX_TERM_LENGTH) {
                $buf .= $char;
                $next_char = $this->next_char($text, $index, $length);                
                $char = $next_char[0];
                $index = $next_char[1];
            }

            if (strlen(utf8_decode($buf)) > 0) {
                $term_out = $buf;

                if ( !($this->case_sensitive)) {
                    $term_out = mb_strtolower($term_out, 'UTF-8');
                }

                if ($space_was_sep && !$saw_char) {
                    $term_before = $last_term;
                } else {
                    $term_before = Null;
                }

                $last_term = $term_out;

                if ($char == " " OR $char == "\n") {
                    $space_was_sep = True;
                }

                $tstem = $this->stem($term_out);

                if (array_key_exists($tstem, $this->term_to_index)) {
                    $out_terms[] = $this->term_to_index[$tstem];
                }

                $index += 1;
            }

        }

        $out_terms = $this->append_bigram($out_terms, $term_before, $last_term);

        return $out_terms;            
    }

    private function next_char($text, $index, $length) {        
        //Auxiliary function to get next char and index with end check

        $index += 1;
        if ($index < $length) {
            $char = mb_substr($text, $index, 1, 'UTF-8');
        } else {
            $char = '';
        }
        return array($char, $index);
    }

    public function sample_topics($document, $assignments, $normalizer, $updates) {
        /*Samples topics for the terms in the given `document` for
           `updates` iterations, using the given set of topic
           `assigments` for the current document and a `normalizer`
           term derived from the dirichlet hyperparameters */

        $counts = array_fill(0, $this->ntopics, 0);

        foreach (range(0, $updates - 1) as $i) {
            foreach ($document as $term) {
                foreach (range(0, $this->ntopics - 1) as $k) {
                    $topic_term = $this->phi[$k][$term];
                    $topic_document = ($assignments[$k] + $this->alpha) / $normalizer;
                    $this->temp[$k] = $topic_term * $topic_document;
                }

                foreach (range(1, $this->ntopics - 1) as $k) {
                    $this->temp[$k] += $this->temp[$k - 1];
                }

                $random = mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax();
                $random_value = $random * array_slice($this->temp,-1)[0];
                $topic = 0;

                while ($this->temp[$topic] < $random_value && $topic < $this->ntopics) {
                    $topic += 1;
                }

                $counts[$topic] += 1;
            }
        }

        return $counts;
    }

    public function sample_uniform($document, $updates) {
        /* Samples topics for the terms in the given `document`
           assuming uniform topic assignments for `updates`
           iterations.  Used to initialize the gibbs sampler. */

        $counts = array_fill(0, $this->ntopics, 0);

        foreach (range(0, $updates - 1) as $i) {
            foreach ($document as $term) {
                foreach (range(0, $this->ntopics - 1) as $k) {
                    $this->temp[$k] = $this->phi[$k][$term];
                }
                foreach (range(1, $this->ntopics - 1) as $k) {
                    $this->temp[$k] += $this->temp[$k - 1];
                }

                $random = mt_rand(0, mt_getrandmax() - 1) / mt_getrandmax();
                $random_value = $random * array_slice($this->temp,-1)[0];
                $topic = 0;

                while ($this->temp[$topic] < $random_value && $topic < $this->ntopics) {
                    $topic += 1;
                }

                $counts[$topic] += 1;
            }
        }

        return $counts;
    }

    public function infer($list_of_indices) {
    /* Infer a topic distribution for a document, presented as a list
       of term indices. */

        sort($list_of_indices);
        $doc = $list_of_indices;
        $updates = 0;

        if (count($doc) > 0) {
            $updates = SAMPLES_PER_TOPIC * $this->ntopics / count($doc);
            $updates = intval(min(MAX_UPDATES, max(MIN_UPDATES, $updates)));
        }

        mt_srand($this->seed);
        $normalizer = (count($doc) * $updates) + $this->ktimesalpha;

        //Initialization
        $uniform_counts = $this->sample_uniform($doc, $updates);

        //Burn-in
        $burn_counts = $this->sample_topics($doc, $uniform_counts, $normalizer, $updates);

        //Sampling
        $sample_counts = $this->sample_topics($doc, $burn_counts, $normalizer, $updates);

        $output = [];
        foreach (range(0, $this->ntopics - 1) as $k) {
            $output[] = ($sample_counts[$k] + $this->alpha) / $normalizer;
        }
        return $output;
    }
}
?>
