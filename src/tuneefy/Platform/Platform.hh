<?hh // strict

namespace tuneefy\Platform;

use tuneefy\Platform\GeneralPlatformInterface;

abstract class Platform implements GeneralPlatformInterface
{

  const string NAME = "";
  const string SAFE_NAME = "";
  const string COLOR = "FFFFFF";

  private bool $default;

  private Map<string, bool> $enables;
  private Map<string, bool> $capabilities;

  public function __construct(mixed $options)
  {
    $this->default = false;
    $this->capabilities = Map {};
    //->add(Pair {$key, $value});
    $this->enables = Map {};

  }

  public function getName(): string
  {
    return self::NAME;
  }

  public function getSafeName(): string
  {
    return self::SAFE_NAME;
  }

  public function getColor(): string
  {
    return self::COLOR;
  }

  // Enabled & default
  public function isDefault(): bool
  {
    return $this->default;
  }

  public function isEnabledForApi(): bool
  {
    return $this->enables['api'];
  }

  public function isEnabledForWebsite(): bool
  {
    return $this->enables['website'];
  }


  // Capabilities
  public function isCapableOfSearchingTracks(): bool
  {
    return $this->capabilities['track_search'];
  }

  public function isCapableOfSearchingAlbums(): bool
  {
    return $this->capabilities['album_search'];
  }

  public function isCapableOfLookingUp(): bool
  {
    return $this->capabilities['lookup'];
  }

}
