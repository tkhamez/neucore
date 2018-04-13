<?php
namespace Brave\Core\Service;

use Brave\Core\Entity\RoleRepository;
use Brave\Core\Entity\Character;
use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Entity\Player;
use Brave\Core\Roles;
use Brave\Slim\Role\RoleProviderInterface;
use Brave\Slim\Session\SessionData;
use Doctrine\ORM\EntityManagerInterface;
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
class UserAuthService implements RoleProviderInterface
{

    private $session;

    private $characterRepository;

    private $roleRepository;

    private $em;

    private $log;

    /**
     *
     * @var Character
     */
    private $user;

    public function __construct(SessionData $session,
        CharacterRepository $characterRepository, RoleRepository $roleRepository,
        EntityManagerInterface $em, LoggerInterface $log)
    {
        $this->log = $log;
        $this->session = $session;
        $this->em = $em;
        $this->characterRepository = $characterRepository;
        $this->roleRepository = $roleRepository;
    }

    /**
     *
     * {@inheritdoc}
     * @see \Brave\Slim\Role\RoleProviderInterface::getRoles()
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
     *
     * @param int $characterId
     * @param string $characterName
     * @param string $characterOwnerHash
     * @param string $accessToken
     * @param int $expires
     * @param string $refreshToken
     * @return boolean
     */
    public function authenticate(int $characterId, string $characterName, string $characterOwnerHash,
        string $accessToken, int $expires = null, string $refreshToken = null): bool
    {
        $char = $this->characterRepository->find($characterId);
        if ($char === null) {

            // first login, create user

            $userRole = $this->roleRepository->findBy(['name' => Roles::USER]);
            if (count($userRole) !== 1) {
                $this->log->critical('UserAuthService::authenticate(): Role "'.Roles::USER.'" not found.');
                return false;
            }

            $player = new Player();
            $player->setName($characterName);
            $player->addRole($userRole[0]);

            $char = new Character();
            $char->setId($characterId);
            $char->setMain(true);
            $char->setPlayer($player);

        } else {
            $player = $char->getPlayer();
            if ($char->getMain()) {
                $player->setName($characterName);
            }
        }

        // update user
        $char->setName($characterName);
        $char->setCharacterOwnerHash($characterOwnerHash); # TODO react to change
        $char->setAccessToken($accessToken);
        $char->setExpires($expires);
        $char->setRefreshToken($refreshToken);

        try {
            $this->em->persist($player); // necessary for new player
            $this->em->persist($char);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return false;
        }

        $this->user = $char;
        $this->session->set('character_id', $this->user->getId());

        return true;
    }

    public function addAlt(int $characterId, string $characterName, string $characterOwnerHash,
        string $accessToken, int $expires = null, string $refreshToken = null): bool
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
        $alt->setCharacterOwnerHash($characterOwnerHash);
        $alt->setAccessToken($accessToken);
        $alt->setExpires($expires);
        $alt->setRefreshToken($refreshToken);

        try {
            $this->em->persist($alt); // necessary for new character
            $this->em->flush();
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return false;
        }

        return true;
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
