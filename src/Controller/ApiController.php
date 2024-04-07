<?php

namespace App\Controller;

use App\Repository\ItemRepository;
use App\Services\PlatformEngine;
use App\Services\Platforms\Platform;
use App\Services\Platforms\PlatformException;
use App\Services\Platforms\PlatformType;
use App\Services\StatsService;
use App\Utils\ApiUtils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/api', name: 'api_')]
class ApiController extends AbstractController
{
    #[Route('/', name: 'documentation')]
    public function api(): Response
    {
        return $this->render('api.html');
    }

    #[Route('/v2', name: 'redirect_v2')]
    public function _redirect(): Response
    {
        return $this->redirectToRoute('api_documentation');
    }

    #[Route('/v2/platforms', name: 'platforms')]
    public function platforms(Request $request, PlatformEngine $engine, ApiUtils $apiUtils): Response
    {
        $type = $request->query->get('type');

        if (null === $type) {
            $platformType = PlatformType::GeneralPlatform;
        } else {
            try {
                $platformType = PlatformType::from(strtolower($type));
            } catch (\ValueError $e) {
                return $apiUtils->createGenericErrorResponse($request, 'BAD_PLATFORM_TYPE');
            }
        }

        $platforms = array_values(array_filter($engine->getAllPlatforms(), function ($e) use ($platformType) {
            return PlatformType::GeneralPlatform === $platformType || $e->getType() === $platformType;
        }));

        $data = ['platforms' => array_map(function ($e) { return $e->toArray(); }, $platforms)];

        return $apiUtils->createFormattedResponse($request, $data, 200, StatsService::METHOD_PLATFORMS);
    }

    #[Route('/v2/platform/{tag}', name: 'platform')]
    public function platform(Request $request, PlatformEngine $engine, ApiUtils $apiUtils, string $tag): Response
    {
        $platform = $engine->getPlatformByTag($tag);

        if (!$platform) {
            return $apiUtils->createGenericErrorResponse($request, 'BAD_PLATFORM');
        }

        $data = $platform->toArray();

        return $apiUtils->createFormattedResponse($request, $data, 200, StatsService::METHOD_PLATFORMS);
    }

    #[Route('/v2/lookup', name: 'lookup')]
    public function lookup(Request $request, PlatformEngine $engine, ApiUtils $apiUtils): Response
    {
        $permalink = $request->query->get('q', null);
        $mode = $request->query->get('mode', null);

        try {
            $real_mode = $engine->translateFlag('mode', $mode);
        } catch (\Exception $e) {
            return $apiUtils->createUnhandledErrorResponse($request, $e->getMessage());
        }

        // Permalink could be null, but we don't accept that
        if (null === $permalink || '' === $permalink) {
            return $apiUtils->createGenericErrorResponse($request, 'MISSING_PERMALINK');
        }

        try {
            $result = $engine->lookup($permalink, $real_mode);
        } catch (PlatformException $e) {
            return $apiUtils->createGenericErrorResponse($request, 'FETCH_PROBLEM');
        }

        if (!isset($result['result'])) {
            return $apiUtils->createUpstreamErrorResponse($request, $result);
        }

        if ($result['result']->getMusicalEntity()) {
            $data = [
                'result' => $result['result']->toArray(),
            ];
        } else {
            $data = [
                'errors' => [PlatformEngine::ERRORS['NO_MATCH_PERMALINK']],
                'result' => $result['result']->toArray(),
            ];
        }

        return $apiUtils->createFormattedResponse($request, $data, 200, StatsService::METHOD_LOOKUP);
    }

