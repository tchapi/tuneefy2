<?hh // strict

namespace tuneefy\Platform;

use tuneefy\Platform\GeneralPlatformInterface;

abstract class Platform implements GeneralPlatformInterface
{

  const string NAME = "";

  public function getName() : string
  {
    return self::NAME;
  }

}
