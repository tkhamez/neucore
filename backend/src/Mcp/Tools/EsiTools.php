<?php

declare(strict_types=1);

namespace Neucore\Mcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Neucore\Entity\EveLogin;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\EsiClient;
use Psr\Http\Client\ClientExceptionInterface;

class EsiTools
{
    private const DEFAULT_EVE_LOGIN = EveLogin::NAME_DEFAULT;

    public function __construct(
        private RepositoryFactory $repositoryFactory,
        private EsiClient $esiClient,
    ) {}

    #[McpTool(
        name: 'esi_request',
        description: 'Make an ESI request using the character\'s token from the default EVE login (core.default)',
    )]
    public function esiRequest(
        int $characterId,
        string $path,
        string $method = 'GET',
        ?string $body = null,
    ): mixed {
        if ($characterId <= 0) {
            throw new \RuntimeException('Character ID must be a positive integer');
        }

        $character = $this->repositoryFactory->getCharacterRepository()->find($characterId);
        if ($character === null) {
            throw new \RuntimeException('Character not found');
        }

        $esiToken = $character->getEsiToken(self::DEFAULT_EVE_LOGIN);
        if ($esiToken === null || $esiToken->getValidToken() !== true) {
            throw new \RuntimeException('Character has no valid ESI token for the default login');
        }

        try {
            $response = $this->esiClient->request(
                $path,
                $method,
                $body,
                $characterId,
                self::DEFAULT_EVE_LOGIN,
            );
        } catch (ClientExceptionInterface $e) {
            throw new \RuntimeException('ESI request failed: ' . $e->getMessage());
        }

        $statusCode = $response->getStatusCode();
        $responseBody = $response->getBody()->getContents();

        if ($statusCode >= 400) {
            throw new \RuntimeException("ESI error ($statusCode): $responseBody");
        }

        $decoded = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $responseBody;
        }

        return $decoded;
    }
}
