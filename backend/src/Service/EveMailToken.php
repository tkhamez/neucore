<?php

declare(strict_types=1);

namespace Neucore\Service;

use Eve\Sso\AuthenticationProvider;
use Eve\Sso\InvalidGrantException;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Neucore\Entity\SystemVariable;
use Neucore\Exception\Exception;
use Neucore\Factory\RepositoryFactory;
use Neucore\Log\Context;
use Neucore\Repository\SystemVariableRepository;
use Psr\Log\LoggerInterface;

class EveMailToken
{
    private SystemVariableRepository $sysVarRepo;

    public function __construct(
        private readonly RepositoryFactory $repositoryFactory,
        private readonly ObjectManager $objectManager,
        private readonly AuthenticationProvider $authenticationProvider,
        private readonly LoggerInterface $log,
    ) {
        $this->sysVarRepo = $this->repositoryFactory->getSystemVariableRepository();
    }

    public function deleteToken(): void
    {
        $token = $this->sysVarRepo->find(SystemVariable::MAIL_TOKEN);
        if ($token) {
            $token->setValue('');
            $this->objectManager->flush();
        }
    }

    /**
     * Returns a valid access token or null.
     */
    public function getAccessToken(): ?string
    {
        try {
            $storedToken = $this->getStoredToken();
        } catch (Exception) {
            return null;
        }

        try {
            $validToken = $this->getValidToken($storedToken);
        } catch (Exception) {
            return null;
        }

        return $validToken->getToken();
    }

    /**
     * @throws Exception
     */
    public function getStoredToken(): array
    {
        $token = $this->sysVarRepo->find(SystemVariable::MAIL_TOKEN);
        if ($token === null || $token->getValue() === '') {
            throw new Exception('Missing character that can send mails.');
        }

        $tokenValues = json_decode($token->getValue(), true);

        if (
            !is_array($tokenValues) ||
            !isset($tokenValues[SystemVariable::TOKEN_ID]) ||
            !isset($tokenValues[SystemVariable::TOKEN_ACCESS]) ||
            !isset($tokenValues[SystemVariable::TOKEN_REFRESH]) ||
            !isset($tokenValues[SystemVariable::TOKEN_EXPIRES])
        ) {
            throw new Exception('Missing token data.');
        }

        return $tokenValues;
    }

    /**
     * @throws Exception If the token could not be refreshed.
     */
    public function getValidToken(array $tokenValues): AccessTokenInterface
    {
        $existingToken = OAuthToken::newAccessToken(
            $tokenValues[SystemVariable::TOKEN_ACCESS],
            $tokenValues[SystemVariable::TOKEN_REFRESH],
            (int) $tokenValues[SystemVariable::TOKEN_EXPIRES],
        );
        try {
            $accessToken = $this->authenticationProvider->refreshAccessToken($existingToken);
        } catch (InvalidGrantException) {
            // Delete the invalid refresh token so that it cannot be used again.
            $this->deleteToken();
            throw new Exception('Invalid token.');
        } catch (\RuntimeException $e) {
            $this->log->error($e->getMessage(), [Context::EXCEPTION => $e]);
            $accessToken = $existingToken;
        }

        if ($tokenValues[SystemVariable::TOKEN_REFRESH] !== $accessToken->getExpires()) {
            $this->updateToken($accessToken, (int) $tokenValues[SystemVariable::TOKEN_ID]);
        }

        return $accessToken;
    }

    /**
     * @see EveMail::storeMailCharacter();
     */
    private function updateToken(AccessTokenInterface $accessToken, int $characterId): void
    {
        $token = $this->sysVarRepo->find(SystemVariable::MAIL_TOKEN);
        if ($token === null) {
            return;
        }

        $token->setValue((string) json_encode([
            SystemVariable::TOKEN_ID => $characterId,
            SystemVariable::TOKEN_ACCESS => $accessToken->getToken(),
            SystemVariable::TOKEN_REFRESH => $accessToken->getRefreshToken(),
            SystemVariable::TOKEN_EXPIRES => $accessToken->getExpires(),
        ]));

        $this->objectManager->flush();
    }
}
