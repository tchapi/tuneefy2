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
    Platform::LOOKUP_TRACK  => "url",
    Platform::LOOKUP_ALBUM  => "url",
    Platform::LOOKUP_ARTIST => "url",
    Platform::SEARCH_TRACK  => "query",
    Platform::SEARCH_ALBUM  => "query",
   // Platform::SEARCH_ARTIST => "query" 
  };
  protected ImmMap<int,Map<string,mixed>> $options = ImmMap {
    Platform::LOOKUP_TRACK  => Map { "method" => "getObjectFromUrl", "extras" => "-*,name,album,artist,icon,type" }, // type = t
    Platform::LOOKUP_ALBUM  => Map { "method" => "getObjectFromUrl", "extras" => "-*,name,artist,icon,type" }, // type = a 
    Platform::LOOKUP_ARTIST => Map { "method" => "getObjectFromUrl", "extras" => "-*,name,icon,type" }, // type = r
    Platform::SEARCH_TRACK  => Map { "method" => "search", "types" => "Track", "count" => Platform::LIMIT },
    Platform::SEARCH_ALBUM  => Map { "method" => "search", "types" => "Album", "count" => Platform::LIMIT },
   // Platform::SEARCH_ARTIST => Map { "method" => "search", "types" => "Artist", "count" => Platform::LIMIT } 
  };

  // http://www.rdio.com/artist/The_Rolling_Stones/
  // http://www.rdio.com/#/artist/David_Myhr/album/Soundshine
  // http://www.rdio.com/artist/Kaskade/album/Never_Sleep_Alone/track/Never_Sleep_Alone/
  const string REGEX_RDIO_TRACK = "/artist\/(?P<artist_slug>".Platform::REGEX_FULLSTRING.")\/album\/(?P<album_slug>".Platform::REGEX_FULLSTRING.")\/track\/(?P<track_slug>".Platform::REGEX_FULLSTRING.")[\/]?$/";
  const string REGEX_RDIO_ALBUM = "/artist\/(?P<artist_slug>".Platform::REGEX_FULLSTRING.")\/album\/(?P<album_slug>".Platform::REGEX_FULLSTRING.")[\/]?$/";
  const string REGEX_RDIO_ARTIST = "/artist\/(?P<artist_slug>".Platform::REGEX_FULLSTRING.")[\/]?$/";
    
  public function hasPermalink(string $permalink): bool
  {
    return (strpos($permalink, "rdio.") !== false);
  }

  public function expandPermalink(string $permalink, int $mode): ?PlatformResult
  {

    $musical_entity = null;
    $query_words = Vector {$permalink};

    $match = Map {};

    if (preg_match(self::REGEX_RDIO_TRACK, $permalink, $match)) {

      $response = $this->fetchSync(Platform::LOOKUP_TRACK, $permalink);

      if ($response === null || $response->data->status === "error") { return null; }

      $entity = $response->data->result;
      $musical_entity = new TrackEntity($entity->name, new AlbumEntity($entity->album, $entity->artist, $entity->icon)); 
      $musical_entity->addLink(static::TAG, $permalink);

      $query_words = Vector {$entity->artist, $entity->name};
      
    } else if (preg_match(self::REGEX_RDIO_ALBUM, $permalink, $match)) {
     
      $response = $this->fetchSync(Platform::LOOKUP_ALBUM, $permalink);

      if ($response === null || $response->data->status === "error") { return null; }

      $entity = $response->data->result;
      $musical_entity = new AlbumEntity($entity->name, $entity->artist, $entity->icon);
      $musical_entity->addLink(static::TAG, $permalink);

      $query_words = Vector {$entity->artist, $entity->name};
      
    } else if (preg_match(self::REGEX_RDIO_ARTIST, $permalink, $match)) {

      $response = $this->fetchSync(Platform::LOOKUP_ARTIST, $permalink);

      if ($response === null || $response->data->status === "error") { return null; }
      
      $query_words = Vector {$response->data->result->name};

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

    if ($response === null || intval($response->data->result->results) === 0) {
      return null;
    }
    $entities = $response->data->result->results;

    // We actually don't pass the limit to the fetch() 
    // request since it's not really useful, in fact
    $length = min(count($entities), $limit?$limit:Platform::LIMIT);
    
    $musical_entities = Vector {};

    // Normalizing each track found
    for($i=0; $i<$length; $i++){
    
      $current_item = $entities[$i];

      if ($type === Platform::SEARCH_TRACK) {
        
        $musical_entity = new TrackEntity($current_item->name, new AlbumEntity($current_item->album, $current_item->artist, $current_item->icon)); 
        $musical_entity->addLink(static::TAG, $current_item->shortUrl);
             
      } else /*if ($type === Platform::SEARCH_ALBUM)*/ {

        $musical_entity = new AlbumEntity($current_item->name, $current_item->artist, $current_item->icon); 
        $musical_entity->addLink(static::TAG, $current_item->shortUrl);
      
      }
      
      $musical_entities->add(new PlatformResult(Map {"score" => Utils::indexScore($i)}, $musical_entity));

    }
    
    return $musical_entities;
  }
}