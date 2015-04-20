<?hh // strict

namespace tuneefy\Platform\Platforms;

use tuneefy\Platform\Platform,
    tuneefy\Platform\PlatformResult,
    tuneefy\Platform\WebStreamingPlatformInterface,
    tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity,
    tuneefy\Utils\Utils;

class GroovesharkPlatform extends Platform implements WebStreamingPlatformInterface
{
  
  const string NAME = "Grooveshark";
  const string TAG = "grooveshark";
  const string COLOR = "999999";

  const string API_ENDPOINT = "http://tinysong.com/";
  const string API_METHOD = Platform::METHOD_GET;

  protected ImmMap<int,?string> $endpoints = ImmMap {
    Platform::LOOKUP_TRACK  => null,
    Platform::LOOKUP_ALBUM  => null,
    Platform::LOOKUP_ARTIST => null,
    Platform::SEARCH_TRACK  => self::API_ENDPOINT . "s/%s",
    Platform::SEARCH_ALBUM  => self::API_ENDPOINT . "s/%s",
   // Platform::SEARCH_ARTIST => self::API_ENDPOINT . "s/%s" 
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
    Platform::SEARCH_TRACK  => Map { "format" => "json", "limit" => Platform::LIMIT },
    Platform::SEARCH_ALBUM  => Map { "format" => "json", "limit" => Platform::LIMIT },
   // Platform::SEARCH_ARTIST => Map { "format" => "json", "limit" => Platform::LIMIT }, 
  };


  // http://grooveshark.com/s/Sweet+Sweet+Heartkiller/2GVBvD?src=5
  // http://grooveshark.com/#!/s/Sweet+Sweet+Heartkiller/2GVBvD?src=5
  // http://grooveshark.com/#!/s/~/2GVBvD?src=11 if we're not lucky ...
  const string REGEX_GROOVESHARK_TRACK = "/\/s\/(?P<track_name>".Platform::REGEX_FULLSTRING.")\/([a-zA-Z0-9]*)\?([a-zA-Z0-9%\+\=-]*)$/";
  // http://grooveshark.com/album/Impeccable+Blahs/1529354
  // http://grooveshark.com/#!/album/Impeccable+Blahs/1529354
  const string REGEX_GROOVESHARK_ALBUM_ARTIST = "/\/(?P<type>album|artist)\/(?P<name>".Platform::REGEX_FULLSTRING.")\/(?P<id>".Platform::REGEX_NUMERIC_ID.")$/";

  public function hasPermalink(string $permalink): bool
  {
    return (strpos($permalink, "grooveshark.") !== false);
  }

  protected function addContextOptions(Map<?string, mixed> $data): Map<?string, mixed>
  {
    return $data->add(Pair{"key", $this->key});
  }

  public function expandPermalink(string $permalink, int $mode): ?PlatformResult
  {

    // We can't really fetch info about any Grooveshark url,
    // so we're trying to extract information from the url itself ...

    $query_words = Vector {$permalink};
    $match = Map {};

    if (preg_match(self::REGEX_GROOVESHARK_TRACK, $permalink, $match)) {

      $query_words = Vector {urldecode($match['track_name'])};
      
    } else if (preg_match(self::REGEX_GROOVESHARK_ALBUM_ARTIST, $permalink, $match)) {
     
      $query_words = Vector {urldecode($match['name'])};
      
    }
  
    // Consolidate results
    $metadata = Map {"query_words" => $query_words};

    return new PlatformResult($metadata, null);
      
  }

  public async function search(int $type, string $query, int $limit, int $mode): Awaitable<?Vector<PlatformResult>>
  {
  
    if ($type === Platform::SEARCH_ALBUM) {
      // Tinysong API allows only to search for tracks
      return null;
    }

    $response = await $this->fetch($type, $query);

    if ($response === null || count($response->data) === 0) {
      return null;
    }
    $entities = $response->data;

    $length = min(count($entities), $limit?$limit:Platform::LIMIT);

    $musical_entities = Vector {};

    // Normalizing each track found
    for($i=0; $i<$length; $i++){
    
      $current_item = $entities[$i];
      $musical_entity = new TrackEntity($current_item->SongName, new AlbumEntity($current_item->AlbumName, $current_item->ArtistName, "")); 
      $musical_entity->addLink($current_item->Url);
      
      $musical_entities->add(new PlatformResult(Map {"score" => Utils::indexScore($i)}, $musical_entity));

    }
    
    return $musical_entities;
    
  }
}
