<?hh // strict

namespace tuneefy\Platform\Platforms;

use tuneefy\Platform\Platform,
    tuneefy\Platform\PlatformResult,
    tuneefy\Platform\WebStreamingPlatformInterface,
    tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity,
    tuneefy\Utils\Utils;

class RdioPlatform extends Platform implements WebStreamingPlatformInterface
{
  
  const string NAME = "Rdio";
  const string TAG = "rdio";
  const string COLOR = "2fb9fd";

  const string API_ENDPOINT = "http://api.rdio.com/1/";
  const string API_METHOD = Platform::METHOD_POST;
  const bool NEEDS_OAUTH = true;

  protected ImmMap<int,?string> $endpoints = ImmMap {
    Platform::LOOKUP_TRACK  => self::API_ENDPOINT,
    Platform::LOOKUP_ALBUM  => self::API_ENDPOINT,
    Platform::LOOKUP_ARTIST => self::API_ENDPOINT,
    Platform::SEARCH_TRACK  => self::API_ENDPOINT,
    Platform::SEARCH_ALBUM  => self::API_ENDPOINT,
   // Platform::SEARCH_ARTIST => self::API_ENDPOINT
  };
  protected ImmMap<int,?string> $terms = ImmMap {
    Platform::LOOKUP_TRACK  => "keys",
    Platform::LOOKUP_ALBUM  => "keys",
    Platform::LOOKUP_ARTIST => "keys",
    Platform::SEARCH_TRACK  => "query",
    Platform::SEARCH_ALBUM  => "query",
   // Platform::SEARCH_ARTIST => "query" 
  };
  protected ImmMap<int,ImmMap<string,mixed>> $options = ImmMap {
    Platform::LOOKUP_TRACK  => ImmMap { "method" => "get", "extras" => "-*,name,album,artist,icon,type" }, // type = t
    Platform::LOOKUP_ALBUM  => ImmMap { "method" => "get", "extras" => "-*,name,artist,icon,type" }, // type = a 
    Platform::LOOKUP_ARTIST => ImmMap { "method" => "get", "extras" => "-*,name,icon,type" }, // type = r
    Platform::SEARCH_TRACK  => ImmMap { "method" => "search", "types" => "Track", "count" => Platform::LIMIT },
    Platform::SEARCH_ALBUM  => ImmMap { "method" => "search", "types" => "Album", "count" => Platform::LIMIT },
   // Platform::SEARCH_ARTIST => ImmMap { "method" => "search", "types" => "Artist", "count" => Platform::LIMIT } 
  };

  // http://open.spotify.com/track/5jhJur5n4fasblLSCOcrTp
  const string REGEX_SPOTIFY_ALL = "/(artist|album|track)(:|\/)(?P<item_id>[a-zA-Z0-9]*)$/";
    
  // LOCAL files : http://open.spotify.com/local/hang+the+bastard/raw+sorcery/doomed+fucking+doomed/206
  const string REGEX_SPOTIFY_LOCAL = "/local\/(?P<artist_name>".Platform::REGEX_FULLSTRING.")\/(?P<album_name>".Platform::REGEX_FULLSTRING.")\/(?P<track_name>".Platform::REGEX_FULLSTRING.")\/[0-9]+$/";
    

  public function hasPermalink(string $permalink): bool
  {
    return (strpos($permalink, "rdio.") !== false);
  }

  public function expandPermalink(string $permalink): ?PlatformResult
  {
    return null;
  }

  public async function search(int $type, string $query, int $limit): Awaitable<?Vector<PlatformResult>>
  {
    return null;
  }
}