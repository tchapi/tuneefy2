<?hh // strict

namespace tuneefy\Platform\Platforms;

use tuneefy\Platform\Platform,
    tuneefy\Platform\PlatformResult,
    tuneefy\Platform\WebStreamingPlatformInterface,
    tuneefy\MusicalEntity\MusicalEntity,
    tuneefy\MusicalEntity\Entities\TrackEntity,
    tuneefy\MusicalEntity\Entities\AlbumEntity,
    tuneefy\Utils\Utils;

class XboxMusicPlatform extends Platform implements WebStreamingPlatformInterface
{
  
  const string NAME = "Xbox Music";
  const string TAG = "xbox";
  const string COLOR = "007500";

  const string API_ENDPOINT = "https://music.xboxlive.com/1/content/";
  const string API_METHOD = Platform::METHOD_GET;

  protected ImmMap<int,?string> $endpoints = ImmMap {
    Platform::LOOKUP_TRACK  => self::API_ENDPOINT . "music.%s/lookup",
    Platform::LOOKUP_ALBUM  => self::API_ENDPOINT . "music.%s/lookup",
    Platform::LOOKUP_ARTIST => self::API_ENDPOINT . "music.%s/lookup",
    Platform::SEARCH_TRACK  => self::API_ENDPOINT . "music/search",
    Platform::SEARCH_ALBUM  => self::API_ENDPOINT . "music/search",
   // Platform::SEARCH_ARTIST => self::API_ENDPOINT . "music/search" 
  };
  protected ImmMap<int,?string> $terms = ImmMap {
    Platform::LOOKUP_TRACK  => null,
    Platform::LOOKUP_ALBUM  => null,
    Platform::LOOKUP_ARTIST => null,
    Platform::SEARCH_TRACK  => "q",
    Platform::SEARCH_ALBUM  => "q",
   // Platform::SEARCH_ARTIST => "q" 
  };
  protected ImmMap<int,Map<string,mixed>> $options = ImmMap {
    Platform::LOOKUP_TRACK  => Map { "contentType" => "json" },
    Platform::LOOKUP_ALBUM  => Map { "contentType" => "json" },
    Platform::LOOKUP_ARTIST => Map { "contentType" => "json" },
    Platform::SEARCH_TRACK  => Map { "contentType" => "json", "filters" => "tracks", "maxItems" => Platform::LIMIT },
    Platform::SEARCH_ALBUM  => Map { "contentType" => "json", "filters" => "albums", "maxItems" => Platform::LIMIT },
   // Platform::SEARCH_ARTIST => Map { "contentType" => "json", "filters" => "artists", "maxItems" => Platform::LIMIT } 
  };


  // http://music.xbox.com/Album/C954F807-0100-11DB-89CA-0019B92A3933
  const string REGEX_XBOX_ALBUM = "/Album\/(?<album_id>".Platform::REGEX_FULLSTRING.").*[\/]?$/";
  // http://music.xbox.com/Track/87CF3706-0100-11DB-89CA-0019B92A3933
  const string REGEX_XBOX_TRACK = "/Track\/(?<track_id>".Platform::REGEX_FULLSTRING.").*[\/]?$/";
  // Artist ??

  public function hasPermalink(string $permalink): bool
  {
    return (strpos($permalink, "music.xbox.") !== false);
  }

  protected function addContextOptions(Map<?string, mixed> $data): Map<?string, mixed>
  {
    // From the XBOX docs : http://msdn.microsoft.com/en-us/library/dn546688.aspx
    $serviceauth = "https://datamarket.accesscontrol.windows.net/v2/OAuth2-13";
    $scope = "http://music.xboxlive.com";
    $grantType = "client_credentials";

    $requestData = array("client_id" => $this->key, "client_secret" => $this->secret, "scope" => $scope, "grant_type" => $grantType);

    $ch = curl_init();
    curl_setopt_array($ch, array(
      CURLOPT_URL => $serviceauth,
      CURLOPT_POST => 1,
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_POSTFIELDS => http_build_query($requestData)
    ));
    // TODO : make awaitable ?
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);

    $token = $result['access_token'];

    return $data->add(Pair {"accessToken", "Bearer " . $token});
  }

  public function expandPermalink(string $permalink, int $mode): ?PlatformResult
  {

    $musical_entity = null;
    $query_words = Vector {$permalink};

    $match = Map {};

    if (preg_match(self::REGEX_XBOX_TRACK, $permalink, $match)) {

      $response = $this->fetchSync(Platform::LOOKUP_TRACK, $match['track_id']);

      if ($response === null || property_exists($response->data, 'Error')) { return null; }
      
      $entity = $response->data->Tracks->Items[0];
      $musical_entity = new TrackEntity($entity->Name, new AlbumEntity($entity->Album->Name, $entity->Artists[0]->Artist->Name, $entity->Album->ImageUrl)); 
      $musical_entity->addLink($entity->Link);

      $query_words = Vector {$entity->Artists[0]->Artist->Name, $entity->Name};
      
    } else if (preg_match(self::REGEX_XBOX_ALBUM, $permalink, $match)) {
     
      $response = $this->fetchSync(Platform::LOOKUP_ALBUM, $match['album_id']);

      if ($response === null || property_exists($response->data, 'Error')) { return null; }
      
      $entity = $response->data->Albums->Items[0];
      $musical_entity = new AlbumEntity($entity->Name, $entity->Artists[0]->Artist->Name, $entity->ImageUrl);
      $musical_entity->addLink($entity->Link);

      $query_words = Vector {$entity->Artists[0]->Artist->Name, $entity->Name};
      
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

    if ($response === null || property_exists($response->data, 'Error')) {
      return null;
    }
    $entities = $response->data;

    switch ($type) {
      case Platform::SEARCH_TRACK:
        $results = $entities->Tracks->Items;
        break;
      case Platform::SEARCH_ALBUM:
        $results = $entities->Albums->Items;
        break;
    }
    $length = min(count($results), $limit?$limit:Platform::LIMIT);
    
    if ($length === 0) {
      return null;
    }

    $musical_entities = Vector {};

    // Normalizing each track found
    for($i=0; $i<$length; $i++){
    
      $current_item = $results[$i];

      if ($type === Platform::SEARCH_TRACK) {
        
        $musical_entity = new TrackEntity($current_item->Name, new AlbumEntity($current_item->Album->Name, $current_item->Artists[0]->Artist->Name, $current_item->ImageUrl)); 
        $musical_entity->addLink($current_item->Link);
             
      } else /*if ($type === Platform::SEARCH_ALBUM)*/ {

        $musical_entity = new AlbumEntity($current_item->Name, $current_item->Artists[0]->Artist->Name, $current_item->ImageUrl); 
        $musical_entity->addLink($current_item->Link);
      
      }
      
      $musical_entities->add(new PlatformResult(Map {"score" => Utils::indexScore($i)}, $musical_entity));

    }
    
    return $musical_entities;  
  }
}
