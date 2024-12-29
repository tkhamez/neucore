<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\Plugin;

/**
 * @method Plugin|null find($id, $lockMode = null, $lockVersion = null)
 * @method Plugin|null findOneBy(array $criteria, array $orderBy = null)
 * @method Plugin[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @psalm-suppress MissingTemplateParam
 */
class PluginRepository extends EntityRepository {}
