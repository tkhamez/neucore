<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Entity\App;
use Neucore\Entity\Character;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Util\Crypto;
use Tkhamez\Slim\RoleAuth\RoleProviderInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Provides methods to authenticate and get an external app.
 *
 * Authentication is done via an "Authorization" request header.
 * Roles are loaded from the database.
 *
 * If the app has corporations, its access to player and character data is
 * restricted to players that have at least one character in one of those
 * corporations.
 */
class AppAuth implements RoleProviderInterface
{
    private RepositoryFactory $repositoryFactory;

    private \Doctrine\Persistence\ObjectManager $objectManager;

    private ?App $app = null;

    /**
     * Corporation IDs of the app, NULL if not calculated yet.
     *
     * @var int[]|null
     */
    private ?array $corporationIds = null;

    /**
     * IDs of all players that have at least one character in one of the
     * corporations of the app, NULL if not calculated yet.
     *
     * @var int[]|null
     */
    private ?array $allowedPlayerIds = null;

    public function __construct(RepositoryFactory $repositoryFactory, \Doctrine\Persistence\ObjectManager $objectManager)
    {
        $this->repositoryFactory = $repositoryFactory;
        $this->objectManager = $objectManager;
    }

    /**
     * @see RoleProviderInterface::getRoles
     */
    public function getRoles(ServerRequestInterface $request): array
    {
        $this->getApp($request);

        $roles = [];
        if ($this->app !== null) {
            foreach ($this->app->getRoles() as $role) {
                $roles[] = $role->getName();
            }
        }

        return $roles;
    }

    public function getApp(ServerRequestInterface $request): ?App
    {
        if ($this->app === null) {
            $this->authenticate($request);
        }

        return $this->app;
    }

    /**
     * Checks if the app may access data of the player.
     *
     * If no app is authenticated, access is denied.
     * If the app has no corporations, access is allowed.
     * Otherwise, access is allowed only if the player has at least one
     * character in one of the corporations of the app.
     */
    public function isPlayerAllowed(int $playerId): bool
    {
        if (($corporationIds = $this->getCorporationIds()) === null) {
            return false; // No app authenticated
        }
        if (count($corporationIds) === 0) {
            return true; // No restriction
        }

        return in_array($playerId, $this->getAllowedPlayerIds(), true);
    }

    /**
     * Checks if the app may access data of the character.
     *
     * The rules are the same as for isPlayerAllowed(), but the player of the
     * character is used. If the character is not found, access is denied.
     *
     * @see self::isPlayerAllowed
     */
    public function isCharacterAllowed(int $characterId): bool
    {
        if (($corporationIds = $this->getCorporationIds()) === null) {
            return false; // No app authenticated
        }
        if (count($corporationIds) === 0) {
            return true; // No restriction
        }

        $character = $this->repositoryFactory->getCharacterRepository()->find($characterId);
        if ($character === null) {
            return false;
        }

        return $this->isPlayerAllowed($character->getPlayer()->getId());
    }

    /**
     * Checks if the app may access data of the corporation.
     *
     * If no app is authenticated, access is denied.
     * If the app has no corporations, access is allowed.
     * Otherwise, the corporation must be one of the corporations of the app.
     */
    public function isCorporationAllowed(int $corporationId): bool
    {
        if (($corporationIds = $this->getCorporationIds()) === null) {
            return false; // No app authenticated
        }
        if (count($corporationIds) === 0) {
            return true; // No restriction
        }

        return in_array($corporationId, $corporationIds, true);
    }

    /**
     * Returns the player IDs of the provided list that the app may access.
     *
     * If no app is authenticated, an empty array is returned.
     * If the app has no corporations, the provided player IDs are returned unchanged.
     *
     * @param int[] $playerIds
     * @return int[]
     */
    public function filterAllowedPlayerIds(array $playerIds): array
    {
        if (count($playerIds) === 0) {
            return [];
        }

        if (($corporationIds = $this->getCorporationIds()) === null) {
            return []; // No app authenticated
        }
        if (count($corporationIds) === 0) {
            return $playerIds; // No restriction
        }

        return array_values(array_intersect(array_map('intval', $playerIds), $this->getAllowedPlayerIds()));
    }

