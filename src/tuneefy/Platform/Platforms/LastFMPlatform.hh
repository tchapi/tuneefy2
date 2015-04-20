<?hh // strict

namespace tuneefy\Platform\Platforms;

use tuneefy\Platform\Platform,
    tuneefy\Platform\PlatformResult,
    tuneefy\Platform\ScrobblingPlatformInterface,
    tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity,
    tuneefy\Utils\Utils;

class LastFMPlatform extends Platform implements ScrobblingPlatformInterface
{
  
  const string NAME = "Last.fm";
  const string TAG = "lastfm";
  const string COLOR = "e41c1c";

  const string API_ENDPOINT = "http://ws.audioscrobbler.com/2.0/";
  const string API_METHOD = Platform::METHOD_GET;

  protected ImmMap<int,?string> $endpoints = ImmMap {
    Platform::LOOKUP_TRACK  => self::API_ENDPOINT,
    Platform::LOOKUP_ALBUM  => self::API_ENDPOINT,
    Platform::LOOKUP_ARTIST => self::API_ENDPOINT,
    Platform::SEARCH_TRACK  => self::API_ENDPOINT,
    Platform::SEARCH_ALBUM  => self::API_ENDPOINT,
   // Platform::SEARCH_ARTIST => self::API_ENDPOINT 
  };
  protected ImmMap<int,?string> $terms = ImmMap {
    Platform::LOOKUP_TRACK  => "track",
    Platform::LOOKUP_ALBUM  => "album",
    Platform::LOOKUP_ARTIST => "artist",
    Platform::SEARCH_TRACK  => "track",
    Platform::SEARCH_ALBUM  => "album",
   // Platform::SEARCH_ARTIST => "artist" 
  };
  protected ImmMap<int,Map<string,mixed>> $options = ImmMap {
    Platform::LOOKUP_TRACK  => Map { "format" => "json", "autocorrect" => 1, "method" => "track.getinfo", "artist" => "%s" },
    Platform::LOOKUP_ALBUM  => Map { "format" => "json", "autocorrect" => 1, "method" => "album.getinfo", "artist" => "%s" },
    Platform::LOOKUP_ARTIST => Map { "format" => "json", "autocorrect" => 1, "method" => "artist.getinfo" },
    Platform::SEARCH_TRACK  => Map { "format" => "json", "autocorrect" => 1, "method" => "track.search", "limit" => Platform::LIMIT },
    Platform::SEARCH_ALBUM  => Map { "format" => "json", "autocorrect" => 1, "method" => "album.search", "limit" => Platform::LIMIT },
   // Platform::SEARCH_ARTIST => Map { "format" => "json", "autocorrect" => 1, "method" => "artist.search", "limit" => Platform::LIMIT } 
  };

  // http://www.lastfm.fr/music/The+Clash/London+Calling/London+Calling
  const string REGEX_LASTFM_TRACK = "/music\/(?P<artist_slug>".Platform::REGEX_FULLSTRING.")\/(?P<album_slug>".Platform::REGEX_FULLSTRING.")\/(?P<track_slug>".Platform::REGEX_FULLSTRING.")$/";
  // http://www.lastfm.fr/music/The+Clash/London+Calling
  const string REGEX_LASTFM_ALBUM = "/music\/(?P<artist_slug>".Platform::REGEX_FULLSTRING.")\/(?P<album_slug>".Platform::REGEX_FULLSTRING.")$/";
  // http://www.lastfm.fr/music/Sex+Pistols
  const string REGEX_LASTFM_ARTIST = "/music\/(?P<artist_slug>".Platform::REGEX_FULLSTRING.")$/";

  public function hasPermalink(string $permalink): bool
  {
    return (strpos($permalink, "lastfm.") !== false || strpos($permalink, "last.fm") !== false);
  }

  protected function addContextOptions(Map<?string, mixed> $data): Map<?string, mixed>
  {
    return $data->add(Pair{"api_key", $this->key});
  }

