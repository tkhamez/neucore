<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\SystemVariable;

/**
 * System Variable Repository
 *
 * @method SystemVariable|null find($id, $lockMode = null, $lockVersion = null)
 * @method SystemVariable[] findBy(array<string, mixed> $criteria, ?array<string, string> $orderBy = null, $limit = null, $offset = null)
 * @method SystemVariable|null findOneBy(array<string, mixed> $criteria, ?array<string, string> $orderBy = null)
 */
class SystemVariableRepository extends EntityRepository {}
