<?hh // strict

namespace tuneefy\Platforms;

use tuneefy\Platform\Platform,
    tuneefy\Platform\WebStreamingPlatformInterface;

class DeezerPlatform extends Platform implements WebStreamingPlatformInterface
{
  
  const string NAME = "Deezer";

  public function hasPermalink(string $permalink) : bool
  {
    return (strpos($permalink, "deezer.") !== false);
  }

}
