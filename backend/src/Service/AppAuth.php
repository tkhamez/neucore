<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Entity\App;
use Neucore\Factory\RepositoryFactory;
use Tkhamez\Slim\RoleAuth\RoleProviderInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Provides methods to authenticate and get an external app.
 *
 * Authentication is done via an "Authorization" request header.
 * Roles are loaded from the database.
 */
class AppAuth implements RoleProviderInterface
{
    private RepositoryFactory $repositoryFactory;

    private \Doctrine\Persistence\ObjectManager $objectManager;

    private ?App $app = null;

    public function __construct(RepositoryFactory $repositoryFactory, \Doctrine\Persistence\ObjectManager $objectManager)
    {
        $this->repositoryFactory = $repositoryFactory;
        $this->objectManager = $objectManager;
    }

    /**
     *
     * {@inheritDoc}
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

        $tokenParts = explode(':', trim($tokenDecoded));
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
        if (password_needs_rehash($app->getSecret(), PASSWORD_BCRYPT)) {
            $hash = password_hash($secret, PASSWORD_BCRYPT);
            if ($hash === '') {
                return;
            }
            $app->setSecret($hash);
            $this->objectManager->flush();
        }
    }
}
