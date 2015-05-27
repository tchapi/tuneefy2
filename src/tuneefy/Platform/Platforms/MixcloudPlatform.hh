<?hh // strict

namespace tuneefy\Platform\Platforms;

use tuneefy\Platform\Platform,
    tuneefy\Platform\PlatformResult,
    tuneefy\Platform\WebStreamingPlatformInterface,
    tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity,
    tuneefy\Utils\Utils;

class MixcloudPlatform extends Platform implements WebStreamingPlatformInterface
{
  
  const string NAME = "Mixcloud";
  const string TAG = "mixcloud";
  const string COLOR = "afd8db";

  const string API_ENDPOINT = "http://api.mixcloud.com/";
  const string API_METHOD = Platform::METHOD_GET;

  protected ImmMap<int,?string> $endpoints = ImmMap {
    Platform::LOOKUP_TRACK  => self::API_ENDPOINT . "track/%s",
    Platform::LOOKUP_ALBUM  => null,
    Platform::LOOKUP_ARTIST => self::API_ENDPOINT . "artist/%s",
    Platform::SEARCH_TRACK  => self::API_ENDPOINT . "search",
    Platform::SEARCH_ALBUM  => null,
   // Platform::SEARCH_ARTIST => self::API_ENDPOINT . "search" 
  };
  protected ImmMap<int,?string> $terms = ImmMap {
    Platform::LOOKUP_TRACK  => null,
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
    Platform::SEARCH_TRACK  => Map { "type" => "cloudcast", "limit" => Platform::LIMIT },
    Platform::SEARCH_ALBUM  => Map {},
   // Platform::SEARCH_ARTIST => Map { "type" => "user", "limit" => Platform::LIMIT } 
  };

  // https://www.mixcloud.com/artist/aphex-twin/
  const string REGEX_MIXCLOUD_ARTIST = "/\/artist\/(?P<artist_slug>".Platform::REGEX_FULLSTRING.")[\/]?$/";

  // http://api.mixcloud.com/track/michael-jackson/everybody/
  const string REGEX_MIXCLOUD_TRACK = "/\/track\/(?P<track_long_slug>".Platform::REGEX_FULLSTRING."\/".Platform::REGEX_FULLSTRING.")[\/]?$/";

  public function hasPermalink(string $permalink): bool
  {
    return (strpos($permalink, "mixcloud.") !== false);
  }

  public function expandPermalink(string $permalink, int $mode): ?PlatformResult
  {

    $musical_entity = null;
    $query_words = Vector {$permalink};

    $match = Map {};

    if (preg_match(self::REGEX_MIXCLOUD_TRACK, $permalink, $match)) {

      $response = $this->fetchSync(Platform::LOOKUP_TRACK, $match['track_long_slug']);

      if ($response === null || property_exists($response->data, 'error')) { return null; }

      $entity = $response->data;

      $musical_entity = new TrackEntity($entity->name, new AlbumEntity("", $entity->artist->name, "")); 
      $musical_entity->addLink(static::TAG, $entity->url);

      $query_words = Vector {$entity->artist->name, $entity->name};
      
    } else if (preg_match(self::REGEX_MIXCLOUD_ARTIST, $permalink, $match)) {

      $response = $this->fetchSync(Platform::LOOKUP_ARTIST, $match['artist_slug']);

      if ($response === null || property_exists($response->data, 'error')) { return null; }

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
  
    return null;
    /*
      Below is the actual working code, but it seems unlikely that we're going
      to use it since we search "mixes" by "users" and not real tracks from 
      artists. So this doesn't really make sense to merge that with any of
      the other results from the other platforms, I guess.
      Still, the following works perfectly.
    */
    // $response = await $this->fetch($type, $query);

    // if ($response === null || count($response->data->data) === 0) {
    //   return null;
    // }
    // $entities = $response->data->data;

    // // We actually don't pass the limit to the fetch() 
    // // request since it's not really useful, in fact
    // $length = min(count($entities), $limit?$limit:Platform::LIMIT);
    
    // $musical_entities = Vector {};
    
    // // Tracks bear a "play_count" score
    // // that we're using to rate the results
    // $max_play_count = 1;
    // if ($type === Platform::SEARCH_TRACK) {
    //   $max_play_count = max(intval($entities[0]->play_count),1);
    // }

    // // Normalizing each track found
    // for($i=0; $i<$length; $i++){
    
    //   $current_item = $entities[$i];

    //   if ($type === Platform::SEARCH_TRACK) {
      
    //     $musical_entity = new TrackEntity($current_item->name, new AlbumEntity("", $current_item->user->name, $current_item->pictures->large)); 
    //     $musical_entity->addLink(static::TAG, $current_item->url);
  
    //     $musical_entities->add(new PlatformResult(Map {"score" => $current_item->play_count/$max_play_count}, $musical_entity));
       
    //   }
      
    // }
    
    // return $musical_entities;
    
  }
}
