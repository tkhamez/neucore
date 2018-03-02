<?php
namespace Brave\Core\Service;

use Brave\Core\Entity\RoleRepository;
use Brave\Core\Entity\Character;
use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Entity\Player;
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
        string $accessToken, int $expires = null, string $refreshToken = null)
    {
        /* @var $user Character */
        $user = $this->characterRepository->find($characterId);

        if ($user === null) {

            // first login, create user

            $userRole = $this->roleRepository->findBy(['name' => 'user']);
            if (count($userRole) !== 1) {
                $this->log->critical('UserAuthService::authenticate(): Role "user" not found.');
                return false;
            }

            $player = new Player();
            $player->setName($characterName);
            $player->addRole($userRole[0]);

            $user = new Character();
            $user->setId($characterId);
            $user->setMain(true);
            $user->setPlayer($player);

        } else {
            $player = $user->getPlayer();
            if ($user->getMain()) {
                $player->setName($characterName);
            }
        }

        $user->setName($characterName);
        $user->setCharacterOwnerHash($characterOwnerHash); # TODO react to change
        $user->setAccessToken($accessToken);
        $user->setExpires($expires);
        $user->setRefreshToken($refreshToken);

        try {
            $this->em->persist($player);
            $this->em->persist($user);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return false;
        }

        $this->user = $user;
        $this->session->set('character_id', $this->user->getId());

        return true;
    }

    /**
     *
     * @param string $accessToken
     * @param int $expires
     * @return void
     */
    public function updateAccessToken(string $accessToken, int $expires)
    {
        $this->loadUser();

        if ($this->user === null) {
            $this->log->error('UserAuthService::updateAccessToken(): User not found.');
            return;
        }

        $this->user->setAccessToken($accessToken);
        $this->user->setExpires($expires);

        try {
            $this->em->persist($this->user);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
        }
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
