<?php

namespace App\Serializer;

use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ApiErrorSerializer implements NormalizerInterface
{
    #[\Override]
    public function normalize($data, ?string $format = null, array $context = []): array
    {
        return [
            'errors' => [['GENERAL_ERROR' => $data->getMessage()]],
        ];
    }

    #[\Override]
    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof FlattenException;
    }

    #[\Override]
    public function getSupportedTypes(?string $format): array
    {
        return [
            FlattenException::class => __CLASS__ === self::class,
        ];
    }
}
