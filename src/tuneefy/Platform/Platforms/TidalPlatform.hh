<?hh // strict

namespace tuneefy\Platform\Platforms;

use tuneefy\Platform\Platform,
    tuneefy\Platform\PlatformResult,
    tuneefy\Platform\WebStreamingPlatformInterface,
    tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity,
    tuneefy\Utils\Utils;

class TidalPlatform extends Platform implements WebStreamingPlatformInterface
{
  
  const string NAME = "Tidal";
  const string TAG = "tidal";
  const string COLOR = "00FFFF";

  const string API_ENDPOINT = "https://listen.tidalhifi.com/v1/";
  const string API_METHOD = Platform::METHOD_GET;

  protected ImmMap<int,?string> $endpoints = ImmMap {
    Platform::LOOKUP_TRACK  => self::API_ENDPOINT . "tracks/%s",
    Platform::LOOKUP_ALBUM  => self::API_ENDPOINT . "albums/%s",
    Platform::LOOKUP_ARTIST => self::API_ENDPOINT . "artists/%s",
    Platform::SEARCH_TRACK  => self::API_ENDPOINT . "search/tracks",
    Platform::SEARCH_ALBUM  => self::API_ENDPOINT . "search/albums",
   // Platform::SEARCH_ARTIST => self::API_ENDPOINT . "search/artists" 
  };
  protected ImmMap<int,?string> $terms = ImmMap {
    Platform::LOOKUP_TRACK  => null,
    Platform::LOOKUP_ALBUM  => null,
    Platform::LOOKUP_ARTIST => null,
    Platform::SEARCH_TRACK  => "query",
    Platform::SEARCH_ALBUM  => "query",
   // Platform::SEARCH_ARTIST => "query" 
  };
  protected ImmMap<int,Map<string,mixed>> $options = ImmMap {
    Platform::LOOKUP_TRACK  => Map { "countryCode" => "FR" },
    Platform::LOOKUP_ALBUM  => Map { "countryCode" => "FR" },
    Platform::LOOKUP_ARTIST => Map { "countryCode" => "FR" },
    Platform::SEARCH_TRACK  => Map { "countryCode" => "FR", "limit" => Platform::LIMIT },
    Platform::SEARCH_ALBUM  => Map { "countryCode" => "FR", "limit" => Platform::LIMIT },
   // Platform::SEARCH_ARTIST => Map { "countryCode" => "FR", "limit" => Platform::LIMIT } 
  };

  // http://www.tidal.com/track/40358305
  const string REGEX_TIDAL_TRACK = "/track\/(?P<track_id>".Platform::REGEX_NUMERIC_ID.")[\/]?$/";
  // http://www.tidal.com/album/571179
  const string REGEX_TIDAL_ALBUM = "/\/album\/(?P<album_id>".Platform::REGEX_NUMERIC_ID.")[\/]?$/";
  // http://www.tidal.com/artist/3528326
  const string REGEX_TIDAL_ARTIST = "/artist\/(?P<artist_id>".Platform::REGEX_FULLSTRING.")[\/]?$/";

  public function hasPermalink(string $permalink): bool
  {
    return (strpos($permalink, "tidal.") !== false || strpos($permalink, "tidalhifi.") !== false);
  }

  protected function addContextOptions(Map<?string, mixed> $data): Map<?string, mixed>
  {
    return $data->add(Pair {"token", $this->key});
  }

  private function getCoverUrlFromCoverHash(string $cover_hash): string
  {
    return sprintf("http://resources.wimpmusic.com/images/%s/320x320.jpg", str_replace('-', '/', $cover_hash));
  }

  public function expandPermalink(string $permalink, int $mode): ?PlatformResult
  {

    $musical_entity = null;
    $query_words = Vector {$permalink};

    $match = Map {};

    if (preg_match(self::REGEX_TIDAL_TRACK, $permalink, $match)) {

      $response = $this->fetchSync(Platform::LOOKUP_TRACK, $match['track_id']);

      if ($response === null || (property_exists($response->data, 'status') && $response->data->status === "error") ) { return null; }

      $entity = $response->data;
      $musical_entity = new TrackEntity($entity->title, new AlbumEntity($entity->album->title, $entity->artist->name, $this->getCoverUrlFromCoverHash($entity->album->cover))); 
      $musical_entity->addLink($entity->url);

      $query_words = Vector {$entity->artist->name, $entity->title};
      
    } else if (preg_match(self::REGEX_TIDAL_ALBUM, $permalink, $match)) {
     
      $response = $this->fetchSync(Platform::LOOKUP_ALBUM, $match['album_id']);

      if ($response === null || (property_exists($response->data, 'status') && $response->data->status === "error") ) { return null; }

      $entity = $response->data;
      $musical_entity = new AlbumEntity($entity->title, $entity->artist->name, $this->getCoverUrlFromCoverHash($entity->cover));
      $musical_entity->addLink($entity->url);

      $query_words = Vector {$entity->artist->name, $entity->title};
      
    } else if (preg_match(self::REGEX_TIDAL_ARTIST, $permalink, $match)) {

      $response = $this->fetchSync(Platform::LOOKUP_ARTIST, $match['artist_id']);

      if ($response === null || (property_exists($response->data, 'status') && $response->data->status === "error") ) { return null; }

      $query_words = Vector {$response->data->name};

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

    if ($response === null || intval($response->data->items) === 0) {
      return null;
    }
    $entities = $response->data->items;

    // We actually don't pass the limit to the fetch() 
    // request since it's not really useful, in fact
    $length = min(count($entities), $limit?$limit:Platform::LIMIT);
    
    $musical_entities = Vector {};

    // Normalizing each track found
    for($i=0; $i<$length; $i++){
    
      $current_item = $entities[$i];

      if ($type === Platform::SEARCH_TRACK) {
        
        $musical_entity = new TrackEntity($current_item->title, new AlbumEntity($current_item->album->title, $current_item->artist->name, $this->getCoverUrlFromCoverHash($current_item->album->cover))); 
        $musical_entity->addLink($current_item->url);
             
      } else /*if ($type === Platform::SEARCH_ALBUM)*/ {

        $musical_entity = new AlbumEntity($current_item->title, $current_item->artist->name, $this->getCoverUrlFromCoverHash($current_item->cover)); 
        $musical_entity->addLink($current_item->url);
      
      }

      // Tidal has a $current_item->popularity key, but right now, it's kind of ... empty.
      $musical_entities->add(new PlatformResult(Map {"score" => Utils::indexScore($i)}, $musical_entity));

    }
    
    return $musical_entities;
    
  }
}
