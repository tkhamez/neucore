<?php

declare(strict_types=1);

namespace Neucore\Mcp\Tools;

use Mcp\Capability\Attribute\McpTool;
use Neucore\Entity\EveLogin;
use Neucore\Factory\RepositoryFactory;
use Neucore\Mcp\ResponseBuilder;
use Neucore\Service\EsiClient;
use Psr\Http\Client\ClientExceptionInterface;

class EsiTools
{
    private const DEFAULT_EVE_LOGIN = EveLogin::NAME_DEFAULT;

    public function __construct(
        private readonly RepositoryFactory $repositoryFactory,
        private readonly EsiClient $esiClient,
        private readonly ResponseBuilder $responseBuilder,
    ) {}

    #[McpTool(
        name: 'esi_request',
        description: "Make an authenticated ESI API request using the character's ESI token. "
            . 'Quick rule: Auth required? → `esi_request`. Public endpoint? → `https://esi.evetech.net` directly. '
            . 'Endpoints with a "security" entry in the ESI OpenAPI spec require authentication '
            . '(e.g. "/characters/{character_id}/assets"). '
            . 'All other endpoints are public (e.g. "/universe/stations/{station_id}"). '
            . 'To get the OpenAPI spec with the latest compatibility date: '
            . '1) GET https://esi.evetech.net/meta/compatibility-dates '
            . '2) Read .compatibility_dates[0] '
            . '3) Use this value as compatibility_date in: '
            . 'https://esi.evetech.net/meta/openapi.json?compatibility_date=<DATE>. '
            . 'See extensions ai.neucore/rateLimits and ai.neucore/esiPagination for information '
            . 'about rate limits and pagination',
    )]
    /**
     * @param int $characterId The EVE character ID to use for authentication
     * @param string $path The ESI API endpoint path (e.g. '/universe/stations/{station_id}')
     * @param string $method The HTTP method (default: 'GET')
     * @param string|null $body Optional request body for POST/PUT requests
     * @param string|null $compatibilityDate ESI compatibility date in YYYY-MM-DD format,
     *        it may not be in the future (UTC-11) (uses current date if not provided)
     */
    public function esiRequest(
        int $characterId,
        string $path,
        string $method = 'GET',
        ?string $body = null,
        ?string $compatibilityDate = null,
    ): array {
        if ($compatibilityDate === null) {
            // ESI uses UTC-11
            $compatibilityDate = gmdate('Y-m-d', time() - 11 * 3600);
        }

        if ($characterId <= 0) {
            return $this->responseBuilder->error(0, 'Character ID must be a positive integer');
        }

        $character = $this->repositoryFactory->getCharacterRepository()->find($characterId);
        if ($character === null) {
            return $this->responseBuilder->error(0, 'Character not found');
        }

        $esiToken = $character->getEsiToken(self::DEFAULT_EVE_LOGIN);
        if ($esiToken === null || $esiToken->getValidToken() !== true) {
            return $this->responseBuilder->error(0, 'Character has no valid ESI token for the default login');
        }

        try {
            $response = $this->esiClient->request(
                $path,
                $method,
                $body,
                $characterId,
                self::DEFAULT_EVE_LOGIN,
                false,
                $compatibilityDate,
            );
        } catch (ClientExceptionInterface $e) {
            return $this->responseBuilder->error(0, 'ESI request failed: ' . $e->getMessage());
        }

        $statusCode = $response->getStatusCode();
        $responseBody = $response->getBody()->getContents();

        $headers = [];
        foreach ([
            # old error limit
            EsiClient::HEADER_ERROR_LIMIT_REMAIN,
            EsiClient::HEADER_ERROR_LIMIT_RESET,
            # new rate limit
            EsiClient::HEADER_RATE_LIMIT_GROUP,
            EsiClient::HEADER_RATE_LIMIT_LIMIT,
            EsiClient::HEADER_RATE_LIMIT_REMAINING,
            EsiClient::HEADER_RATE_LIMIT_USED,
            EsiClient::HEADER_RETRY_AFTER,
            # other
            EsiClient::HEADER_PAGES,
            EsiClient::HEADER_COMPATIBILITY_DATE,
            EsiClient::HEADER_WARNING,
            # cursor-based pagination (token-based)
            EsiClient::HEADER_BEFORE,
            EsiClient::HEADER_AFTER,
        ] as $headerName) {
            $headerValue = $response->getHeader($headerName)[0] ?? '';
            if ($headerValue !== '') {
                $headers[$headerName] = $headerValue;
            }
        }

        if ($statusCode >= 400) {
            return $this->responseBuilder->error(
                $statusCode,
                "ESI error ($statusCode): $responseBody",
                $headers,
            );
        }

        $decoded = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->responseBuilder->error($statusCode, 'Failed to decode body.', $headers);
        }

        return $this->responseBuilder->success($decoded, $headers);
    }
}
