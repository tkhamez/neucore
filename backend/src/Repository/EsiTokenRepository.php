<?php

declare(strict_types=1);

namespace Neucore\Repository;

use Doctrine\ORM\EntityRepository;
use Neucore\Api;
use Neucore\Entity\EsiToken;

/**
 * @method EsiToken|null find($id, $lockMode = null, $lockVersion = null)
 * @method EsiToken|null findOneBy(array $criteria, array $orderBy = null)
 * @method EsiToken[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class EsiTokenRepository extends EntityRepository
{
    /**
     * @return EsiToken[]
     */
    public function findByLoginAndCorporation(string $loginName, int $corporationId): array
    {
        return $this->createQueryBuilder('e')
            ->join('e.eveLogin', 'l')
            ->join('e.character', 'char')
            ->join('char.corporation', 'corp')
            ->andWhere('l.name = :name')
            ->andWhere('corp.id = :id')
            ->setParameter('name', $loginName)
            ->setParameter('id', $corporationId)
            ->orderBy('char.name')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return int[]
     */
    public function findCharacterIdsByLoginId(int $loginId): array
    {
        $result = $this->createQueryBuilder('e')
            ->select('IDENTITY(e.character) AS character_id')
            ->where('e.eveLogin = :loginId')
            ->setParameter('loginId', $loginId)
            ->getQuery()
            ->getResult();

        return array_map(function (array $token) {
            return (int)$token['character_id'];
        }, $result);
    }

    public function findValidTokens(int $loginId): array
    {
        $qb = $this->createQueryBuilder('token');
        $qb->select([
                'token.lastChecked',
                'char.id AS characterId',
                'char.name AS characterName',
                'corp.id AS corporationId',
                'alliance.id AS allianceId',
            ])
            ->join('token.character', 'char')
            ->leftJoin('char.corporation', 'corp')
            ->leftJoin('corp.alliance', 'alliance')
            ->andWhere('token.eveLogin = :loginId')
            ->andWhere('token.validToken = 1')
            ->andWhere($qb->expr()->orX(
                $qb->expr()->isNull('token.hasRoles'),
                $qb->expr()->eq('token.hasRoles', '1')
            ))
            ->setParameter('loginId', $loginId);

        return array_map(function (array $data) {
            return [
                'lastChecked' => $data['lastChecked'] ? $data['lastChecked']->format(Api::DATE_FORMAT) : null,
                'characterId' => (int)$data['characterId'],
                'characterName' => (string)$data['characterName'],
                'corporationId' => $data['corporationId'] ? (int)$data['corporationId'] : null,
                'allianceId' => $data['allianceId'] ? (int)$data['allianceId'] : null,
            ];
        }, $qb->getQuery()->getResult());
    }
}
