<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\SystemVariable;

/**
 * System Variable Repository
 *
 * @method SystemVariable|null find($id, $lockMode = null, $lockVersion = null)
 * @method SystemVariable[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * @method SystemVariable|null findOneBy(array $criteria, array $orderBy = null)
 *
 * @psalm-suppress MissingTemplateParam
 */
class SystemVariableRepository extends EntityRepository
{
}
