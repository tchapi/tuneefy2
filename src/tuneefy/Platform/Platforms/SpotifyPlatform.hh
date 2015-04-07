<?hh // strict

namespace tuneefy\Platform\Platforms;

use tuneefy\Platform\Platform,
    tuneefy\Platform\WebStreamingPlatformInterface,
    tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity,
    tuneefy\Utils\Utils;

class SpotifyPlatform extends Platform implements WebStreamingPlatformInterface
{
  
  const string NAME = "Spotify";
  const string TAG = "spotify";
  const string COLOR = "4DA400";

  const string API_ENDPOINT = "https://api.spotify.com/v1/";
  const string API_METHOD = Platform::METHOD_GET;
  const bool NEEDS_OAUTH = false;

  protected ImmMap<int,?string> $endpoints = ImmMap {
    Platform::LOOKUP_TRACK  => self::API_ENDPOINT . "tracks/%s",
    Platform::LOOKUP_ALBUM  => self::API_ENDPOINT . "albums/%s",
    Platform::LOOKUP_ARTIST => self::API_ENDPOINT . "artists/%s",
    Platform::SEARCH_TRACK  => self::API_ENDPOINT . "search",
    Platform::SEARCH_ALBUM  => self::API_ENDPOINT . "search",
   // Platform::SEARCH_ARTIST => self::API_ENDPOINT . "search" 
  };
  protected ImmMap<int,?string> $terms = ImmMap {
    Platform::LOOKUP_TRACK  => null,
    Platform::LOOKUP_ALBUM  => null,
    Platform::LOOKUP_ARTIST => null,
    Platform::SEARCH_TRACK  => "q",
    Platform::SEARCH_ALBUM  => "q",
   // Platform::SEARCH_ARTIST => "q" 
  };
  protected ImmMap<int,ImmMap<string,mixed>> $options = ImmMap {
    Platform::LOOKUP_TRACK  => ImmMap {},
    Platform::LOOKUP_ALBUM  => ImmMap {},
    Platform::LOOKUP_ARTIST => ImmMap {},
    Platform::SEARCH_TRACK  => ImmMap { "type" => "track", "limit" => Platform::LIMIT },
    Platform::SEARCH_ALBUM  => ImmMap { "type" => "album", "limit" => Platform::LIMIT },
   // Platform::SEARCH_ARTIST => ImmMap { "type" => "artist", "limit" => Platform::LIMIT } 
  };

  // http://open.spotify.com/track/5jhJur5n4fasblLSCOcrTp
  const string REGEX_SPOTIFY_ALL = "/(artist|album|track)(:|\/)(?P<item_id>[a-zA-Z0-9]*)$/";
    
  // LOCAL files : http://open.spotify.com/local/hang+the+bastard/raw+sorcery/doomed+fucking+doomed/206
  const string REGEX_SPOTIFY_LOCAL = "/local\/(?P<artist_name>".Platform::REGEX_FULLSTRING.")\/(?P<album_name>".Platform::REGEX_FULLSTRING.")\/(?P<track_name>".Platform::REGEX_FULLSTRING.")\/[0-9]+$/";
    

  public function hasPermalink(string $permalink): bool
  {
    return (strpos($permalink, "spotify:") !== false || strpos($permalink, "open.spotify.") !== false || strpos($permalink, "play.spotify.") !== false);
  }

  public function expandPermalink(string $permalink): ?Map<string,mixed>
  {
    return null;
  }

  public async function search(int $type, string $query, int $limit): Awaitable<?Vector<Map<string,mixed>>>
  {
    return null;
  }
}