  public function expandPermalink(string $permalink, int $mode): ?PlatformResult
  {

    $musical_entity = null;
    $query_words = Vector {$permalink};

    $match = Map {};

    if (preg_match(self::REGEX_LASTFM_TRACK, $permalink, $match)) {

      // This is a bit dirty, I must admit.
      $this->options[Platform::LOOKUP_TRACK]["artist"] = $match['artist_slug'];
      $response = $this->fetchSync(Platform::LOOKUP_TRACK, $match['track_slug']);

      if ($response === null || property_exists($response->data, 'error')) { return null; }

      $entity = $response->data->track;

      if (property_exists($entity->album, 'image')) {
        $picture = get_object_vars($entity->album->image[2]);
        $picture = $picture["#text"];
      } else { 
        $picture = "";
      }

      $musical_entity = new TrackEntity($entity->name, new AlbumEntity($entity->album->title, $entity->artist->name, $picture)); 
      $musical_entity->addLink($entity->url);

      $query_words = Vector {$entity->artist->name, $entity->name};
      
    } else if (preg_match(self::REGEX_LASTFM_ALBUM, $permalink, $match)) {
      
      // This is a bit dirty, I must admit.
      $this->options[Platform::LOOKUP_ALBUM]["artist"] = $match['artist_slug'];
      $response = $this->fetchSync(Platform::LOOKUP_ALBUM, $match['album_slug']);

      if ($response === null || property_exists($response->data, 'error')) { return null; }

      $entity = $response->data->album;

      if (property_exists($entity, 'image')) {
        $picture = get_object_vars($entity->image[2]);
        $picture = $picture["#text"];
      } else { 
        $picture = "";
      }

      $musical_entity = new AlbumEntity($entity->name, $entity->artist, $picture);
      $musical_entity->addLink($entity->url);

      $query_words = Vector {$entity->artist, $entity->name};
      
    } else if (preg_match(self::REGEX_LASTFM_ARTIST, $permalink, $match)) {

      $response = $this->fetchSync(Platform::LOOKUP_ARTIST, $match['artist_slug']);

      if ($response === null || property_exists($response->data, 'error')) { return null; }
      
      $query_words = Vector {$response->data->artist->name};

    }
  
    // Consolidate results
    $metadata = Map {"query_words" => $query_words};

    if ($musical_entity !== null) {
      $metadata->add(Pair {"platform", $this->getName()});
    }

    return new PlatformResult($metadata, $musical_entity);
      
  }

  public async function search(int $type, string $query, int $limit, int $mode): Awaitable<?Vector<PlatformResult>>
  {
  
    $response = await $this->fetch($type, $query);

    if ($response === null) {
      return null;
    }
    $entities = $response->data;

    switch ($type) {
      case Platform::SEARCH_TRACK:
        $results = $entities->results->trackmatches->track;
        break;
      case Platform::SEARCH_ALBUM:
        $results = $entities->results->albummatches->album;
        break;
    }

    // We actually don't pass the limit to the fetch() 
    // request since it's not really useful, in fact
    $length = min(count($results), $limit?$limit:Platform::LIMIT);
    
    $musical_entities = Vector {};

    // Normalizing each track found
    for($i=0; $i<$length; $i++){
    
      $current_item = $results[$i];

      if ($type === Platform::SEARCH_TRACK) {
        
        if (property_exists($current_item, 'image')) {
          $picture = get_object_vars($current_item->image[2]);
          $picture = $picture["#text"];
        } else { 
          $picture = "";
        }
        
        $musical_entity = new TrackEntity($current_item->name, new AlbumEntity("", $current_item->artist, $picture)); 
        $musical_entity->addLink($current_item->url);
             
      } else /*if ($type === Platform::SEARCH_ALBUM)*/ {

        if (property_exists($current_item, 'image')) {
          $picture = get_object_vars($current_item->image[2]);
          $picture = $picture["#text"];
        } else { 
          $picture = "";
        }

        $musical_entity = new AlbumEntity($current_item->name, $current_item->artist, $picture); 
        $musical_entity->addLink($current_item->url);
      
      }
      
      $musical_entities->add(new PlatformResult(Map {"score" => Utils::indexScore($i)}, $musical_entity));

    }
    
    return $musical_entities;
    
  }
}
