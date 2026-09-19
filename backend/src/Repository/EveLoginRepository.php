<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\EveLogin;

/**
 * @method EveLogin|null find($id, $lockMode = null, $lockVersion = null)
 * @method EveLogin|null findOneBy(array<string, mixed> $criteria, ?array<string, mixed> $orderBy = null)
 * @method EveLogin[] findBy(array<string, mixed> $criteria, ?array<string, mixed> $orderBy = null, $limit = null, $offset = null)
 */
class EveLoginRepository extends EntityRepository {}
