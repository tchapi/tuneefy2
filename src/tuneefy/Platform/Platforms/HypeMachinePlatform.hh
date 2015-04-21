<?hh // strict

namespace tuneefy\Platform\Platforms;

use tuneefy\Platform\Platform,
    tuneefy\Platform\PlatformResult,
    tuneefy\Platform\WebStreamingPlatformInterface,
    tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity,
    tuneefy\Utils\Utils;

class HypeMachinePlatform extends Platform implements WebStreamingPlatformInterface
{
  
  const string NAME = "Hype Machine";
  const string TAG = "hypem";
  const string COLOR = "83C441";

  const string API_ENDPOINT = "http://hypem.com/";
  const string API_METHOD = Platform::METHOD_GET;

  protected ImmMap<int,?string> $endpoints = ImmMap {
    Platform::LOOKUP_TRACK  => self::API_ENDPOINT . "playlist/item/%s/json/1/data.js",
    Platform::LOOKUP_ALBUM  => null,
    Platform::LOOKUP_ARTIST => self::API_ENDPOINT . "playlist/artist/%s/json/1/data.js",
    Platform::SEARCH_TRACK  => self::API_ENDPOINT . "playlist/search/%s/json/1/data.js",
    Platform::SEARCH_ALBUM  => self::API_ENDPOINT . "playlist/search/%s/json/1/data.js",
   // Platform::SEARCH_ARTIST => self::API_ENDPOINT . "playlist/search/%s/json/1/data.js"
  };
  protected ImmMap<int,?string> $terms = ImmMap {
    Platform::LOOKUP_TRACK  => null,
    Platform::LOOKUP_ALBUM  => null,
    Platform::LOOKUP_ARTIST => null,
    Platform::SEARCH_TRACK  => null,
    Platform::SEARCH_ALBUM  => null,
   // Platform::SEARCH_ARTIST => null 
  };
  protected ImmMap<int,Map<string,mixed>> $options = ImmMap {
    Platform::LOOKUP_TRACK  => Map {},
    Platform::LOOKUP_ALBUM  => Map {},
    Platform::LOOKUP_ARTIST => Map {},
    Platform::SEARCH_TRACK  => Map {},
    Platform::SEARCH_ALBUM  => Map {},
   // Platform::SEARCH_ARTIST => Map {} 
  };

  // http://hypem.com/item/1arwr/Digitalism+-+2+Hearts
  const string REGEX_HYPEM_TRACK = "/\/(?:item|track)\/(?P<track_id>[0-9a-zA-Z]*)(|\/(?P<track_slug>".Platform::REGEX_FULLSTRING."))[\/]?$/";
  // http://hypem.com/artist/Digitalism
  const string REGEX_HYPEM_ARTIST = "/\/artist\/(?P<artist_slug>".Platform::REGEX_FULLSTRING.")[\/]?$/";
    
  public function hasPermalink(string $permalink): bool
  {
    return (strpos($permalink, "hypem.") !== false);
  }

  private function getPermalinkFromTrackId(string $track_id): string
  {
    return sprintf("http://hypem.com/track/%s", $track_id);
  }

  public function expandPermalink(string $permalink, int $mode): ?PlatformResult
  {

    $musical_entity = null;
    $query_words = Vector {$permalink};

    $match = Map {};

    if (preg_match(self::REGEX_HYPEM_TRACK, $permalink, $match)) {

      $response = $this->fetchSync(Platform::LOOKUP_TRACK, $match['track_id']);

      if ($response === null || !property_exists($response->data, '0')) { return null; }

      $entity = array_values(get_object_vars($response->data)); // "O" as a key, seriously ?

      // No cover : on HypeM, covers are not the album's, so they are not relevant
      $musical_entity = new TrackEntity($entity[1]->title, new AlbumEntity("", $entity[1]->artist, "")); 
      $musical_entity->addLink($permalink);

      $query_words = Vector {$entity[1]->artist, $entity[1]->title};
      
    } else if (preg_match(self::REGEX_HYPEM_ARTIST, $permalink, $match)) {

      $response = $this->fetchSync(Platform::LOOKUP_ARTIST, $match['artist_slug']);

      if ($response === null || !property_exists($response->data, '0')) { return null; }
      
      $entity = array_values(get_object_vars($response->data));
      $query_words = Vector {$entity[1]->artist};

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

    if ($response === null || !property_exists($response->data, '0')) {
      return null;
    }
    $entities = array_values(get_object_vars($response->data)); // "O" as a key, seriously ?

    // -1 since we have this "version" key/value pair that gets in the way
    $length = min(count($entities), $limit?$limit:Platform::LIMIT);
    
    $musical_entities = Vector {};
    // Normalizing each track found
    for($i=0; $i<$length; $i++){
    
      $current_item = $entities[$i];
      if (get_class($current_item) !== "stdClass") { continue; }

      if ($type === Platform::SEARCH_TRACK) {
        
        $musical_entity = new TrackEntity($current_item->title, new AlbumEntity("", $current_item->artist, "")); 
        $musical_entity->addLink($this->getPermalinkFromTrackId($current_item->mediaid));
        $musical_entities->add(new PlatformResult(Map {"score" => Utils::indexScore($i)}, $musical_entity));
  
      }

      
    }
    
    return $musical_entities;
    
  }
}
