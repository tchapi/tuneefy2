<?hh // strict

namespace tuneefy\Platform;

interface WebStreamingPlatformInterface
{

  public function hasPermalink(string $permalink): bool;

}
