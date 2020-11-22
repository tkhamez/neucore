<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\PlayerLogins;

/**
 * @method PlayerLogins|null find($id, $lockMode = null, $lockVersion = null)
 * @method PlayerLogins|null findOneBy(array $criteria, array $orderBy = null)
 * @method PlayerLogins[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlayerLoginsRepository extends EntityRepository
{
}
