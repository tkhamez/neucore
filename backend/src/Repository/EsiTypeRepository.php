<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\EsiType;

/**
 * @method EsiType|null find($id, $lockMode = null, $lockVersion = null)
 * @method EsiType|null findOneBy(array $criteria, array $orderBy = null)
 * @method EsiType[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EsiTypeRepository extends EntityRepository {}
