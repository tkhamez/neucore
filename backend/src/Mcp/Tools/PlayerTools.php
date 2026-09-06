<?php

declare(strict_types=1);

namespace Neucore\Mcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\JsonRpc\Error;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\Alliance;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;

class PlayerTools
{
    public function __construct(
        private RepositoryFactory $repositoryFactory,
    ) {}

    #[McpTool(
        name: 'get_player',
        description: 'Get a player with their characters (including corporation and alliance) and groups',
    )]
    public function getPlayer(int $playerId): array
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if ($player === null) {
            throw new \RuntimeException('Player not found');
        }

        return $this->formatPlayer($player);
    }

    #[McpTool(
        name: 'get_players',
        description: 'Get a list of players with optional filters',
    )]
    public function getPlayers(array $filter = []): array
    {
        $repository = $this->repositoryFactory->getPlayerRepository();
        $qb = $repository->createQueryBuilder('p');

        if (!empty($filter['name'])) {
            $qb->andWhere('p.name LIKE :name')
               ->setParameter('name', '%' . $filter['name'] . '%');
        }

        if (!empty($filter['status'])) {
            $qb->andWhere('p.status = :status')
               ->setParameter('status', $filter['status']);
        }

        if (!empty($filter['role'])) {
            $qb->innerJoin('p.roles', 'r')
               ->andWhere('r.name = :role')
               ->setParameter('role', $filter['role']);
        }

        $players = $qb->getQuery()->getResult();

        return array_map(fn(Player $player) => $this->formatPlayer($player), $players);
    }

    #[McpTool(
        name: 'get_character',
        description: 'Get a character with their corporation and alliance',
    )]
    public function getCharacter(int $characterId): array
    {
        $character = $this->repositoryFactory->getCharacterRepository()->find($characterId);
        if ($character === null) {
            throw new \RuntimeException('Character not found');
        }

        return $this->formatCharacter($character);
    }

    #[McpTool(
        name: 'get_corporation',
        description: 'Get a corporation with its alliance',
    )]
    public function getCorporation(int $corporationId): array
    {
        $corporation = $this->repositoryFactory->getCorporationRepository()->find($corporationId);
        if ($corporation === null) {
            throw new \RuntimeException('Corporation not found');
        }

        return $this->formatCorporation($corporation);
    }

    #[McpTool(
        name: 'get_alliance',
        description: 'Get an alliance',
    )]
    public function getAlliance(int $allianceId): array
    {
        $alliance = $this->repositoryFactory->getAllianceRepository()->find($allianceId);
        if ($alliance === null) {
            throw new \RuntimeException('Alliance not found');
        }

        return $this->formatAlliance($alliance);
    }

    #[McpTool(
        name: 'get_groups',
        description: 'Get groups a player belongs to',
    )]
    public function getGroups(int $playerId): array
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if ($player === null) {
            throw new \RuntimeException('Player not found');
        }

        $groups = $player->getGroups();

        return array_map(fn(Group $group) => $this->formatGroup($group), $groups);
    }

    private function formatPlayer(Player $player): array
    {
        $characters = [];
        foreach ($player->getCharacters() as $character) {
            $characters[] = $this->formatCharacter($character);
        }

        $groups = [];
        foreach ($player->getGroups() as $group) {
            $groups[] = $this->formatGroup($group);
        }

        return [
            'id' => $player->getId(),
            'name' => $player->getName(),
            'status' => $player->getStatus(),
            'roles' => $player->getRoleNames(),
            'characters' => $characters,
            'groups' => $groups,
        ];
    }

    private function formatCharacter(Character $character): array
    {
        $corporation = $character->getCorporation();
        $alliance = $corporation?->getAlliance();

        return [
            'id' => $character->getId(),
            'name' => $character->getName(),
            'main' => $character->getMain(),
            'corporation' => $corporation ? $this->formatCorporation($corporation) : null,
            'alliance' => $alliance ? $this->formatAlliance($alliance) : null,
        ];
    }

    private function formatCorporation(Corporation $corporation): array
    {
        $alliance = $corporation->getAlliance();

        return [
            'id' => $corporation->getId(),
            'name' => $corporation->getName(),
            'ticker' => $corporation->getTicker(),
            'alliance' => $alliance ? $this->formatAlliance($alliance) : null,
        ];
    }

    private function formatAlliance(Alliance $alliance): array
    {
        return [
            'id' => $alliance->getId(),
            'name' => $alliance->getName(),
            'ticker' => $alliance->getTicker(),
        ];
    }

    private function formatGroup(Group $group): array
    {
        return [
            'id' => $group->getId(),
            'name' => $group->getName(),
            'description' => $group->getDescription(),
            'visibility' => $group->getVisibility(),
            'autoAccept' => $group->getAutoAccept(),
            'isDefault' => $group->getIsDefault(),
        ];
    }
}
