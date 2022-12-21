<?php

declare(strict_types=1);

namespace Neucore\Service;

use Eve\Sso\EveAuthentication;
use Neucore\Entity\Character;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Player;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
use Neucore\Entity\Service;
use Neucore\Entity\SystemVariable;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\RepositoryFactory;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Tkhamez\Slim\RoleAuth\RoleProviderInterface;

/**
 * Provides methods to authenticate and get a user.
 *
 * A user is identified by it's Eve character ID and is
 * created in the database if it does not already exist already.
 *
 * After that, the session variable "character_id" identifies the user.
 */
class UserAuth implements RoleProviderInterface
{
    public const LOGIN_AUTHENTICATED_SUCCESS = 1;

    public const LOGIN_AUTHENTICATED_FAIL = 2;

    public const LOGIN_CHARACTER_ADDED_SUCCESS = 3;

    public const LOGIN_CHARACTER_ADDED_FAIL = 4;

    public const LOGIN_ACCOUNTS_MERGED = 5;

    public const LOGIN_ALT_FAILED = 6;

    private SessionData $session;

    private Account $accountService;

    private AccountGroup $accountGroupService;

    private ObjectManager $objectManager;

    private RepositoryFactory $repositoryFactory;

    private LoggerInterface $log;

    private ?Character $user = null;

    public function __construct(
        SessionData $session,
        Account $accountService,
        AccountGroup $accountGroupService,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        LoggerInterface $log
    ) {
        $this->session = $session;
        $this->accountService = $accountService;
        $this->accountGroupService = $accountGroupService;
        $this->objectManager = $objectManager;
        $this->repositoryFactory = $repositoryFactory;
        $this->log = $log;
    }

    /**
     * {@inheritdoc}
     * @see \Tkhamez\Slim\RoleAuth\RoleProviderInterface::getRoles()
     */
    public function getRoles(ServerRequestInterface $request = null): array
    {
        $this->getUser();

        $roles = [];
        if ($this->user !== null) {
            foreach ($this->user->getPlayer()->getRoles() as $role) {
                $roles[] = $role->getName();
            }
        }
        if (empty($roles)) {
            $roles[] = Role::ANONYMOUS;
        }

        return $roles;
    }

    /**
     * Loads and returns current logged-in user from the database.
     */
    public function getUser(): ?Character
    {
        if ($this->user === null) {
            $this->loadUser();
        }

        return $this->user;
    }

    public function login(EveAuthentication $eveAuth): int
    {
        $this->getUser();
        if ($this->user === null) {
            $result = $this->authenticate($eveAuth);
            if ($result === 0) {
                return self::LOGIN_AUTHENTICATED_SUCCESS;
            } elseif ($result === 2) { // Login with alt but only main logins are allowed
                return self::LOGIN_ALT_FAILED;
            } else { // 1, other error
                return self::LOGIN_AUTHENTICATED_FAIL;
            }
        } else {
            return $this->addAltMoveOrMergeAccounts($eveAuth, $this->user->getPlayer());
        }
    }

    public function findCharacterOnAccount(EveAuthentication $eveAuth): ?Character
    {
        $user = $this->getUser();
        $character = $user?->getPlayer()->getCharacter($eveAuth->getCharacterId());
        if ($character) {
            return $character;
        }
        return null;
    }

    /**
     * @param EveLogin $eveLogin An instance attached to the entity manager.
     * @return bool False if save failed.
     */
    public function addToken(EveLogin $eveLogin, EveAuthentication $eveAuth, Character $character): bool
    {
        $esiToken = $this->repositoryFactory->getEsiTokenRepository()->findOneBy([
            'character' => $character,
            'eveLogin' => $eveLogin
        ]);
        if (!$esiToken) {
            $esiToken = new EsiToken();
            $esiToken->setEveLogin($eveLogin);
            $esiToken->setCharacter($character);
            $this->objectManager->persist($esiToken);
        }

        $token = $eveAuth->getToken();
        $esiToken->setAccessToken($token->getToken());
        $esiToken->setRefreshToken((string)$token->getRefreshToken());
        $esiToken->setLastChecked(new \DateTime());
        $esiToken->setExpires((int)$token->getExpires());
        $esiToken->setValidToken(true);
        if (!empty($eveLogin->getEveRoles())) {
            $esiToken->setHasRoles(true);
        }

        return $this->objectManager->flush();
    }

    public function hasRequiredGroups(Service $service): bool
    {
        $character = $this->getUser();
        if ($character === null) {
            return false;
        }

        $serviceConfig = $service->getConfiguration();

        if (
            !empty($serviceConfig->requiredGroups) &&
            $this->accountGroupService->groupsDeactivated($character->getPlayer(), true) // true = ignore delay
        ) {
            return false;
        }

        $hasOneGroup = empty($serviceConfig->requiredGroups);
        foreach ((array)$serviceConfig->requiredGroups as $group) {
            $group = (int)$group;
            if ($group > 0 && $character->getPlayer()->hasGroup($group)) {
                $hasOneGroup = true;
            }
        }

        return $hasOneGroup;
    }

