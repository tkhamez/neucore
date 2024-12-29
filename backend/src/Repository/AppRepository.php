<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\App;

/**
 * AppRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 *
 * @method App|null find($id, $lockMode = null, $lockVersion = null)
 * @method App|null findOneBy(array $criteria, array $orderBy = null)
 * @method App[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @psalm-suppress MissingTemplateParam
 */
class AppRepository extends EntityRepository {}
