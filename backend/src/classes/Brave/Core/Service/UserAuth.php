<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\Character;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Roles;
use Brave\Slim\Session\SessionData;
use Brave\Sso\Basics\EveAuthentication;
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
     * @var CharacterService
     */
    private $characterService;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var Character
     */
    private $user;

    public function __construct(
        SessionData $session,
        CharacterService $charService,
        RepositoryFactory $repositoryFactory,
        LoggerInterface $log
    ) {
        $this->session = $session;
        $this->characterService = $charService;
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
        if ($this->user !== null && $this->user->getPlayer() !== null) {
            foreach ($this->user->getPlayer()->getRoles() as $role) {
                $roles[] = $role->getName();
            }
        }
        if (count($roles) === 0) {
            $roles[] = Roles::ANONYMOUS;
        }

        return $roles;
    }

    /**
     * Loads and returns current logged in user from the database.
     *
     * @return NULL|\Brave\Core\Entity\Character
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
        $characterId = (int) $eveAuth->getCharacterId();
        $char = $this->repositoryFactory->getCharacterRepository()->find($characterId);

        if ($char === null || $char->getCharacterOwnerHash() !== $eveAuth->getCharacterOwnerHash()) {
            // first login or changed owner, create account
            $userRole = $this->repositoryFactory->getRoleRepository()->findBy(['name' => Roles::USER]);
            if (count($userRole) !== 1) {
                $this->log->critical('UserAuth::authenticate(): Role "'.Roles::USER.'" not found.');
                return false;
            }
            if ($char === null) {
                $char = $this->characterService->createNewPlayerWithMain($characterId, $eveAuth->getCharacterName());
            } else {
                $char = $this->characterService->moveCharacterToNewAccount($char);
            }
            $char->getPlayer()->addRole($userRole[0]);
        }

        $success = $this->characterService->updateAndStoreCharacterWithPlayer($char, $eveAuth);

        if (! $success) {
            return false;
        }

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
        $characterId = (int) $eveAuth->getCharacterId();

        $this->getUser();

        // check if logged in
        if ($this->user === null || $this->user->getPlayer() === null) {
            return false;
        }

        $player = $this->user->getPlayer();

        // check if the character was already registered,
        // if so, move it to this player account, otherwise create it
        // (there is no need to check for a changed character owner hash here)
        $alt = $this->repositoryFactory->getCharacterRepository()->find($characterId);
        if ($alt !== null) {
            $oldPlayer = $alt->getPlayer();
            if ($oldPlayer && $oldPlayer->getId() !== $player->getId()) {
                $oldPlayer->removeCharacter($alt);
                $alt->setPlayer(null); // the current player will be added below
            }
        } else {
            $alt = new Character();
            $alt->setId($characterId);
        }

        // add alt to account if it is not the currently logged in user
        if ($alt->getId() !== $this->user->getId()) {
            $player->addCharacter($alt);
            $alt->setPlayer($player);
            $alt->setMain(false);
        }

        return $this->characterService->updateAndStoreCharacterWithPlayer($alt, $eveAuth);
    }

    /**
     * @return void
     */
    private function loadUser()
    {
        try {
            $userId = $this->session->get('character_id');
        } catch (\RuntimeException $e) {
            // session could not be started, e. g. for 404 errors.
            return;
        }

        if ($userId !== null) {
            $this->user = $this->repositoryFactory->getCharacterRepository()->find($userId);
        }
    }
}
