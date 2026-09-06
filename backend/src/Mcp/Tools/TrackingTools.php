<?php

declare(strict_types=1);

namespace Neucore\Mcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Factory\RepositoryFactory;

class TrackingTools
{
    public function __construct(
        private RepositoryFactory $repositoryFactory,
    ) {}

    #[McpTool(
        name: 'get_member_tracking',
        description: 'Get member tracking data for a corporation (requires tracking role)',
    )]
    public function getMemberTracking(
        int $corporationId,
        array $filter = [],
    ): array {
        $corporation = $this->repositoryFactory->getCorporationRepository()->find($corporationId);
        if ($corporation === null) {
            throw new \RuntimeException('Corporation not found');
        }

        $repository = $this->repositoryFactory->getCorporationMemberRepository();
        $qb = $repository->createQueryBuilder('m')
            ->andWhere('m.corporation = :corporation')
            ->setParameter('corporation', $corporation);

        if (!empty($filter['locationId'])) {
            $qb->andWhere('m.location = :locationId')
               ->setParameter('locationId', $filter['locationId']);
        }

        if (!empty($filter['online'])) {
            $now = new \DateTime();
            $threshold = clone $now;
            $threshold->modify('-10 minutes');
            $qb->andWhere('m.logonDate > :threshold')
               ->setParameter('threshold', $threshold);
        }

        $members = $qb->getQuery()->getResult();

        return array_map(fn(CorporationMember $member) => $this->formatMember($member), $members);
    }

    private function formatMember(CorporationMember $member): array
    {
        $character = $member->getCharacter();
        $player = $character?->getPlayer();

        return [
            'id' => $member->getId(),
            'name' => $member->getName(),
            'location' => $member->getLocation() ? [
                'id' => $member->getLocation()->getId(),
                'name' => $member->getLocation()->getName(),
                'category' => $member->getLocation()->getCategory(),
            ] : null,
            'logoffDate' => $member->getLogoffDate()?->format('c'),
            'logonDate' => $member->getLogonDate()?->format('c'),
            'shipType' => $member->getShipType() ? [
                'id' => $member->getShipType()->getId(),
                'name' => $member->getShipType()->getName(),
            ] : null,
            'startDate' => $member->getStartDate()?->format('c'),
            'character' => $character ? [
                'id' => $character->getId(),
                'name' => $character->getName(),
            ] : null,
            'player' => $player ? [
                'id' => $player->getId(),
                'name' => $player->getName(),
            ] : null,
        ];
    }
}
