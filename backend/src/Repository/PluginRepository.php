<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\Plugin;

/**
 * @method Plugin|null find($id, $lockMode = null, $lockVersion = null)
 * @method Plugin|null findOneBy(array<string, mixed> $criteria, ?array<string, mixed> $orderBy = null)
 * @method Plugin[] findBy(array<string, mixed> $criteria, ?array<string, mixed> $orderBy = null, $limit = null, $offset = null)
 */
class PluginRepository extends EntityRepository {}