    /**
     * User login.
     *
     * Creates character with player account if it is missing.
     *
     * @param EveAuthentication $eveAuth
     * @return int 0 = success, >0 = error
     */
    private function authenticate(EveAuthentication $eveAuth): int
    {
        $characterId = $eveAuth->getCharacterId();
        $char = $this->repositoryFactory->getCharacterRepository()->find($characterId);

        $updateAutoGroups = false;
        if ($char === null || $char->getCharacterOwnerHash() !== $eveAuth->getCharacterOwnerHash()) {
            // First login or changed owner, create account.
            $userRole = $this->repositoryFactory->getRoleRepository()->findBy(['name' => Role::USER]);
            if (count($userRole) !== 1) {
                $this->log->critical('UserAuth::authenticate(): Role "'.Role::USER.'" not found.');
                return 1;
            }
            $updateAutoGroups = true;
            if ($char === null) {
                $char = $this->accountService->createNewPlayerWithMain($characterId, $eveAuth->getCharacterName());
            } else {
                $oldPlayerId = $char->getPlayer()->getId();
                $char = $this->accountService->moveCharacterToNewAccount($char);
                $this->accountService->updateGroups($oldPlayerId); // flushes the entity manager
            }
            $char->getPlayer()->addRole($userRole[0]);
        } else {
            // Login with existing character
            $disableAltLogin = $this->repositoryFactory->getSystemVariableRepository()
                ->findOneBy(['name' => SystemVariable::DISABLE_ALT_LOGIN]);
            if ($disableAltLogin && $disableAltLogin->getValue() === '1' && !$char->getMain()) {
                $this->log->debug("Login with alt {$char->getId()} denied.");
                return 2;
            }
        }

        $char->setCharacterOwnerHash($eveAuth->getCharacterOwnerHash());
        $success = $this->accountService->updateAndStoreCharacterWithPlayer($char, $eveAuth); // flushes
        if ($updateAutoGroups) {
            $this->accountService->updateGroups($char->getPlayer()->getId()); // flushes
        }

        if (!$success) {
            return 1;
        }

        $this->accountService->increaseLoginCount($char->getPlayer());
        $this->user = $char;
        $this->session->set('character_id', $this->user->getId());

        return 0;
    }

    private function addAltMoveOrMergeAccounts(EveAuthentication $eveAuth, Player $player): int
    {
        $characterId = $eveAuth->getCharacterId();

        $existingChar = true;
        $alt = $this->repositoryFactory->getCharacterRepository()->find($characterId);
        if ($alt === null) {
            $existingChar = false;
            $alt = new Character();
            $alt->setId($characterId);
            try {
                $alt->setCreated(new \DateTime());
            } catch (\Exception) {
                // ignore
            }
            $player->addCharacter($alt);
            $alt->setPlayer($player);
        }

        $success = $this->accountService->updateAndStoreCharacterWithPlayer($alt, $eveAuth); // flushes
        if (!$success) {
            return self::LOGIN_CHARACTER_ADDED_FAIL;
        }

        if ($existingChar && $alt->getPlayer()->getId() !== $player->getId()) {
            if ($alt->getCharacterOwnerHash() !== $eveAuth->getCharacterOwnerHash()) {
                $oldPlayer = $alt->getPlayer();
                $alt->setCharacterOwnerHash($eveAuth->getCharacterOwnerHash());
                $alt->setMain(false);
                $this->accountService->moveCharacter($alt, $player, RemovedCharacter::REASON_MOVED_OWNER_CHANGED);
                $this->accountService->assureMain($oldPlayer);
                $this->objectManager->flush();
                return self::LOGIN_CHARACTER_ADDED_SUCCESS;
            } else {
                $alt->setCharacterOwnerHash($eveAuth->getCharacterOwnerHash());
                $this->accountService->mergeAccounts($player, $alt->getPlayer()); // flushes
                return self::LOGIN_ACCOUNTS_MERGED;
            }
        } else {
            $alt->setCharacterOwnerHash($eveAuth->getCharacterOwnerHash());
            $this->accountService->updateGroups($player->getId()); // flushes
            return self::LOGIN_CHARACTER_ADDED_SUCCESS;
        }
    }

    private function loadUser(): void
    {
        try {
            $userId = $this->session->get('character_id');
        } catch (RuntimeException) {
            // session could not be started, e.g. for 404 errors.
            return;
        }

        if ($userId !== null) {
            $this->user = $this->repositoryFactory->getCharacterRepository()->find($userId);
        }
    }
}
