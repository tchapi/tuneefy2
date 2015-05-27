<?hh // strict

namespace tuneefy\Platform\Platforms;

use tuneefy\Platform\Platform,
    tuneefy\Platform\PlatformResult,
    tuneefy\Platform\WebStreamingPlatformInterface,
    tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity,
    tuneefy\Utils\Utils;

class YoutubePlatform extends Platform implements WebStreamingPlatformInterface
{
  
  const string NAME = "Youtube";
  const string TAG = "youtube";
  const string COLOR = "c8120b";

  const string API_ENDPOINT = "https://www.googleapis.com/youtube/v3/";
  const string API_METHOD = Platform::METHOD_GET;

  protected ImmMap<int,?string> $endpoints = ImmMap {
    Platform::LOOKUP_TRACK  => self::API_ENDPOINT . "videos",
    Platform::LOOKUP_ALBUM  => null,
    Platform::LOOKUP_ARTIST => null,
    Platform::SEARCH_TRACK  => self::API_ENDPOINT . "search",
    Platform::SEARCH_ALBUM  => null,
   // Platform::SEARCH_ARTIST => null 
  };
  protected ImmMap<int,?string> $terms = ImmMap {
    Platform::LOOKUP_TRACK  => "id",
    Platform::LOOKUP_ALBUM  => null,
    Platform::LOOKUP_ARTIST => null,
    Platform::SEARCH_TRACK  => "q",
    Platform::SEARCH_ALBUM  => null,
   // Platform::SEARCH_ARTIST => null 
  };
  protected ImmMap<int,Map<string,mixed>> $options = ImmMap {
    Platform::LOOKUP_TRACK  => Map { "part" => "snippet" },
    Platform::LOOKUP_ALBUM  => Map {},
    Platform::LOOKUP_ARTIST => Map {},
    Platform::SEARCH_TRACK  => Map { "part" => "snippet", "order" => "relevance", "type" => "video", "videoCategoryId" => "10", "maxResults" => Platform::LIMIT }, // Music category
    Platform::SEARCH_ALBUM  => Map {},
   // Platform::SEARCH_ARTIST => Map {} 
  };

  const string REGEX_YOUTUBE_ALL = "/\/watch\?v\=(?P<video_id>[a-zA-Z0-9\-\_]*)(|\&(.*))$/";

  public function hasPermalink(string $permalink): bool
  {
    return (strpos($permalink, "youtube.") !== false);
  }

  protected function addContextOptions(Map<?string, mixed> $data): Map<?string, mixed>
  {
    return $data->add(Pair{"key", $this->key});
  }

  private function getPermalinkFromTrackId(string $video_id): string
  {
    return sprintf("https://www.youtube.com/watch?v=%s", $video_id);
  }

  public function expandPermalink(string $permalink, int $mode): ?PlatformResult
  {

    $musical_entity = null;
    $query_words = Vector {$permalink};

    $match = Map {};

    if (preg_match(self::REGEX_YOUTUBE_ALL, $permalink, $match)) {

      $response = $this->fetchSync(Platform::LOOKUP_TRACK, $match['video_id']);

      if ($response === null || count($response->data->items) === 0) { return null; }

      $entity = $response->data->items[0];
      $musical_entity = new TrackEntity($entity->snippet->title, new AlbumEntity("", "", $entity->snippet->thumbnails->medium->url)); 
      $musical_entity->addLink(static::TAG, $this->getPermalinkFromTrackId($entity->id));

      $query_words = Vector {$entity->snippet->title};
      
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

    if ($response === null || count($response->data->items) === 0) {
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
        
        $musical_entity = new TrackEntity($current_item->snippet->title, new AlbumEntity("", "", $current_item->snippet->thumbnails->medium->url)); 
        $musical_entity->addLink(static::TAG, $this->getPermalinkFromTrackId($current_item->id->videoId));
        $musical_entities->add(new PlatformResult(Map {"score" => Utils::indexScore($i)}, $musical_entity));
             
      }
      

    }
    
    return $musical_entities;
    
  }
}