    /**
     * Returns the players of the provided list that the app may access.
     *
     * If no app is authenticated, an empty array is returned.
     * If the app has no corporations, the provided player IDs are returned unchanged.
     *
     * @param Player[] $players
     * @return Player[]
     */
    public function filterAllowedPlayers(array $players): array
    {
        $allowedPlayerIds = $this->filterAllowedPlayerIds(array_map(
            fn(Player $player) => $player->getId(),
            $players,
        ));

        return array_values(array_filter(
            $players,
            fn(Player $player) => in_array($player->getId(), $allowedPlayerIds, true),
        ));
    }

    /**
     * Returns the character IDs of the provided list that the app may access.
     *
     * If no app is authenticated, an empty array is returned.
     * If the app has no corporations, the provided character IDs are returned unchanged.
     *
     * @param int[] $characterIds
     * @return int[]
     */
    public function filterAllowedCharacterIds(array $characterIds): array
    {
        if (count($characterIds) === 0) {
            return [];
        }

        if (($corporationIds = $this->getCorporationIds()) === null) {
            return []; // No app authenticated
        }
        if (count($corporationIds) === 0) {
            return $characterIds; // No restriction
        }

        $allowedPlayerIds = $this->getAllowedPlayerIds();
        $characters = $this->repositoryFactory->getCharacterRepository()->findBy(['id' => $characterIds]);

        $result = [];
        foreach ($characters as $character) {
            if (in_array($character->getPlayer()->getId(), $allowedPlayerIds, true)) {
                $result[] = $character->getId();
            }
        }

        return $result;
    }

    /**
     * Returns the characters of the provided list that the app may access.
     *
     * If no app is authenticated, an empty array is returned.
     * If the app has no corporations, the provided character IDs are returned unchanged.
     *
     * @param Character[] $characters
     * @return Character[]
     */
    public function filterAllowedCharacters(array $characters): array
    {
        $allowedCharacterIds = $this->filterAllowedCharacterIds(array_map(
            fn(Character $character) => $character->getId(),
            $characters,
        ));

        return array_values(array_filter(
            $characters,
            fn(Character $character) => in_array($character->getId(), $allowedCharacterIds, true),
        ));
    }

    /**
     * Returns the corporation IDs of the app.
     *
     * @return int[]|null NULL if no app is authenticated, an empty array if the app has no corporations.
     */
    private function getCorporationIds(): ?array
    {
        if ($this->corporationIds === null) {
            if ($this->app === null) {
                return null;
            }

            $this->corporationIds = [];
            foreach ($this->app->getCorporations() as $corporation) {
                $this->corporationIds[] = $corporation->getId();
            }
        }

        return $this->corporationIds;
    }

    /**
     * @return int[] IDs of all players that have at least one character in one of the corporations of the app.
     */
    private function getAllowedPlayerIds(): array
    {
        if ($this->allowedPlayerIds === null) {
            $this->allowedPlayerIds = $this->repositoryFactory
                ->getPlayerRepository()
                ->findInCorporations($this->getCorporationIds() ?? []);
        }

        return $this->allowedPlayerIds;
    }

    private function authenticate(ServerRequestInterface $request): void
    {
        $token = $this->findToken($request);
        if ($token === null) {
            return;
        }

        $tokenDecoded = base64_decode($token, true);
        if ($tokenDecoded === false) {
            return;
        }

        $tokenParts = explode(':', trim($tokenDecoded), 2);
        if (!isset($tokenParts[1])) {
            return;
        }

        $appId = $tokenParts[0];
        $secret = $tokenParts[1];

        $appEntity = $this->repositoryFactory->getAppRepository()->find($appId);
        if ($appEntity !== null && password_verify($secret, $appEntity->getSecret())) {
            $this->app = $appEntity;
            $this->upgradeHash($secret, $this->app);
        }
    }

    private function findToken(ServerRequestInterface $request): ?string
    {
        $token = null;
        if ($request->hasHeader('Authorization')) {
            $header = $request->getHeader('Authorization')[0];
            $matches = [];
            if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
                $token = $matches[1];
            }
        }

        return $token;
    }

    /**
     * upgrade hash if needed
     */
    private function upgradeHash(string $secret, App $app): void
    {
        if (password_needs_rehash($app->getSecret(), Crypto::PASSWORD_HASH)) {
            $hash = password_hash($secret, Crypto::PASSWORD_HASH);
            $app->setSecret($hash);
            $this->objectManager->flush();
        }
    }
}
