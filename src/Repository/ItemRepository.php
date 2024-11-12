<?php

namespace App\Repository;

use App\Dataclass\MusicalEntity\Entities\Track;
use App\Dataclass\MusicalEntity\MusicalEntity;
use App\Entity\Item;
use App\Services\Platforms\PlatformResult;
use App\Utils\Utils;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * @method Item|null find($id, $lockMode = null, $lockVersion = null)
 * @method Item|null findOneBy(array $criteria, array $orderBy = null)
 * @method Item[]    findAll()
 * @method Item[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ItemRepository extends ServiceEntityRepository
{
    private $intentSecret;
    private $intentLifetime;

    public function __construct(
        ManagerRegistry $registry,
        ParameterBagInterface $params,
        private Utils $utils
    ) {
        $this->intentSecret = $params->get('intents.secret');
        $this->intentLifetime = $params->get('intents.lifetime');
        parent::__construct($registry, Item::class);
    }

    public function getMusicalEntityByItemId(int $id): ?MusicalEntity
    {
        $item = $this->createQueryBuilder('i')
            ->where('i.id = :id')
            ->setParameter('id', $id)
            ->andWhere('i.expiresAt IS NULL')
            ->andWhere('i.intent IS NULL')
            ->getQuery()
            ->getSingleResult();

        if (null === $item) {
            return null;
        }

        if ($item->getSignature() !== hash_hmac('md5', $item->getObject(), $this->intentSecret)) {
            throw new \Exception('Data for id: '.$id.' has been tampered with, the signature is not valid.');
        }

        $result = $item->getMusicalEntity();

        if (false === $result) {
            throw new \Exception('Stored object is not unserializable');
        }

        return $result;
    }

    public function addItem(PlatformResult $result, ?string $clientId = null): \DateTime
    {
        // Persist intent and object in DB for a later share if necessary
        $entity = $result->getMusicalEntity();

        if (!$entity) {
            throw new \Exception('Error adding intent: this result does not have a musical entity bound to it.');
        }

        $expires = new \DateTime('now');
        $expires->add(new \DateInterval('PT'.$this->intentLifetime.'S'));

        $item = (new Item())
          ->setIntent($result->getIntent())
          ->setMusicalEntity($entity)
          ->setTrack($entity instanceof Track ? $entity->getSafeTitle() : null)
          ->setAlbum($entity instanceof Track ? $entity->getAlbum()->getSafeTitle() : $entity->getSafeTitle())
          ->setArtist($entity->getArtist())
          ->setCreatedAt(new \DateTime('now'))
          ->setExpiresAt($expires)
          ->setSignature(hash_hmac('md5', serialize($entity), $this->intentSecret))
          ->setClientId($clientId);

        $entityManager = $this->getEntityManager();

        $entityManager->persist($item);
        $entityManager->flush();

        return $expires;
    }

    public function fixItemWithIntent(string $intent): array
    {
        /*
            To make an item permanent, we remove its intent, and remove its expiration date.
            The item thus becomes available as a conventional tuneefy link with its id and
            cannot be shared as an intent anymore.
        */

        $intentItem = $this->createQueryBuilder('i')
            ->andWhere('i.intent = :intent')
            ->setParameter('intent', $intent)
            ->getQuery()
            ->getOneOrNullResult();

        if (null === $intentItem) {
            throw new \Exception('NO_OR_EXPIRED_INTENT');
        }

        // Try to find an identical object, already persisted (same signature, no intent, not expirable)
        $items = $this->createQueryBuilder('i')
            ->andWhere('i.signature = :signature')
            ->setParameter('signature', $intentItem->getSignature())
            ->andWhere('i.intent IS NULL')
            ->andWhere('i.expiresAt IS NULL')
            ->getQuery()
            ->getResult();

        $entityManager = $this->getEntityManager();

        if (count($items) > 0) {
            // In this case, we return the previous identical result and we delete the intent
            $this->delete($intentItem);

            $entityManager->flush();

            return [$items[0]->getMusicalEntity()->getType(), $this->utils->toUId($items[0]->getId())];
        } else {
            // Otherwise, we persist this one
            $intentItem->setIntent(null)->setExpiresAt(null);

            $entityManager->flush();

            return [$intentItem->getMusicalEntity()->getType(), $this->utils->toUId($intentItem->getId())];
        }
    }

    public function cleanExpiredIntents()
    {
        $expiredIntents = $this->createQueryBuilder('i')
          ->andWhere('i.intent IS NOT NULL')
          ->andWhere('i.expiresAt IS NOT NULL')
          ->andWhere('i.expiresAt < CURRENT_TIMESTAMP()')
          ->getQuery()
          ->getResult();

        foreach ($expiredIntents as $intent) {
            $entityManager->remove($intent);
        }

        $entityManager = $this->getEntityManager();
        $entityManager->flush();
    }
}
