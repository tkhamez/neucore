<?php

declare(strict_types=1);

namespace Neucore\Mcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Neucore\Data\ServiceAccount;
use Neucore\Entity\Character;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Mcp\ResponseBuilder;
use Neucore\Service\PluginService;

class PlayerTools
{
    public function __construct(
        private readonly RepositoryFactory $repositoryFactory,
        private readonly ResponseBuilder $responseBuilder,
        private readonly PluginService $pluginService,
    ) {}

    /**
     * @param int $playerId The player ID
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'get_player',
        description: 'Get a player with their characters (including corporation and alliance) and groups',
    )]
    public function getPlayer(int $playerId): array
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if ($player === null) {
            return $this->responseBuilder->error(0, 'Player not found');
        }

        return $this->responseBuilder->success($player->jsonSerialize(withoutInternalInfo: true));
    }

    /**
     * @param string $name The player name to search for (minimum 3 characters)
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'find_players',
        description: 'Find players by their name..',
    )]
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

    /**
     * @param string $name The character name to search for (minimum 3 characters)
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'find_characters',
        description: 'Find characters by their name (minimum 3 characters)',
    )]
    public function findCharacters(string $name): array
    {
        if (mb_strlen($name) < 3) {
            return $this->responseBuilder->error(0, 'Name must be at least 3 characters long');
        }

        $repository = $this->repositoryFactory->getCharacterRepository();
        $qb = $repository->createQueryBuilder('c');
        $qb->andWhere('c.name LIKE :name')
           ->setParameter('name', '%' . $name . '%');

        $characters = $qb->getQuery()->getResult();

        return $this->responseBuilder->success(array_map(
            fn(Character $character) => $character->jsonSerialize(true, withPlayerId: true, withIsMain: true),
            $characters,
        ));
    }

    /**
     * @param int $characterId The character ID
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'get_character',
        description: 'Get a character with their corporation and alliance',
    )]
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

    /**
     * @param int $corporationId The corporation ID
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'get_corporation',
        description: 'Get a corporation with its alliance',
    )]
    public function getCorporation(int $corporationId): array
    {
        $corporation = $this->repositoryFactory->getCorporationRepository()->find($corporationId);
        if ($corporation === null) {
            return $this->responseBuilder->error(0, 'Corporation not found');
        }

        return $this->responseBuilder->success($corporation->jsonSerialize());
    }

    /**
     * @param int $allianceId The alliance ID
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'get_alliance',
        description: 'Get an alliance',
    )]
    public function getAlliance(int $allianceId): array
    {
        $alliance = $this->repositoryFactory->getAllianceRepository()->find($allianceId);
        if ($alliance === null) {
            return $this->responseBuilder->error(0, 'Alliance not found');
        }

        return $this->responseBuilder->success($alliance->jsonSerialize());
    }

    /**
     * @param int $playerId The player ID
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'get_groups',
        description: 'Get groups a player belongs to',
    )]
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

    /**
     * @param int $groupId The group ID
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'get_group_members',
        description: 'Get all players that belong to a group',
    )]
    public function getGroupMembers(int $groupId): array
    {
        $group = $this->repositoryFactory->getGroupRepository()->find($groupId);
        if ($group === null) {
            return $this->responseBuilder->error(0, 'Group not found');
        }

        $players = $group->getPlayers();

        return $this->responseBuilder->success(
            array_map(fn(Player $player) => [
                'id' => $player->getId(),
                'name' => $player->getName(),
            ], $players),
        );
    }

    /**
     * @param int $playerId The player ID
     * @return array<string, mixed>
     */
    #[McpTool(
        name: 'get_service_accounts',
        description: 'Get all service accounts from active plugins for a player',
    )]
    public function getServiceAccounts(int $playerId): array
    {
        $player = $this->repositoryFactory->getPlayerRepository()->find($playerId);
        if ($player === null) {
            return $this->responseBuilder->error(0, 'Player not found');
        }

        $accounts = $this->pluginService->getActiveServiceAccounts($player, true);

        return $this->responseBuilder->success(
            array_map(fn(ServiceAccount $account) => $account->jsonSerialize(), $accounts),
        );
    }
}
