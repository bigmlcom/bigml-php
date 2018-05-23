<?php

include 'test_utils.php';

if (!class_exists('BigML\BigML')) {
    include ('../bigml/bigml.php');
}

if (!class_exists('BigML\BaseModel')) {
    include '../bigml/basemodel.php';
}

use BigML\BigML;
use BigML\BigMLRequest;
use BigML\BaseModel;

class BigMLTestCompareRegressions extends PHPUnit_Framework_TestCase
{
    protected static $api;
    protected static $project;

    public static function setUpBeforeClass() {
        print __FILE__;
        self::$api = new BigML([
            "storage" => "./test-cache"]);
        ini_set('memory_limit', '512M');
        ini_set('xdebug.max_nesting_level', '500');
        $test_name = basename(preg_replace('/\.php$/', '', __FILE__));
        self::$api->delete_all_project_by_name($test_name);
        self::$project = self::$api->create_project(array('name'=> $test_name));
    }

    public static function tearDownAfterClass() {
        self::$api->delete_all_project_by_name(basename(preg_replace('/\.php$/', '', __FILE__)));
    }

    #
    # Successfully comparing predictions
    #

    public function test_scenario1() {

        $data = array(array("filename" => "data/iris.csv"));

        foreach($data as $item) {
            print "\nSuccessfully creating BaseModel with default API object:\n";

            print "Given I create a data source uploading a ". $item["filename"]. " file\n";
            $source = self::$api->create_source($item["filename"],$options=array('name'=>'local_test_source', 'project'=> self::$project->resource));
            $this->assertEquals(BigMLRequest::HTTP_CREATED, $source->code);
            $this->assertEquals(1, $source->object->status->code);

            print "And I wait until the source is ready\n";
            $resource = self::$api->_check_resource($source->resource, null, 10000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

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

            print "And I wait until the model is ready\n";
            $resource = self::$api->_check_resource($model->resource, null, 10000, 30);
            $this->assertEquals(BigMLRequest::FINISHED, $resource["status"]);

            print "And I create a base model " . $model->resource . "\n";
            $localmodel = new BaseModel($model->resource);
        }
    }
    #
    # Successfully creating base model with default API object
    #

}
