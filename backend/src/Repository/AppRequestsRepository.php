<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\AppRequests;

/**
 * @method AppRequests|null find($id, $lockMode = null, $lockVersion = null)
 * @method AppRequests|null findOneBy(array $criteria, array $orderBy = null)
 * @method AppRequests[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AppRequestsRepository extends EntityRepository
{
}
