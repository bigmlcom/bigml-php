<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
 include '../bigml/bigml.php';
}
if (!class_exists('BigML\Model')) {
 include '../bigml/model.php';
}

use BigML\BigML;
use BigML\BigMLRequest;
use BigML\Model;

class BigMLTestLocalModelInfo extends PHPUnit_Framework_TestCase
{
    protected static $username; # "you_username"
    protected static $api_key; # "your_api_key"
    protected static $api;
    protected static $project;

    public static function setUpBeforeClass() {
       print __FILE__;
       self::$api =  new BigML(self::$username, self::$api_key, false);
       if (!file_exists('tmp')) {
          mkdir('tmp');
       }
       ini_set('memory_limit', '512M');
       ini_set('xdebug.max_nesting_level', '500');
       $test_name=basename(preg_replace('/\.php$/', '', __FILE__));
       self::$api->delete_all_project_by_name($test_name);
       self::$project=self::$api->create_project(array('name'=> $test_name));
    }

    public static function tearDownAfterClass() {
       self::$api->delete_all_project_by_name(basename(preg_replace('/\.php$/', '', __FILE__)));
    }

    /*   Testing local model information output methods */

    public function test_scenario1() {

        $data = array(array("filename"=>  "data/iris.csv",
	                    "local_file" => "tmp/if_then_rules_iris.txt",
			    "expected_file" => "data/model/if_then_rules_iris.txt"),
                      array("filename"=>  "data/iris_sp_chars.csv",
                            "local_file" => "tmp/iris_sp_chars.txt",
                            "expected_file" => "data/model/if_then_rules_iris_sp_chars.txt"),
                      array("filename"=>  "data/spam.csv",
                            "local_file" => "tmp/if_then_rules_spam.txt",
                            "expected_file" => "data/model/if_then_rules_spam.txt"),
                      array("filename"=>  "data/grades.csv",
                            "local_file" => "tmp/if_then_rules_grades.txt",
                            "expected_file" => "data/model/if_then_rules_grades.txt"),
                      array("filename"=>  "data/diabetes.csv",
                            "local_file" => "tmp/if_then_rules_diabetes.txt",
                            "expected_file" => "data/model/if_then_rules_diabetes.txt"),
                      array("filename"=>  "data/iris_missing2.csv",
                            "local_file" => "tmp/if_then_rules_iris_missing2.txt",
                            "expected_file" => "data/model/if_then_rules_iris_missing2.txt"),
                      array("filename"=>  "data/tiny_kdd.csv",
                            "local_file" => "tmp/if_then_rules_tiny_kdd.txt",
                            "expected_file" => "data/model/if_then_rules_tiny_kdd.txt")
	             );

        foreach($data as $item) {
            print "\nSuccessfully creating a model and translate the tree model into a set of IF-THEN rules\n";
	    print "Given I create a data source uploading a ". $item["filename"]. " file\n";
	    $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
	    $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
	    $this->assertEquals(1, $source->object->status->code);

            print "And I wait until the local source is ready\n";
	    $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
	    $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create dataset with local source\n";
	    $dataset = self::$api->create_dataset($source->resource);
	    $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
	    $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

            print "And I wait until the dataset is ready\n";
	    $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
	    $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create model\n";
	    $model = self::$api->create_model($dataset->resource);
	    $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

            print "And I wail until the model is ready\n";
            $resource = self::$api->_check_resource($model->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create a local model\n";
	    $local_model = new Model($model->resource, self::$api);
            print "I translate the tree into IF_THEN rules\n";

	    $fp = fopen($item["local_file"], 'w');
        $local_model->rules($fp);
        fclose($fp);
	    print " Then I check the output is like " . $item["expected_file"] . " expected file\n";
            $this->assertEquals(0, strcmp(trim(file_get_contents($item["local_file"])), trim(file_get_contents($item["expected_file"]))));

        }


    }

    //Successfully creating a model with missing values and translate the tree model into a set of IF-THEN rules

    public function test_scenario2() {
        $data = array(array("filename"=>  "data/iris_missing2.csv",
                            "local_file" => "tmp/if_then_rules_iris_missing2_MISSINGS.txt",
                            "expected_file" => "data/model/if_then_rules_iris_missing2_MISSINGS.txt"));
        foreach($data as $item) {
            print "\nSuccessfully creating a model with missing values and translate the tree model into a set of IF-THEN rules\n";

            print "Given I create a data source uploading a ". $item["filename"]. " file\n";
            $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
            $this->assertEquals(1, $source->object->status->code);

            print "And I wait until the source is ready\n";
            $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create dataset with local source\n";
            $dataset = self::$api->create_dataset($source->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
            $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

            print "And I wait until the dataset is ready\n";
            $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create model\n";
            $model = self::$api->create_model($dataset->resource, array("missing_splits" => true));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

            print "And I wail until the model is ready\n";
            $resource = self::$api->_check_resource($model->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create a local model\n";
            $local_model = new Model($model->resource, self::$api);
            print "And I translate the tree into IF_THEN rules\n";

            $fp = fopen($item["local_file"], 'w');
            $local_model->rules($fp);
            fclose($fp);

            print " Then I check the output is like " . $item["expected_file"] . " expected file\n";
            $this->assertEquals(0, strcmp(trim(file_get_contents($item["local_file"])), trim(file_get_contents($item["expected_file"]))));

        }
    }

    // Successfully creating a model and translate the tree model into a set of IF-THEN rules

    public function test_scenario3() {

        $data = array(array("filename"=>  "data/spam.csv",
                            "local_file" => "tmp/if_then_rules_spam_textanalysis_1.txt",
                            "expected_file" => "data/model/if_then_rules_spam_textanalysis_1.txt",
                            "options" =>  array("fields" => array("000001" => array("optype" => "text", "term_analysis" => array("case_sensitive" => true, "stem_words" => true, "use_stopwords" => false, "language" => "en"))))),
                      array("filename"=>  "data/spam.csv",
                            "local_file" => "tmp/if_then_rules_spam_textanalysis_2.txt",
                            "expected_file" => "data/model/if_then_rules_spam_textanalysis_2.txt",
                            "options" =>  array("fields" => array("000001" => array("optype" => "text", "term_analysis" => array("case_sensitive" => true, "stem_words" => true, "use_stopwords" => false))))),
                      array("filename"=>  "data/spam.csv",
                            "local_file" => "tmp/if_then_rules_spam_textanalysis_3.txt",
                            "expected_file" => "data/model/if_then_rules_spam_textanalysis_3.txt",
                            "options" =>  array("fields" => array("000001" => array("optype" => "text", "term_analysis" => array("case_sensitive" => false, "stem_words" => false, "use_stopwords" => false, "language" => "en"))))),
                      array("filename"=>  "data/spam.csv",
                            "local_file" => "tmp/if_then_rules_spam_textanalysis_4.txt",
                            "expected_file" => "data/model/if_then_rules_spam_textanalysis_4.txt",
                            "options" =>  array("fields" => array("000001" => array("optype" => "text", "term_analysis" => array("case_sensitive" => false, "stem_words" => true, "use_stopwords" => true,  "language" => "en"))))),
                      array("filename"=>  "data/spam.csv",
                            "local_file" => "tmp/if_then_rules_spam_textanalysis_5.txt",
                            "expected_file" => "data/model/if_then_rules_spam_textanalysis_5.txt",
                            "options" =>  array("fields" => array("000001" => array("optype" => "text", "term_analysis" => array("token_mode" => "full_terms_only", "language" => "en" )))))
                     );


        foreach($data as $item) {
            print "\nSuccessfully creating a model and translate the tree model into a set of IF-THEN rules\n";

            print "Given I create a data source uploading a ". $item["filename"]. " file\n";
            $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
            $this->assertEquals(1, $source->object->status->code);

            print "And I wait until the source is ready\n";
            $resource = self::$api->_check_resource($source->resource, null, 10000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I update the source with options " . json_encode($item["options"]) . "\n";
	    $source = self::$api->update_source($source->resource, $item["options"]);
	    $this->assertEquals(BigMLRequest::HTTP_ACCEPTED, $source->code);

            print "And I create dataset with local source\n";
            $dataset = self::$api->create_dataset($source->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
            $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

            print "And I wait until the dataset is ready\n";
            $resource = self::$api->_check_resource($dataset->resource, null, 10000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create model\n";
            $model = self::$api->create_model($dataset->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

            print "And I wait until the model is ready ". $model->resource  . "\n";
            $resource = self::$api->_check_resource($model->resource, null, 10000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create a local model\n";
            $local_model = new Model($model->resource, self::$api);
            print "And I translate the tree into IF_THEN rules\n";

            $fp = fopen($item["local_file"], 'w');
            $local_model->rules($fp);
            fclose($fp);
            print " Then I check the output is like " . $item["expected_file"] . " expected file\n";
            $this->assertEquals(0, strcmp(trim(file_get_contents($item["local_file"])), trim(file_get_contents($item["expected_file"]))));

        }
    }

    public function test_scenario4() {
        $data = array(array("filename"=>  "data/iris.csv",
                            "expected_file" => "data/model/data_distribution_iris.txt"),
                     array("filename"=>  "data/iris_sp_chars.csv",
                            "expected_file" => "data/model/data_distribution_iris_sp_chars.txt"),
                     array("filename"=>  "data/spam.csv",
                            "expected_file" => "data/model/data_distribution_spam.txt"),
                     array("filename"=>  "data/grades.csv",
                            "expected_file" => "data/model/data_distribution_grades.txt"),
                     array("filename"=>  "data/diabetes.csv",
                            "expected_file" => "data/model/data_distribution_diabetes.txt"),
                     array("filename"=>  "data/iris_missing2.csv",
                            "expected_file" => "data/model/data_distribution_iris_missing2.txt"),
                     array("filename"=>  "data/tiny_kdd.csv",
                            "expected_file" => "data/model/data_distribution_tiny_kdd.txt")
                     );


        foreach($data as $item) {
            print "\nSuccessfully creating a model and check its data distribution\n";

            print "Given I create a data source uploading a ". $item["filename"]. " file\n";
            $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
            $this->assertEquals(1, $source->object->status->code);

            print "And I wait until source is ready\n";
            $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create dataset with local source\n";
            $dataset = self::$api->create_dataset($source->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
            $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

            print "And I wail until the dataset is ready\n";
            $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create model\n";
            $model = self::$api->create_model($dataset->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

            print "And I wail until the model is ready ". $model->resource  . "\n";
            $resource = self::$api->_check_resource($model->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create a local model\n";
            $local_model = new Model($model->resource, self::$api);

            $file_distribution = file_get_contents($item["expected_file"]);
            print "And I translate the tree into IF_THEN rules\n";
            $distribution = $local_model->get_data_distribution();
            $distribution_str='';
            foreach($distribution as $value) {
              $distribution_str= $distribution_str . "[" . $value[0] . "," . $value[1] . "]\n";
            }
            print " Then I check the output is like " . $item["expected_file"] . " expected file\n";
            $this->assertEquals(trim($distribution_str), trim($file_distribution));

        }
    }


    // Successfully creating a model and check its predictions distribution

    public function test_scenario5() {

       $data = array(array('filename' => 'data/iris.csv', 'expected_file' => 'data/model/predictions_distribution_iris.txt'),
                     array('filename' => 'data/iris_sp_chars.csv', 'expected_file' => 'data/model/predictions_distribution_iris_sp_chars.txt'),
                     array('filename' => 'data/spam.csv', 'expected_file' => 'data/model/predictions_distribution_spam.txt'),
                     array('filename' => 'data/grades.csv', 'expected_file' => 'data/model/predictions_distribution_grades.txt'),
                     array('filename' => 'data/diabetes.csv', 'expected_file' => 'data/model/predictions_distribution_diabetes.txt'),
                     array('filename' => 'data/iris_missing2.csv', 'expected_file' => 'data/model/predictions_distribution_iris_missing2.txt'),
                     array('filename' => 'data/tiny_kdd.csv', 'expected_file' => 'data/model/predictions_distribution_tiny_kdd.txt')
                    );

       foreach($data as $item) {
            print "\n Successfully creating a model and check its predictions distribution\n";

            print "Given I create a data source uploading a ". $item["filename"]. " file\n";
            $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
            $this->assertEquals(1, $source->object->status->code);

            print "And I wait until the source is ready\n";
            $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create dataset with local source\n";
            $dataset = self::$api->create_dataset($source->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
            $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

            print "And I wait until the dataset is ready\n";
            $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create model\n";
            $model = self::$api->create_model($dataset->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

            print "And I wait until the model is ready ". $model->resource  . "\n";
            $resource = self::$api->_check_resource($model->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create a local model\n";
            $local_model = new Model($model->resource, self::$api);
            print "Then I check the predictions distribution with ". $item["expected_file"] . " file\n";

            $file_distribution = file_get_contents($item["expected_file"]);
            $distribution = $local_model->get_prediction_distribution();
            $distribution_str='';

            foreach($distribution as $key => $value) {
              $distribution_str= $distribution_str . "[" . $key . "," . $value . "]\n";
            }

            $this->assertEquals(trim($distribution_str), trim($file_distribution));

      }
    }

    //  Successfully creating a model and check its summary information

    public function test_scenario6() {
       $data = array(array('filename' => 'data/iris.csv', 'expected_file' => 'data/model/summarize_iris.txt', 'local_file' => 'tmp/summarize_iris.txt'),
                     array('filename' => 'data/iris_sp_chars.csv', 'expected_file' => 'data/model/summarize_iris_sp_chars.txt', 'local_file' => 'tmp/summarize_iris_sp_chars.txt'),
                     array('filename' => 'data/spam.csv', 'expected_file' => 'data/model/summarize_spam.txt',  'local_file' => 'tmp/summarize_spam.txt'),
                     array('filename' => 'data/grades.csv', 'expected_file' => 'data/model/summarize_grades.txt',  'local_file' => 'tmp/summarize_grades.txt'),
                     array('filename' => 'data/diabetes.csv', 'expected_file' => 'data/model/summarize_diabetes.txt',  'local_file' => 'tmp/summarize_diabetes.txt'),
                     array('filename' => 'data/iris_missing2.csv', 'expected_file' => 'data/model/summarize_iris_missing2.txt',  'local_file' => 'tmp/summarize_iris_missing2.txt'),
                     array('filename' => 'data/tiny_kdd.csv', 'expected_file' => 'data/model/summarize_tiny_kdd.txt',  'local_file' => 'tmp/summarize_tiny_kdd.txt')
                    );

       foreach($data as $item) {
            print "\nSuccessfully creating a model and check its summary information:\n";
            print "Given I create a data source uploading a ". $item["filename"]. " file\n";
            $source = self::$api->create_source($item["filename"], $options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
            $this->assertEquals(1, $source->object->status->code);

            print "And I wait until the source is ready\n";
            $resource = self::$api->_check_resource($source->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I  create dataset with local source\n";
            $dataset = self::$api->create_dataset($source->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $dataset->code);
            $this->assertEquals(BigMLRequest::QUEUED, $dataset->object->status->code);

            print "And I wait until the dataset is ready\n";
            $resource = self::$api->_check_resource($dataset->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create model\n";
            $model = self::$api->create_model($dataset->resource);
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $model->code);

            print "And I wait until the model is ready ". $model->resource  . "\n";
            $resource = self::$api->_check_resource($model->resource, null, 3000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create a local model\n";
            $local_model = new Model($model->resource, self::$api);
            print "And I translate the tree into IF_THEN rules\n";
	    $local_model->summarize(fopen($item["local_file"],'w'));
            print "Then I check the predictions distribution with ". $item["expected_file"] . " file\n";
	    $this->assertEquals(0, strcmp(trim(file_get_contents($item["local_file"])), trim(file_get_contents($item["expected_file"]))));

       }

    }
}
