<?hh // strict

namespace tuneefy\Platform\Platforms;

use tuneefy\Platform\Platform,
    tuneefy\Platform\PlatformResult,
    tuneefy\Platform\WebStoreInterface,
    tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity,
    tuneefy\Utils\Utils;

class ItunesPlatform extends Platform implements WebStoreInterface
{
  
  const string NAME = "iTunes";
  const string TAG = "itunes";
  const string COLOR = "216be4";

  const string API_ENDPOINT = "https://itunes.apple.com/";
  const string API_METHOD = Platform::METHOD_GET;

  protected ImmMap<int,?string> $endpoints = ImmMap {
    Platform::LOOKUP_TRACK  => null,
    Platform::LOOKUP_ALBUM  => self::API_ENDPOINT . "lookup",
    Platform::LOOKUP_ARTIST => self::API_ENDPOINT . "lookup",
    Platform::SEARCH_TRACK  => self::API_ENDPOINT . "search/track",
    Platform::SEARCH_ALBUM  => self::API_ENDPOINT . "search/album",
   // Platform::SEARCH_ARTIST => self::API_ENDPOINT . "search/artist" 
  };
  protected ImmMap<int,?string> $terms = ImmMap {
    Platform::LOOKUP_TRACK  => null,
    Platform::LOOKUP_ALBUM  => "id",
    Platform::LOOKUP_ARTIST => "id",
    Platform::SEARCH_TRACK  => "term",
    Platform::SEARCH_ALBUM  => "term",
   // Platform::SEARCH_ARTIST => "term" 
  };
  protected ImmMap<int,ImmMap<string,mixed>> $options = ImmMap {
    Platform::LOOKUP_TRACK  => ImmMap {},
    Platform::LOOKUP_ALBUM  => ImmMap {},
    Platform::LOOKUP_ARTIST => ImmMap {},
    Platform::SEARCH_TRACK  => ImmMap { "media" => "music", "entity" => "song", "limit" => Platform::LIMIT },
    Platform::SEARCH_ALBUM  => ImmMap { "media" => "music", "entity" => "album", "limit" => Platform::LIMIT },
   // Platform::SEARCH_ARTIST => ImmMap { "media" => "music", "entity" => "musicArtist", "limit" => Platform::LIMIT } 
  };

  // https://itunes.apple.com/us/artist/jack-johnson/id909253
  const string REGEX_ITUNES_ARTIST = "/\/artist\/(?P<artist_name>".Platform::REGEX_FULLSTRING.")\/id(?P<artist_id>".Platform::REGEX_NUMERIC_ID.")$/";

  // https://itunes.apple.com/us/album/weezer/id115255
  const string REGEX_ITUNES_ALBUM = "/\/album\/(?P<album_name>".Platform::REGEX_FULLSTRING.")\/id(?P<album_id>".Platform::REGEX_NUMERIC_ID.")$/";

  public function hasPermalink(string $permalink): bool
  {
    return (strpos($permalink, "itunes.apple.") !== false);
  }

  public function expandPermalink(string $permalink, int $mode): ?PlatformResult
  {

    $musical_entity = null;
    $query_words = Vector {$permalink};

    $match = Map {};

    if (preg_match(self::REGEX_ITUNES_ALBUM, $permalink, $match)) {
     
      $response = $this->fetchSync(Platform::LOOKUP_ALBUM, $match['album_id']);

      if ($response === null || intval($response->data->resultCount) === 0) { return null; }

      $entity = $response->data->results[0];
      $musical_entity = new AlbumEntity($entity->collectionName, $entity->artistName, $entity->artworkUrl100);
      $musical_entity->addLink($entity->collectionViewUrl);

      $query_words = Vector {$entity->artistName, $entity->collectionName};
      
    } else if (preg_match(self::REGEX_ITUNES_ARTIST, $permalink, $match)) {

      $response = $this->fetchSync(Platform::LOOKUP_ARTIST, $match['artist_id']);

      if ($response === null || intval($response->data->resultCount) === 0) { return null; }
      
      $query_words = Vector {$response->data->results[0]->artistName};

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

    if ($response === null || intval($response->data->resultCount) === 0) {
      return null;
    }
    $entities = $response->data->results;

    // We actually don't pass the limit to the fetch() 
    // request since it's not really useful, in fact
    $length = min(intval($response->data->resultCount), $limit?$limit:Platform::LIMIT);
    
    $musical_entities = Vector {};

    // Normalizing each track found
    for($i=0; $i<$length; $i++){
    
      $current_item = $entities[$i];

      if ($type === Platform::SEARCH_TRACK) {
        
        $musical_entity = new TrackEntity($current_item->trackName, new AlbumEntity($current_item->collectionName, $current_item->artistName, $current_item->artworkUrl100)); 
        $musical_entity->addLink($current_item->trackViewUrl);
             
      } else /*if ($type === Platform::SEARCH_ALBUM)*/ {

        $musical_entity = new AlbumEntity($current_item->collectionName, $current_item->artistName, $current_item->artworkUrl100); 
        $musical_entity->addLink($current_item->collectionViewUrl);
      
      }
      
      $musical_entities->add(new PlatformResult(Map {"score" => Utils::indexScore($i)}, $musical_entity));

    }
    
    return $musical_entities;
    
  }
}
