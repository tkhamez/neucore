<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\Service;

/**
 * @method Service|null find($id, $lockMode = null, $lockVersion = null)
 * @method Service|null findOneBy(array $criteria, array $orderBy = null)
 * @method Service[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @psalm-suppress MissingTemplateParam
 */
class ServiceRepository extends EntityRepository
{
}
