<?php

namespace tuneefy\Platform;

use tuneefy\DB\DatabaseHandler;
use tuneefy\MusicalEntity\Entities\AlbumEntity;
use tuneefy\MusicalEntity\Entities\TrackEntity;
use tuneefy\MusicalEntity\MusicalEntity;

class PlatformResult
{
    private $intent;
    private $expires;
    private $musical_entity;
    private $metadata;

    public function __construct(array $metadata, MusicalEntity $musical_entity = null)
    {
        $this->musical_entity = $musical_entity;
        $this->metadata = $metadata;
        $this->intent = uniqid(); // We create it now for later use
        $this->expires = null;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getMusicalEntity()// : ?MusicalEntity
    {
        return $this->musical_entity;
    }

    public function getIntent(): string
    {
        return $this->intent;
    }

    public function toArray(): array
    {
        if (null === $this->musical_entity) {
            return [
                'metadata' => $this->metadata,
            ];
        } else {
            return [
                'musical_entity' => $this->musical_entity->toArray(),
                'metadata' => $this->metadata,
                'share' => [
                    'intent' => $this->intent,
                    'expires' => $this->expires ? $this->expires->format(\DateTime::ATOM) : null,
                ],
            ];
        }
    }

    public function mergeWith(PlatformResult $that): PlatformResult
    {
        $thatMusicalEntity = $that->getMusicalEntity();

        if (null !== $this->musical_entity && null !== $thatMusicalEntity) {
            // Merge musical entities
            if ($this->musical_entity instanceof TrackEntity) {
                $this->musical_entity = TrackEntity::merge($this->musical_entity, $thatMusicalEntity);
            } elseif ($this->musical_entity instanceof AlbumEntity) {
                $this->musical_entity = AlbumEntity::merge($this->musical_entity, $thatMusicalEntity);
            }

            // Merge score
            $thatMetadata = $that->getMetadata();
            if (array_key_exists('score', $this->metadata) && array_key_exists('score', $thatMetadata)) {
                $this->metadata['score'] = floatval($this->metadata['score']) + floatval($thatMetadata['score']);
            }

            // Merge other metadata
            $this->metadata['externalIds'] = array_merge($this->metadata['externalIds'], $thatMetadata['externalIds']);

            if (array_key_exists('merges', $this->metadata)) {
                $this->metadata['merges'] = $this->metadata['merges'] + 1;
            } else {
                $this->metadata['merges'] = 1;
            }
        }

        return $this;
    }

    public function store(array $token = null): PlatformResult
    {
        if (is_null($this->musical_entity)) {
            return $this;
        }

        // Store guid + serialized platformResult in db
        $db = DatabaseHandler::getInstance(null);
        $this->expires = $db->addItemForClient($this, $token ? $token['client_id'] : null);

        return $this;
    }

    public function finalizeMerge(): PlatformResult
    {
        // Compute a final score
        if (array_key_exists('merges', $this->metadata) && array_key_exists('score', $this->metadata)) {
            // The more merges, the better the result must be
            $merge_quantifier_offset = floatval($this->metadata['merges']) / 2.25; // Completely heuristic number
            $this->metadata['score'] = $merge_quantifier_offset + floatval($this->metadata['score']) / (floatval($this->metadata['merges']) + 1);
        } elseif (array_key_exists('score', $this->metadata)) {
            // has not been merged, ever. Lower score
            $this->metadata['score'] = floatval($this->metadata['score']) / 2;
        } else {
            $this->metadata['score'] = 0.0;
        }

        $this->metadata['score'] = round($this->metadata['score'], 3);

        return $this;
    }
}
