<?php

namespace tuneefy\Controller;

use Interop\Container\ContainerInterface;
use tuneefy\DB\DatabaseHandler;
use tuneefy\MusicalEntity\Entities\TrackEntity;
use tuneefy\MusicalEntity\Entities\AlbumEntity;
use tuneefy\Platform\PlatformResult;

class BackendController
{
    protected $container;

    // constructor receives container instance
    public function __construct(ContainerInterface $container)
    {
        // Slim container
        $this->container = $container;
    }

    public function dashboard($request, $response)
    {
        $db = DatabaseHandler::getInstance(null);
        return $this->container->get('view')->render($response, 'admin/dashboard.html.twig', [
            'params' => $this->container->get('params'),
            "stats" => $db->getItemsStats(),
        ]);
    }

    public function clients($request, $response)
    {
        $db = DatabaseHandler::getInstance(null);
        $clients = $db->getApiClients();

        return $this->container->get('view')->render($response, 'admin/clients.html.twig', [
            'params' => $this->container->get('params'),
            "clients" => $clients,
        ]);
    }

    public function createClient($request, $response)
    {
        if ($request->isPost()) {
            $db = DatabaseHandler::getInstance(null);
            $db->addApiClient(
                $request->getParsedBodyParam('name'),
                $request->getParsedBodyParam('client_id'),
                $request->getParsedBodyParam('client_secret'),
                $request->getParsedBodyParam('description'),
                $request->getParsedBodyParam('email'),
                $request->getParsedBodyParam('url')
            );
            
            $uri = $request->getUri()->withPath($this->container->get('router')->pathFor('admin_clients'));    
            return $response->withRedirect($uri);
        }

        return $this->container->get('view')->render($response, 'admin/clients.new.html.twig', [
            'params' => $this->container->get('params'),
        ]);
    }

    public function migrate($request, $response)
    {
        $body = $response->getBody();

        $db = DatabaseHandler::getInstance(null);
        $connection = $db->getConnection();

        // We must empty the table and reset the AUTO_INCREMENT to 1
        $statement = $connection->prepare('SET FOREIGN_KEY_CHECKS=0; TRUNCATE TABLE `stats_listening`; TRUNCATE TABLE `stats_viewing`; TRUNCATE TABLE `items`;');
        $res = $statement->execute();

        if ($res === false) {
            throw new \Exception('Could not truncate table : '.$statement->errorInfo()[2]);
        }
        
        $statement = $connection->prepare('ALTER TABLE `items` AUTO_INCREMENT=1; SET FOREIGN_KEY_CHECKS=1;');
        $res = $statement->execute();

        if ($res === false) {
            throw new \Exception('Could not reset auto increment : '.$statement->errorInfo()[2]);
        }
        

        // Count items in DB
        $statement = $connection->prepare('SELECT COUNT(`id`) FROM `items_legacy`;');
        $res = $statement->execute();

        if ($res === false) {
            throw new \Exception('Error getting item count : '.$statement->errorInfo()[2]);
        }

        $total = $statement->fetchColumn();
        $body->write("Got ".$total." legacy items to migrate\n");

        $count = $total / 100;

        // Get items 100 by 100
        for ($i=0; $i < $count; $i++) { 
            # fetch 100 items
            $body->write(" * Getting items [".($i*100)." → ".(($i+1)*100)."] ... ");

            $statement = $connection->prepare('SELECT * FROM `items_legacy` LIMIT '.($i*100).',100;');
            $res = $statement->execute();
            $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                if ($row['type'] == 0) {
                    // Track
                    $album = new AlbumEntity($row['album'], $row['artist'], $row['image']);
                    $entity = new TrackEntity($row['name'], $album);
                } else {
                    $entity = new AlbumEntity($row['album'], $row['artist'], $row['image']);
                }

                $platformResult = new PlatformResult([], $entity);
                
                $statement = $connection->prepare('INSERT INTO `items` (`id`, `intent`, `object`, `track`, `album`, `artist`, `created_at`, `expires_at`, `signature`, `client_id`) VALUES (:id, :intent, :object, :track, :album, :artist, NOW(), :expires, :signature, :client_id)');

                $entity = $platformResult->getMusicalEntity();

                $entityAsString = serialize($entity);
                $expires = new \DateTime('now');
                $expires->add(new \DateInterval('PT'. $this->container->get('params')['intents']['lifetime'].'S'));
                $res = $statement->execute([
                  ':id' => $row['id'],
                  ':intent' => null,
                  ':object' => $entityAsString,
                  ':track' => ($entity->getType() === 'track') ? $entity->getTitle() : null,
                  ':album' => ($entity->getType() === 'track') ? $entity->getAlbum()->getTitle() : $entity->getTitle(),
                  ':artist' => $entity->getArtist(),
                  ':expires' => null,
                  ':signature' => hash_hmac('md5', $entityAsString, $this->container->get('params')['intents']['secret']),
                  ':client_id' => "legacy",
                ]);

                if ($res === false) {
                    throw new \Exception('Error adding intent : '.$statement->errorInfo()[2]);
                }
            }

            $body->write("done.\n");
        }

        // Return a response
        $body = $response->getBody();
        $body->write("OK");

        return $response;
    }
}
