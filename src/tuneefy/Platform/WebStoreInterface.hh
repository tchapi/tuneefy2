<?hh // strict

namespace tuneefy\Platform;

interface WebStoreInterface
{

  public function hasPermalink(string $permalink): bool;
  public function expandPermalink(string $permalink, int $mode): ?PlatformResult;

}
