BigML PHP Bindings v. 2.0
=========================

**Notice: the BigML PHP bindings 2.0 may break existing code. In
particular, all static methods have been removed from the BigML
class. So, if you ever used the syntaxis, e.g.:**

.. code-block:: php

    BigML::create_source(...);

**you will get an error. On the other hand, if you followed the syntaxis
which was documented in this README, i.e.,**

.. code-block:: php

    $api->create_source(...)

**you will be fine.**

**Additionally, notice that the old constructor which accepted all of
its parameters as individual arguments has been deprecated in favour
of a new one supporting the specification of named parameters. Use the
new syntaxis instead, as described below. The old constructor syntaxis
will be maintained until version 3.0, then removed.**

In this repository you'll find an open source PHP library that gives
you a simple way to interact with `BigML <https://bigml.com>`_.

This module is licensed under the `Apache License, Version
2.0 <http://www.apache.org/licenses/LICENSE-2.0.html>`_.**

.. contents:: Table of Contents

Requirements
------------

PHP 5.3.2 or higher are currently supported by these bindings.

You will also need to have the non-default extensions `mbstring
<http://php.net/manual/en/book.mbstring.php>`_, `cURL
<http://php.net/manual/en/book.curl.php>`_, and `OpenSSL
<http://php.net/manual/en/book.openssl.php>`_ installed. Depending on
how you installed PHP, you may already have one or more of these
extensions.

To check which modules you have currently installed, run

.. code-block:: bash

  php -m

To install with Linux:

At the command line, run

.. code-block:: bash

  sudo apt-get install phpXY-mbstring
  sudo apt-get install phpXY-curl

where XY is the PHP version currently installed on your system (e.g.,
php72-curl).

To install with MacOS:

At the command line, run

.. code-block:: bash

  sudo port install phpXY-mbstring
  sudo port install phpXY-curl
  sudo port install phpXY-openssl

where XY is the PHP version currently installed on your system (e.g.,
php72-curl).

If you installed PHP by tapping homebrew-php, mbstring should already
be installed. You will still need to install curl and openssl using

.. code-block:: bash

  brew install --with-openssl curl

To install with Windows:

If you have access to the php.ini, remove the semicolon in front of
these lines in the php.ini

.. code-block:: bash

  extension = php_mbstring.dll
  extension = php_curl.dll
  extension = php_openssl.dll

You will have to be sure you have these dll files, and they are
available on your PATH. You may also need to check that `libeay32.dll`
and `ssleay32.dll` are in your php directory.

Once you have made the changes, don't forget to restart your server
for them to take effect.

Importing the module
--------------------

Using Composer
""""""""""""""

If you are currently using Composer to manage your project's
libraries, simply add the following to your current `composer.json`

.. code-block:: json

    {
        "repositories": [
            {
                "type": "vcs",
                "url": "https://github.com/bigmlcom/bigml-php/"
            }
        ],
        "require": {
            "bigml/bigml-php": "dev-master",
            "wamania/php-stemmer": "@dev"
        },
        "autoload":{
            "classmap": ["vendor/bigml/bigml-php/bigml/"]
        }
    }

At the command line, run the command

.. code-block:: bash

    php composer.phar install

This will install this library and all required library dependencies
(but not extensions such as mbstring).

In your code:

At the beginning of your file include the line

.. code-block:: php

    <? php
    require 'vendor/autoload.php';

Cloning from GitHub
"""""""""""""""""""

If you would prefer, you can manually clone this repo from GitHub. You
will still need to use Composer to install some third-party libraries.

If you haven't already done so, you will need to install `Composer
<https://getcomposer.org/>`_.

Linux/OSX:

Follow the instructions in the `download section <https://getcomposer.org/download/>`_ to get the
`composer.phar` file, and run

.. code-block:: bash

  php composer.phar install

This will install all necessary dependencies.

Windows:

Follow the instructions on the Composer website for `downloading <https://getcomposer.org/doc/00-intro.md#installation-windows>`_ Composer, and run

.. code-block:: bash

  php composer.phar install

This will install all necessary dependencies.

In your code:

At the beginning of your file you will need to include the various
files you will be using. If you will be making any remote calls, you
will need bigml.php. If you will be making any local models, you will
need their specific files. The most common files to include are


.. code-block:: php

  <?php
  include('bigml.php');
  include('anomaly.php');
  include('association.php');
  include('boostedensemble.php');
  include('cluster.php');
  include('ensemble.php');
  include('logistic.php');
  include('model.php');
  include('prediction.php');
  include('topicmodel.php');

Authentication
--------------

All the requests to BigML.io must be authenticated using your username
and `API key <https://bigml.com/account/apikey>`_. and are always
transmitted over HTTPS.

This module will look for your username and API key in the environment
variables BIGML_USERNAME and BIGML_API_KEY respectively.  You can add
the following lines to your .bashrc or .bash_profile to set those
variables automatically when you log in


.. code-block:: bash

    export BIGML_USERNAME=myusername
    export BIGML_API_KEY=a11e579e7e53fb9abd646a6ff8aa99d4afe83ac2

With that environment and your aliases set up, connecting to BigML is
a breeze

.. code-block:: php

   $api = new BigML\BigML();

Otherwise, you can initialize directly when instantiating the BigML
class as follows by manually supplying your credentials:

.. code-block:: php

   $api = new BigML\BigML([ "username" => "myusername",
                            "apiKey" => "my_api_key"]);

Caching
-------

An important feature provided by the api constructor is the
specification of a local cache to speed up the retrieval of
resources. If you supply a storage for your BigML instance, the PHP
bindings will hit the network only once for each resource. On
subsequent accesses, the resource will be retrieved from the local
cache.

This is how you can set the storage argument when you instantiate the
BigML class:

.. code-block:: php

   $api = new BigML\BigML([ "username" => "myusername",
                            "apiKey" => "my_api_key",
                            "storage" => "storage/data"]);

Or, more succinctly:

.. code-block:: php

   $api = new BigML\BigML(["storage" => "storage/data"]);

if you have your environment set.

All resources will be created, updated, or retrieved in/from the chosen directory.

Virtual Private Clouds
----------------------

For Virtual Private Cloud setups, you can change the remote server domain:

.. code-block:: php

   $api = new BigML\BigML([ "username" => "myusername",
                            "apiKey" => "my_api_key",
                            "domain" => "my_VPC.bigml.io",
                            "storage" => "storage/data"]);

NOTICE: BigML API used to provide a sandbox mode, also know as
development mode. This has been deprecated and is not supported in the
PHP binding anymore. To guarantee backward-compatibility, the BigML
class constructor still supports the specification of a ``dev_mode``
argument, but it is now ignored.


Projects and Organizations
--------------------------

When you instantiate the BigML class you can specify a project or
organization that the instance shall default to:

.. code-block:: php

   $api = new BigML\BigML(["username" => "myusername",
                            "apiKey" => "my_api_key",
                            "project" => $projectID]);

   $api = new BigML\BigML(["username" => "myusername",
                            "apiKey" => "my_api_key",
                            "organization" => $organization]);


When $project is set to a project ID and that project exists for an
organization, the user is considered to be working in an organization
project. The scope of the API requests will be limited to this project
and permissions should be previously given by the organization
administrator.

If the specified project does not belong to an organization but is a
project of the user's, then the scope of all API requests will be
limited to that project.

When $organization is set to an organization ID, the user is considered
to be working for an organization. The scope of the API requests will
be limited to the projects of the organization and permissions need to
be previously given by the organization administrator.


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

.. code-block:: php

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

If your credentials are stored in the environment as mentioned above,
you can easily generate a prediction following these steps

.. code-block:: php

    $api = new BigML\BigML();

    $source = $api->create_source('./tests/data/iris.csv');
    $dataset = $api->create_dataset($source);
    $model = $api->create_model($dataset);
    $prediction = $api->create_prediction($model, array('sepal length'=> 5, 'sepal width'=> 2.5));

then:

.. code-block:: php

    $objective_field_name = $prediction->object->fields->{$prediction->object->objective_fields[0]}->name;

    "petal width"

    $value = $prediction->object->prediction->{$prediction->object->objective_fields[0]};

    0.30455

    $api->pprint($prediction);

    petal width for {"sepal length":5,"sepal width":2.5} is 0.30455

also, you can generate an evaluation for the model by using

.. code-block:: php

    $test_source = $api->create_source('./tests/data/iris.csv');
    $test_dataset = $api->create_dataset($test_source);
    $evaluation = $api->create_evaluation($model, $test_dataset);


Dataset
-------

If you want to get some basic statistics for each field you can retrieve
the fields from the dataset as follows to get a dictionary keyed by field id

.. code-block:: php

    $dataset = $api->get_dataset($dataset);
    print_r($api->get_fields($dataset))

The field filtering options are also available using a query string expression, for instance

.. code-block:: php

    $dataset = $api->get_dataset($dataset, "limit=20")

limits the number of fields that will be included in dataset to 20.

Model
-----

One of the greatest things about BigML is that the models that it generates for you are fully white-boxed.
To get the explicit tree-like predictive model for the example above

.. code-block:: php

    $model = $api->get_model($model_id);

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

Again, filtering options are also available using a query string expression, for instance

.. code-block:: php

    $model = $api->get_model($model_id, "limit=5");

limits the number of fields that will be included in model to 5.


Evaluation
----------

The predictive performance of a model can be measured using many different measures.
In BigML these measures can be obtained by creating evaluations.
To create an evaluation you need the id of the model you are evaluating and the id of
the dataset that contains the data to be tested with. The result is shown as

.. code-block:: php

    $evaluation = $api->get_evaluation($evaluation_id);

Cluster
-------

For unsupervised learning problems, the cluster is used to classify in a limited number of groups your training data.
The cluster structure is defined by the centers of each group of data, named centroids, and the data enclosed in the group.
As for in the model’s case, the cluster is a white-box resource and can be retrieved as a JSON

.. code-block:: php

    $cluster = $api->get_cluster($cluster_id)

Anomaly detector
----------------

For anomaly detection problems, BigML anomaly detector uses iforest as an unsupervised kind of model that detects anomalous data in a dataset. The information it returns encloses a top_anomalies block that contains a list of the most anomalous points. For each, we capture a score from 0 to 1. The closer to 1, the more anomalous. We also capture the row which gives values for each field in the order defined by input_fields. Similarly we give a list of importances which match the row values. These importances tell us which values contributed most to the anomaly score. Thus, the structure of an anomaly detector is similar to

