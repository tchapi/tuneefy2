<?php

namespace App\Controller;

use App\Repository\ItemRepository;
use App\Services\PlatformEngine;
use App\Services\StatsService;
use App\Utils\Utils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

class FrontendController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function home(StatsService $statsService, PlatformEngine $engine, Request $request, Utils $utils): Response
    {
        $widget = $request->query->get('widget', null);
        $searchQuery = $request->query->get('q', null);

        // Let's bypass OAuth dance via the session
        $request->getSession()->set('shouldBypass', true);

        $allPlatforms = $engine->getAllPlatforms();
        $defaultPlatforms = implode(',', array_reduce($allPlatforms, function ($carry, $e) {
            if ($e->isDefault()) {
                $carry[] = $e->getTag();
            }

            return $carry;
        }, []));

        // Get the most viewed and last shared
        $hot = $statsService->getHotItems();

        if (isset($hot['most'])) {
            $mostViewed = $hot['most'];
            if (isset($mostViewed['id'])) {
                $mostViewed['uid'] = $utils->toUId($mostViewed['id']);
            }
        } else {
            $mostViewed = ['entity' => null];
        }

        if (isset($hot['track']) && $hot['track']['id']) {
            $hot['track']['uid'] = $utils->toUId($hot['track']['id']);
        }
        if (isset($hot['album']) && $hot['album']['id']) {
            $hot['album']['uid'] = $utils->toUId($hot['album']['id']);
        }

        if ('42' == $widget) {
            return $this->render('_widget.html.twig', [
                'query' => $searchQuery,
                'default_platforms' => $defaultPlatforms,
            ]);
        } else {
            return $this->render('home.html.twig', [
                'query' => $searchQuery,
                'platforms' => $allPlatforms,
                'default_platforms' => $defaultPlatforms,
                'last_shared' => $hot,
                'most_viewed' => $mostViewed,
            ]);
        }
    }

    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('about.html.twig');
    }

    #[Route('/mail', name: 'mail', methods: ['POST'])]
    public function mail(Request $request, MailerInterface $mailer): Response
    {
        // Check if spam
        $verification_params = http_build_query([
            'secret' => $this->getParameter('mail.captcha_secret'),
            'response' => $request->get('captcha'),
            'remoteip' => $_SERVER['REMOTE_ADDR'],
        ]);

        $verification = file_get_contents('https://www.google.com/recaptcha/api/siteverify?'.$verification_params);
        $json = json_decode($verification, true);

        if (false === $json['success']) {
            error_log('Error validating captcha: '.$verification);
            return new Response('0');
        }

        try {
            $sanitized_email = filter_var($request->get('mail'), FILTER_SANITIZE_EMAIL);

            $message = (new Email())
              ->subject('[CONTACT] '.$sanitized_email.' (via tuneefy.com)"')
              ->from($this->getParameter('mail.contact_email'))
              ->to($this->getParameter('mail.team_email'))
              ->text($sanitized_email." sent a message from the site : \n\n".nl2br($request->get('message')));

            // Send the message now
            $result = $mailer->send($message);
        } catch (\Exception $e) {
            error_log('Error sending mail from contact form: '.$e->getMessage());
            $result = 0;
        }

        return new Response($result > 0 ? '1' : '0');
    }

    #[Route('/trends', name: 'trends')]
    public function trends(StatsService $statsService, PlatformEngine $engine, Utils $utils): Response
    {
        $mostViewedStatsLimit = 5;

        $all = $statsService->getAllTrends();

        $total = 0;
        $stats = [
            'hits' => [],
            'tracks' => [],
            'albums' => [],
            'artists' => [],
        ];

        foreach ($all as $value) {
            if ('platform' === $value['type']) {
                $stats['hits'][] = [
                    'platform' => $engine->getPlatformByTag($value['platform']) ?? ['name' => ucfirst($value['platform'])], // For legacy platforms
                    'count' => $value['count'],
                ];
                $total = $total + intval($value['count']);
            } elseif ('track' === $value['type']) {
                $value['uid'] = $utils->toUId($value['id']);
                $stats['tracks'][] = $value;
            } elseif ('album' === $value['type']) {
                $value['uid'] = $utils->toUId($value['id']);
                $stats['albums'][] = $value;
            } elseif ('artist' === $value['type']) {
                $stats['artists'][] = $value;
            }
        }

        return $this->render('trends.html.twig', [
            'most_viewed_stats_limit' => $mostViewedStatsLimit,
            'stats' => $stats,
            'total' => $total,
        ]);
    }

    // Handles legacy routes as well with a 301
    #[Route('/t/{uid}', name: 'legacy_show_track')]
    #[Route('/a/{uid}', name: 'legacy_show_album')]
    #[Route('/s/{type}/{uid}', name: 'show')]
    public function show(StatsService $statsService, ItemRepository $itemRepository, Utils $utils, Request $request, string $uid, ?string $type = null): Response
    {
        $format = $request->query->get('format', null);
        $embed = $request->query->get('embed', null);

        if ('' === $uid) {
            throw $this->createNotFoundException('No uid');
        }

        // Translate into good id
        $id = $utils->fromUId($uid);
        try {
            $item = $itemRepository->getMusicalEntityByItemId($id);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Could not get item from database');
        }

        // Check the type (track || album) and redirect if necessary
        if (null === $type || $item->getType() !== $type) {
            return $this->redirectToRoute('show', [
                'uid' => $uid,
                'type' => $item->getType(),
            ], 301);
        }

        // Increment stats
        try {
            $statsService->addViewingStat($id);
        } catch (\Exception $e) {
            // Let's redirect anyway, we should log an error somehow TODO FIX ME
        }

        if (null !== $item) {
            // Override, just to get the page in JSON
            if ('json' === $format) {
                return new JsonResponse($item->toArray());
            } else {
                return $this->render('item.'.$type.'.html.twig', [
                    'uid' => $uid,
                    'item' => $item,
                    'embed' => null !== $embed,
                ]);
            }
        } else {
            throw $this->createNotFoundException('Could not get item from database');
        }
    }

    #[Route('/s/{type}/{uid}/listen/{platform}/{i<[0-9]>}', name: 'listen')]
    public function listen(StatsService $statsService, ItemRepository $itemRepository, Utils $utils, string $type, string $uid, string $platform, int $i = 0): Response
    {
        $platform = strtolower($platform);

        if ('' === $uid) {
            throw $this->createNotFoundException('No uid');
        }

        // Translate into good id
        $id = $utils->fromUId($uid);
        try {
            $item = $itemRepository->getMusicalEntityByItemId($id);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Could not get item from database');
        }

        // Check we have a 'platform' link
        $links = $item->getLinksForPlatform($platform);

        if ([] === $links || count($links) <= $i) {
            throw $this->createNotFoundException('No link to go to');
        }

        // Increment stats
        try {
            $statsService->addListeningStat($id, $platform, $i);
        } catch (\Exception $e) {
            // Let's redirect anyway, we should log an error somehow TODO FIX ME
        }

        // Eventually, redirect to platform
        return $this->redirect($links[$i]);
    }

    #[Route('/listen/{platform}', name: 'listen_direct')]
    public function listenDirect(Request $request, StatsService $statsService, string $platform): Response
    {
        $platform = strtolower($platform);

        $link = $request->query->get('l', null);

        if (!$link) {
            throw $this->createNotFoundException('No link to go to');
        }

        // Increment stats
        try {
            $statsService->addListeningStatDirect($platform);
        } catch (\Exception $e) {
            // Let's redirect anyway, we should log an error somehow TODO FIX ME
        }

        // Eventually, redirect to platform
        return $this->redirect($link);
    }
}
