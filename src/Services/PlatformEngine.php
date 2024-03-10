<?php

namespace App\Services;

use App\Repository\ItemRepository;
use App\Services\Platforms\Interfaces\ScrobblingPlatformInterface;
use App\Services\Platforms\Interfaces\WebStoreInterface;
use App\Services\Platforms\Interfaces\WebStreamingPlatformInterface;
use App\Services\Platforms\Platform;
use App\Services\Platforms\PlatformException;
use App\Services\Platforms\PlatformResult;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class PlatformEngine
{
    public const ERRORS = [
      'GENERAL_ERROR' => ['GENERAL_ERROR' => 'An error was encountered'],
      'BAD_PLATFORM_TYPE' => ['BAD_PLATFORM_TYPE' => 'This type of platform does not exist'],
      'BAD_MUSICAL_TYPE' => ['BAD_MUSICAL_TYPE' => 'This musical type is not valid'],
      'BAD_PLATFORM' => ['BAD_PLATFORM' => 'This platform does not exist'],
      'BAD_MODE' => ['BAD_MODE' => 'This mode is not valid'],
      'MISSING_PERMALINK' => ['MISSING_PERMALINK' => 'Missing or empty parameter: q (permalink)'],
      'PERMALINK_UNKNOWN' => ['PERMALINK_UNKNOWN' => 'This permalink does not belong to any known platform'],
      'FETCH_PROBLEM' => ['FETCH_PROBLEM' => 'There was a problem while fetching data from the platform'],
      'FETCH_PROBLEMS' => ['FETCH_PROBLEMS' => 'There was a problem while fetching data from the platforms'],
      'NO_MATCH' => ['NO_MATCH' => 'No match was found for this search'],
      'NO_MATCH_PERMALINK' => ['NO_MATCH_PERMALINK' => 'No match was found for this permalink'],
      'MISSING_QUERY' => ['MISSING_QUERY' => 'Missing or empty parameter: q (query)'],
      'BAD_MUSICAL_TYPE' => ['BAD_MUSICAL_TYPE' => 'This musical type does not exist'],
      'NO_INTENT' => ['NO_INTENT' => 'Missing or empty parameter: intent'],
      'NO_OR_EXPIRED_INTENT' => ['NO_OR_EXPIRED_INTENT' => 'No intent with the requested uid'],
      'INVALID_INTENT_SIGNATURE' => ['INVALID_INTENT_SIGNATURE' => 'Data for this intent has been tampered with, the signature is not valid.'],
      'SERIALIZATION_ERROR' => ['SERIALIZATION_ERROR' => 'Stored object is not unserializable'],
      'NOT_CAPABLE_TRACKS' => ['NOT_CAPABLE_TRACKS' => 'This platform is not capable of searching tracks'],
      'NOT_CAPABLE_ALBUMS' => ['NOT_CAPABLE_ALBUMS' => 'This platform is not capable of searching albums'],

      'NOT_AUTHORIZED' => ['NOT_AUTHORIZED' => 'Not authorized, check the token'],
      'NOT_ACTIVE' => ['NOT_ACTIVE' => 'Your client/secret pair is not active, contact us'],

      'RATE_LIMITING' => ['RATE_LIMITING' => 'You are being rate limited'],

      'NOT_FOUND' => ['NOT_FOUND' => 'Method not found'],
];

    /**
     * @var Platform[]
     */
    private $platforms;

    /**
     * @var ?string
     */
    private $token;

    private $flags = [
      'type/track' => Platform::SEARCH_TRACK,
      'type/album' => Platform::SEARCH_ALBUM,
      'mode/lazy' => Platform::MODE_LAZY,
      'mode/eager' => Platform::MODE_EAGER,
      'mode/*' => Platform::MODE_LAZY, // '*' indicates default
    ];

    public function __construct(
        private ItemRepository $itemRepository,
        #[TaggedIterator('app.platform', defaultIndexMethod: 'getTag')]
        iterable $platforms
    ) {
        $this->platforms = iterator_to_array($platforms);
    }

    public function setCurrentToken(?array $token = null): PlatformEngine
    {
        $this->token = $token;

        return $this;
    }

    /**
     * @return Platform[]
     */
    public function getAllPlatforms(): array
    {
        return array_values($this->platforms);
    }

    public function getPlatformByTag(string $tag): ?Platform
    {
        return isset($this->platforms[$tag]) ? $this->platforms[$tag] : null;
    }

    /**
     * @param string[]
     *
     * @return Platform[]
     */
    public function getPlatformsByTags(array $tags): array
    {
        return array_intersect_key($this->platforms, array_flip($tags));
    }

    public function translateFlag(string $namespace, ?string $flag = null): int
    {
        if (null === $flag || '' === $flag) {
            $flag = '*';
        }

        $path = $namespace.'/'.$flag;

        if (!isset($this->flags[$path]) && 'type' === $namespace) {
            throw new \Exception('BAD_MUSICAL_TYPE');
        } elseif (!isset($this->flags[$path]) && 'mode' === $namespace) {
            throw new \Exception('BAD_MODE');
        }

        return $this->flags[$path];
    }

    public function lookup(string $permalink, int $mode): array
    {
        /**
         * @var WebStreamingPlatformInterface|WebStoreInterface|ScrobblingPlatformInterface|null
         **/
        $platform = null;

        foreach ($this->platforms as $p) {
            if ($p->isCapableOfLookingUp()) {
                if ($p instanceof WebStreamingPlatformInterface && $p->hasPermalink($permalink)) {
                    $platform = $p;
                    break;
                }
                if ($p instanceof WebStoreInterface && $p->hasPermalink($permalink)) {
                    $platform = $p;
                    break;
                }
                if ($p instanceof ScrobblingPlatformInterface && $p->hasPermalink($permalink)) {
                    $platform = $p;
                    break;
                }
            }
        }

        if (null === $platform) {
            return ['errors' => [self::ERRORS['PERMALINK_UNKNOWN']]];
        }

        // Initiate a lookup on this platform
        $platformResult = $platform->expandPermalink($permalink, $mode);

        if (!$platformResult || null === $platformResult->getMusicalEntity()) {
            // Don't store anything in this case
            return ['result' => $platformResult];
        }

        // Store the intent
        $expires = $this->itemRepository->addItem($platformResult, $this->token ? $this->token['client_id'] : null);
        $platformResult->setExpires($expires);

        return ['result' => $platformResult];
    }

    public function search(Platform $platform, int $type, string $query, int $limit, int $mode, ?string $countryCode = null): array
    {
        try {
            $lookup = $this->lookup($query, $mode);

            if (array_key_exists('result', $lookup) && $lookup['result'] instanceof PlatformResult) {
                $metadata = $lookup['result']->getMetadata();
                $query = implode(' ', $metadata['query_words']);
            }
        } catch (PlatformException $e) {
            // Do nothing ?
        }

        if (($platform->isCapableOfSearchingTracks() && Platform::SEARCH_TRACK === $type)
         || ($platform->isCapableOfSearchingAlbums() && Platform::SEARCH_ALBUM === $type)) {
            $results = Platform::search($platform, $type, $query, $limit, $mode, $countryCode);
            foreach ($results as $result) {
                $expires = $this->itemRepository->addItem($result, $this->token ? $this->token['client_id'] : null);
                $result->setExpires($expires);
            }

            return ['results' => $results];
        } elseif (Platform::SEARCH_TRACK === $type) {
            return ['errors' => [self::ERRORS['NOT_CAPABLE_TRACKS']]];
        } elseif (Platform::SEARCH_ALBUM === $type) {
            return ['errors' => [self::ERRORS['NOT_CAPABLE_ALBUMS']]];
        }
    }

    public function aggregate(array $platforms, int $type, string $query, int $limit, int $mode, bool $aggressive, ?string $countryCode = null): array
    {
        try {
            $lookup = $this->lookup($query, $mode);

            if (array_key_exists('result', $lookup) && $lookup['result'] instanceof PlatformResult) {
                $metadata = $lookup['result']->getMetadata();
                $query = implode(' ', $metadata['query_words']);
            }
        } catch (PlatformException $e) {
            // Do nothing ?
        }

        $result = [];

        $filtered_platforms = array_filter($platforms, function ($p) use ($type) {
            return ($p->isCapableOfSearchingTracks() && Platform::SEARCH_TRACK === $type)
             || ($p->isCapableOfSearchingAlbums() && Platform::SEARCH_ALBUM === $type);
        });

        $resultArray = Platform::aggregate($filtered_platforms, $type, $query, Platform::AGGREGATE_LIMIT, $mode, $countryCode);

        return ['results' => $this->mergeResults($resultArray['results'], $aggressive, $limit), 'errors' => $resultArray['errors']];
    }

    public function mergeResults(array $results, bool $aggressive, int $limit): array
    {
        $merged_results = [];

        foreach ($results as $result) {
            $current_entity = $result->getMusicalEntity();

            if (null === $current_entity) {
                continue;
            }

            // Run introspection and get hash
            $key = $current_entity->introspect()->getHash($aggressive);

            // Then merges with the actual array we already have
            if (!array_key_exists($key, $merged_results)) {
                $merged_results[$key] = $result;
            } else {
                $merged_results[$key]->mergeWith($result);
            }
        }

        // Sorts by score
        usort($merged_results, function ($a, $b) {
            $am = $a->getMetadata();
            $bm = $b->getMetadata();
            if ($am['score'] == $bm['score']) {
                return 0;
            }

            return ($am['score'] > $bm['score']) ? -1 : 1;
        });

        // Resizes to keep only the wanted number of elements
        array_splice($merged_results, $limit);

        // Gives each element a last chance of doing something useful on its data
        array_map(function ($e) { return $e->finalizeMerge()->store($this->token); }, $merged_results);

        // Discards the key (hash) that we don't need anymore
        return array_values($merged_results);
    }
}
