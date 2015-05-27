<?hh // strict

namespace tuneefy\Platform\Platforms;

use tuneefy\Platform\Platform,
    tuneefy\Platform\PlatformResult,
    tuneefy\Platform\WebStreamingPlatformInterface,
    tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity,
    tuneefy\Utils\Utils;

class SoundcloudPlatform extends Platform implements WebStreamingPlatformInterface
{
  
  const string NAME = "Soundcloud";
  const string TAG = "soundcloud";
  const string COLOR = "ff6600";

  const string API_ENDPOINT = "https://api.soundcloud.com/";
  const string API_METHOD = Platform::METHOD_GET;

  protected ImmMap<int,?string> $endpoints = ImmMap {
    Platform::LOOKUP_TRACK  => self::API_ENDPOINT . "resolve.json",
    Platform::LOOKUP_ALBUM  => null,
    Platform::LOOKUP_ARTIST => null,
    Platform::SEARCH_TRACK  => self::API_ENDPOINT . "tracks",
    Platform::SEARCH_ALBUM  => null,
   // Platform::SEARCH_ARTIST => self::API_ENDPOINT . "users" 
  };
  protected ImmMap<int,?string> $terms = ImmMap {
    Platform::LOOKUP_TRACK  => "url",
    Platform::LOOKUP_ALBUM  => null,
    Platform::LOOKUP_ARTIST => null,
    Platform::SEARCH_TRACK  => "q",
    Platform::SEARCH_ALBUM  => null,
   // Platform::SEARCH_ARTIST => "q" // Search for a user, in fact
  };
  protected ImmMap<int,Map<string,mixed>> $options = ImmMap {
    Platform::LOOKUP_TRACK  => Map {},
    Platform::LOOKUP_ALBUM  => Map {},
    Platform::LOOKUP_ARTIST => Map {},
    Platform::SEARCH_TRACK  => Map { "limit" => Platform::LIMIT },
    Platform::SEARCH_ALBUM  => Map { "limit" => Platform::LIMIT },
   // Platform::SEARCH_ARTIST => Map { "limit" => Platform::LIMIT } 
  };

  // http://soundcloud.com/mariecolonna/eminem-feat-tricky-welcome-to
  const string REGEX_SOUNDCLOUD_ALL = "/\/".Platform::REGEX_FULLSTRING."\/".Platform::REGEX_FULLSTRING."[\/]?$/";

  public function hasPermalink(string $permalink): bool
  {
    return (strpos($permalink, "soundcloud.") !== false);
  }

  protected function addContextOptions(Map<?string, mixed> $data): Map<?string, mixed>
  {
    return $data->add(Pair{"client_id", $this->key});
  }

  public function expandPermalink(string $permalink, int $mode): ?PlatformResult
  {

    $musical_entity = null;
    $query_words = Vector {$permalink};

    $match = Map {};

    if (preg_match(self::REGEX_SOUNDCLOUD_ALL, $permalink, $match)) {

      $response = $this->fetchSync(Platform::LOOKUP_TRACK, $permalink);

      if ($response === null || property_exists($response->data, 'errors')) { return null; }

      $entity = $response->data;

      $musical_entity = new TrackEntity($entity->title, new AlbumEntity("", $entity->user->username, $entity->artwork_url?$entity->artwork_url:"")); 
      $musical_entity->addLink(static::TAG, $entity->permalink_url);

      $query_words = Vector {$entity->user->username, $entity->title};
      
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

    if ($response === null || count($response->data) === 0) {
      return null;
    }
    $entities = $response->data;

    // We actually don't pass the limit to the fetch() 
    // request since it's not really useful, in fact
    $length = min(count($entities), $limit?$limit:Platform::LIMIT);
    
    $musical_entities = Vector {};
    
    // Tracks bear a "playback_count" score
    // that we're using to rate the results
    $max_playback_count = 1;
    if ($type === Platform::SEARCH_TRACK) {
      $max_playback_count = max(intval($entities[0]->playback_count),1);
    }

    // Normalizing each track found
    for($i=0; $i<$length; $i++){
    
      $current_item = $entities[$i];

      if ($type === Platform::SEARCH_TRACK) {
      
        $musical_entity = new TrackEntity($current_item->title, new AlbumEntity("", $current_item->user->username, $current_item->artwork_url?$current_item->artwork_url:"")); 
        $musical_entity->addLink(static::TAG, $current_item->permalink_url);
  
        $musical_entities->add(new PlatformResult(Map {"score" => $current_item->playback_count/$max_playback_count}, $musical_entity));
       
      }
      
    }
    
    return $musical_entities;
    
  }
}
