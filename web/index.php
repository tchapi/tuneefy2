<?php

  require '../vendor/autoload.php';

  // TODO : Cache of some sorts ?

  $app = new tuneefy\Application();
  $app->configure();
  $app->run();
