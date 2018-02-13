<?php

namespace BigML;

define("NUMERIC", "numeric");
define("CATEGORICAL", "categorical");

define("TEST_MODEL", "test");
define("SINGLE_MODEL", "single");
define("MODEL_SEARCH", "search");
define("SHUTDOWN", "shutdown");

define("DEFAULT_PORT", 8042);
define("DEFAULT_MAX_JOBS", 4);

define("ERROR", "error");
define("QUEUED", "queued");
define("STARTED", "started");
define("IN_PROGRESS", "in-progress");
define("FINISHED", "finished");

//This can be any x where np.exp(x) + 1 == np.exp(x)
define("LARGE_EXP", 512);

define("EPSILON", 1E-4);

//Model search parameters
define("VALIDATION_FRAC", 0.15);
define("MAX_VALIDATION_ROWS", 4096);
define("LEARN_INCREMENT", 8);
define("MAX_QUEUE", LEARN_INCREMENT * 4);
define("N_CANDIDATES", MAX_QUEUE * 64);

?>