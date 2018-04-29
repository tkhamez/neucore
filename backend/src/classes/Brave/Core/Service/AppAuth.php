<?php declare(strict_types=1);

namespace Brave\Core\Service;

use Brave\Core\Entity\App;
use Brave\Core\Entity\AppRepository;
use Brave\Slim\Role\RoleProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides methods to authenticate and get an external app.
 *
 * Authentication is done via an "Authorization" request header.
 * Roles are loaded from the database.
 */
class AppAuth implements RoleProviderInterface
{
    private $appRepository;

    private $em;

    private $log;

    /**
     *
     * @var App
     */
    private $app;

    public function __construct(AppRepository $appRepository, EntityManagerInterface $em, LoggerInterface $log)
    {
        $this->appRepository = $appRepository;
        $this->em = $em;
        $this->log = $log;
    }

    /**
     *
     * {@inheritDoc}
     * @see \Brave\Slim\Role\RoleProviderInterface::getRoles()
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

    /**
     *
     * @param ServerRequestInterface $request
     * @return NULL|\Brave\Core\Entity\App
     */
    public function getApp(ServerRequestInterface $request)
    {
        if ($this->app === null) {
            $this->authenticate($request);
        }

        return $this->app;
    }

    /**
     *
     * @param ServerRequestInterface $request
     * @return void
     */
    private function authenticate(ServerRequestInterface $request)
    {
        $token = $this->findToken($request);
        if ($token === null) {
            return;
        }

        $tokenDecoded = base64_decode($token, true);
        if ($tokenDecoded === false) {
            return;
        }

        $tokenParts = explode(':', $tokenDecoded);
        if (! isset($tokenParts[1])) {
            return;
        }

        $appId = $tokenParts[0];
        $secret = $tokenParts[1];

        $app = $this->appRepository->find($appId);
        if ($app !== null && password_verify($secret, $app->getSecret())) {
            $this->app = $app;
            $this->upgradeHash($secret);
        }
    }

    /**
     *
     * @param ServerRequestInterface $request
     * @return NULL|string
     */
    private function findToken(ServerRequestInterface $request)
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
    private function upgradeHash(string $secret)
    {
        if (password_needs_rehash($this->app->getSecret(), PASSWORD_DEFAULT)) {
            $this->app->setSecret(password_hash($secret, PASSWORD_DEFAULT));
            try {
                $this->em->persist($this->app);
                $this->em->flush();
            } catch (\Exception $e) {
                $this->log->critical($e->getMessage(), ['exception' => $e]);
            }
        }
    }
}
