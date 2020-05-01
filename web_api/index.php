<?php

  require '../vendor/autoload.php';

  $app = new tuneefy\Application();
  $app->configure();
  $app->setupV2ApiRoutes();
  $app->run();
