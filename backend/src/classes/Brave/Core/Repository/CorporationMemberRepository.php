<?php declare(strict_types=1);

namespace Brave\Core\Repository;

use Brave\Core\Entity\CorporationMember;

/**
 * @method CorporationMember|null find($id, $lockMode = null, $lockVersion = null)
 * @method CorporationMember[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CorporationMemberRepository extends \Doctrine\ORM\EntityRepository
{
}
