<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\EsiLocation;

/**
 * @method EsiLocation|null find($id, $lockMode = null, $lockVersion = null)
 * @method EsiLocation|null findOneBy(array $criteria, array $orderBy = null)
 * @method EsiLocation[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EsiLocationRepository extends EntityRepository
{
}
