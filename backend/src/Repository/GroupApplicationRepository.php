<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Neucore\Entity\GroupApplication;
use Doctrine\ORM\EntityRepository;

/**
 * GroupApplicationRepository
 *
 * @method GroupApplication|null find($id, $lockMode = null, $lockVersion = null)
 * @method GroupApplication|null findOneBy(array<string, mixed> $criteria, ?array<string, mixed> $orderBy = null)
 * @method GroupApplication[] findBy(array<string, mixed> $criteria, ?array<string, mixed> $orderBy = null, $limit = null, $offset = null)
 */
class GroupApplicationRepository extends EntityRepository {}
