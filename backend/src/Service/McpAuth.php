<?php

declare(strict_types=1);

namespace Neucore\Service;

use Neucore\Entity\McpToken;
use Neucore\Factory\RepositoryFactory;
use Neucore\Util\Crypto;
use Tkhamez\Slim\RoleAuth\RoleProviderInterface;
use Psr\Http\Message\ServerRequestInterface;

class McpAuth implements RoleProviderInterface
{
    private RepositoryFactory $repositoryFactory;

    private \Doctrine\Persistence\ObjectManager $objectManager;

    private ?McpToken $token = null;

    public function __construct(RepositoryFactory $repositoryFactory, \Doctrine\Persistence\ObjectManager $objectManager)
    {
        $this->repositoryFactory = $repositoryFactory;
        $this->objectManager = $objectManager;
    }

    public function getRoles(ServerRequestInterface $request): array
    {
        $this->getToken($request);

        $roles = [];
        if ($this->token !== null) {
            foreach ($this->token->getRoles() as $role) {
                $roles[] = $role->getName();
            }
        }

        return $roles;
    }

    public function getToken(ServerRequestInterface $request): ?McpToken
    {
        if ($this->token === null) {
            $this->authenticate($request);
        }

        return $this->token;
    }

    private function authenticate(ServerRequestInterface $request): void
    {
        $tokenString = $this->findToken($request);
        if ($tokenString === null) {
            return;
        }

        $tokenDecoded = base64_decode($tokenString, true);
        if ($tokenDecoded === false) {
            return;
        }

        $tokenParts = explode(':', trim($tokenDecoded), 2);
        if (!isset($tokenParts[1])) {
            return;
        }

        $tokenId = $tokenParts[0];
        $secret = $tokenParts[1];

        if (!is_numeric($tokenId) || (string) (int) $tokenId !== $tokenId) {
            return;
        }

        $tokenEntity = $this->repositoryFactory->getMcpTokenRepository()->findById((int) $tokenId);
        if ($tokenEntity !== null && password_verify($secret, $tokenEntity->getSecret())) {
            $this->token = $tokenEntity;
            $this->upgradeHash($secret, $this->token);
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

    private function upgradeHash(string $secret, McpToken $token): void
    {
        if (password_needs_rehash($token->getSecret(), Crypto::PASSWORD_HASH)) {
            $hash = password_hash($secret, Crypto::PASSWORD_HASH);
            $token->setSecret($hash);
            $this->objectManager->flush();
        }
    }
}
