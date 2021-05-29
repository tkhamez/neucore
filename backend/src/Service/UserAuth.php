<?php

declare(strict_types=1);

namespace Neucore\Service;

use Eve\Sso\EveAuthentication;
use Neucore\Entity\Character;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\Role;
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
    /**
     * @var SessionData
     */
    private $session;

    /**
     * @var Account
     */
    private $accountService;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var Character|null
     */
    private $user;

    public function __construct(
        SessionData $session,
        Account $charService,
        RepositoryFactory $repositoryFactory,
        LoggerInterface $log
    ) {
        $this->session = $session;
        $this->accountService = $charService;
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
     * Loads and returns current logged in user from the database.
     *
     * @return NULL|Character
     */
    public function getUser()
    {
        if ($this->user === null) {
            $this->loadUser();
        }

        return $this->user;
    }

    /**
     * User login.
     *
     * Creates character with player account if it is missing.
     *
     * @param EveAuthentication $eveAuth
     * @return bool
     */
    public function authenticate(EveAuthentication $eveAuth): bool
    {
        $characterId = $eveAuth->getCharacterId();
        $char = $this->repositoryFactory->getCharacterRepository()->find($characterId);

        $updateAutoGroups = false;
        if ($char === null || $char->getCharacterOwnerHash() !== $eveAuth->getCharacterOwnerHash()) {
            // first login or changed owner, create account
            $userRole = $this->repositoryFactory->getRoleRepository()->findBy(['name' => Role::USER]);
            if (count($userRole) !== 1) {
                $this->log->critical('UserAuth::authenticate(): Role "'.Role::USER.'" not found.');
                return false;
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
        }

        $success = $this->accountService->updateAndStoreCharacterWithPlayer($char, $eveAuth, $updateAutoGroups);

        if (! $success) {
            return false;
        }

        $this->accountService->increaseLoginCount($char->getPlayer());
        $this->user = $char;
        $this->session->set('character_id', $this->user->getId());

        return true;
    }

    /**
     * @param EveAuthentication $eveAuth
     * @return bool
     */
    public function addAlt(EveAuthentication $eveAuth): bool
    {
        $characterId = $eveAuth->getCharacterId();

        $this->getUser();

        // check if logged in
        if ($this->user === null) {
            return false;
        }

        $player = $this->user->getPlayer();

        // check if the character was already registered,
        // if so, move it to this player account if needed, otherwise create it
        // (there is no need to check for a changed character owner hash here)
        $alt = $this->repositoryFactory->getCharacterRepository()->find($characterId);
        if ($alt !== null && $alt->getPlayer()->getId() !== $player->getId()) {
            $oldPlayerId = $alt->getPlayer()->getId();
            $this->accountService->moveCharacter($alt, $player, RemovedCharacter::REASON_MOVED);
            $this->accountService->updateGroups($oldPlayerId); // flushes the entity manager
            $alt->setMain(false);
        } elseif ($alt === null) {
            $alt = new Character();
            $alt->setId($characterId);
            try {
                $alt->setCreated(new \DateTime());
            } catch (\Exception $e) {
                // ignore
            }
            $player->addCharacter($alt);
            $alt->setPlayer($player);
            $alt->setMain(false);
        }

        return $this->accountService->updateAndStoreCharacterWithPlayer($alt, $eveAuth, true);
    }

    /**
     * @return void
     */
    private function loadUser()
    {
        try {
            $userId = $this->session->get('character_id');
        } catch (RuntimeException $e) {
            // session could not be started, e. g. for 404 errors.
            return;
        }

        if ($userId !== null) {
            $this->user = $this->repositoryFactory->getCharacterRepository()->find($userId);
        }
    }
}
