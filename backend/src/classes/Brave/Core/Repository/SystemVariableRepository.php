<?php declare(strict_types=1);

namespace Brave\Core\Repository;

use Brave\Core\Entity\SystemVariable;

/**
 * System Variable Repository
 *
 * @method SystemVariable|null find($id, $lockMode = null, $lockVersion = null)
 * @method SystemVariable[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 * @method SystemVariable findOneBy(array $criteria, array $orderBy = null)
 */
class SystemVariableRepository extends \Doctrine\ORM\EntityRepository
{
}
