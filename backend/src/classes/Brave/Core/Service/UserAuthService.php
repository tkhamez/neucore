<?php
namespace Brave\Core\Service;

use Brave\Core\Entity\RoleRepository;
use Brave\Core\Entity\User;
use Brave\Core\Entity\UserRepository;
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
 * After that, the session variable "user_id" identifies the user.
 */
class UserAuthService implements RoleProviderInterface
{

    private $session;

    private $userRepository;

    private $roleRepository;

    private $em;

    private $log;

    /**
     *
     * @var User
     */
    private $user;

    public function __construct(SessionData $session, UserRepository $user, RoleRepository $role,
        EntityManagerInterface $em, LoggerInterface $log)
    {
        $this->log = $log;
        $this->session = $session;
        $this->em = $em;
        $this->userRepository = $user;
        $this->roleRepository = $role;
    }

    /**
     *
     * {@inheritdoc}
     * @see \Brave\Slim\Role\RoleProviderInterface::getRoles()
     */
    public function getRoles(ServerRequestInterface $request): array
    {
        $this->getUser();

        $roles = [];
        if ($this->user !== null) {
            foreach ($this->user->getRoles() as $role) {
                $roles[] = $role->getName();
            }
        }

        return $roles;
    }

    /**
     *
     * @param int $characterId
     * @param string $characterName
     * @return boolean
     */
    public function authenticate(int $characterId, string $characterName)
    {
        $users = $this->userRepository->findBy(['characterId' => $characterId]);

        if (count($users) === 0) {

            // first login, create user

            $userRole = $this->roleRepository->findBy(['name' => 'user']);
            if (count($userRole) !== 1) {
                $this->log->critical('UserAuthService::authenticate(): Role "user" not found.');
                return false;
            }

            $user = new User();
            $user->setCharacterId($characterId);
            $user->setName($characterName);
            $user->addRole($userRole[0]);

        } else {
            $user = $users[0];
            $user->setName($characterName);
        }

        try {
            $this->em->persist($user);
            $this->em->flush();
        } catch (\Exception $e) {
            $this->log->critical($e->getMessage(), ['exception' => $e]);
            return false;
        }

        $this->session->set('user_id', $user->getId());

        return true;
    }

    /**
     *
     * @return NULL|\Brave\Core\Entity\User
     */
    public function getUser()
    {
        if ($this->user === null) {
            $this->loadUser();
        }

        return $this->user;
    }

    private function loadUser()
    {
        $userId = $this->session->get('user_id');
        if ($userId !== null) {
            $this->user = $this->userRepository->find($userId);
        }
    }
}
