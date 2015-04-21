<?hh // strict

namespace tuneefy\Platform\Platforms;

use tuneefy\Platform\Platform,
    tuneefy\Platform\PlatformResult,
    tuneefy\Platform\WebStoreInterface,
    tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity,
    tuneefy\Utils\Utils;

class AmazonMP3Platform extends Platform implements WebStoreInterface
{
  
  const string NAME = "Amazon MP3";
  const string TAG = "amazon";
  const string COLOR = "E47911";

  const string API_ENDPOINT = "http://www.amazon.com/gp/dmusic/aws/";
  const string API_METHOD = Platform::METHOD_GET;
  const int RETURN_CONTENT_TYPE = Platform::RETURN_XML;

  protected ImmMap<int,?string> $endpoints = ImmMap {
    Platform::LOOKUP_TRACK  => self::API_ENDPOINT . "lookup.html",
    Platform::LOOKUP_ALBUM  => self::API_ENDPOINT . "lookup.html",
    Platform::LOOKUP_ARTIST => self::API_ENDPOINT . "lookup.html",
    Platform::SEARCH_TRACK  => self::API_ENDPOINT . "search.html",
    Platform::SEARCH_ALBUM  => self::API_ENDPOINT . "search.html",
   // Platform::SEARCH_ARTIST => self::API_ENDPOINT . "search.html" 
  };
  protected ImmMap<int,?string> $terms = ImmMap {
    Platform::LOOKUP_TRACK  => "ASIN",
    Platform::LOOKUP_ALBUM  => "ASIN",
    Platform::LOOKUP_ARTIST => "ASIN",
    Platform::SEARCH_TRACK  => "field-keywords",
    Platform::SEARCH_ALBUM  => "field-keywords",
   // Platform::SEARCH_ARTIST => "field-keywords" 
  };
  protected ImmMap<int,Map<string,mixed>> $options = ImmMap {
    Platform::LOOKUP_TRACK  => Map {},
    Platform::LOOKUP_ALBUM  => Map {},
    Platform::LOOKUP_ARTIST => Map {},
    Platform::SEARCH_TRACK  => Map { "type" => "TRACK", "pagesize" => Platform::LIMIT },
    Platform::SEARCH_ALBUM  => Map { "type" => "ALBUM", "pagesize" => Platform::LIMIT },
   // Platform::SEARCH_ARTIST => Map { "type" => "ARTIST", "pagesize" => Platform::LIMIT } 
  };

  // http://www.amazon.com/gp/product/B00GLQQ07E/whatever
  // http://www.amazon.fr/Reine-Neiges-Bande-Originale-Fran%C3%A7aise/dp/B00GMHCPVC/ref=sr_1_1?s=dmusic&ie=UTF8&qid=1429567463&sr=1-1&keywords=frozen
  // http://www.amazon.com/dp/B00GLQQ0JW/ref=dm_ws_tlw_trk1
  const string REGEX_AMAZON_ALL = "/\/(?:gp\/product|dp)\/(?P<asin>".Platform::REGEX_FULLSTRING.")[\/]?.*$/";

  public function hasPermalink(string $permalink): bool
  {
    return (strpos($permalink, "amazon.") !== false);
  }

  protected function addContextOptions(Map<?string, mixed> $data): Map<?string, mixed>
  {
    return $data->add(Pair {"clientid", $this->key});
  }

  private function getPermalinkFromASIN(string $asin): string
  {
    /// Returns the global amazon.com link, by default...
    return sprintf("http://www.amazon.com/gp/product/%s", $asin);
  }

  public function expandPermalink(string $permalink, int $mode): ?PlatformResult
  {

    $musical_entity = null;
    $query_words = Vector {$permalink};

    $match = Map {};

    if (preg_match(self::REGEX_AMAZON_ALL, $permalink, $match)) {
     
      $response = $this->fetchSync(Platform::LOOKUP_TRACK, $match['asin']);

      if ($response === null) { return null; }

      if (property_exists($response->data, "trackList")) { // It's a track then

        $entity = $response->data->trackList->track;
        $musical_entity = new TrackEntity($entity->title, new AlbumEntity($entity->album, $entity->creator, $entity->imageMedium)); 
        $musical_entity->addLink($this->getPermalinkFromASIN($match['asin']));

        $query_words = Vector {$entity->creator, $entity->title};
      
      } else if (property_exists($response->data, "album")) { // It's an album
        
        $entity = $response->data->album;
        $musical_entity = new AlbumEntity($entity->title, $entity->creator, $entity->imageMedium);
        $musical_entity->addLink($this->getPermalinkFromASIN($match['asin']));

        $query_words = Vector {$entity->creator, $entity->title};
      
      } else if (property_exists($response->data, "artist")) {

        $query_words = Vector {$response->data->artist->title};

      }

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
    if ($response === null || !property_exists($response->data->results, "result")) {
      return null;
    }
    $entities = $response->data->results->result;

    // We actually don't pass the limit to the fetch() 
    // request since it's not really useful, in fact
    $length = min(count($entities), $limit?$limit:Platform::LIMIT);
    
    $musical_entities = Vector {};

    // Normalizing each track found
    for($i=0; $i<$length; $i++){

      if ($type === Platform::SEARCH_TRACK) {
        
        $current_item = $entities[$i]->track;
        
        $musical_entity = new TrackEntity($current_item->title, new AlbumEntity($current_item->album, $current_item->creator, $current_item->imageMedium)); 
        $musical_entity->addLink($this->getPermalinkFromASIN($current_item->ASIN));
             
      } else /*if ($type === Platform::SEARCH_ALBUM)*/ {
      
        $current_item = $entities[$i]->album;

        $musical_entity = new AlbumEntity($current_item->title, $current_item->creator, $current_item->imageMedium); 
        $musical_entity->addLink($this->getPermalinkFromASIN($current_item->ASIN));
      
      }
      
      $musical_entities->add(new PlatformResult(Map {"score" => Utils::indexScore($i)}, $musical_entity));

    }
    
    return $musical_entities;
    
  }
}
