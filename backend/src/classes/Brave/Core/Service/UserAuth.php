<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Entity\RoleRepository;
use Brave\Core\Roles;
use Tkhamez\Slim\RoleAuth\RoleProviderInterface;
use Brave\Slim\Session\SessionData;
use Doctrine\ORM\EntityManagerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

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
     * @var CoreCharacter
     */
    private $characterService;

    /**
     * @var CharacterRepository
     */
    private $characterRepository;

    /**
     * @var RoleRepository
     */
    private $roleRepository;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var Character
     */
    private $user;

    public function __construct(SessionData $session, CoreCharacter $charService,
        CharacterRepository $characterRepository, RoleRepository $roleRepository,
        EntityManagerInterface $em, LoggerInterface $log)
    {
        $this->session = $session;
        $this->characterService = $charService;
        $this->characterRepository = $characterRepository;
        $this->roleRepository = $roleRepository;
        $this->em = $em;
        $this->log = $log;
    }

    /**
     *
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
     */
    public function authenticate(int $characterId, string $characterName, string $characterOwnerHash,
        string $scopes, AccessToken $token): bool
    {
        $char = $this->characterRepository->find($characterId);
        if ($char === null) {

            // first login, create user

            $userRole = $this->roleRepository->findBy(['name' => Roles::USER]);
            if (count($userRole) !== 1) {
                $this->log->critical('UserAuth::authenticate(): Role "'.Roles::USER.'" not found.');
                return false;
            }

            $char = $this->characterService->createNewPlayerWithMain(
                $characterId, $characterName);

            $char->getPlayer()->addRole($userRole[0]);
        }

        $char->setName($characterName);
        $char->setLastLogin(new \DateTime());
        $char->setValidToken(true); // set valid even if there is no token

        $success = $this->characterService->updateAndStoreCharacterWithPlayer(
            $char, $characterOwnerHash, $token, $scopes);

        if (! $success) {
            return false;
        }

        $this->user = $char;
        $this->session->set('character_id', $this->user->getId());

        return true;
    }

    public function addAlt(int $characterId, string $characterName, string $characterOwnerHash,
        string $scopes, AccessToken $token): bool
    {
        $this->getUser();

        // check if logged in
        if ($this->user === null || $this->user->getPlayer() === null) {
            return false;
        }

        $player = $this->user->getPlayer();

        // check if the character was already registered,
        // if yes, move it to this player account, otherwise create it
        $alt = $this->characterRepository->find($characterId);
        if ($alt !== null) {

            // check if new alt is the currently logged in user
            if ($alt->getId() === $this->user->getId()) {
                return true;
            }

            // check if new alt is on another player account
            $oldPlayer = $alt->getPlayer();
            if ($oldPlayer && $oldPlayer->getId() !== $player->getId()) {
                $oldPlayer->removeCharacter($alt);
                $alt->setPlayer(null);
            }
        } else {
            $alt = new Character();
            $alt->setId($characterId);
        }

        $player->addCharacter($alt);
        $alt->setPlayer($player);
        $alt->setMain(false);
        $alt->setName($characterName);
        $alt->setValidToken(true); // set valid even if there is no token

        return $this->characterService->updateAndStoreCharacterWithPlayer(
            $alt, $characterOwnerHash, $token, $scopes);
    }

    /**
     * @return void
     */
    private function loadUser()
    {
        $userId = $this->session->get('character_id');
        if ($userId !== null) {
            $this->user = $this->characterRepository->find($userId);
        }
    }
}
