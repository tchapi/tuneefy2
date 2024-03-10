<?php

namespace App\Utils;

use App\Services\PlatformEngine;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\SerializerInterface;

final class ApiUtils
{
    public const ACCEPTABLE_FORMATS = ['json', 'xml'];

    public function __construct(private SerializerInterface $serializer)
    {
    }

    public function createFormattedResponse(Request $request, mixed $data, int $httpStatus = 200, ?string $apiMethod = null)
    {
        $format = $request->query->get('format') ?? $request->getPreferredFormat();

        if (!in_array($format, self::ACCEPTABLE_FORMATS)) {
            $format = 'json';
        }

        $mimeType = $request->getMimeType($format);

        if ($apiMethod) {
            $request->attributes->set('api_method', $apiMethod);
        }

        return new Response($this->serializer->serialize($data, $format), $httpStatus, [
          'Content-type' => $mimeType,
        ]);
    }

    public function createGenericErrorResponse(Request $request, string $error_code, int $httpStatus = 400): Response
    {
        return $this->createFormattedResponse($request, [
          'errors' => [PlatformEngine::ERRORS[$error_code]],
        ], $httpStatus);
    }

    public function createUnhandledErrorResponse(Request $request, string $message, int $httpStatus = 400): Response
    {
        return $this->createFormattedResponse($request, [
          'errors' => [['GENERAL_ERROR' => $message]],
        ], $httpStatus);
    }

    public function createUpstreamErrorResponse(Request $request, array $errorPayload, int $httpStatus = 400): Response
    {
        return $this->createFormattedResponse($request, $errorPayload, $httpStatus);
    }
}
