BigML PHP Bindings
=====================
In this repository you'll find an open source PHP client that gives you a simple binding to interact with `BigML <https://bigml.com>`_.

This module is licensed under the `Apache License, Version
2.0 <http://www.apache.org/licenses/LICENSE-2.0.html>`_.

Requirements
------------

PHP 5.3.2 or higher are currently supported by these bindings.

You need `mbstring <http://www.php.net/manual/en/mbstring.installation.php`_. installed
And Https Module Support for Curl.

Importing the module
--------------------

To import the module::

    include 'bigml.php';

Autoload Class in PHP 5::

    function __autoload($class_name) {
        if (!class_exists($class_name, false)){
            include $class_name . '.php';
    }


Authentication
--------------

All the requests to BigML.io must be authenticated using your username
and `API key <https://bigml.com/account/apikey>`_. and are always
transmitted over HTTPS.

This module will look for your username and API key in the environment variables BIGML_USERNAME and BIGML_API_KEY respectively. 
You can add the following lines to your .bashrc or .bash_profile to set those variables automatically when you log in::

    export BIGML_USERNAME=myusername
    export BIGML_API_KEY=a11e579e7e53fb9abd646a6ff8aa99d4afe83ac2

With that environment set up, connecting to BigML is a breeze::

   $api = new BigML();

You can initialize directly when instantiating the BigML
class as follows::

   $api = new BigML("myusername", "my_api_key");

Also, you can initialize the library to work in the Sandbox environment by
passing the parameter ``dev_mode``::

   $api = new BigML("myusername", "my_api_key", true);

Setting the storage argument in the api instantiation::

   $api = new BigML("myusername", "my_api_key", true, 'storage/data');

all the generated, updated or retrieved resources will be automatically saved to the chosen directory.

For Virtual Private Cloud setups, you can change the remote server domain::
    
   $api = new BigML("myusername", "my_api_key", true, 'storage/data', my_VPC.bigml.io);

Quick Start
-----------

Imagine that you want to use `this csv
file <https://static.bigml.com/csv/iris.csv>`_ containing the `Iris
flower dataset <http://en.wikipedia.org/wiki/Iris_flower_data_set>`_ to
predict the species of a flower whose ``sepal length`` is ``5`` and
whose ``sepal width`` is ``2.5``. A preview of the dataset is shown
below. It has 4 numeric fields: ``sepal length``, ``sepal width``,
``petal length``, ``petal width`` and a categorical field: ``species``.
By default, BigML considers the last field in the dataset as the
objective field (i.e., the field that you want to generate predictions
for).

::

    sepal length,sepal width,petal length,petal width,species
    5.1,3.5,1.4,0.2,Iris-setosa
    4.9,3.0,1.4,0.2,Iris-setosa
    4.7,3.2,1.3,0.2,Iris-setosa
    ...
    5.8,2.7,3.9,1.2,Iris-versicolor
    6.0,2.7,5.1,1.6,Iris-versicolor
    5.4,3.0,4.5,1.5,Iris-versicolor
    ...
    6.8,3.0,5.5,2.1,Iris-virginica
    5.7,2.5,5.0,2.0,Iris-virginica
    5.8,2.8,5.1,2.4,Iris-virginica

You can easily generate a prediction following these steps::

    $api = new BigML("myusername", "my_api_key");

    $source = $api::create_source('./tests/data/iris.csv');
    $dataset = $api::create_dataset($source);
    $model = $api::create_model($dataset);
    $prediction = $api::create_prediction($model, array('sepal length'=> 5, 'sepal width'=> 2.5));

    $api::pprint($prediction);

    petal width for {"sepal length":5,"sepal width":2.5} is 0.30455

also generate an evaluation for the model by using::

    $test_source = $api::create_source('./tests/data/iris.csv');
    $test_dataset = $api::create_dataset($test_source);
    $evaluation = $api::create_evaluation($model, $test_dataset);


Dataset
-------

If you want to get some basic statistics for each field you can retrieve 
the fields from the dataset as follows to get a dictionary keyed by field id::

    $dataset = $api::get_dataset($dataset);
    print_r($api::get_fields($dataset))

The field filtering options are also available using a query string expression, for instance::

    $dataset = $api::get_dataset($dataset, "limit=20")

limits the number of fields that will be included in dataset to 20.

Model
-----

One of the greatest things about BigML is that the models that it generates for you are fully white-boxed. 
To get the explicit tree-like predictive model for the example above::

    $model = $api::get_model($model_id);

    print_r($model->object->model->root);

    stdClass Object
    (
    [children] => Array
        (
            [0] => stdClass Object
                (
                    [children] => Array
                        (
                            [0] => stdClass Object...

Again, filtering options are also available using a query string expression, for instance::

    $model = $api::get_model($model_id, "limit=5");

limits the number of fields that will be included in model to 5.


Evaluation
----------

The predictive performance of a model can be measured using many different measures. 
In BigML these measures can be obtained by creating evaluations. 
To create an evaluation you need the id of the model you are evaluating and the id of 
the dataset that contains the data to be tested with. The result is shown as::

    $evaluation = $api::get_evaluation($evaluation_id);

Cluster
-------

For unsupervised learning problems, the cluster is used to classify in a limited number of groups your training data. 
The cluster structure is defined by the centers of each group of data, named centroids, and the data enclosed in the group. 
As for in the model’s case, the cluster is a white-box resource and can be retrieved as a JSON::

    $cluster = $api::get_cluster($cluster_id)

Anomaly detector
----------------

For anomaly detection problems, BigML anomaly detector uses iforest as an unsupervised kind of model that detects anomalous data in a dataset. The information it returns encloses a top_anomalies block that contains a list of the most anomalous points. For each, we capture a score from 0 to 1. The closer to 1, the more anomalous. We also capture the row which gives values for each field in the order defined by input_fields. Similarly we give a list of importances which match the row values. These importances tell us which values contributed most to the anomaly score. Thus, the structure of an anomaly detector is similar to::

    {   'category': 0,
    'code': 200,
    'columns': 14,
    'constraints': False,
    'created': '2014-09-08T18:51:11.893000',
    'credits': 0.11653518676757812,
    'credits_per_prediction': 0.0,
    'dataset': 'dataset/540dfa9d9841fa5c88000765',
    'dataset_field_types': {   'categorical': 21,
                               'datetime': 0,
                               'numeric': 21,
                               'preferred': 14,
                               'text': 0,
                               'total': 42},
    'dataset_status': True,
    'dataset_type': 0,
    'description': '',
    'excluded_fields': [],
    'fields_meta': {   'count': 14,
                       'limit': 1000,
                       'offset': 0,
                       'query_total': 14,
                       'total': 14},
    'forest_size': 128,
    'input_fields': [   '000004',
                        '000005',
                        '000009',
                        '000016',
                        '000017',
                        '000018',
                        '000019',
                        '00001e',
                        '00001f',
                        '000020',
                        '000023',
                        '000024',
                        '000025',
                        '000026'],
    'locale': 'en_US',
    'max_columns': 42,
    'max_rows': 200,
    'model': {   'fields': {   '000004': {   'column_number': 4,
                                             'datatype': 'int16',
                                             'name': 'src_bytes',
                                             'optype': 'numeric',
                                             'order': 0,
                                             'preferred': True,
                                             'summary': {   'bins': [   [   143,
                                                                            2],
                                                                        ...
                                                                        [   370,
                                                                            2]],
                                                            'maximum': 370,
                                                            'mean': 248.235,
                                                            'median': 234.57157,
                                                            'minimum': 141,
                                                            'missing_count': 0,
                                                            'population': 200,
                                                            'splits': [   159.92462,
                                                                          173.73312,
                                                                          188,
                                                                          ...
                                                                          339.55228],
                                                            'standard_deviation': 49.39869,
                                                            'sum': 49647,
                                                            'sum_squares': 12809729,
                                                            'variance': 2440.23093}},
                               '000005': {   'column_number': 5,
                                             'datatype': 'int32',
                                             'name': 'dst_bytes',
                                             'optype': 'numeric',
                                             'order': 1,
                                             'preferred': True,
                                              ...
                                                            'sum': 1030851,
                                                            'sum_squares': 22764504759,
                                                            'variance': 87694652.45224}},
                               '000009': {   'column_number': 9,
                                             'datatype': 'string',
                                             'name': 'hot',
                                             'optype': 'categorical',
                                             'order': 2,
                                             'preferred': True,
                                             'summary': {   'categories': [   [   '0',
                                                                                  199],
                                                                              [   '1',
                                                                                  1]],
                                                            'missing_count': 0},
                                             'term_analysis': {   'enabled': True}},
                               '000016': {   'column_number': 22,
                                             'datatype': 'int8',
                                             'name': 'count',
                                             'optype': 'numeric',
                                             'order': 3,
                                             'preferred': True,
                                                            ...
                                                            'population': 200,
                                                            'standard_deviation': 5.42421,
                                                            'sum': 1351,
                                                            'sum_squares': 14981,
                                                            'variance': 29.42209}},
                               '000017': { ... }}},
                 'kind': 'iforest',
                 'mean_depth': 12.314174107142858,
                 'top_anomalies': [   {   'importance': [   0.06768,
                                                            0.01667,
                                                            0.00081,
                                                            0.02437,
                                                            0.04773,
                                                            0.22197,
                                                            0.18208,
                                                            0.01868,
                                                            0.11855,
                                                            0.01983,
                                                            0.01898,
                                                            0.05306,
                                                            0.20398,
                                                            0.00562],
                                          'row': [   183.0,
                                                     8654.0,
                                                     '0',
                                                     4.0,
                                                     4.0,
                                                     0.25,
                                                     0.25,
                                                     0.0,
                                                     123.0,
                                                     255.0,
                                                     0.01,
                                                     0.04,
                                                     0.01,
                                                     0.0],
                                          'score': 0.68782},
                                      {   'importance': [   0.05645,
                                                            0.02285,
                                                            0.0015,
                                                            0.05196,
                                                            0.04435,
                                                            0.0005,
                                                            0.00056,
                                                            0.18979,
                                                            0.12402,
                                                            0.23671,
                                                            0.20723,
                                                            0.05651,
                                                            0.00144,
                                                            0.00612],
                                          'row': [   212.0,
                                                     1940.0,
                                                     '0',
                                                     1.0,
                                                     2.0,
                                                     0.0,
                                                     0.0,
                                                     1.0,
                                                     1.0,
                                                     69.0,
                                                     1.0,
                                                     0.04,
                                                     0.0,
                                                     0.0],
                                          'score': 0.6239},
                                          ...],
                 'trees': [   {   'root': {   'children': [   {   'children': [   {   'children': [   {   'children': [   {   'children':[   {   'population': 1,
                                                                                                                              'predicates': [   {   'field': '00001f',
                                                                                                                                                    'op': '>',
                                                                                                                                                    'value': 35.54357}]},

                                                                                                                          {   'population': 1,
                                                                                                                              'predicates': [   {   'field': '00001f',
                                                                                                                                                    'op': '<=',
                                                                                                                                                    'value': 35.54357}]}],
                                                                                                          'population': 2,
                                                                                                          'predicates': [   {   'field': '000005',
                                                                                                                                'op': '<=',
                                                                                                                                'value': 1385.5166}]}],
                                                                                      'population': 3,
                                                                                      'predicates': [   {   'field': '000020',
                                                                                                            'op': '<=',
                                                                                                            'value': 65.14308},
                                                                                                        {   'field': '000019',
                                                                                                            'op': '=',
                                                                                                            'value': 0}]}],
                                                                  'population': 105,
                                                                  'predicates': [   {   'field': '000017',
                                                                                        'op': '<=',
                                                                                        'value': 13.21754},
                                                                                    {   'field': '000009',
                                                                                        'op': 'in',
                                                                                        'value': [   '0']}]}],
                                              'population': 126,
                                              'predicates': [   True,
                                                                {   'field': '000018',
                                                                    'op': '=',
                                                                    'value': 0}]},
                                  'training_mean_depth': 11.071428571428571}]},
    'name': "tiny_kdd's dataset anomaly detector",
    'number_of_batchscores': 0,
    'number_of_public_predictions': 0,
    'number_of_scores': 0,
    'out_of_bag': False,
    'price': 0.0,
    'private': True,
    'project': None,
    'range': [1, 200],
    'replacement': False,
    'resource': 'anomaly/540dfa9f9841fa5c8800076a',
    'rows': 200,
    'sample_rate': 1.0,
    'sample_size': 126,
    'seed': 'BigML',
    'shared': False,
    'size': 30549,
    'source': 'source/540dfa979841fa5c7f000363',
    'source_status': True,
    'status': {   'code': 5,
                  'elapsed': 32397,
                  'message': 'The anomaly detector has been created',
                  'progress': 1.0},
    'subscription': False,
    'tags': [],
    'updated': '2014-09-08T23:54:28.647000',
    'white_box': False}

Statuses
--------
Please, bear in mind that resource creation is almost always asynchronous (predictions are the only exception). 
Therefore, when you create a new source, a new dataset or a new model, even if you receive an immediate response from the BigML servers, 
the full creation of the resource can take from a few seconds to a few days, depending on the size of the resource and BigML’s load. 
A resource is not fully created until its status is bigml.api.FINISHED. 
See the documentation on status codes for the listing of potential states and their semantics::

        BigMLRequest::WAITING 
        BigMLRequest::QUEUED 
        BigMLRequest::STARTED 
        BigMLRequest::IN_PROGRESS 
        BigMLRequest::SUMMARIZED 
        BigMLRequest::FINISHED 
        BigMLRequest::UPLOADING
        BigMLRequest::FAULTY 
        BigMLRequest::UNKNOWN
        BigMLRequest::RUNNABLE 

You can query the status of any resource with the status method::
    
    $api::status($source)
    $api::status($dataset)
    $api::status($model)
    $api::status($prediction)
    $api::status($evaluation)
    $api::status($ensemble)
    $api::status($batch_prediction)
    $api::status($cluster)
    $api::status($centroid)
    $api::status($batch_centroid)
    $api::status($anomaly)
    $api::status($anomaly_score)
    $api::status($batch_anomaly_score)

Creating sources
----------------

To create a source from a local data file, you can use the create_source method. The only required parameter is the path to the data file (or file-like object). You can use a second optional parameter to specify any of the options for source creation described in the `BigML API documentation <https://bigml.com/developers>`_.

Here’s a sample invocation::
   
    $source = $api::create_source('./tests/data/iris.csv', array('name'=> 'my source'));

or you may want to create a source from a file in a remote location::

    $source = $api::create_source('s3://bigml-public/csv/iris.csv');

Creating datasets 
-----------------

Once you have created a source, you can create a dataset. The only required argument to create a dataset is a source id. 
You can add all the additional arguments accepted by BigML and documented in `the Datasets section of the Developer’s documentation <https://bigml.com/developers/datasets>`_.

For example, to create a dataset named “my dataset” with the first 1024 bytes of a source, you can submit the following request::

    $dataset = $api::create_dataset($source, array("name"=> "mydata", "size"=> 1024));

You can also extract samples from an existing dataset and generate a new one with them using the api.create_dataset method::

    $dataset = $api::create_dataset($origin_dataset, array("sample_rate"=> 0.8));

It is also possible to generate a dataset from a list of datasets (multidataset)::

    $dataset1 = $api::create_dataset($source1);
    $dataset2 = $api::create_dataset($source2);
    $multidataset = $api::create_dataset(array($dataset1, $dataset2));

Clusters can also be used to generate datasets containing the instances grouped around each centroid. 
You will need the cluster id and the centroid id to reference the dataset to be created. For instance::

    $cluster = $api::create_cluster($dataset);
    $cluster_dataset_1 = $api::create_dataset($cluster,array('centroid'=>'000000'));

would generate a new dataset containing the subset of instances in the cluster associated to the centroid id 000000.


Creating models
---------------

Once you have created a dataset you can create a model from it. 
If you don’t select one, the model will use the last field of the dataset as objective field. 
The only required argument to create a model is a dataset id. 
You can also include in the request all the additional arguments accepted by BigML and documented in `the Models section of the Developer’s documentation <https://bigml.com/developers/models>`_.

For example, to create a model only including the first two fields and the first 10 instances in the dataset, you can use the following invocation::

    $model = $api::create_model($dataset, array("name"=>"my model", "input_fields"=> array("000000", "000001"), "range"=> array(1, 10)));

the model is scheduled for creation.


Creating clusters
-----------------

If your dataset has no fields showing the objective information to predict for the training data, 
you can still build a cluster that will group similar data around some automatically chosen points (centroids). 
Again, the only required argument to create a cluster is the dataset id. 
You can also include in the request all the additional arguments accepted by BigML and documented in `the Clusters section of the Developer’s documentation <https://bigml.com/developers/clusters>`_.

Let’s create a cluster from a given dataset::

    $cluster = $api::create_cluster($dataset, array("name"=> "my cluster", "k"=> 5}));

that will create a cluster with 5 centroids.    


Creating anomaly detectors
--------------------------

If your problem is finding the anomalous data in your dataset, you can build an anomaly detector, that will use iforest to single out the anomalous records. Again, the only required argument to create an anomaly detector is the dataset id. You can also include in the request all the additional arguments accepted by BigML and documented in the `Anomaly detectors section of the Developer’s documentation <https://bigml.com/developers/anomalies>`_.

Let’s create an anomaly detector from a given dataset::

    $anomaly = $api::create_anomaly($dataset, array("name"=>"my anomaly"})

Creating predictions
--------------------

You can now use the model resource identifier together with some input parameters to ask for predictions, using the create_prediction method. 
You can also give the prediction a name::

    $prediction = $api::create_prediction($model,
                                          array("sepal length"=> 5,
                                                "sepal width" => 2.5),
                                          array("name"=>"my prediction"));

    $api::pprint($prediction);

    petal width for {"sepal length":5,"sepal width":2.5} is 0.30455

Creating centroids
------------------

To obtain the centroid associated to new input data, you can now use the create_centroid method. 
Give the method a cluster identifier and the input data to obtain the centroid. 
You can also give the centroid predicition a name::

    $centroid = $api::create_centroid($cluster,
                                      array("pregnancies"=> 0,
                                            "plasma glucose"=> 118,
                                            "blood pressure"=> 84,
                                            "triceps skin thickness"=> 47,
                                            "insulin"=> 230,
                                            "bmi"=> 45.8,
                                            "diabetes pedigree"=> 0.551,
                                            "age"=> 31,
                                            "diabetes"=> "true"),
                                      array("name"=> "my centroid"));


Creating anomaly scores
-----------------------

To obtain the anomaly score associated to new input data, you can now use the
create_anomaly_score method. Give the method an anomaly detector identifier and the input data to obtain the score::

     $anomaly_score = $api::create_anomaly_score($anomaly, 
                                                 array("src_bytes"=> 350),
                                                 array("name"=> "my score"));



Creating evaluations
--------------------

Once you have created a model, you can measure its perfomance by running a dataset of test data through it 
and comparing its predictions to the objective field real values. 
Thus, the required arguments to create an evaluation are model id and a dataset id. 
You can also include in the request all the additional arguments accepted by BigML and documented in `the Evaluations section of the Developer’s documentation <https://bigml.com/developers/evaluations>`_.

For instance, to evaluate a previously created model using at most 10000 rows from an existing dataset you can use the following call::
    
    $evaluation = $api::create_evaluation($model, 
                                          $dataset, 
                                          array("name"=>"my model", "max_rows"=>10000));

Evaluations can also check the ensembles’ performance. 
To evaluate an ensemble you can do exactly what we just did for the model case, using the ensemble object instead of the model as first argument::

    $evaluation = $api::create_evaluation($ensemble, $dataset);


Creating ensembles
------------------

To improve the performance of your predictions, you can create an ensemble of models and combine their individual predictions. 
The only required argument to create an ensemble is the dataset id::

    $ensemble = $api::create_ensemble($datasetid);

but you can also specify the number of models to be built and the parallelism level for the task::

    $args = array('number_of_models'=> 20, 'tlp'=> 3);
    $ensemble = $api::create_ensemble($datasetid, $args);


Creating batch predictions
--------------------------

We have shown how to create predictions individually, but when the amount of predictions to make increases, this procedure is far from optimal. 
In this case, the more efficient way of predicting remotely is to create a dataset containing the input data you want your model to predict 
from and to give its id and the one of the model to the create_batch_prediction api call::

    $batch_prediction = $api::$create_batch_prediction($model, 
                                                       $dataset, 
                                                       array("name"=>"my batch prediction", 
                                                             "all_fields"=> true,
                                                             "header": true,
                                                             "confidence": true));


In this example, setting all_fields to true causes the input data to be included in the prediction output, header controls whether a headers line 
is included in the file or not and confidence set to true causes the confidence of the prediction to be appended. 
If none of these arguments is given, the resulting file will contain the name of the objective field as a header row followed by the predictions.

As for the rest of resources, the create method will return an incomplete object, that can be updated by issuing the corresponding 
$api::get_batch_prediction call until it reaches a FINISHED status. 
Then you can download the created predictions file using::

   $api::download_batch_prediction('batchprediction/526fc344035d071ea3031d70',
                                   'my_dir/my_predictions.csv'); 


Creating batch centroids
------------------------

As described in the previous section, it is also possible to make centroids’ predictions in batch. 
First you create a dataset containing the input data you want your cluster to relate to a centroid. 
The create_batch_centroid call will need the id of the dataset and the cluster to assign a centroid to each input data::

    $batch_centroid = $api::create_batch_centroid($cluster, 
                                                  $dataset, 
                                                  array("name"=>"my batch centroid", 
                                                        "all_fields"=> true,
                                                        "header"=> true));


Creating batch anomaly scores
-----------------------------

Input data can also be assigned an anomaly score in batch. You train an anomaly detector with your training data and then build a dataset from your input data. The create_batch_anomaly_score call will need the id of the dataset and of the anomaly detector to assign an anomaly score to each input data instance::

   $batch_anomaly_score = $api::create_batch_anomaly_score($anomaly, 
                                                           $dataset, 
                                                           array("name" => "my batch anomaly score"
                                                                 "all_fields" => true,
                                                                 "header" => true))

Listing Resources
-----------------

You can list resources with the appropriate api method::

    $api::list_sources()
    $api::list_datasets()
    $api::list_models()
    $api::list_predictions()
    $api::list_evaluations()
    $api::list_ensembles()
    $api::list_batch_predictions()
    $api::list_clusters()
    $api::list_centroids()
    $api::list_batch_centroids()
    $api::list_anomalies()
    $api::list_anomaly_scores()
    $api::list_batch_anomaly_scores()

you will receive a dictionary with the following keys:

-  **code**: If the request is successful you will get a bigml.api.HTTP_OK (200) status code. Otherwise, it will be one of the standard HTTP error codes. See BigML documentation on status codes for more info.
-  **meta**: A dictionary including the following keys that can help you paginate listings:
-  **previous**: Path to get the previous page or None if there is no previous page.
-  **next**: Path to get the next page or None if there is no next page.
-  **offset**: How far off from the first entry in the resources is the first one listed in the resources key.
-  **limit**: Maximum number of resources that you will get listed in the resources key.
-  **total_count**: The total number of resources in BigML.
-  **objects**: A list of resources as returned by BigML.
-  **error**: If an error occurs and the resource cannot be created, it will contain an additional code and a description of the error. In this case, meta, and resources will be None.

Filtering Resources
-------------------

You can filter resources in listings using the syntax and fields labeled as filterable in the `BigML documentation <https://bigml.com/developers>`_. for each resource.

A few examples:

- Ids of the first 5 sources created before April 1st, 2012::

    $api::list_sources("limit=5;created__lt=2012-04-1");

- Name of the first 10 datasets bigger than 1MB::

    $api::list_datasets("limit=10;size__gt=1048576");

- Name of models with more than 5 fields (columns)::

    $api::list_models("columns__gt=5");

- Ids of predictions whose model has not been deleted::
 
    $api::list_predictions("model_status=true");

Ordering Resources
------------------

You can order resources in listings using the syntax and fields labeled as sortable in the `BigML documentation <https://bigml.com/developers>`_. for each resource.

A few examples:

- Name of sources ordered by size::
    
     $api::list_sources("order_by=size");

- Number of instances in datasets created before April 1st, 2012 ordered by size::

     $api::list_datasets("created__lt=2012-04-1;order_by=size");

- Model ids ordered by number of predictions (in descending order)::
  
     $api::list_models("order_by=-number_of_predictions");

- Name of predictions ordered by name::
 
     $api::list_predictions("order_by=name");

Updating Resources
------------------

When you update a resource, it is returned in a dictionary exactly like the one you get when you create a new one. 
However the status code will be bigml.api.HTTP_ACCEPTED if the resource can be updated without problems or one of the HTTP standard error codes otherwise::

    $api::update_source($source, array("name"=> "new name"));
    $api::update_dataset($dataset, array("name"=> "new name"));
    $api::update_model($model, array("name"=> "new name"));
    $api::update_prediction($prediction, array("name"=> "new name"));
    $api::update_evaluation($evaluation, array("name"=> "new name"));
    $api::update_ensemble($ensemble, array("name"=> "new name"));
    $api::update_batch_prediction($batch_prediction, array("name"=> "new name"));
    $api::update_cluster($cluster, array("name"=> "new name"));
    $api::update_centroid($centroid, array("name"=> "new name"));
    $api::update_batch_centroid($batch_centroid, array("name"=> "new name"));
    $api::update_anomaly($anomaly, array("name"=> "new name"));
    $api::update_anomaly_score($anomaly_score, array("name": "new name"));
    $api::update_batch_anomaly_score($batch_anomaly_score, array("name": "new name"));



Updates can change resource general properties, such as the name or description attributes of a dataset, or specific properties. 
As an example, let’s say that your source has a certain field whose contents are numeric integers. 
BigML will assign a numeric type to the field, but you might want it to be used as a categorical field. You could change its type to categorical by calling::

    $api::update_source($source, array("fields"=> array("000001"=> array("optype"=> "categorical"))));

where 000001 is the field id that corresponds to the updated field. 
You will find detailed information about the updatable attributes of each resource in `BigML developer’s documentation <https://bigml.com/developers>`_.

Deleting Resources
------------------
Resources can be deleted individually using the corresponding method for each type of resource::

    $api::delete_source($source);
    $api::delete_dataset($dataset);
    $api::delete_model($model);
    $api::delete_prediction($prediction);
    $api::delete_evaluation($evaluation);
    $api::delete_ensemble($ensemble);
    $api::delete_batch_prediction($batch_prediction);
    $api::delete_cluster($cluster);
    $api::delete_centroid($centroid);
    $api::delete_batch_centroid($batch_centroid);
    $api::delete_anomaly(anomaly);
    $api::delete_anomaly_score(anomaly_score);
    $api::delete_batch_anomaly_score(batch_anomaly_score);

Each of the calls above will return a dictionary with the following keys:

code If the request is successful, the code will be a bigml.api.HTTP_NO_CONTENT (204) status code. 
Otherwise, it wil be one of the standard HTTP error codes. See the documentation on status codes for more info.
error If the request does not succeed, it will contain a dictionary with an error code and a message. It will be None otherwise.

Local Models
------------
You can instantiate a local version of a remote model::

    include 'model.php';

This will retrieve the remote model information, using an implicitly built BigML() connection object 
(see the Authentication section for more details on how to set your credentials) and return a Model object that you can use to make local predictions. 
If you want to use a specfic connection object for the remote retrieval, you can set it as second parameter::

    $api = new BigML("username", "api_key", false, 'storage');

    $model = api::get_model('model/538XXXXXXXXXXXXXXXXXXX2');
    $local_model = new Model(model);
   
or::
 
    $local_model = new Model("model/538XXXXXXXXXXXXXXXXXXX2");
    $local_model = new Model("model/538XXXXXXXXXXXXXXXXXXX2", $api);

Any of these methods will return a Model object that you can use to make local predictions.

For set default storage::

    $local_model = new Model("model/538XXXXXXXXXXXXXXXXXXX2", null, 'storagedirectory');

Local Predictions
-----------------

Once you have a local model you can use to generate predictions locally::

    $prediction = $local_model->predict(array("petal length"=> 3, "petal width"=> 1));

Local predictions have three clear advantages:
    
- Removing the dependency from BigML to make new predictions.

- No cost (i.e., you do not spend BigML credits).

- Extremely low latency to generate predictions for huge volumes of data.

Local Clusters
--------------

You can also instantiate a local version of a remote cluster::

    include 'cluster.php';

    $cluster = $api::get_cluster("cluster/539xxxxxxxxxxxxxxxxxxxx18");
    $local_cluster = new Cluster($cluster);

or::

    $local_cluster = new Cluster("cluster/539xxxxxxxxxxxxxxxxxxxx18");

This will retrieve the remote cluster information, using an implicitly built BigML() connection object 
(see the Authentication section for more details on how to set your credentials) and return a Cluster object that you can use to make local centroid predictions. 
If you want to use a specfic connection object for the remote retrieval, you can set it as second parameter::

    $local_cluster = new Cluster("cluster/539xxxxxxxxxxxxxxxxxxxx18", $api);

For set default storage if you have storage unset in api::

    $local_cluster = new Cluster("cluster/539xxxxxxxxxxxxxxxxxxxx18", null, storagedirectory);


Local Centroids
---------------

Using the local cluster object, you can predict the centroid associated to an input data set::

    $local_cluster->centroid(array("pregnancies"=> 0,
                                   "plasma glucose"=> 118,
                                   "blood pressure"=> 84,
                                   "triceps skin thickness"=> 47,
                                   "insulin"=> 230,
                                   "bmi"=> 45.8,
                                   "diabetes pedigree"=> 0.551,
                                   "age"=> 31,
                                   "diabetes"=> "true"));

    array('distance'=> 0.454110207355, 'centroid_name'=> 'Cluster 4', 'centroid_id'=> '000004');

You must keep in mind, though, that to obtain a centroid prediction, input data must have values for all the numeric fields. No missing values for the numeric fields are allowed.
As in the local model predictions, producing local centroids can be done independently of BigML servers, so no cost or connection latencies are involved.

Local Anomaly Detector
----------------------

You can also instantiate a local version of a remote anomaly.::

    $local_anomaly = new Anomaly('anomaly/502fcbff15526876610002435');

This will retrieve the remote anomaly detector information, using an implicitly built BigML() connection object (see the Authentication section for more details on how to set your credentials) and return an Anomaly object that you can use to make local anomaly scores. If you want to use a specfic connection object for the remote retrieval, you can set it as second parameter::

    $api = new BigML("username", "password");
    $local_anomaly = new Anomaly('anomaly/502fcbff15526876610002435',
                                 $api);

or even use the remote anomaly information retrieved previously to build the local anomaly detector object::

    $api = new BigML()
    $anomaly = $api::get_anomaly('anomaly/502fcbff15526876610002435',
                                 'limit=-1')

Note that in this example we used a limit=-1 query string for the anomaly retrieval. This ensures that all fields are retrieved by the get method in the same call (unlike in the standard calls where the number of fields returned is limited).

Local Anomaly Scores
--------------------

Using the local anomaly detector object, you can predict the anomaly score associated to an input data set::

    $local_anomaly->anomaly_score(array("src_bytes"=> 350))
    0.9268527808726705

As in the local model predictions, producing local anomaly scores can be done independently of BigML servers, so no cost or connection latencies are involved.

Multi Models
------------

Multi Models use a numbers of BigML remote models to build a local version that can be used to generate predictions locally. Predictions are generated combining the outputs of each model::

    include 'multimodel.php';

    $multimodel = new MultiModel(array("model/5111xxxxxxxxxxxxxxxxxx12",model/538Xxxxxxxxxxxxxxxxxxx32"));

or::

    $multimodel = new MultiModel(array("model/5111xxxxxxxxxxxxxxxxxx12",model/538Xxxxxxxxxxxxxxxxxxx32"), $api);

or set default storage if you have storage unset in api::

    $multimodel = new MultiModel(array("model/5111xxxxxxxxxxxxxxxxxx12",model/538Xxxxxxxxxxxxxxxxxxx32"), null, $storage);


    $prediction = $multimodel->predict(array("petal length"=> 3, "petal width"=> 1));

The combination method used by default is plurality for categorical predictions and mean value for numerical ones. You can also use:

confidence weighted::
    
    $prediction = $multimodel->predict(array("petal length"=> 3, "petal width"=> 1), 1);  
        
that will weight each vote using the confidence/error given by the model to each prediction, or even probability weighted::

    $prediction = $multimodel->predict(array("petal length"=> 3, "petal width"=> 1), 2); 

that weights each vote by using the probability associated to the training distribution at the prediction node.


There’s also a threshold method that uses an additional set of options: threshold and category. 
The category is predicted if and only if the number of predictions for that category is at least the threshold value. 
Otherwise, the prediction is plurality for the rest of predicted values.

An example of threshold combination method would be::

    $prediction = $multimodel->predict(array("petal length"=> 3, "petal width"=> 1), 3, false, array('threshold'=> 3, 'category'=> 'Iris-virginica'));    

When making predictions on a test set with a large number of models, batch_predict can be useful to log each model’s predictions in a separated file. 
It expects a list of input data values and the directory path to save the prediction files in::
     
    $multimodel->batch_predict(array("petal length"=> 3, "petal width"=> 1, "petal length"=> 1, "petal width"=> 5.1), "data/predictions");

The predictions generated for each model will be stored in an output file in data/predictions using the syntax model_[id of the model]__predictions.csv. 
For instance, when using model/50c0de043b563519830001c2 to predict, the output file name will be model_50c0de043b563519830001c2__predictions.csv. 
An additional feature is that using reuse=True as argument will force the function to skip the creation of the file if it already exists. 
This can be helpful when using repeatedly a bunch of models on the same test set::

    $multimodel->batch_predict(array("petal length"=> 3, "petal width"=> 1, "petal length"=> 1, "petal width"=> 5.1), "data/predictions", true, true);


Prediction files can be subsequently retrieved and converted into a votes list using batch_votes::

    $multimodel.batch_votes("data/predictions");

which will return a list of MultiVote objects. Each MultiVote contains a list of predictions.

These votes can be further combined to issue a final prediction for each input data element using the method combine::

   foreach($multimodel->batch_votes("data/predictions") as $multivote) {
      $prediction = $multivote->combine();
   }

Again, the default method of combination is plurality for categorical predictions and mean value for numerical ones. You can also use confidence weighted::

    $prediction = $multivote->combine(1);

or probability weighted::

    $prediction = $multivote->combine(2);

You can also get a confidence measure for the combined prediction::

    $prediction = $multivote->combine(0, true);

For classification, the confidence associated to the combined prediction is derived by first selecting the model’s predictions 
that voted for the resulting prediction and computing the weighted average of their individual confidence. 
Nevertheless, when probability weighted is used, the confidence is obtained by using each model’s distribution at the prediction 
node to build a probability distribution and combining them. The confidence is then computed as the wilson score interval of the combined distribution 
(using as total number of instances the sum of all the model’s distributions original instances at the prediction node)

In regression, all the models predictions’ confidences contribute to the weighted average confidence.


Ensembles
---------

Remote ensembles can also be used locally through the Ensemble class. The simplest way to access an existing ensemble and using it to predict locally is::

    include 'ensemble.php';

    $ensemble = new Ensemble("ensemble/53dxxxxxxxxxxxxxxxxxxafa");

    $ensemble->predict(array("petal length"=>3, "petal width"=> 1));

This call will download all the ensemble related info and store it in a ./storage directory ready to be used to predict. 
As in MultipleModel, several prediction combination methods are available, and you can choose another storage directory or even avoid storing at all, for instance::

    $api = new BigML("username", "password", false, "storagedirectory");

    ensemble = $api::create_ensemble('dataset/5143a51a37203f2cf7000972');

    $ensemble = new Ensemble($ensemble, $api); 

    $ensemble->predict(array("petal length"=>3, "petal width"=> 1), true, 1);


creates a new ensemble and stores its information in ./storagedirectory folder. Then this information is used to predict locally using the confidence weighted method.
   

Similarly, local ensembles can also be created by giving a list of models to be combined to issue the final prediction::

    $ensemble = new Ensemble(array('model/50c0de043b563519830001c2','model/50c0de043b5635198300031b'));

    $ensemble->predict(array("petal length": 3, "petal width": 1));

Rule Generation
---------------
You can also use a local model to generate a IF-THEN rule set that can be very helpful to understand how the model works internally::

    $local_model->rules();
    
    IF petal_length > 2.45 AND
       IF petal_width > 1.65 AND
          IF petal_length > 5.05 THEN
             species = Iris-virginica
          IF petal_length <= 5.05 AND
             IF sepal_width > 2.9 AND
                IF sepal_length > 5.95 AND
                   IF petal_length > 4.95 THEN
                      species = Iris-versicolor
                   IF petal_length <= 4.95 THEN
                      species = Iris-virginica
                IF sepal_length <= 5.95 THEN
                   species = Iris-versicolor
             IF sepal_width <= 2.9 THEN
                species = Iris-virginica
       IF petal_width <= 1.65 AND
          IF petal_length > 4.95 AND
             IF sepal_length > 6.05 THEN
                species = Iris-virginica
             IF sepal_length <= 6.05 AND
                IF sepal_width > 2.45 THEN
                  species = Iris-versicolor
                IF sepal_width <= 2.45 THEN
                  species = Iris-virginica
          IF petal_length <= 4.95 THEN
             species = Iris-versicolor
    IF petal_length <= 2.45 THEN
       species = Iris-setosa

Testing
-------
To run the test you need phpunit that you can download from here `http://phpunit.de/#download <http://phpunit.de/#download>`_.

Make sure that you have set up your authentication variables in your environment.

To run all tests::

     cd tests
     configtests.xml
     phpunit.phar --configuration configtests.xml


To Run a specific test::

     phpunit.phar test_01_prediction.php