    #[Route('/v2/search/{type}/{platformTag}', name: 'search')]
    public function search(Request $request, PlatformEngine $engine, ApiUtils $apiUtils, string $type, string $platformTag): Response
    {
        $countryCode = $request->query->get('countryCode', null);
        $mode = $request->query->get('mode', null);
        $query = $request->query->get('q', null);
        $rawLimit = $request->query->get('limit', null);
        $limit = $rawLimit ? max(0, min(intval($rawLimit), Platform::LIMIT * 2)) : Platform::LIMIT;

        try {
            $real_type = $engine->translateFlag('type', $type);
            $real_mode = $engine->translateFlag('mode', $mode);
        } catch (\Exception $e) {
            return $apiUtils->createUnhandledErrorResponse($request, $e->getMessage());
        }

        if (null === $query || '' === $query) {
            return $apiUtils->createGenericErrorResponse($request, 'MISSING_QUERY');
        }

        $platform = $engine->getPlatformByTag($platformTag);
        if (null === $platform) {
            return $apiUtils->createGenericErrorResponse($request, 'BAD_PLATFORM');
        }

        try {
            $result = $engine->search($platform, $real_type, $query, $limit, $real_mode, $countryCode);
        } catch (PlatformException $e) {
            return $apiUtils->createGenericErrorResponse($request, 'FETCH_PROBLEM');
        }

        if (!isset($result['result'])) {
            return $apiUtils->createUpstreamErrorResponse($request, $result);
        }

        if (count($result['results']) > 0) {
            $data = [
                'results' => array_map(function ($e) { return $e->toArray(); }, $result['results']),
            ];
        } else {
            return $apiUtils->createGenericErrorResponse($request, 'NO_MATCH');
        }

        return $apiUtils->createFormattedResponse($request, $data, 200, StatsService::METHOD_SEARCH);
    }

    #[Route('/aggregate/{type}', name: 'aggregate')]
    public function aggregate(Request $request, PlatformEngine $engine, ApiUtils $apiUtils, string $type): Response
    {
        $countryCode = $request->query->get('countryCode', null);
        $mode = $request->query->get('mode', null);
        $query = $request->query->get('q', null);
        $rawLimit = $request->query->get('limit', null);
        $limit = $rawLimit ? max(0, min(intval($rawLimit), Platform::LIMIT * 2)) : Platform::LIMIT;

        $include = strtolower($request->query->get('include', ''));
        $aggressive = 'true' === $request->query->get('aggressive', 'false');

        try {
            $real_type = $engine->translateFlag('type', strtolower($type));
            $real_mode = $engine->translateFlag('mode', $mode);
        } catch (\Exception $e) {
            return $apiUtils->createUnhandledErrorResponse($request, $e->getMessage());
        }

        if (null === $query || '' === $query) {
            return $apiUtils->createGenericErrorResponse($request, 'MISSING_QUERY');
        }

        $platforms = $engine->getPlatformsByTags(explode(',', $include));
        if (null === $include || '' === $include || null === $platforms) { // Silently fails if a name is invalid, that's ok
            $platforms = $engine->getAllPlatforms();
        }

        try {
            $result = $engine->aggregate($platforms, $real_type, $query, $limit, $real_mode, $aggressive, $countryCode);
        } catch (PlatformException $e) {
            return $apiUtils->createGenericErrorResponse($request, 'FETCH_PROBLEMS');
        }

        if (!isset($result['result'])) {
            return $apiUtils->createUpstreamErrorResponse($request, $result);
        }

        if (count($result['results']) > 0) {
            $data = [
                'errors' => $result['errors'],
                'results' => array_map(function ($e) { return $e->toArray(); }, $result['results']),
            ];
        } else {
            return $apiUtils->createGenericErrorResponse($request, 'NO_MATCH');
        }

        return $apiUtils->createFormattedResponse($request, $data, 200, StatsService::METHOD_AGGREGATE);
    }

    #[Route('/share/{intent}', name: 'share')]
    public function share(Request $request, ItemRepository $itemRepository, ApiUtils $apiUtils, string $intent): Response
    {
        $intent = $request->query->get('intent', null);

        if (null === $intent || '' === $intent) {
            return $apiUtils->createGenericErrorResponse($request, 'NO_INTENT');
        }

        // Retrieve the intent
        try {
            list($type, $uid) = $itemRepository->fixItemWithIntent($intent);
        } catch (\Exception $e) {
            return $apiUtils->createUnhandledErrorResponse($request, $e->getMessage());
        }

        $link = $this->generateUrl('show', ['type' => $type, 'uid' => $uid], UrlGeneratorInterface::ABSOLUTE_URL);

        $data = ['uid' => $uid, 'link' => $link];

        return $apiUtils->createFormattedResponse($request, $data, 200, StatsService::METHOD_SHARE);
    }

    #[Route('/rate-limiting', name: 'rate_limiting')]
    public function apiRateLimiting(Request $request, ApiUtils $apiUtils): Response
    {
        return $apiUtils->createGenericErrorResponse($request, 'RATE_LIMITING');
    }

    #[Route('/{path}', name: 'catch_all', priority: -9999, requirements: ['path' => '.+'])]
    public function catchAll(Request $request, ApiUtils $apiUtils): Response
    {
        return $apiUtils->createGenericErrorResponse($request, 'NOT_FOUND');
    }
}
