<?php

namespace App\Controller;

use App\Repository\ItemRepository;
use App\Services\PlatformEngine;
use App\Services\Platforms\Platform;
use App\Services\Platforms\PlatformException;
use App\Services\Platforms\PlatformType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    public function platforms(Request $request, PlatformEngine $engine): Response
    {
        $type = $request->query->get('type');

        if (null === $type) {
            $platformType = PlatformType::GeneralPlatform;
        } else {
            try {
                $platformType = PlatformType::from(strtolower($type));
            } catch (\ValueError $e) {
                return new JsonResponse(['BAD_PLATFORM_TYPE'], 400);
            }
        }
        $platforms = array_values(array_filter($engine->getAllPlatforms(), function ($e) use ($platformType) {
            return PlatformType::GeneralPlatform === $platformType || $e->getType() === $platformType;
        }));

        return new JsonResponse(['platforms' => array_map(function ($e) { return $e->toArray(); }, $platforms)]);
    }

    #[Route('/v2/platform/{tag}', name: 'platform')]
    public function platform(PlatformEngine $engine, string $tag): Response
    {
        $platform = $engine->getPlatformByTag($tag);

        if (!$platform) {
            return new JsonResponse(['BAD_PLATFORM'], 400);
        }

        return new JsonResponse($platform->toArray());
    }

    #[Route('/v2/lookup', name: 'lookup')]
    public function lookup(Request $request, PlatformEngine $engine): Response
    {
        // Which client is calling us?
        // $token = $request->getAttribute(Authorization::TOKEN_ATTRIBUTE_KEY);
        // $engine->setCurrentToken($token);

        $permalink = $request->query->get('q', null);
        $mode = $request->query->get('mode', null);

        try {
            $real_mode = $engine->translateFlag('mode', $mode);
        } catch (\Exception $e) {
            return new JsonResponse($e->getMessage(), 400);
        }

        // Permalink could be null, but we don't accept that
        if (null === $permalink || '' === $permalink) {
            return new JsonResponse(['MISSING_PERMALINK'], 400);
        }

        try {
            $result = $engine->lookup($permalink, $real_mode);
        } catch (PlatformException $e) {
            $result = false;
        }

        // From the try/catch up there
        if (false === $result) {
            $data = ['errors' => [PlatformEngine::ERRORS['FETCH_PROBLEM']]];
        // If we have a result
        } elseif (isset($result['result'])) {
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
        // Result is only an error message
        } else {
            $data = $result;
        }

        return new JsonResponse($data);
    }

    #[Route('/v2/search/{type}/{platformTag}', name: 'search')]
    public function search(Request $request, PlatformEngine $engine, string $type, string $platformTag): Response
    {
        // Which client is calling us?
        // $token = $request->getAttribute(Authorization::TOKEN_ATTRIBUTE_KEY);
        // $engine->setCurrentToken($token);

        $countryCode = $request->query->get('countryCode', null);
        $mode = $request->query->get('mode', null);
        $query = $request->query->get('q', null);
        $rawLimit = $request->query->get('limit', null);
        $limit = $rawLimit ? max(0, min(intval($rawLimit), Platform::LIMIT * 2)) : Platform::LIMIT;

        try {
            $real_type = $engine->translateFlag('type', $type);
            $real_mode = $engine->translateFlag('mode', $mode);
        } catch (\Exception $e) {
            return new JsonResponse($e->getMessage(), 400);
        }

        if (null === $query || '' === $query) {
            return new JsonResponse(['MISSING_QUERY'], 400);
        }

        $platform = $engine->getPlatformByTag($platformTag);
        if (null === $platform) {
            return new JsonResponse(['BAD_PLATFORM'], 400);
        }

        try {
            $result = $engine->search($platform, $real_type, $query, $limit, $real_mode, $countryCode);
        } catch (PlatformException $e) {
            $result = false;
        }

        // From the try/catch up there
        if (false === $result) {
            $data = ['errors' => [PlatformEngine::ERRORS['FETCH_PROBLEM']]];
        // If we have a result
        } elseif (isset($result['results'])) {
            if (count($result['results']) > 0) {
                $data = [
                    'results' => array_map(function ($e) { return $e->toArray(); }, $result['results']),
                ];
            } else {
                $data = ['errors' => [PlatformEngine::ERRORS['NO_MATCH']]];
            }
        // Result is only an error message
        } else {
            $data = $result;
        }

        return new JsonResponse($data);
    }

    #[Route('/aggregate/{type}', name: 'aggregate')]
    public function aggregate(Request $request, PlatformEngine $engine, string $type): Response
    {
        // Which client is calling us?
        // $token = $request->getAttribute(Authorization::TOKEN_ATTRIBUTE_KEY);
        // $engine->setCurrentToken($token);

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
            return new JsonResponse($e->getMessage(), 400);
        }

        if (null === $query || '' === $query) {
            return new JsonResponse(['MISSING_QUERY'], 400);
        }

        $platforms = $engine->getPlatformsByTags(explode(',', $include));
        if (null === $include || '' === $include || null === $platforms) { // Silently fails if a name is invalid, that's ok
            $platforms = $engine->getAllPlatforms();
        }

        try {
            $result = $engine->aggregate($platforms, $real_type, $query, $limit, $real_mode, $aggressive, $countryCode);
        } catch (PlatformException $e) {
            $result = false;
        }

        // From the try/catch up there
        if (false === $result) {
            $data = ['errors' => [PlatformEngine::ERRORS['FETCH_PROBLEMS']]];
        // If we have a result
        } elseif (isset($result['results'])) {
            if (count($result['results']) > 0) {
                $data = [
                    'errors' => $result['errors'],
                    'results' => array_map(function ($e) { return $e->toArray(); }, $result['results']),
                ];
            } else {
                $data = ['errors' => [PlatformEngine::ERRORS['NO_MATCH']]];
            }
        // Result is only an error message
        } else {
            $data = $result;
        }

        return new JsonResponse($data);
    }

    #[Route('/share/{intent}', name: 'share')]
    public function share(Request $request, ItemRepository $itemRepository, string $intent): Response
    {
        $intent = $request->query->get('intent', null);

        if (null === $intent || '' === $intent) {
            return new JsonResponse(['NO_INTENT'], 400);
        }

        // Retrieve the intent
        try {
            list($type, $uid) = $itemRepository->fixItemWithIntent($intent);
        } catch (\Exception $e) {
            return new JsonResponse($e->getMessage(), 400);
        }

        $link = $this->generateUrl('show', ['type' => $type, 'uid' => $uid], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse([
          'uid' => $uid,
          'link' => $link,
      ]);
    }

    #[Route('/rate-limiting', name: 'rate_limiting')]
    public function apiRateLimiting(): Response
    {
        return $this->render('503.html.twig');
    }
}
