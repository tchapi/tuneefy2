<?hh // partial

  /*
  
    Partial and not strict type checking here since we cannot 
    put top-level code :

    http://docs.hhvm.com/manual/en/hack.unsupportedphpfeatures.toplevelcode.php

  */

  require '../vendor/autoload.php';

  // TODO : Cache of some sorts ?

  $app = new tuneefy\Application();
  $app->configure();
  $app->prepare();
  $app->run();