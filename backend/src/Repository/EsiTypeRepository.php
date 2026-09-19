<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\EsiType;

/**
 * @method EsiType|null find($id, $lockMode = null, $lockVersion = null)
 * @method EsiType|null findOneBy(array<string, mixed> $criteria, ?array<string, mixed> $orderBy = null)
 * @method EsiType[] findBy(array<string, mixed> $criteria, ?array<string, mixed> $orderBy = null, $limit = null, $offset = null)
 */
class EsiTypeRepository extends EntityRepository {}
