<?php

declare(strict_types=1);

namespace Neucore\Mcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Mcp\ResponseBuilder;

class PlayerTools
{
    public function __construct(
        private readonly RepositoryFactory $repositoryFactory,
        private readonly ResponseBuilder $responseBuilder,
    ) {}

    #[McpTool(
        name: 'get_player',
        description: 'Get a player with their characters (including corporation and alliance) and groups',
    )]
    /**
     * @param int $playerId The player ID
     */
    public function getPlayer(int $playerId): array
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if ($player === null) {
            return $this->responseBuilder->error(0, 'Player not found');
        }

        return $this->responseBuilder->success($player->jsonSerialize());
    }

    #[McpTool(
        name: 'find_players',
        description: 'Find players by their name..',
    )]
    /**
     * @param string $name The player name to search for (minimum 3 characters)
     */
    public function findPlayers(string $name): array
    {
        if (mb_strlen($name) < 2) {
            return $this->responseBuilder->error(0, 'Name must be at least 3 characters long');
        }

        $repository = $this->repositoryFactory->getPlayerRepository();
        $qb = $repository->createQueryBuilder('p');
        $qb->andWhere('p.name LIKE :name')
           ->setParameter('name', '%' . $name . '%');

        $players = $qb->getQuery()->getResult();

        return $this->responseBuilder->success(
            array_map(fn(Player $player) => $player->jsonSerialize(true), $players),
        );
    }

    #[McpTool(
        name: 'get_character',
        description: 'Get a character with their corporation and alliance',
    )]
    /**
     * @param int $characterId The character ID
     */
    public function getCharacter(int $characterId): array
    {
        $character = $this->repositoryFactory->getCharacterRepository()->find($characterId);
        if ($character === null) {
            return $this->responseBuilder->error(0, 'Character not found');
        }

        return $this->responseBuilder->success(
            $character->jsonSerialize(true, withPlayerId: true, withIsMain: true),
        );
    }

    #[McpTool(
        name: 'get_corporation',
        description: 'Get a corporation with its alliance',
    )]
    /**
     * @param int $corporationId The corporation ID
     */
    public function getCorporation(int $corporationId): array
    {
        $corporation = $this->repositoryFactory->getCorporationRepository()->find($corporationId);
        if ($corporation === null) {
            return $this->responseBuilder->error(0, 'Corporation not found');
        }

        return $this->responseBuilder->success($corporation->jsonSerialize());
    }

    #[McpTool(
        name: 'get_alliance',
        description: 'Get an alliance',
    )]
    /**
     * @param int $allianceId The alliance ID
     */
    public function getAlliance(int $allianceId): array
    {
        $alliance = $this->repositoryFactory->getAllianceRepository()->find($allianceId);
        if ($alliance === null) {
            return $this->responseBuilder->error(0, 'Alliance not found');
        }

        return $this->responseBuilder->success($alliance->jsonSerialize());
    }

    #[McpTool(
        name: 'get_groups',
        description: 'Get groups a player belongs to',
    )]
    /**
     * @param int $playerId The player ID
     */
    public function getGroups(int $playerId): array
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if ($player === null) {
            return $this->responseBuilder->error(0, 'Player not found');
        }

        $groups = $player->getGroups();

        return $this->responseBuilder->success(
            array_map(fn(Group $group) => $group->jsonSerialize(true), $groups),
        );
    }
}
