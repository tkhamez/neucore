<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\Watchlist;

/**
 * @method Watchlist|null find($id, $lockMode = null, $lockVersion = null)
 * @method Watchlist[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * @method Watchlist|null findOneBy(array $criteria, array $orderBy = null)
 */
class WatchlistRepository extends EntityRepository {}
