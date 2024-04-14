<?php

namespace App\Repository;

use App\Entity\ApiClient;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ApiClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiClient::class);
    }

    public function findAllWithOAuth2Client(): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
          SELECT apic.*, c.*, COUNT(`items`.`id`) AS `nb_items`
          FROM api_client apic
          LEFT JOIN oauth2_client c on apic.oauth2_client_identifier = c.identifier
          LEFT JOIN items ON items.client_id = c.identifier
          GROUP BY apic.id
          ORDER BY apic.created_at DESC
      ';

        $resultSet = $conn->executeQuery($sql);

        // returns an array of arrays (i.e. a raw data set)
        return $resultSet->fetchAllAssociative();
    }
}
