<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\PlayerLogin;

/**
 * @method PlayerLogin|null find($id, $lockMode = null, $lockVersion = null)
 * @method PlayerLogin|null findOneBy(array $criteria, array $orderBy = null)
 * @method PlayerLogin[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PlayerLoginRepository extends EntityRepository
{
}
