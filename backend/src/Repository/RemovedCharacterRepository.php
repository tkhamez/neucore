<?php declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Entity\RemovedCharacter;

/**
 * @method RemovedCharacter|null findOneBy(array $criteria, array $orderBy = null)
 * @method RemovedCharacter[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RemovedCharacterRepository extends EntityRepository
{
}