.. code-block:: json

    {"category": 0,
    "code": 200,
    "columns": 14,
    "constraints": false,
    "created": "2014-09-08T18:51:11.893000",
    "credits": 0.11653518676757812,
    "credits_per_prediction": 0.0,
    "dataset": "dataset/540dfa9d9841fa5c88000765",
    "dataset_field_types": {   "categorical": 21,
                               "datetime": 0,
                               "numeric": 21,
                               "preferred": 14,
                               "text": 0,
                               "total": 42},
    "dataset_status": true,
    "dataset_type": 0,
    "description": "",
    "excluded_fields": [],
    "fields_meta": {   "count": 14,
                       "limit": 1000,
                       "offset": 0,
                       "query_total": 14,
                       "total": 14},
    "forest_size": 128,
    "input_fields": [   "000004",
                        "000005",
                        "000009",
                        "000016",
                        "000017",
                        "000018",
                        "000019",
                        "00001e",
                        "00001f",
                        "000020",
                        "000023",
                        "000024",
                        "000025",
                        "000026"],
    "locale": "en_US",
    "max_columns": 42,
    "max_rows": 200,
    "model": {   "fields": {   "000004": {   "column_number": 4,
                                             "datatype": "int16",
                                             "name": "src_bytes",
                                             "optype": "numeric",
                                             "order": 0,
                                             "preferred": true,
                                             "summary": {   "bins": [   [   143,
                                                                            2],
                                                                        ...
                                                                        [   370,
                                                                            2]],
                                                            "maximum": 370,
                                                            "mean": 248.235,
                                                            "median": 234.57157,
                                                            "minimum": 141,
                                                            "missing_count": 0,
                                                            "population": 200,
                                                            "splits": [   159.92462,
                                                                          173.73312,
                                                                          188,
                                                                          ...
                                                                          339.55228],
                                                            "standard_deviation": 49.39869,
                                                            "sum": 49647,
                                                            "sum_squares": 12809729,
                                                            "variance": 2440.23093}},
                               "000005": {   "column_number": 5,
                                             "datatype": "int32",
                                             "name": "dst_bytes",
                                             "optype": "numeric",
                                             "order": 1,
                                             "preferred": true,
                                              ...
                                                            "sum": 1030851,
                                                            "sum_squares": 22764504759,
                                                            "variance": 87694652.45224}},
                               "000009": {   "column_number": 9,
                                             "datatype": "string",
                                             "name": "hot",
                                             "optype": "categorical",
                                             "order": 2,
                                             "preferred": true,
                                             "summary": {   "categories": [   [   "0",
                                                                                  199],
                                                                              [   "1",
                                                                                  1]],
                                                            "missing_count": 0},
                                             "term_analysis": {   "enabled": true}},
                               "000016": {   "column_number": 22,
                                             "datatype": "int8",
                                             "name": "count",
                                             "optype": "numeric",
                                             "order": 3,
                                             "preferred": true,
                                                            ...
                                                            "population": 200,
                                                            "standard_deviation": 5.42421,
                                                            "sum": 1351,
                                                            "sum_squares": 14981,
                                                            "variance": 29.42209}},
                               "000017": { ... }}},
                 "kind": "iforest",
                 "mean_depth": 12.314174107142858,
                 "top_anomalies": [   {   "importance": [   0.06768,
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
                                          "row": [   183.0,
                                                     8654.0,
                                                     "0",
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
                                          "score": 0.68782},
                                      {   "importance": [   0.05645,
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
                                          "row": [   212.0,
                                                     1940.0,
                                                     "0",
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
                                          "score": 0.6239},
                                          ...],
                 "trees": [   {   "root": {   "children": [   {   "children": [   {   "children": [   {   "children": [   {   "children":[   {   "population": 1,
                                                                                                                              "predicates": [   {   "field": "00001f",
                                                                                                                                                    "op": ">",
                                                                                                                                                    "value": 35.54357}]},

                                                                                                                          {   "population": 1,
                                                                                                                              "predicates": [   {   "field": "00001f",
                                                                                                                                                    "op": "<=",
                                                                                                                                                    "value": 35.54357}]}],
                                                                                                          "population": 2,
                                                                                                          "predicates": [   {   "field": "000005",
                                                                                                                                "op": "<=",
                                                                                                                                "value": 1385.5166}]}],
                                                                                      "population": 3,
                                                                                      "predicates": [   {   "field": "000020",
                                                                                                            "op": "<=",
                                                                                                            "value": 65.14308},
                                                                                                        {   "field": "000019",
                                                                                                            "op": "=",
                                                                                                            "value": 0}]}],
                                                                  "population": 105,
                                                                  "predicates": [   {   "field": "000017",
                                                                                        "op": "<=",
                                                                                        "value": 13.21754},
                                                                                    {   "field": "000009",
                                                                                        "op": "in",
                                                                                        "value": [   "0"]}]}],
                                              "population": 126,
                                              "predicates": [   true,
                                                                {   "field": "000018",
                                                                    "op": "=",
                                                                    "value": 0}]},
                                  "training_mean_depth": 11.071428571428571}]},
    "name": "tiny_kdd's dataset anomaly detector",
    "number_of_batchscores": 0,
    "number_of_public_predictions": 0,
    "number_of_scores": 0,
    "out_of_bag": false,
    "price": 0.0,
    "private": true,
    "project": null,
    "range": [1, 200],
    "replacement": false,
    "resource": "anomaly/540dfa9f9841fa5c8800076a",
    "rows": 200,
    "sample_rate": 1.0,
    "sample_size": 126,
    "seed": "BigML",
    "shared": false,
    "size": 30549,
    "source": "source/540dfa979841fa5c7f000363",
    "source_status": true,
    "status": {   "code": 5,
                  "elapsed": 32397,
                  "message": "The anomaly detector has been created",
                  "progress": 1.0},
    "subscription": false,
    "tags": [],
    "updated": "2014-09-08T23:54:28.647000",
    "white_box": false}



Samples
-------

To provide quick access to your row data you can create a ``sample``. Samples
are in-memory objects that can be queried for subsets of data by limiting
their size, the fields or the rows returned. The structure of a sample would
be::

Samples are not permanent objects. Once they are created, they will be
available as long as GETs are requested within periods smaller than
a pre-established TTL (Time to Live). The expiration timer of a sample is
reset every time a new GET is received.

If requested, a sample can also perform linear regression and compute
Pearson's and Spearman's correlations for either one numeric field
against all other numeric fields or between two specific numeric fields.

Correlations
------------

A ``correlation`` resource contains a series of computations that reflect the
degree of dependence between the field set as objective for your predictions
and the rest of fields in your dataset. The dependence degree is obtained by
comparing the distributions in every objective and non-objective field pair,
as independent fields should have probabilistic
independent distributions. Depending on the types of the fields to compare,
the metrics used to compute the correlation degree will be:

- for numeric to numeric pairs:
  `Pearson's <https://en.wikipedia.org/wiki/Pearson_product-moment_correlation_coefficient>`_
  and `Spearman's correlation <https://en.wikipedia.org/wiki/Spearman%27s_rank_correlation_coefficient>`_
  coefficients.
- for numeric to categorical pairs:
  `One-way Analysis of Variance <https://en.wikipedia.org/wiki/One-way_analysis_of_variance>`_, with the
  categorical field as the predictor variable.
- for categorical to categorical pairs:
  `contingency table (or two-way table) <https://en.wikipedia.org/wiki/Contingency_table>`_,
  `Chi-square test of independence <https://en.wikipedia.org/wiki/Pearson%27s_chi-squared_test>`_
  , and `Cramer's V <https://en.wikipedia.org/wiki/Cram%C3%A9r%27s_V>`_
  and `Tschuprow's T <https://en.wikipedia.org/wiki/Tschuprow%27s_T>`_ coefficients.

An example of the correlation resource JSON structure is

.. code-block:: json

    {"category": 0,
    "clones": 0,
    "code": 200,
    "columns": 5,
    "correlations": {   "correlations": [   {   "name": "one_way_anova",
                                                  "result": {   "000000": {   "eta_square": 0.61871,
                                                                                "f_ratio": 119.2645,
                                                                                "p_value": 0,
                                                                                "significant": [   true,
                                                                                                    true,
                                                                                                    true]},
                                                                 "000001": {   "eta_square": 0.40078,
                                                                                "f_ratio": 49.16004,
                                                                                "p_value": 0,
                                                                                "significant": [   true,
                                                                                                    true,
                                                                                                    true]},
                                                                 "000002": {   "eta_square": 0.94137,
                                                                                "f_ratio": 1180.16118,
                                                                                "p_value": 0,
                                                                                "significant": [   true,
                                                                                                    true,
                                                                                                    true]},
                                                                 "000003": {   "eta_square": 0.92888,
                                                                                "f_ratio": 960.00715,
                                                                                "p_value": 0,
                                                                                "significant": [   true,
                                                                                                    true,
                                                                                                    true]}}}],
                         "fields": {   "000000": {   "column_number": 0,
                                                       "datatype": "double",
                                                       "idx": 0,
                                                       "name": "sepal length",
                                                       "optype": "numeric",
                                                       "order": 0,
                                                       "preferred": true,
                                                       "summary": {   "bins": [   [   4.3,
                                                                                        1],
                                                                                    [   4.425,
                                                                                        4],
                                                                                      ...
                                                                                    [   7.9,
                                                                                        1]],
                                                                       "kurtosis": -0.57357,
                                                                       "maximum": 7.9,
                                                                       "mean": 5.84333,
                                                                       "median": 5.8,
                                                                       "minimum": 4.3,
                                                                       "missing_count": 0,
                                                                       "population": 150,
                                                                       "skewness": 0.31175,
                                                                       "splits": [   4.51526,
                                                                                      4.67252,
                                                                                      4.81113,
                                                                                      4.89582,
                                                                                      4.96139,
                                                                                      5.01131,
                                                                                      ...
                                                                                      6.92597,
                                                                                      7.20423,
                                                                                      7.64746],
                                                                       "standard_deviation": 0.82807,
                                                                       "sum": 876.5,
                                                                       "sum_squares": 5223.85,
                                                                       "variance": 0.68569}},
                                        "000001": {   "column_number": 1,
                                                       "datatype": "double",
                                                       "idx": 1,
                                                       "name": "sepal width",
                                                       "optype": "numeric",
                                                       "order": 1,
                                                       "preferred": true,
                                                       "summary": {   "counts": [   [   2,
                                                                                          1],
                                                                                      [   2.2,
                                                                                      ...
                                                                   ]]}},
                                        "000004": {   "column_number": 4,
                                                       "datatype": "string",
                                                       "idx": 4,
                                                       "name": "species",
                                                       "optype": "categorical",
                                                       "order": 4,
                                                       "preferred": true,
                                                       "summary": {   "categories": [   [   "Iris-setosa",
                                                                                              50],
                                                                                          [   "Iris-versicolor",
                                                                                              50],
                                                                                          [   "Iris-virginica",
                                                                                              50]],
                                                                       "missing_count": 0},
                                                       "term_analysis": {   "enabled": true}}},
                         "significance_levels": [0.01, 0.05, 0.1]},
    "created": "2015-07-28T18:07:37.010000",
    "credits": 0.017581939697265625,
    "dataset": "dataset/55b7a6749841fa2500000d41",
    "dataset_status": true,
    "dataset_type": 0,
    "description": "",
    "excluded_fields": [],
    "fields_meta": {   "count": 5,
                        "limit": 1000,
                        "offset": 0,
                        "query_total": 5,
                        "total": 5},
    "input_fields": ["000000", "000001", "000002", "000003"],
    "locale": "en_US",
    "max_columns": 5,
    "max_rows": 150,
    "name": "iris' dataset correlation",
    "objective_field_details": {   "column_number": 4,
                                    "datatype": "string",
                                    "name": "species",
                                    "optype": "categorical",
                                    "order": 4},
    "out_of_bag": false,
    "price": 0.0,
    "private": true,
    "project": null,
    "range": [1, 150],
    "replacement": false,
    "resource": "correlation/55b7c4e99841fa24f20009bf",
    "rows": 150,
    "sample_rate": 1.0,
    "shared": false,
    "size": 4609,
    "source": "source/55b7a6729841fa24f100036a",
    "source_status": true,
    "status": {   "code": 5,
                   "elapsed": 274,
                   "message": "The correlation has been created",
                   "progress": 1.0},
    "subscription": true,
    "tags": [],
    "updated": "2015-07-28T18:07:49.057000",
    "white_box": false}


Note that the output in the snippet above has been abbreviated. As you see, the
``correlations`` attribute contains the information about each field
correlation to the objective field.


Statistical Tests
-----------------

A ``statisticaltest`` resource contains a series of tests
that compare the
distribution of data in each numeric field of a dataset
to certain canonical distributions,
such as the
`normal distribution <https://en.wikipedia.org/wiki/Normal_distribution>`_
or `Benford's law <https://en.wikipedia.org/wiki/Benford%27s_law>`_
distribution. Statistical test are useful in tasks such as fraud, normality,
or outlier detection.

- Fraud Detection Tests:
Benford: This statistical test performs a comparison of the distribution of
first significant digits (FSDs) of each value of the field to the Benford's
law distribution. Benford's law applies to numerical distributions spanning
several orders of magnitude, such as the values found on financial balance
sheets. It states that the frequency distribution of leading, or first
significant digits (FSD) in such distributions is not uniform.
On the contrary, lower digits like 1 and 2 occur disproportionately
often as leading significant digits. The test compares the distribution
in the field to Bendford's distribution using a Chi-square goodness-of-fit
test, and Cho-Gaines d test. If a field has a dissimilar distribution,
it may contain anomalous or fraudulent values.

- Normality tests:
These tests can be used to confirm the assumption that the data in each field
of a dataset is distributed according to a normal distribution. The results
are relevant because many statistical and machine learning techniques rely on
this assumption.
Anderson-Darling: The Anderson-Darling test computes a test statistic based on
the difference between the observed cumulative distribution function (CDF) to
that of a normal distribution. A significant result indicates that the
assumption of normality is rejected.
Jarque-Bera: The Jarque-Bera test computes a test statistic based on the third
and fourth central moments (skewness and kurtosis) of the data. Again, a
significant result indicates that the normality assumption is rejected.
Z-score: For a given sample size, the maximum deviation from the mean that
would expected in a sampling of a normal distribution can be computed based
on the 68-95-99.7 rule. This test simply reports this expected deviation and
the actual deviation observed in the data, as a sort of sanity check.

- Outlier tests:
Grubbs: When the values of a field are normally distributed, a few values may
still deviate from the mean distribution. The outlier tests reports whether
at least one value in each numeric field differs significantly from the mean
using Grubb's test for outliers. If an outlier is found, then its value will
be returned.

The JSON structure for ``statisticaltest`` resources is similar to this one

.. code-block:: json

     {  "category": 0,
        "clones": 0,
        "code": 200,
        "columns": 5,
        "created": "2015-07-28T18:16:40.582000",
        "credits": 0.017581939697265625,
        "dataset": "dataset/55b7a6749841fa2500000d41",
        "dataset_status": true,
        "dataset_type": 0,
        "description": "",
        "excluded_fields": [],
        "fields_meta": {   "count": 5,
                            "limit": 1000,
                            "offset": 0,
                            "query_total": 5,
                            "total": 5},
        "input_fields": ["000000", "000001", "000002", "000003"],
        "locale": "en_US",
        "max_columns": 5,
        "max_rows": 150,
        "name": "iris' dataset test",
        "out_of_bag": false,
        "price": 0.0,
        "private": true,
        "project": null,
        "range": [1, 150],
        "replacement": false,
        "resource": "statisticaltest/55b7c7089841fa25000010ad",
        "rows": 150,
        "sample_rate": 1.0,
        "shared": false,
        "size": 4609,
        "source": "source/55b7a6729841fa24f100036a",
        "source_status": true,
        "status": {   "code": 5,
                       "elapsed": 302,
                       "message": "The test has been created",
                       "progress": 1.0},
        "subscription": true,
        "tags": [],
        "statistical_tests": {   "ad_sample_size": 1024,
                      "fields": {   "000000": {   "column_number": 0,
                                                    "datatype": "double",
                                                    "idx": 0,
                                                    "name": "sepal length",
                                                    "optype": "numeric",
                                                    "order": 0,
                                                    "preferred": true,
                                                    "summary": {   "bins": [   [   4.3,
                                                                                     1],
                                                                                 [   4.425,
                                                                                     4],
                                                                                 [   7.9,
                                                                                     1]],
                                                                    "kurtosis": -0.57357,
                                                                    "maximum": 7.9,
                                                                    "mean": 5.84333,
                                                                    "median": 5.8,
                                                                    "minimum": 4.3,
                                                                    "missing_count": 0,
                                                                    "population": 150,
                                                                    "skewness": 0.31175,
                                                                    "splits": [   4.51526,
                                                                                   4.67252,
                                                                                   4.81113,
                                                                                   4.89582,
                                                                                   ...
                                                                                   7.20423,
                                                                                   7.64746],
                                                                    "standard_deviation": 0.82807,
                                                                    "sum": 876.5,
                                                                    "sum_squares": 5223.85,
                                                                    "variance": 0.68569}},
                                     ...
                                     "000004": {   "column_number": 4,
                                                    "datatype": "string",
                                                    "idx": 4,
                                                    "name": "species",
                                                    "optype": "categorical",
                                                    "order": 4,
                                                    "preferred": true,
                                                    "summary": {   "categories": [   [   "Iris-setosa",
                                                                                           50],
                                                                                       [   "Iris-versicolor",
                                                                                           50],
                                                                                       [   "Iris-virginica",
                                                                                           50]],
                                                                    "missing_count": 0},
                                                    "term_analysis": {   "enabled": true}}},
                      "fraud": [   {   "name": "benford",
                                        "result": {   "000000": {   "chi_square": {   "chi_square_value": 506.39302,
                                                                                         "p_value": 0,
                                                                                         "significant": [   true,
                                                                                                             true,
                                                                                                             true]},
                                                                      "cho_gaines": {   "d_statistic": 7.124311073683573,
                                                                                         "significant": [   true,
                                                                                                             true,
                                                                                                             true]},
                                                                      "distribution": [   0,
                                                                                           0,
                                                                                           0,
                                                                                           22,
                                                                                           61,
                                                                                           54,
                                                                                           13,
                                                                                           0,
                                                                                           0],
                                                                      "negatives": 0,
                                                                      "zeros": 0},
                                                       "000001": {   "chi_square": {   "chi_square_value": 396.76556,
                                                                                         "p_value": 0,
                                                                                         "significant": [   true,
                                                                                                             true,
                                                                                                             true]},
                                                                      "cho_gaines": {   "d_statistic": 7.503503138331123,
                                                                                         "significant": [   true,
                                                                                                             true,
                                                                                                             true]},
                                                                      "distribution": [   0,
                                                                                           57,
                                                                                           89,
                                                                                           4,
                                                                                           0,
                                                                                           0,
                                                                                           0,
                                                                                           0,
                                                                                           0],
                                                                      "negatives": 0,
                                                                      "zeros": 0},
                                                       "000002": {   "chi_square": {   "chi_square_value": 154.20728,
                                                                                         "p_value": 0,
                                                                                         "significant": [   true,
                                                                                                             true,
                                                                                                             true]},
                                                                      "cho_gaines": {   "d_statistic": 3.9229974017266054,
                                                                                         "significant": [   true,
                                                                                                             true,
                                                                                                             true]},
                                                                      "distribution": [   50,
                                                                                           0,
                                                                                           11,
                                                                                           43,
                                                                                           35,
                                                                                           11,
                                                                                           0,
                                                                                           0,
                                                                                           0],
                                                                      "negatives": 0,
                                                                      "zeros": 0},
                                                       "000003": {   "chi_square": {   "chi_square_value": 111.4438,
                                                                                         "p_value": 0,
                                                                                         "significant": [   true,
                                                                                                             true,
                                                                                                             true]},
                                                                      "cho_gaines": {   "d_statistic": 4.103257341299901,
                                                                                         "significant": [   true,
                                                                                                             true,
                                                                                                             true]},
                                                                      "distribution": [   76,
                                                                                           58,
                                                                                           7,
                                                                                           7,
                                                                                           1,
                                                                                           1,
                                                                                           0,
                                                                                           0,
                                                                                           0],
                                                                      "negatives": 0,
                                                                      "zeros": 0}}}],
                      "normality": [   {   "name": "anderson_darling",
                                            "result": {   "000000": {   "p_value": 0.02252,
                                                                          "significant": [   false,
                                                                                              true,
                                                                                              true]},
                                                           "000001": {   "p_value": 0.02023,
                                                                          "significant": [   false,
                                                                                              true,
                                                                                              true]},
                                                           "000002": {   "p_value": 0,
                                                                          "significant": [   true,
                                                                                              true,
                                                                                              true]},
                                                           "000003": {   "p_value": 0,
                                                                          "significant": [   true,
                                                                                              true,
                                                                                              true]}}},
                                        {   "name": "jarque_bera",
                                            "result": {   "000000": {   "p_value": 0.10615,
                                                                          "significant": [   false,
                                                                                              false,
                                                                                              false]},
                                                           "000001": {   "p_value": 0.25957,
                                                                          "significant": [   false,
                                                                                              false,
                                                                                              false]},
                                                           "000002": {   "p_value": 0.0009,
                                                                          "significant": [   true,
                                                                                              true,
                                                                                              true]},
                                                           "000003": {   "p_value": 0.00332,
                                                                          "significant": [   true,
                                                                                              true,
                                                                                              true]}}},
                                        {   "name": "z_score",
                                            "result": {   "000000": {   "expected_max_z": 2.71305,
                                                                          "max_z": 2.48369},
                                                           "000001": {   "expected_max_z": 2.71305,
                                                                          "max_z": 3.08044},
                                                           "000002": {   "expected_max_z": 2.71305,
                                                                          "max_z": 1.77987},
                                                           "000003": {   "expected_max_z": 2.71305,
                                                                          "max_z": 1.70638}}}],
                      "outliers": [   {   "name": "grubbs",
                                           "result": {   "000000": {   "p_value": 1,
                                                                         "significant": [   false,
                                                                                             false,
                                                                                             false]},
                                                          "000001": {   "p_value": 0.26555,
                                                                         "significant": [   false,
                                                                                             false,
                                                                                             false]},
                                                          "000002": {   "p_value": 1,
                                                                         "significant": [   false,
                                                                                             false,
                                                                                             false]},
                                                          "000003": {   "p_value": 1,
                                                                         "significant": [   false,
                                                                                             false,
                                                                                             false]}}}],
                      "significance_levels": [0.01, 0.05, 0.1]},
        "updated": "2015-07-28T18:17:11.829000",
        "white_box": false}


Note that the output in the snippet above has been abbreviated. As you see, the
``statistical_tests`` attribute contains the ``fraud`, ``normality``
and ``outliers``
sections where the information for each field's distribution is stored.

Logistic Regressions
--------------------

A logistic regression is a supervised machine learning method for
solving classification problems. Each of the classes in the field
you want to predict, the objective field, is assigned a probability depending
on the values of the input fields. The probability is computed
as the value of a logistic function,
whose argument is a linear combination of the predictors' values.
You can create a logistic regression selecting which fields from your
dataset you want to use as input fields (or predictors) and which
categorical field you want to predict, the objective field. Then the
created logistic regression is defined by the set of coefficients in the
linear combination of the values. Categorical
and text fields need some prior work to be modelled using this method. They
are expanded as a set of new fields, one per category or term (respectively)
where the number of occurrences of the category or term is store. Thus,
the linear combination is made on the frequency of the categories or terms.

The JSON structure for a logistic regression is

.. code-block:: json

    {   "balance_objective": false,
        "category": 0,
        "code": 200,
        "columns": 5,
        "created": "2015-10-09T16:11:08.444000",
        "credits": 0.017581939697265625,
        "credits_per_prediction": 0.0,
        "dataset": "dataset/561304f537203f4c930001ca",
        "dataset_field_types": {   "categorical": 1,
                                    "datetime": 0,
                                    "effective_fields": 5,
                                    "numeric": 4,
                                    "preferred": 5,
                                    "text": 0,
                                    "total": 5},
        "dataset_status": true,
        "description": "",
        "excluded_fields": [],
        "fields_meta": {   "count": 5,
                            "limit": 1000,
                            "offset": 0,
                            "query_total": 5,
                            "total": 5},
        "input_fields": ["000000", "000001", "000002", "000003"],
        "locale": "en_US",
        "logistic_regression": {   "bias": 1,
                                    "c": 1,
                                    "coefficients": [   [   "Iris-virginica",
                                                             [   -1.7074433493289376,
                                                                 -1.533662474502423,
                                                                 2.47026986670851,
                                                                 2.5567582221085563,
                                                                 -1.2158200612711925]],
                                                         [   "Iris-setosa",
                                                             [   0.41021712519841674,
                                                                 1.464162165246765,
                                                                 -2.26003266131107,
                                                                 -1.0210350909174153,
                                                                 0.26421852991732514]],
                                                         [   "Iris-versicolor",
                                                             [   0.42702327817072505,
                                                                 -1.611817241669904,
                                                                 0.5763832839459982,
                                                                 -1.4069842681625884,
                                                                 1.0946877732663143]]],
                                    "eps": 1e-05,
                                    "fields": {   "000000": {   "column_number": 0,
                                                                  "datatype": "double",
                                                                  "name": "sepal length",
                                                                  "optype": "numeric",
                                                                  "order": 0,
                                                                  "preferred": true,
                                                                  "summary": {   "bins": [   [   4.3,
                                                                                                   1],
                                                                                               [   4.425,
                                                                                                   4],
                                                                                               [   4.6,
                                                                                                   4],
    ...
                                                                                               [   7.9,
                                                                                                   1]],
                                                                                  "kurtosis": -0.57357,
                                                                                  "maximum": 7.9,
                                                                                  "mean": 5.84333,
                                                                                  "median": 5.8,
                                                                                  "minimum": 4.3,
                                                                                  "missing_count": 0,
                                                                                  "population": 150,
                                                                                  "skewness": 0.31175,
                                                                                  "splits": [   4.51526,
                                                                                                 4.67252,
                                                                                                 4.81113,
    ...
                                                                                                 6.92597,
                                                                                                 7.20423,
                                                                                                 7.64746],
                                                                                  "standard_deviation": 0.82807,
                                                                                  "sum": 876.5,
                                                                                  "sum_squares": 5223.85,
                                                                                  "variance": 0.68569}},
                                                   "000001": {   "column_number": 1,
                                                                  "datatype": "double",
                                                                  "name": "sepal width",
                                                                  "optype": "numeric",
                                                                  "order": 1,
                                                                  "preferred": true,
                                                                  "summary": {   "counts": [   [   2,
                                                                                                     1],
                                                                                                 [   2.2,
                                                                                                     3],
    ...
                                                                                                 [   4.2,
                                                                                                     1],
                                                                                                 [   4.4,
                                                                                                     1]],
                                                                                  "kurtosis": 0.18098,
                                                                                  "maximum": 4.4,
                                                                                  "mean": 3.05733,
                                                                                  "median": 3,
                                                                                  "minimum": 2,
                                                                                  "missing_count": 0,
                                                                                  "population": 150,
                                                                                  "skewness": -0.27213,
                                                                                  "splits": [   1.25138,
                                                                                                 1.32426,
                                                                                                 1.37171,
    ...
                                                                                                 6.02913,
                                                                                                 6.38125],
                                                                                  "standard_deviation": 1.7653,
                                                                                  "sum": 563.7,
                                                                                  "sum_squares": 2582.71,
                                                                                  "variance": 3.11628}},
                                                   "000003": {   "column_number": 3,
                                                                  "datatype": "double",
                                                                  "name": "petal width",
                                                                  "optype": "numeric",
                                                                  "order": 3,
                                                                  "preferred": true,
                                                                  "summary": {   "counts": [   [   0.1,
                                                                                                     5],
                                                                                                 [   0.2,
                                                                                                     29],
    ...
                                                                                                 [   2.4,
                                                                                                     3],
                                                                                                 [   2.5,
                                                                                                     3]],
                                                                                  "kurtosis": -1.33607,
                                                                                  "maximum": 2.5,
                                                                                  "mean": 1.19933,
                                                                                  "median": 1.3,
                                                                                  "minimum": 0.1,
                                                                                  "missing_count": 0,
                                                                                  "population": 150,
                                                                                  "skewness": -0.10193,
                                                                                  "standard_deviation": 0.76224,
                                                                                  "sum": 179.9,
                                                                                  "sum_squares": 302.33,
                                                                                  "variance": 0.58101}},
                                                   "000004": {   "column_number": 4,
                                                                  "datatype": "string",
                                                                  "name": "species",
                                                                  "optype": "categorical",
                                                                  "order": 4,
                                                                  "preferred": true,
                                                                  "summary": {   "categories": [   [   "Iris-setosa",
                                                                                                         50],
                                                                                                     [   "Iris-versicolor",
                                                                                                         50],
                                                                                                     [   "Iris-virginica",
                                                                                                         50]],
                                                                                  "missing_count": 0},
                                                                  "term_analysis": {   "enabled": true}}},
                                    "normalize": false,
                                    "regularization": "l2"},
        "max_columns": 5,
        "max_rows": 150,
        "name": "iris' dataset's logistic regression",
        "number_of_batchpredictions": 0,
        "number_of_evaluations": 0,
        "number_of_predictions": 1,
        "objective_field": "000004",
        "objective_field_name": "species",
        "objective_field_type": "categorical",
        "objective_fields": ["000004"],
        "out_of_bag": false,
        "private": true,
        "project": "project/561304c137203f4c9300016c",
        "range": [1, 150],
        "replacement": false,
        "resource": "logisticregression/5617e71c37203f506a000001",
        "rows": 150,
        "sample_rate": 1.0,
        "shared": false,
        "size": 4609,
        "source": "source/561304f437203f4c930001c3",
        "source_status": true,
        "status": {   "code": 5,
                       "elapsed": 86,
                       "message": "The logistic regression has been created",
                       "progress": 1.0},
        "subscription": false,
        "tags": ["species"],
        "updated": "2015-10-09T16:14:02.336000",
        "white_box": false}

Note that the output in the snippet above has been abbreviated. As you see,
the ``logistic_regression`` attribute stores the coefficients used in the
logistic function as well as the configuration parameters described in
the `developers section <https://bigml.com/developers/logisticregressions>`_ .



Associations
------------

Association Discovery is a popular method to find out relations among values
in high-dimensional datasets.

A common case where association discovery is often used is
market basket analysis. This analysis seeks for customer shopping
patterns across large transactional
datasets. For instance, do customers who buy hamburgers and ketchup also
consume bread?

Businesses use those insights to make decisions on promotions and product
placements.
Association Discovery can also be used for other purposes such as early
incident detection, web usage analysis, or software intrusion detection.

In BigML, the Association resource object can be built from any dataset, and
its results are a list of association rules between the items in the dataset.
In the example case, the corresponding
association rule would have hamburguers and ketchup as the items at the
left hand side of the association rule and bread would be the item at the
right hand side. Both sides in this association rule are related,
in the sense that observing
the items in the left hand side implies observing the items in the right hand
side. There are some metrics to ponder the quality of these association rules:

- Support: the proportion of instances which contain an itemset.

For an association rule, it means the number of instances in the dataset which
contain the rule's antecedent and rule's consequent together
over the total number of instances (N) in the dataset.

It gives a measure of the importance of the rule. Association rules have
to satisfy a minimum support constraint (i.e., min_support).

- Coverage: the support of the antedecent of an association rule.
It measures how often a rule can be applied.

- Confidence or (strength): The probability of seeing the rule's consequent
under the condition that the instances also contain the rule's antecedent.
Confidence is computed using the support of the association rule over the
coverage. That is, the percentage of instances which contain the consequent
and antecedent together over the number of instances which only contain
the antecedent.

Confidence is directed and gives different values for the association
rules Antecedent → Consequent and Consequent → Antecedent. Association
rules also need to satisfy a minimum confidence constraint
(i.e., min_confidence).

- Leverage: the difference of the support of the association
rule (i.e., the antecedent and consequent appearing together) and what would
be expected if antecedent and consequent where statistically independent.
This is a value between -1 and 1. A positive value suggests a positive
relationship and a negative value suggests a negative relationship.
0 indicates independence.

Lift: how many times more often antecedent and consequent occur together
than expected if they where statistically independent.
A value of 1 suggests that there is no relationship between the antecedent
and the consequent. Higher values suggest stronger positive relationships.
Lower values suggest stronger negative relationships (the presence of the
antecedent reduces the likelihood of the consequent)

As to the items used in association rules, each type of field is parsed to
extract items for the rules as follows:

- Categorical: each different value (class) will be considered a separate item.
- Text: each unique term will be considered a separate item.
- Items: each different item in the items summary will be considered.
- Numeric: Values will be converted into categorical by making a
segmentation of the values.
For example, a numeric field with values ranging from 0 to 600 split
into 3 segments:
segment 1 → [0, 200), segment 2 → [200, 400), segment 3 → [400, 600].
You can refine the behavior of the transformation using
`discretization <https://bigml.com/developers/associations#ad_create_discretization>`_
and `field_discretizations <https://bigml.com/developers/associations#ad_create_field_discretizations>`_.

The JSON structure for an association resource is

.. code-block:: json

 {
        "associations":{
            "complement":false,
            "discretization":{
                "pretty":true,
                "size":5,
                "trim":0,
                "type":"width"
            },
            "items":[
                {
                    "complement":false,
                    "count":32,
                    "field_id":"000000",
                    "name":"Segment 1",
                    "bin_end":5,
                    "bin_start":null
                },
                {
                    "complement":false,
                    "count":49,
                    "field_id":"000000",
                    "name":"Segment 3",
                    "bin_end":7,
                    "bin_start":6
                },
                {
                    "complement":false,
                    "count":12,
                    "field_id":"000000",
                    "name":"Segment 4",
                    "bin_end":null,
                    "bin_start":7
                },
                {
                    "complement":false,
                    "count":19,
                    "field_id":"000001",
                    "name":"Segment 1",
                    "bin_end":2.5,
                    "bin_start":null
                },
                 ...
                {
                    "complement":false,
                    "count":50,
                    "field_id":"000004",
                    "name":"Iris-versicolor"
                },
                {
                    "complement":false,
                    "count":50,
                    "field_id":"000004",
                    "name":"Iris-virginica"
                }
            ],
            "max_k": 100,
            "min_confidence":0,
            "min_leverage":0,
            "min_lift":1,
            "min_support":0,
            "rules":[
                {
                    "confidence":1,
                    "id":"000000",
                    "leverage":0.22222,
                    "lhs":[
                        13
                    ],
                    "lhs_cover":[
                        0.33333,
                        50
                    ],
                    "lift":3,
                    "p_value":0.000000000,
                    "rhs":[
                        6
                    ],
                    "rhs_cover":[
                        0.33333,
                        50
                    ],
                    "support":[
                        0.33333,
                        50
                    ]
                },
                {
                    "confidence":1,
                    "id":"000001",
                    "leverage":0.22222,
                    "lhs":[
                        6
                    ],
                    "lhs_cover":[
                        0.33333,
                        50
                    ],
                    "lift":3,
                    "p_value":0.000000000,
                    "rhs":[
                        13
                    ],
                    "rhs_cover":[
                        0.33333,
                        50
                    ],
                    "support":[
                        0.33333,
                        50
                    ]
                },
                ...
                {
                    "confidence":0.26,
                    "id":"000029",
                    "leverage":0.05111,
                    "lhs":[
                        13
                    ],
                    "lhs_cover":[
                        0.33333,
                        50
                    ],
                    "lift":2.4375,
                    "p_value":0.0000454342,
                    "rhs":[
                        5
                    ],
                    "rhs_cover":[
                        0.10667,
                        16
                    ],
                    "support":[
                        0.08667,
                        13
                    ]
                },
                {
                    "confidence":0.18,
                    "id":"00002a",
                    "leverage":0.04,
                    "lhs":[
                        15
                    ],
                    "lhs_cover":[
                        0.33333,
                        50
                    ],
                    "lift":3,
                    "p_value":0.0000302052,
                    "rhs":[
                        9
                    ],
                    "rhs_cover":[
                        0.06,
                        9
                    ],
                    "support":[
                        0.06,
                        9
                    ]
                },
                {
                    "confidence":1,
                    "id":"00002b",
                    "leverage":0.04,
                    "lhs":[
                        9
                    ],
                    "lhs_cover":[
                        0.06,
                        9
                    ],
                    "lift":3,
                    "p_value":0.0000302052,
                    "rhs":[
                        15
                    ],
                    "rhs_cover":[
                        0.33333,
                        50
                    ],
                    "support":[
                        0.06,
                        9
                    ]
                }
            ],
            "rules_summary":{
                "confidence":{
                    "counts":[
                        [
                            0.18,
                            1
                        ],
                        [
                            0.24,
                            1
                        ],
                        [
                            0.26,
                            2
                        ],
                        ...
                        [
                            0.97959,
                            1
                        ],
                        [
                            1,
                            9
                        ]
                    ],
                    "maximum":1,
                    "mean":0.70986,
                    "median":0.72864,
                    "minimum":0.18,
                    "population":44,
                    "standard_deviation":0.24324,
                    "sum":31.23367,
                    "sum_squares":24.71548,
                    "variance":0.05916
                },
                "k":44,
                "leverage":{
                    "counts":[
                       [
                            0.04,
                            2
                        ],
                        [
                            0.05111,
                            4
                        ],
                        [
                            0.05316,
                            2
                        ],
                        ...
                        [
                            0.22222,
                            2
                        ]
                    ],
                    "maximum":0.22222,
                    "mean":0.10603,
                    "median":0.10156,
                    "minimum":0.04,
                    "population":44,
                    "standard_deviation":0.0536,
                    "sum":4.6651,
                    "sum_squares":0.61815,
                    "variance":0.00287
                },
                "lhs_cover":{
                    "counts":[
                        [
                            0.06,
                            2
                        ],
                        [
                            0.08,
                            2
                        ],
                        [
                            0.10667,
                            4
                        ],
                        [
                            0.12667,
                            1
                        ],
                        ...
                        [
                            0.5,
                            4
                        ]
                    ],
                    "maximum":0.5,
                    "mean":0.29894,
                    "median":0.33213,
                    "minimum":0.06,
                    "population":44,
                    "standard_deviation":0.13386,
                    "sum":13.15331,
                    "sum_squares":4.70252,
                    "variance":0.01792
                },
                "lift":{
                    "counts":[
                        [
                            1.40625,
                            2
                        ],
                        [
                            1.5067,
                            2
                        ],
                        ...
                        [
                            2.63158,
                            4
                        ],
                        [
                            3,
                            10
                        ],
                        [
                            4.93421,
                            2
                        ],
                        [
                            12.5,
                            2
                        ]
                    ],
                    "maximum":12.5,
                    "mean":2.91963,
                    "median":2.58068,
                    "minimum":1.40625,
                    "population":44,
                    "standard_deviation":2.24641,
                    "sum":128.46352,
                    "sum_squares":592.05855,
                    "variance":5.04635
                },
                "p_value":{
                    "counts":[
                        [
                            0.000000000,
                            2
                        ],
                        [
                            0.000000000,
                            4
                        ],
                        [
                            0.000000000,
                            2
                        ],
                        ...
                        [
                            0.0000910873,
                            2
                        ]
                    ],
                    "maximum":0.0000910873,
                    "mean":0.0000106114,
                    "median":0.00000000,
                    "minimum":0.000000000,
                    "population":44,
                    "standard_deviation":0.0000227364,
                    "sum":0.000466903,
                    "sum_squares":0.0000000,
                    "variance":0.000000001
                },
                "rhs_cover":{
                    "counts":[
                        [
                            0.06,
                            2
                        ],
                        [
                            0.08,
                            2
                        ],
                        ...
                        [
                            0.42667,
                            2
                        ],
                        [
                            0.46667,
                            3
                        ],
                        [
                            0.5,
                            4
                        ]
                    ],
                    "maximum":0.5,
                    "mean":0.29894,
                    "median":0.33213,
                    "minimum":0.06,
                    "population":44,
                    "standard_deviation":0.13386,
                    "sum":13.15331,
                    "sum_squares":4.70252,
                    "variance":0.01792
                },
                "support":{
                    "counts":[
                        [
                            0.06,
                            4
                        ],
                        [
                            0.06667,
                            2
                        ],
                        [
                            0.08,
                            2
                        ],
                        [
                            0.08667,
                            4
                        ],
                        [
                            0.10667,
                            4
                        ],
                        [
                            0.15333,
                            2
                        ],
                        [
                            0.18667,
                            4
                        ],
                        [
                            0.19333,
                            2
                        ],
                        [
                            0.20667,
                            2
                        ],
                        [
                            0.27333,
                            2
                        ],
                        [
                            0.28667,
                            2
                        ],
                        [
                            0.3,
                            4
                        ],
                        [
                            0.32,
                            2
                        ],
                        [
                            0.33333,
                            6
                        ],
                        [
                            0.37333,
                            2
                        ]
                    ],
                    "maximum":0.37333,
                    "mean":0.20152,
                    "median":0.19057,
                    "minimum":0.06,
                    "population":44,
                    "standard_deviation":0.10734,
                    "sum":8.86668,
                    "sum_squares":2.28221,
                    "variance":0.01152
                }
            },
            "search_strategy":"leverage",
            "significance_level":0.05
        },
        "category":0,
        "clones":0,
        "code":200,
        "columns":5,
        "created":"2015-11-05T08:06:08.184000",
        "credits":0.017581939697265625,
        "dataset":"dataset/562fae3f4e1727141d00004e",
        "dataset_status":true,
        "dataset_type":0,
        "description":"",
        "excluded_fields":[ ],
        "fields_meta":{
            "count":5,
            "limit":1000,
            "offset":0,
            "query_total":5,
            "total":5
        },
        "input_fields":[
            "000000",
            "000001",
            "000002",
            "000003",
            "000004"
        ],
        "locale":"en_US",
        "max_columns":5,
        "max_rows":150,
        "name":"iris' dataset's association",
        "out_of_bag":false,
        "price":0,
        "private":true,
        "project":null,
        "range":[
            1,
            150
        ],
        "replacement":false,
        "resource":"association/5621b70910cb86ae4c000000",
        "rows":150,
        "sample_rate":1,
        "shared":false,
        "size":4609,
        "source":"source/562fae3a4e1727141d000048",
        "source_status":true,
        "status":{
            "code":5,
            "elapsed":1072,
            "message":"The association has been created",
            "progress":1
        },
        "subscription":false,
        "tags":[ ],
        "updated":"2015-11-05T08:06:20.403000",
        "white_box":false
     }


Note that the output in the snippet above has been abbreviated. As you see,
the ``associations`` attribute stores items, rules and metrics extracted
from the datasets as well as the configuration parameters described in
the `developers section <https://bigml.com/developers/associations>`_ .

Topic Models
------------

A topic model is an unsupervised machine learning method for unveiling
all the different topics underlying a collection of documents. BigML
uses Latent Dirichlet Allocation (LDA), one of the most popular
probabilistic methods for topic modeling. In BigML, each instance
(i.e. each row in your dataset) will be considered a document and the
contents of all the text fields given as inputs will be automatically
concatenated and considered the document bag of words.

Topic model is based on the assumption that any document exhibits a
mixture of topics. Each topic is composed of a set of words which are
thematically related. The words from a given topic have different
probabilities for that topic. At the same time, each word can be
attributable to one or several topics. So for example the word “sea”
may be found in a topic related with sea transport but also in a topic
related to holidays. Topic model automatically discards stop words and
high frequency words.

Topic model’s main applications include browsing, organizing and
understanding large archives of documents. It can been applied for
information retrieval, collaborative filtering, assessing document
similarity among others. The topics found in the dataset can also be
very useful new features before applying other models like
classification, clustering, or anomaly detection.

The JSON structure for a topic model is:

.. code-block:: json

     {"category": 0,
      "clones": 0,
      "code": 200,
      "columns": 1,
      "configuration": null,
      "configuration_status": false,
      "created": "2017-10-23T18:27:46.118000",
      "credits": 0.0,
      "credits_per_prediction": 0.0,
      "dataset": "dataset/59ee239eaf447f0b0b0001ff",
      "dataset_field_types": {
        "categorical": 1,
        "datetime": 0,
        "effective_fields": 672,
        "items": 0,
        "numeric": 0,
        "preferred": 2,
        "text": 1,
        "total": 2
      },
      "dataset_status": true,
      "dataset_type": 0,
      "description": "",
      "excluded_fields": [

      ],
      "fields_meta": {
        "count": 1,
        "limit": 1000,
        "offset": 0,
        "query_total": 1,
        "total": 1
      },
      "input_fields": [
        "000001"
      ],
      "locale": "en-us",
      "max_columns": 2,
      "max_rows": 656,
      "name": "spam_ topics",
      "name_options": "number of topics=12, top-n terms=10, term limit=4096",
      "number_of_batchtopicdistributions": 0,
      "number_of_public_topicdistributions": 0,
      "number_of_topicdistributions": 0,
      "ordering": 0,
      "out_of_bag": false,
      "price": 0.0,
      "private": true,
      "project": null,
      "range": [
        1,
        656
      ],
      "replacement": false,
      "resource": "topicmodel/59ee34a23645274acf003cab",
      "rows": 656,
      "sample_rate": 1.0,
      "shared": false,
      "short_url": "",
      "size": 54739,
      "source": "source/59ee23257811dd79430001d9",
      "source_status": true,
      "status": {
        "code": 5,
        "elapsed": 4992,
        "message": "The topic model has been created",
        "progress": 1.0
      },
      "subscription": true,
      "tags": [

      ],
      "topic_model": {
        "alpha": 4.166666666666667,
        "beta": 0.1,
        "bigrams": false,
        "case_sensitive": false,
        "fields": {
          "000001": {
            "column_number": 1,
            "datatype": "string",
            "name": "Message",
            "optype": "text",
            "order": 0,
            "preferred": true,
            "summary": {
              "average_length": 78.14787,
              "missing_count": 0,
              "tag_cloud": [
                [
                  "call",
                  72
                ],
                [
                  "ok",
                  36
                ],
                [
                  "gt",
                  34
                ],
                [
                  "lt",
                  31
                ],
                [
                  "free",
                  30
                ],
                [
                  "time",
                  27
                ],
                [
                  "ur",
                  27
                ],
                [
                  "lor",
                  23
                ],
                [
                  "send",
                  23
                ],
                [
                  "dont",
                  22
                ],
                [
                  "tell",
                  20
                ],
                [
                  "text",
                  20
                ]
              ],
              "term_forms": {

              }
            },
            "term_analysis": {
              "case_sensitive": false,
              "enabled": true,
              "language": "en",
              "stem_words": false,
              "token_mode": "all",
              "use_stopwords": false
            }
          }
        },
        "hashed_seed": 62146850,
        "language": "en",
        "number_of_topics": 12,
        "term_limit": 4096,
        "term_topic_assignments": [
          [
            0,
            5,
            0,
            1,
            0,
            19,
            0,
            0,
            19,
            0,
            1,
            0
          ],
          [
            0,
            0,
            0,
            13,
            0,
            0,
            0,
            0,
            5,
            0,
            0,
            0
          ],
          [
            5,
            0,
            0,
            0,
            0,
            17,
            0,
            0,
            0,
            5,
            0,
            0
          ],
          [
            0,
            1,
            5,
            0,
            1,
            8,
            12,
            0,
            0,
            0,
            0,
            0
          ],
          [
            0,
            0,
            0,
            2,
            0,
            0,
            0,
            0,
            0,
            0,
            0,
            16
          ],
          [
            3,
            0,
            0,
            0,
            0,
            2,
            1,
            0,
            0,
            0,
            12,
            0
          ],
        ],
        "termset": [
          "000",
          "03",
          "04",
          "06",
          "08000839402",
          "08712460324",
          "able",
          "acc",
          "account",
          "actually",
          "address",
          "afternoon",
          "aftr",
          "age",
          "ah",
          "aight",
          "album",
          "amp",
          "b'day",
          "babe",
          "baby",
          "babysit",
          "bad",
          "bags",
          "bank",
          "basic",
          "bathe",
          "battery",
          "claim",
          "class",
          "close",
          "co",
          "code",
          "colleagues",
          "collection",
          "college",
          "colour",
        ],
        "top_n_terms": 10,
        "topicmodel_seed": "26c386d781963ca1ea5c90dab8a6b023b5e1d180",
        "topics": [
          {
            "id": "000000",
            "name": "Topic 00",
            "probability": 0.09375,
            "top_terms": [
              [
                "im",
                0.04849
              ],
              [
                "hi",
                0.04717
              ],
              [
                "love",
                0.04585
              ],
              [
                "please",
                0.02867
              ],
              [
                "tomorrow",
                0.02867
              ],
              [
                "cos",
                0.02823
              ],
              [
                "sent",
                0.02647
              ],
              [
                "da",
                0.02383
              ],
              [
                "meet",
                0.02207
              ],
              [
                "dinner",
                0.01898
              ]
            ]
          },
          {
            "id": "000001",
            "name": "Topic 01",
            "probability": 0.08215,
            "top_terms": [
              [
                "lt",
                0.1015
              ],
              [
                "gt",
                0.1007
              ],
              [
                "wish",
                0.03958
              ],
              [
                "feel",
                0.0272
              ],
              [
                "shit",
                0.02361
              ],
              [
                "waiting",
                0.02281
              ],
              [
                "stuff",
                0.02001
              ],
              [
                "name",
                0.01921
              ],
              [
                "comp",
                0.01522
              ],
              [
                "forgot",
                0.01482
              ]
            ]
          },
          {
            "id": "000002",
            "name": "Topic 02",
            "probability": 0.08771,
            "top_terms": [
              [
                "ok",
                0.15142
              ],
              [
                "pls",
                0.03938
              ],
              [
                "hey",
                0.03083
              ],
              [
                "send",
                0.02998
              ],
              [
                "drive",
                0.02955
              ],
              [
                "msg",
                0.02827
              ],
              [
                "min",
                0.01758
              ],
              [
                "joking",
                0.01672
              ],
              [
                "changed",
                0.01544
              ],
              [
                "mom",
                0.01415
              ]
            ]
          }
        ],
        "use_stopwords": false
      },
      "type": 0,
      "updated": "2017-10-23T18:31:59.793000",
      "white_box": false
    }

Note that the output in the snippet above has been abbreviated.

The topic model returns a list of top terms for each topic found in
the data. Note that topics are not labeled, so you have to infer their
meaning according to the words they are composed of.

Once you build the topic model you can calculate each topic
probability for a given document by using Topic Distribution. This
information can be useful to find documents similarities based on
their thematic.

As you see, the ``topic_model`` attribute stores the topics and termset
and term to topic assignment, as well as the configuration parameters
described in the `developers section <https://bigml.com/api/topicmodels>`_ .

Whizzml Resources
-----------------

Whizzml is a Domain Specific Language that allows the definition and
execution of ML-centric workflows. Its objective is allowing BigML
users to define their own composite tasks, using as building blocks
the basic resources provided by BigML itself. Using Whizzml they can be
glued together using a higher order, functional, Turing-complete language.
The Whizzml code can be stored and executed in BigML using three kinds of
resources: ``Scripts``, ``Libraries`` and ``Executions``.

Whizzml ``Scripts`` can be executed in BigML's servers, that is,
in a controlled, fully-scalable environment which takes care of their
parallelization and fail-safe operation. Each execution uses an ``Execution``
resource to store the arguments and results of the process. Whizzml
``Libraries`` store generic code to be shared of reused in other Whizzml
``Scripts``.

Scripts
-------

In BigML a ``Script`` resource stores Whizzml source code, and the results of
its compilation. Once a Whizzml script is created, it's automatically compiled;
if compilation succeeds, the script can be run, that is,
used as the input for a Whizzml execution resource.

An example of a ``script`` that would create a ``source`` in BigML using the
contents of a remote file is:

.. code-block:: php

    $api =  new BigML\BigML();

    # creating a script directly from the source code.

    $api->create_script(array('source_code' => '(+ 1 1)'));
    $api->create_script('/files/diabetes.csv');

The ``script`` can also use a ``library`` resource (please, see the
``Libraries`` section below for more details) by including its id in the
``imports`` attribute. Other attributes can be checked at the
`API Developers documentation for Scripts <https://bigml.com/developers/scripts#ws_script_arguments>`_ .

Executions
----------

To execute in BigML a compiled Whizzml ``script`` you need to create an
``execution`` resource. It's also possible to execute a pipeline of
many compiled scripts in one request.

Each ``execution`` is run under its associated user credentials and its
particular environment constaints. As ``scripts`` can be shared,
you can execute the same ``script``
several times under different
usernames by creating different ``executions``.

As an example of ``execution`` resource, let's create one for the script
in the previous section:

.. code-block:: php

    $execution = $api->create_execution('script/573c9e2db85eee23cd000489')

An ``execution`` receives inputs, the ones defined in the ``script`` chosen
to be executed, and generates a result. It can also generate outputs.
As you can see, the execution resource contains information about the result
of the execution, the resources that have been generated while executing and
users can define some variables in the code to be exported as outputs. Please
refer to the
`Developers documentation for Executions <https://bigml.com/developers/executions#we_execution_arguments>`_
for details on how to define execution outputs.
the `developers section <https://bigml.com/developers/associations>`_ .

Libraries
---------

The ``library`` resource in BigML stores a special kind of compiled Whizzml
source code that only defines functions and constants. The ``library`` is
intended as an import for executable scripts.
Thus, a compiled library cannot be executed, just used as an
import in other ``libraries`` and ``scripts`` (which then have access
to all identifiers defined in the ``library``).

As an example, we build a ``library`` to store the definition of two functions:
``mu`` and ``g``. The first one adds one to the value set as argument and
the second one adds two variables and increments the result by one.

.. code-block:: php

    $library = $api->create_library("(define (mu x) (+ x 1)) (define (g z y) (mu (+ y z)))");

Libraries can be imported in scripts. The ``imports`` attribute of a ``script``
can contain a list of ``library`` IDs whose defined functions
and constants will be ready to be used throughout the ``script``. Please,
refer to the `API Developers documentation for Libraries <https://bigml.com/developers/libraries#wl_library_arguments>`_
for more details.


Statuses
--------
Please, bear in mind that resource creation is almost always asynchronous (predictions are the only exception).
Therefore, when you create a new source, a new dataset or a new model, even if you receive an immediate response from the BigML servers,
the full creation of the resource can take from a few seconds to a few days, depending on the size of the resource and BigML’s load.
A resource is not fully created until its status is bigml.api.FINISHED.
See the documentation on status codes for the listing of potential states and their semantics

.. code-block:: php


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

You can query the status of any resource with the status method

.. code-block:: php

    $api->status($source)
    $api->status($dataset)
    $api->status($model)
    $api->status($prediction)
    $api->status($evaluation)
    $api->status($ensemble)
    $api->status($batch_prediction)
    $api->status($cluster)
    $api->status($centroid)
    $api->status($batch_centroid)
    $api->status($anomaly)
    $api->status($anomaly_score)
    $api->status($batch_anomaly_score)

Projects
---------

A special kind of resource is ``project``. Projects are repositories
for resources, intended to fulfill organizational purposes. Each project can
contain any other kind of resource, but the project that a certain resource
belongs to is determined by the one used in the ``source``
they are generated from. Thus, when a source is created
and assigned a certain ``project_id``, the rest of resources generated from
this source will remain in this project.

The REST calls to manage the ``project`` resemble the ones used to manage the
rest of resources. When you create a ``project``

.. code-block:: php

    $api = new BigML\BigML();
    $project = $api->create_project(array('name' => 'my first project'));

the resulting resource is similar to the rest of resources, although shorter

.. code-block:: php

    (
    [code] => 201
    [resource] => project/5b187d647e0a8d1c780046c2
    [location] => http://bigml.io/andromeda/project/5b187d647e0a8d1c780046c2
    [object] => stdClass Object
        (
            [category] => 0
            [code] => 201
            [configuration] =>
            [configuration_status] =>
            [created] => 2018-06-07T00:33:40.371425
            [creator] => me
            [description] =>
            [execution_id] =>
            [execution_status] =>
            [manage_permission] => Array
                (
                )

            [name] => my first project
            [name_options] =>
            [private] => 1
            [resource] => project/5b187d647e0a8d1c780046c2
            [stats] => stdClass Object
                (
                    [anomalies] => stdClass Object
                        (
                            [count] => 0
                        )

                    [anomalyscores] => stdClass Object
                        (
                            [count] => 0
                        )

                    [associations] => stdClass Object
                        (
                            [count] => 0
                        )

                    [associationsets] => stdClass Object
                        (
                            [count] => 0
                        )

                    [batchanomalyscores] => stdClass Object
                        (
                            [count] => 0
                        )

                    [batchcentroids] => stdClass Object
                        (
                            [count] => 0
                        )

                    [batchpredictions] => stdClass Object
                        (
                            [count] => 0
                        )

                    [batchtopicdistributions] => stdClass Object
                        (
                            [count] => 0
                        )

                    [centroids] => stdClass Object
                        (
                            [count] => 0
                        )

                    [clusters] => stdClass Object
                        (
                            [count] => 0
                        )

                    [composites] => stdClass Object
                        (
                            [count] => 0
                        )

                    [configurations] => stdClass Object
                        (
                            [count] => 0
                        )

                    [correlations] => stdClass Object
                        (
                            [count] => 0
                        )

                    [datasets] => stdClass Object
                        (
                            [count] => 0
                        )

                    [deepnets] => stdClass Object
                        (
                            [count] => 0
                        )

                    [ensembles] => stdClass Object
                        (
                            [count] => 0
                        )

                    [evaluations] => stdClass Object
                        (
                            [count] => 0
                        )

                    [executions] => stdClass Object
                        (
                            [count] => 0
                        )

                    [forecasts] => stdClass Object
                        (
                            [count] => 0
                        )

                    [fusions] => stdClass Object
                        (
                            [count] => 0
                        )

                    [libraries] => stdClass Object
                        (
                            [count] => 0
                        )

                    [logisticregressions] => stdClass Object
                        (
                            [count] => 0
                        )

                    [models] => stdClass Object
                        (
                            [count] => 0
                        )

                    [optimls] => stdClass Object
                        (
                            [count] => 0
                        )

                    [predictions] => stdClass Object
                        (
                            [count] => 0
                        )

                    [samples] => stdClass Object
                        (
                            [count] => 0
                        )

                    [scripts] => stdClass Object
                        (
                            [count] => 0
                        )

                    [sources] => stdClass Object
                        (
                            [count] => 0
                        )

                    [statisticaltests] => stdClass Object
                        (
                            [count] => 0
                        )

                    [timeseries] => stdClass Object
                        (
                            [count] => 0
                        )

                    [topicdistributions] => stdClass Object
                        (
                            [count] => 0
                        )

                    [topicmodels] => stdClass Object
                        (
                            [count] => 0
                        )

                )

            [status] => stdClass Object
                (
                    [code] => 5
                    [message] => The project has been created
                )

            [tags] => Array
                (
                )

            [type] => 0
            [updated] => 2018-06-07T00:33:40.371446
            [user_metadata] => Array
                (
                )

        )

    [error] =>
    )


and you can use its project id to get, update or delete it

.. code-block:: php

    $project = $api->get_project('project/54a1bd0958a27e3c4c0002f0');
    $api->update_project($project->resource,
                         array('description' => 'This is my first project'));

    $api->delete_project($project->resource);

**Important**: Deleting a non-empty project will also delete **all resources**
assigned to it, so please be extra-careful when using
the ``$api->delete_project`` call.


Creating sources
----------------

To create a source from a local data file, you can use the create_source method. The only required parameter is the path to the data file (or file-like object). You can use a second optional parameter to specify any of the options for source creation described in the `BigML API documentation <https://bigml.com/developers>`_.

Here’s a sample invocation

.. code-block:: php

    $source = $api->create_source('./tests/data/iris.csv', array('name'=> 'my source'));

or you may want to create a source from a file in a remote location

.. code-block:: php

    $source = $api->create_source('s3://bigml-public/csv/iris.csv');

Creating datasets
-----------------

Once you have created a source, you can create a dataset. The only required argument to create a dataset is a source id.
You can add all the additional arguments accepted by BigML and documented in `the Datasets section of the Developer’s documentation <https://bigml.com/developers/datasets>`_.

For example, to create a dataset named “my dataset” with the first 1024 bytes of a source, you can submit the following request

.. code-block:: php

    $dataset = $api->create_dataset($source, array("name"=> "mydata", "size"=> 1024));

You can also extract samples from an existing dataset and generate a new one with them using the api.create_dataset method

.. code-block:: php

    $dataset = $api->create_dataset($origin_dataset, array("sample_rate"=> 0.8));

It is also possible to generate a dataset from a list of datasets (multidataset)

.. code-block:: php

    $dataset1 = $api->create_dataset($source1);
    $dataset2 = $api->create_dataset($source2);
    $multidataset = $api->create_dataset(array($dataset1, $dataset2));

Clusters can also be used to generate datasets containing the instances grouped around each centroid.
You will need the cluster id and the centroid id to reference the dataset to be created. For instance

.. code-block:: php

    $cluster = $api->create_cluster($dataset);
    $cluster_dataset_1 = $api->create_dataset($cluster,array('centroid'=>'000000'));

would generate a new dataset containing the subset of instances in the cluster associated to the centroid id 000000.


Creating models
---------------

Once you have created a dataset you can create a model from it.
If you don’t select one, the model will use the last field of the dataset as objective field.
The only required argument to create a model is a dataset id.
You can also include in the request all the additional arguments accepted by BigML and documented in `the Models section of the Developer’s documentation <https://bigml.com/developers/models>`_.

For example, to create a model only including the first two fields and the first 10 instances in the dataset, you can use the following invocation

.. code-block:: php

    $model = $api->create_model($dataset, array("name"=>"my model", "input_fields"=> array("000000", "000001"), "range"=> array(1, 10)));

the model is scheduled for creation.


Creating clusters
-----------------

If your dataset has no fields showing the objective information to predict for the training data,
you can still build a cluster that will group similar data around some automatically chosen points (centroids).
Again, the only required argument to create a cluster is the dataset id.
You can also include in the request all the additional arguments accepted by BigML and documented in `the Clusters section of the Developer’s documentation <https://bigml.com/developers/clusters>`_.

Let’s create a cluster from a given dataset

.. code-block:: php

    $cluster = $api->create_cluster($dataset, array("name"=> "my cluster", "k"=> 5}));

that will create a cluster with 5 centroids.


Creating anomaly detectors
--------------------------

If your problem is finding the anomalous data in your dataset, you can build an anomaly detector, that will use iforest to single out the anomalous records. Again, the only required argument to create an anomaly detector is the dataset id. You can also include in the request all the additional arguments accepted by BigML and documented in the `Anomaly detectors section of the Developer’s documentation <https://bigml.com/developers/anomalies>`_.

Let’s create an anomaly detector from a given dataset

.. code-block:: php

    $anomaly = $api->create_anomaly($dataset, array("name"=>"my anomaly"})

Creating associations
---------------------

To find relations between the field values you can create an association
discovery resource. The only required argument to create an association
is a dataset id.
You can also
include in the request all the additional arguments accepted by BigML
and documented in the `Association section of the Developer's
documentation <https://bigml.com/developers/associations>`_.

For example, to create an association only including the first two fields and
the first 10 instances in the dataset, you can use the following
invocation

.. code-block:: php

    $model = $api->create_association($dataset,
                                       array("name" => "my association",
                                             "input_fields" => array("000000", "000001"),
                                             "range" => array(1,10)));

Associations can also be created from lists of datasets. Just use the
list of ids as the first argument in the api call

.. code-block:: php

    $model = $api->create_association(array(dataset1, dataset2),
                                      array("name" => "my association",
                                            "input_fields" => array("000000", "000001"),
                                            "range" => array(1,10)));

Creating topic models
---------------------

To find which topics your documents refer to you can create a topic
model. The only required argument to create a topic model is a
dataset id. You can also include in the request all the additional
arguments accepted by BigML and documented in the `Topic Model section
of the Developer’s documentation <https://bigml.com/api/topicmodels>`_ .

For example, to create a topic model including exactly 32 topics you
can use the following invocation

.. code-block:: php

    $topic_model = $api->create_topicmodel($dataset,
                                            array("name" => "my topics",
                                                  "number_of_topics" => 32));

Topic models can also be created from lists of datasets. Just use the
list of ids as the first argument in the api call

.. code-block:: php

    $topic_model = $api->create_topic_model([$dataset1, $dataset2],
                                            array("name" => "my topics",
                                                  "number_of_topics" => 32));


Creating predictions
--------------------

You can now use the model resource identifier together with some input parameters to ask for predictions, using the create_prediction method.
You can also give the prediction a name

.. code-block:: php

    $prediction = $api->create_prediction($model,
                                          array("sepal length"=> 5,
                                                "sepal width" => 2.5),
                                          array("name"=>"my prediction"));

    $api->pprint($prediction);

    petal width for {"sepal length":5,"sepal width":2.5} is 0.30455

Creating centroids
------------------

To obtain the centroid associated to new input data, you can now use the create_centroid method.
Give the method a cluster identifier and the input data to obtain the centroid.
You can also give the centroid predicition a name

.. code-block:: php

    $centroid = $api->create_centroid($cluster,
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
create_anomaly_score method. Give the method an anomaly detector identifier and the input data to obtain the score

.. code-block:: php

     $anomaly_score = $api->create_anomaly_score($anomaly,
                                                 array("src_bytes"=> 350),
                                                 array("name"=> "my score"));



Creating association sets
-------------------------

Using the association resource, you can obtain the consequent items associated
by its rules to your input data. These association sets can be obtained calling
the ``create_association_set`` method. The first argument is the association
ID or object and the next one is the input data

.. code-block:: php

     $association_set = $api->create_association_set($association,
                                                      array('genres'=> "Action\$Adventure"),
                                                      array('name' => "my association set"));


Creating evaluations
--------------------

Once you have created a model, you can measure its perfomance by running a dataset of test data through it
and comparing its predictions to the objective field real values.
Thus, the required arguments to create an evaluation are model id and a dataset id.
You can also include in the request all the additional arguments accepted by BigML and documented in `the Evaluations section of the Developer’s documentation <https://bigml.com/developers/evaluations>`_.

For instance, to evaluate a previously created model using at most 10000 rows from an existing dataset you can use the following call

.. code-block:: php

    $evaluation = $api->create_evaluation($model,
                                          $dataset,
                                          array("name"=>"my model", "max_rows"=>10000));

Evaluations can also check the ensembles’ performance.
To evaluate an ensemble you can do exactly what we just did for the model case, using the ensemble object instead of the model as first argument

.. code-block:: php

    $evaluation = $api->create_evaluation($ensemble, $dataset);


Creating ensembles
------------------

To improve the performance of your predictions, you can create an ensemble of models and combine their individual predictions.
The only required argument to create an ensemble is the dataset id

.. code-block:: php

    $ensemble = $api->create_ensemble($datasetid);

but you can also specify the number of models to be built and the parallelism level for the task


.. code-block:: php

    $args = array('number_of_models'=> 20, 'tlp'=> 3);
    $ensemble = $api->create_ensemble($datasetid, $args);


Creating logistic regressions
-----------------------------

For classification problems, you can choose also logistic regressions to model
your data. Logistic regressions compute a probability associated to each class
in the objective field. The probability is obtained using a logistic
function, whose argument is a linear combination of the field values.

As the rest of models, logistic regressions can be created from a dataset by
calling the corresponding create method:

.. code-block:: php

    logistic_regression = $api->create_logistic_regression(
        'dataset/5143a51a37203f2cf7000972',
        array("name" => "my logistic regression",
         "objective_field" => "my_objective_field"))

In this example, we created a logistic regression named
``my logistic regression`` and set the objective field to be
``my_objective_field``. Other arguments, like ``bias``, ``missing_numerics``
and ``c`` can also be specified as attributes in arguments dictionary at
creation time.
Particularly for categorical fields, there are four different available
`field_codings`` options (``dummy``, ``contrast``, ``other`` or the ``one-hot``
default coding). For a more detailed description of the
``field_codings`` attribute and its syntax, please see the `Developers API
Documentation
<https://bigml.com/developers/logisticregressions#lr_logistic_regression_arguments>`_.

Creating deepnets
-----------------


Deepnets can also solve classification and regression
problems. Deepnets are an optimized version of Deep Neural Networks, a
class of machine-learned models inspired by the neural circuitry of
the human brain. In these classifiers, the input features are fed to a
group of “nodes” called a “layer”. Each node is essentially a function
on the input that transforms the input features into another value or
collection of values. Then the entire layer transforms an input vector
into a new “intermediate” feature vector. This new vector is fed as
input to another layer of nodes. This process continues layer by
layer, until we reach the final “output” layer of nodes, where the
output is the network’s prediction: an array of per-class
probabilities for classification problems or a single, real value for
regression problems.

Deepnets predictions compute a probability associated to each class in
the objective field for classification problems. As the rest of
models, deepnets can be created from a dataset by calling the
corresponding create method:

.. code-block:: php

  $deepnet = $api->create_deepnet('dataset/5143a51a37203f2cf7000972',
                                  array("name" => "my deepnet",
                                        "objective_field" => "my_objective_field"));

In this example, we created a deepnet named ``my deepnet`` and set the
objective field to be ``my_objective_field``. Other arguments, like
``number_of_hidden_layers``, ``learning_rate`` and ``missing_numerics`` can also
be specified as attributes in an arguments dictionary at creation
time. For a more detailed description of the available attributes and
its syntax, please see the `Developers API
Documentation
<https://bigml.com/api/deepnets#dn_deepnet_arguments>`_.



Creating batch predictions
--------------------------

We have shown how to create predictions individually, but when the amount of predictions to make increases, this procedure is far from optimal.
In this case, the more efficient way of predicting remotely is to create a dataset containing the input data you want your model to predict
from and to give its id and the one of the model to the create_batch_prediction api call

.. code-block:: php

    $batch_prediction = $api->$create_batch_prediction($model,
                                                       $dataset,
                                                       array("name"=>"my batch prediction",
                                                             "all_fields"=> true,
                                                             "header": true,
                                                             "confidence": true));


In this example, setting all_fields to true causes the input data to be included in the prediction output, header controls whether a headers line
is included in the file or not and confidence set to true causes the confidence of the prediction to be appended.
If none of these arguments is given, the resulting file will contain the name of the objective field as a header row followed by the predictions.

As for the rest of resources, the create method will return an incomplete object, that can be updated by issuing the corresponding
$api->get_batch_prediction call until it reaches a FINISHED status.
Then you can download the created predictions file using

.. code-block:: php

   $api->download_batch_prediction('batchprediction/526fc344035d071ea3031d70',
                                   'my_dir/my_predictions.csv');


Creating batch centroids
------------------------

As described in the previous section, it is also possible to make centroids’ predictions in batch.
First you create a dataset containing the input data you want your cluster to relate to a centroid.
The create_batch_centroid call will need the id of the dataset and the cluster to assign a centroid to each input data

.. code-block:: php

    $batch_centroid = $api->create_batch_centroid($cluster,
                                                  $dataset,
                                                  array("name"=>"my batch centroid",
                                                        "all_fields"=> true,
                                                        "header"=> true));


Creating batch anomaly scores
-----------------------------

Input data can also be assigned an anomaly score in batch. You train an anomaly detector with your training data and then build a dataset from your input data. The create_batch_anomaly_score call will need the id of the dataset and of the anomaly detector to assign an anomaly score to each input data instance

.. code-block:: php

   $batch_anomaly_score = $api->create_batch_anomaly_score($anomaly,
                                                           $dataset,
                                                           array("name" => "my batch anomaly score"
                                                                 "all_fields" => true,
                                                                 "header" => true))

Listing Resources
-----------------

You can list resources with the appropriate api method:

.. code-block:: php

    $api->list_sources()
    $api->list_datasets()
    $api->list_models()
    $api->list_predictions()
    $api->list_evaluations()
    $api->list_ensembles()
    $api->list_batch_predictions()
    $api->list_clusters()
    $api->list_centroids()
    $api->list_batch_centroids()
    $api->list_anomalies()
    $api->list_anomaly_scores()
    $api->list_batch_anomaly_scores()
    $api->list_deepnets()

you will receive a dictionary with the following keys:

-  **code**: If the request is successful you will get a bigml.api.HTTP_OK (200) status code. Otherwise, it will be one of the standard HTTP error codes. See BigML documentation on status codes for more info.
-  **meta**: A dictionary including the following keys that can help you paginate listings:
-  **previous**: Path to get the previous page or null if there is no previous page.
-  **next**: Path to get the next page or null if there is no next page.
-  **offset**: How far off from the first entry in the resources is the first one listed in the resources key.
-  **limit**: Maximum number of resources that you will get listed in the resources key.
-  **total_count**: The total number of resources in BigML.
-  **objects**: A list of resources as returned by BigML.
-  **error**: If an error occurs and the resource cannot be created, it will contain an additional code and a description of the error. In this case, meta, and resources will be null.

Filtering Resources
-------------------

You can filter resources in listings using the syntax and fields labeled as filterable in the `BigML documentation <https://bigml.com/developers>`_. for each resource.

A few examples:

- Ids of the first 5 sources created before April 1st, 2012:

.. code-block:: php

    $api->list_sources("limit=5;created__lt=2012-04-1");

- Name of the first 10 datasets bigger than 1MB:

.. code-block:: php

    $api->list_datasets("limit=10;size__gt=1048576");

- Name of models with more than 5 fields (columns):

.. code-block:: php

    $api->list_models("columns__gt=5");

- Ids of predictions whose model has not been deleted:

.. code-block:: php

    $api->list_predictions("model_status=true");

Ordering Resources
------------------

You can order resources in listings using the syntax and fields labeled as sortable in the `BigML documentation <https://bigml.com/developers>`_. for each resource.

A few examples:

- Name of sources ordered by size:

.. code-block:: php

     $api->list_sources("order_by=size");

- Number of instances in datasets created before April 1st, 2012 ordered by size:

.. code-block:: php

     $api->list_datasets("created__lt=2012-04-1;order_by=size");

- Model ids ordered by number of predictions (in descending order):

.. code-block:: php

     $api->list_models("order_by=-number_of_predictions");

- Name of predictions ordered by name:

.. code-block:: php

     $api->list_predictions("order_by=name");

Updating Resources
------------------

When you update a resource, it is returned in a dictionary exactly like the one you get when you create a new one.
However the status code will be bigml.api.HTTP_ACCEPTED if the resource can be updated without problems or one of the HTTP standard error codes otherwise:

.. code-block:: php

    $api->update_source($source, array("name"=> "new name"));
    $api->update_dataset($dataset, array("name"=> "new name"));
    $api->update_model($model, array("name"=> "new name"));
    $api->update_prediction($prediction, array("name"=> "new name"));
    $api->update_evaluation($evaluation, array("name"=> "new name"));
    $api->update_ensemble($ensemble, array("name"=> "new name"));
    $api->update_batch_prediction($batch_prediction, array("name"=> "new name"));
    $api->update_cluster($cluster, array("name"=> "new name"));
    $api->update_centroid($centroid, array("name"=> "new name"));
    $api->update_batch_centroid($batch_centroid, array("name"=> "new name"));
    $api->update_anomaly($anomaly, array("name"=> "new name"));
    $api->update_anomaly_score($anomaly_score, array("name": "new name"));
    $api->update_batch_anomaly_score($batch_anomaly_score, array("name": "new name"));
    $api->update_deepnet($deepnet, array("name": "new name"));

Updates can change resource general properties, such as the name or description attributes of a dataset, or specific properties.
As an example, let’s say that your source has a certain field whose contents are numeric integers.
BigML will assign a numeric type to the field, but you might want it to be used as a categorical field. You could change its type to categorical by calling:

.. code-block:: php

    $api->update_source($source, array("fields"=> array("000001"=> array("optype"=> "categorical"))));

where 000001 is the field id that corresponds to the updated field.
You will find detailed information about the updatable attributes of each resource in `BigML developer’s documentation <https://bigml.com/developers>`_.

Deleting Resources
------------------
Resources can be deleted individually using the corresponding method for each type of resource

.. code-block:: php

    $api->delete_source($source);
    $api->delete_dataset($dataset);
    $api->delete_model($model);
    $api->delete_prediction($prediction);
    $api->delete_evaluation($evaluation);
    $api->delete_ensemble($ensemble);
    $api->delete_batch_prediction($batch_prediction);
    $api->delete_cluster($cluster);
    $api->delete_centroid($centroid);
    $api->delete_batch_centroid($batch_centroid);
    $api->delete_anomaly($anomaly);
    $api->delete_anomaly_score($anomaly_score);
    $api->delete_batch_anomaly_score($batch_anomaly_score);
    $api->delete_deepnet($deepnet);

Each of the calls above will return a dictionary with the following keys:

code If the request is successful, the code will be a bigml.api.HTTP_NO_CONTENT (204) status code.
Otherwise, it wil be one of the standard HTTP error codes. See the documentation on status codes for more info.
error If the request does not succeed, it will contain a dictionary with an error code and a message. It will be null otherwise.

Local Models
------------

You can use the information returned by the API when asking for a
model to create a Model object in your own computer that will be able
to produce predictions with no further connection to the remote
API. The local Model object can be instantiated by using the entire
response of the GET call to the API:

.. code-block:: php

    $api = new BigML\BigML();

    $model = api->get_model('model/538XXXXXXXXXXXXXXXXXXX2');
    $local_model = new BigML\Model(model);

It also accepts the model ID as the first argument. In this case, a
new connection will be created internally to download the model
information.:

.. code-block:: php

    $local_model = new BigML\Model("model/538XXXXXXXXXXXXXXXXXXX2");

If you want to use a specific connection object for the remote
retrieval, you can set it as the second parameter:

.. code-block:: php

    $local_model = new BigML\Model("model/538XXXXXXXXXXXXXXXXXXX2", $api);

To set default storage

.. code-block:: php

    $local_model = new BigML\Model("model/538XXXXXXXXXXXXXXXXXXX2", null, 'storagedirectory');

Local Predictions
-----------------

Once you have a local model you can use to generate predictions locally

.. code-block:: php

    $prediction = $local_model->predict(array("petal length"=> 3, "petal width"=> 1));

You can also use the `predict_probability` function to obtain a probability prediction for each possible class of the objective field:

.. code-block:: php

    $predict_probability = $local_model->predict_probability(array("petal width"=> 0.5));

Local predictions have three clear advantages:

- Removing the dependency from BigML to make new predictions.

- No cost (i.e., you do not spend BigML credits).

- Extremely low latency to generate predictions for huge volumes of data.


Local Clusters
--------------

You can use the information returned by the API when asking for a
cluster to create a Cluster object in your own computer that will be
able to produce predictions with no further connection to the remote
API. The local Cluster object can be instantiated by using the entire
response of the GET call to the API:

.. code-block:: php

    $cluster = $api->get_cluster("cluster/539xxxxxxxxxxxxxxxxxxxx18");
    $local_cluster = new BigML\Cluster($cluster);

It also accepts the cluster ID as the first argument. This will
retrieve the remote cluster information, using an implicitly built
BigML() connection object (see the Authentication section for more
details on how to set your credentials) and return a Cluster object
that you can use to make local centroid predictions.

.. code-block:: php

    $local_cluster = new BigML\Cluster("cluster/539xxxxxxxxxxxxxxxxxxxx18");

If you want to use a specfic connection object for the remote
retrieval, you can set it as second parameter

.. code-block:: php

    $local_cluster = new BigML\Cluster("cluster/539xxxxxxxxxxxxxxxxxxxx18", $api);

To set default storage if you have storage unset in your API object

.. code-block:: php

  $local_cluster = new BigML\Cluster("cluster/539xxxxxxxxxxxxxxxxxxxx18", null, $storagedirectory);

(where `$storagedirectory` is the desired storage location.)

Local Centroids
---------------

Using the local cluster object, you can predict the centroid associated to an input data set:

.. code-block:: php

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

You can also instantiate a local version of a remote anomaly.

.. code-block:: php

    $local_anomaly = new BigML\Anomaly('anomaly/502fcbff15526876610002435');

This will retrieve the remote anomaly detector information, using an implicitly built BigML() connection object (see the Authentication section for more details on how to set your credentials) and return an Anomaly object that you can use to make local anomaly scores. If you want to use a specfic connection object for the remote retrieval, you can set it as second parameter

.. code-block:: php

    $api = new BigML\BigML("username", "password");
    $local_anomaly = new BigML\Anomaly('anomaly/502fcbff15526876610002435',
                                 $api);

or even use the remote anomaly information retrieved previously to build the local anomaly detector object

.. code-block:: php

    $api = new BigML\BigML()
    $anomaly = $api->get_anomaly('anomaly/502fcbff15526876610002435',
                                 'limit=-1')

Note that in this example we used a limit=-1 query string for the anomaly retrieval. This ensures that all fields are retrieved by the get method in the same call (unlike in the standard calls where the number of fields returned is limited).

Local Anomaly Scores
--------------------

Using the local anomaly detector object, you can predict the anomaly score associated to an input data set

.. code-block:: php

    $local_anomaly->anomaly_score(array("src_bytes"=> 350))
    0.9268527808726705

As in the local model predictions, producing local anomaly scores can be done independently of BigML servers, so no cost or connection latencies are involved.

Local Deepnet
-------------

You can also instantiate a local version of a remote Deepnet.

.. code-block:: php

    require 'vendor/autoload.php';

    $local_deepnet = new Deepnet('deepnet/502fdbff15526876610022435');

This will retrieve the remote deepnet information, using an implicitly
built ``BigML()`` connection object (see the ``Authentication`` section for
more details on how to set your credentials) and return a ``Deepnet``
object that you can use to make local predictions. If you want to use
a specfic connection object for the remote retrieval, you can set it
as second parameter

.. code-block:: php

     require 'vendor/autoload.php';
     $api = new BigML();

     $local_deepnet = new Deepnet('deepnet/502fdbcf15526876210042435', $api);

You can also reuse a remote Deepnet JSON structure as previously
retrieved to build the local Deepnet object

.. code-block:: php

    require 'vendor/autoload.php';

    $api = new BigML();
    $deepnet = $api->get_deepnet('deepnet/502fdbcf15526876210042435', 'limit=-1');

    $local_deepnet = new Deepnet($deepnet);

Note that in this example we used a ``limit=-1`` query string for the
deepnet retrieval. This ensures that all fields are retrieved by the
get method in the same call (unlike in the standard calls where the
number of fields returned is limited).

Local Deepnet Predictions
-------------------------

Using the local deepnet object, you can predict the prediction for an
input data set

.. code-block:: php

  $local_deepnet->predict(array("petal length" => 2, "sepal length" => 1.5,
                                "petal width" => 0.5, "sepal width" => 0.7));

  array('distribution' => array( array('category' => 'Iris-virginica',
                                       'probability' => 0.5041444478857267),
                                 array('category' => 'Iris-versicolor',
                                       'probability' => 0.46926542042788333),
                                 array('category' => 'Iris-setosa',
                                       'probability' => 0.02659013168639014)),
        'prediction' => 'Iris-virginica',
        'probability' => 0.5041444478857267)

As you can see, the prediction contains the predicted category and the
associated probability. It also shows the distribution of
probabilities for all the possible categories in the objective field.

To be consistent with the ``Model`` class interface, deepnets have also a
``predict_probability`` method, which takes two of the same arguments as
``Model->predict``: ``by_name`` and ``compact``.

As with local Models, if ``compact`` is ``False`` (the default), the output is
a list of maps, each with the keys ``prediction`` and ``probability`` mapped
to the class name and its associated probability.

So, for example

.. code-block:: php

  $local_deepnet->predict_probability(array("petal length" => 2, "sepal length" => 1.5,
                                            "petal width" => 0.5, "sepal width" => 0.7));

  array( array('prediction' => 'Iris-setosa', 'probability' => 0.02659013168639014),
         array('prediction' => 'Iris-versicolor', 'probability' => 0.46926542042788333),
         array('prediction' => 'Iris-virginica', 'probability' => 0.5041444478857267))

If ``compact`` is ``True``, only the probabilities themselves are returned, as
a list in class name order, again, as is the case with local Models.

Local Topic Model
-----------------

You can also instantiate a local version of a remote topic model.

.. code-block:: php

    require 'vendor/autoload.php';

    $local_topic_model = new BigML\TopicModel('topicmodel/502fdbcf15526876210042435');

This will retrieve the remote topic model information, using an
implicitly built ``BigML()`` connection object (see the ``Authentication``
section for more details on how to set your credentials) and return a
``TopicModel`` object that you can use to obtain local topic
distributions. If you want to use a specific connection object for the
remote retrieval, you can set it as second parameter

.. code-block:: php

    require 'vendor/autoload.php';

    $api = new BigML\BigML();
    $local_topic_model = new BigML\TopicModel('topicmodel/502fdbcf15526876210042435', $api);

You can also reuse a remote topic model JSON structure as previously
retrieved to build the local topic model object

.. code-block:: php

    require 'vendor/autoload.php';

    $api = new BigML\BigML();
    $topic_model = $api->get_topicmodel('topicmodel/502fdbcf15526876210042435', 'limit=-1');

    $local_topic_model = new TopicModel($topic_model);

Note that in this example we used a ``limit=-1`` query string for the
topic model retrieval. This ensures that all fields are retrieved by
the get method in the same call (unlike in the standard calls where
the number of fields returned is limited).

Please note you will need to use Composer to import the third-party
stemming library used to create local topic models.

Multi Models
------------

Multi Models use a numbers of BigML remote models to build a local
version that can be used to generate predictions locally. Predictions
are generated combining the outputs of each model

.. code-block:: php

    $multimodel = new BigML\MultiModel(array("model/5111xxxxxxxxxxxxxxxxxx12",model/538Xxxxxxxxxxxxxxxxxxx32"));

or

.. code-block:: php

    $multimodel = new BigML\MultiModel(array("model/5111xxxxxxxxxxxxxxxxxx12",model/538Xxxxxxxxxxxxxxxxxxx32"), $api);

or set default storage if you have storage unset in `$api`

.. code-block:: php

  $multimodel = new BigML\MultiModel(array("model/5111xxxxxxxxxxxxxxxxxx12",model/538Xxxxxxxxxxxxxxxxxxx32"), null, $storage);

    $prediction = $multimodel->predict(array("petal length"=> 3, "petal width"=> 1));

The combination method used by default is plurality for categorical predictions and mean value for numerical ones. You can also use:

confidence weighted

.. code-block:: php

    $prediction = $multimodel->predict(array("petal length"=> 3, "petal width"=> 1), 1);

that will weight each vote using the confidence/error given by the model to each prediction, or even probability weighted

.. code-block:: php

    $prediction = $multimodel->predict(array("petal length"=> 3, "petal width"=> 1), 2);

that weights each vote by using the probability associated to the training distribution at the prediction node.


There’s also a threshold method that uses an additional set of options: threshold and category.
The category is predicted if and only if the number of predictions for that category is at least the threshold value.
Otherwise, the prediction is plurality for the rest of predicted values.

An example of threshold combination method would be

.. code-block:: php

    $prediction = $multimodel->predict(array("petal length"=> 3, "petal width"=> 1), 3, false, array('threshold'=> 3, 'category'=> 'Iris-virginica'));

When making predictions on a test set with a large number of models, batch_predict can be useful to log each model’s predictions in a separated file.
It expects a list of input data values and the directory path to save the prediction files in

.. code-block:: php

    $multimodel->batch_predict(array("petal length"=> 3, "petal width"=> 1, "petal length"=> 1, "petal width"=> 5.1), "data/predictions");

The predictions generated for each model will be stored in an output file in data/predictions using the syntax model_[id of the model]__predictions.csv.
For instance, when using model/50c0de043b563519830001c2 to predict, the output file name will be model_50c0de043b563519830001c2__predictions.csv.
An additional feature is that using reuse=True as argument will force the function to skip the creation of the file if it already exists.
This can be helpful when using repeatedly a bunch of models on the same test set

.. code-block:: php

    $multimodel->batch_predict(array("petal length"=> 3, "petal width"=> 1, "petal length"=> 1, "petal width"=> 5.1), "data/predictions", true, true);


Prediction files can be subsequently retrieved and converted into a votes list using batch_votes

.. code-block:: php

    $multimodel.batch_votes("data/predictions");

which will return a list of MultiVote objects. Each MultiVote contains a list of predictions.

These votes can be further combined to issue a final prediction for each input data element using the method combine

.. code-block:: php

   foreach($multimodel->batch_votes("data/predictions") as $multivote) {
      $prediction = $multivote->combine();
   }

Again, the default method of combination is plurality for categorical predictions and mean value for numerical ones. You can also use confidence weighted

.. code-block:: php

    $prediction = $multivote->combine(1);

or probability weighted

.. code-block:: php

    $prediction = $multivote->combine(2);

You can also get a confidence measure for the combined prediction

.. code-block:: php

    $prediction = $multivote->combine(0, true);

For classification, the confidence associated to the combined prediction is derived by first selecting the model’s predictions
that voted for the resulting prediction and computing the weighted average of their individual confidence.
Nevertheless, when probability weighted is used, the confidence is obtained by using each model’s distribution at the prediction
node to build a probability distribution and combining them. The confidence is then computed as the wilson score interval of the combined distribution
(using as total number of instances the sum of all the model’s distributions original instances at the prediction node)

In regression, all the models predictions’ confidences contribute to the weighted average confidence.

Local Ensembles
---------------

Remote ensembles can also be used locally through the Ensemble class. The simplest way to access an existing ensemble and using it to predict locally is

.. code-block:: php

    $ensemble = new BigML\Ensemble("ensemble/53dxxxxxxxxxxxxxxxxxxafa");

    $ensemble->predict(array("petal length"=>3, "petal width"=> 1));

This call will download all the ensemble related info and store it in a ./storage directory ready to be used to predict.
As in MultipleModel, several prediction combination methods are available, and you can choose another storage directory or even avoid storing at all, for instance

.. code-block:: php

    $api = new BigML("username", "password", false, "storagedirectory");

    ensemble = $api->create_ensemble('dataset/5143a51a37203f2cf7000972');

    $ensemble = new BigML\Ensemble($ensemble, $api);

    $ensemble->predict(array("petal length"=>3, "petal width"=> 1), true, 1);

creates a new ensemble and stores its information in ./storagedirectory folder. Then this information is used to predict locally using the confidence weighted method.

Similarly, local ensembles can also be created by giving a list of models to be combined to issue the final prediction

.. code-block:: php

    $ensemble = new BigML\Ensemble(array('model/50c0de043b563519830001c2','model/50c0de043b5635198300031b'));

    $ensemble->predict(array("petal length": 3, "petal width": 1));

You can also use the `predict_probability` function to obtain a probability prediction for each possible class of the objective field

.. code-block:: php

    $ensemble->predict_probability(array("petal width"=> 0.5));

Rule Generation
---------------

You can also use a local model to generate a IF-THEN rule set that can be very helpful to understand how the model works internally

.. code-block:: php

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

To run all tests

.. code-block:: batch

     cd tests
     configtests.xml
     phpunit.phar --configuration configtests.xml


To Run a specific test

.. code-block:: batch

     phpunit.phar test_01_prediction.php
