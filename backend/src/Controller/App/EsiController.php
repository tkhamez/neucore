<?php

declare(strict_types=1);

namespace Neucore\Controller\App;

use Neucore\Application;
use Neucore\Controller\BaseController;
use Neucore\Entity\App;
use Neucore\Entity\EveLogin;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\HttpClientFactoryInterface;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\AppAuth;
use Neucore\Service\EsiClient;
use Neucore\Service\OAuthToken;
use Neucore\Service\ObjectManager;
use Neucore\Storage\StorageInterface;
use OpenApi\Attributes as OA;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[OA\Tag(name: 'Application - ESI')]
#[OA\Schema(
    schema: 'EsiTokenData',
    required: ['lastChecked', 'characterId', 'characterName', 'corporationId', 'allianceId'],
    properties: [
        new OA\Property(property: 'lastChecked', type: 'string', nullable: true),
        new OA\Property(property: 'characterId', type: 'integer'),
        new OA\Property(property: 'characterName', type: 'string'),
        new OA\Property(property: 'corporationId', type: 'integer', nullable: true),
        new OA\Property(property: 'allianceId', type: 'integer', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'EsiAccessToken',
    required: ['token', 'scopes', 'expires'],
    properties: [
        new OA\Property(property: 'token', type: 'string'),
        new OA\Property(property: 'scopes', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'expires', type: 'integer'),
    ],
)]
class EsiController extends BaseController
{
    private const ERROR_MESSAGE_PREFIX = 'App\EsiController: ';

    private const PARAM_DATASOURCE = 'datasource';

    private StorageInterface $storage;

    private LoggerInterface $log;

    private HttpClientFactoryInterface $httpClientFactory;

    private AppAuth $appAuth;

    private EsiClient $esiClient;

    private ?App $app = null;

    private ?EveLogin $eveLogin = null;

    /**
     * @see \Neucore\Plugin\Core\EsiClient::$errorLimitRemaining
     * @see \Neucore\Command\Traits\EsiRateLimited::$errorLimitRemaining
     */
    private int $errorLimitRemain = 20;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        StorageInterface $storage,
        LoggerInterface $log,
        HttpClientFactoryInterface $httpClientFactory,
        AppAuth $appAuth,
        EsiClient $esiClient,
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);

        $this->storage = $storage;
        $this->log = $log;
        $this->httpClientFactory = $httpClientFactory;
        $this->appAuth = $appAuth;
        $this->esiClient = $esiClient;
    }

    #[OA\Get(
        path: '/app/v1/esi/eve-login/{name}/characters',
        operationId: 'esiEveLoginCharactersV1',
        description: 'Needs role: app-esi-login.',
        summary: 'Returns character IDs of characters that have an ESI token (including invalid) of an EVE login.',
        security: [['BearerAuth' => []]],
        tags: ['Application - ESI'],
        parameters: [
            new OA\Parameter(
                name: 'name',
                description: 'EVE login name.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', maxLength: 20, pattern: '^[-._a-zA-Z0-9]+$'),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: '',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'integer')),
            ),
            new OA\Response(
                response: '403',
                description: 'Forbidden',
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(
                response: '404',
                description: 'EVE login not found.',
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(response: '500', description: '', content: new OA\JsonContent(type: 'string')),
        ],
    )]
    public function eveLoginCharacters(string $name, ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->validateTokenRequest($name, $request);
        if ($response) {
            return $response;
        }

        $charIds = $this->repositoryFactory->getEsiTokenRepository()
            // Note: $this->eveLogin always exists at this point
            ->findCharacterIdsByLoginId((int) $this->eveLogin?->getId());

        return $this->withJson($charIds);
    }

    #[OA\Get(
        path: '/app/v1/esi/eve-login/{name}/token-data',
        operationId: 'esiEveLoginTokenDataV1',
        description: 'Needs role: app-esi-login.',
        summary: 'Returns data for all valid tokens (roles are also checked if applicable) for an EVE login.',
        security: [['BearerAuth' => []]],
        tags: ['Application - ESI'],
        parameters: [
            new OA\Parameter(
                name: 'name',
                description: "EVE login name, 'core.default' is not allowed.",
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', maxLength: 20, pattern: '^[-._a-zA-Z0-9]+$'),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: '',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/EsiTokenData'),
                ),
            ),
            new OA\Response(
                response: '403',
                description: 'Forbidden',
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(
                response: '404',
                description: 'EVE login not found.',
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(response: '500', description: '', content: new OA\JsonContent(type: 'string')),
        ],
    )]
    public function eveLoginTokenData(string $name, ServerRequestInterface $request): ResponseInterface
    {
        if ($name === EveLogin::NAME_DEFAULT) {
            return $this->response->withStatus(403);
        }

        $response = $this->validateTokenRequest($name, $request);
        if ($response) {
            return $response;
        }

        $tokenData = $this->repositoryFactory->getEsiTokenRepository()
            // Note: $this->eveLogin always exists at this point
            ->findValidTokens((int) $this->eveLogin?->getId());

        return $this->withJson($tokenData);
    }

    #[OA\Get(
        path: '/app/v1/esi/access-token/{characterId}',
        operationId: 'esiAccessTokenV1',
        description: 'Needs role: app-esi-token',
        summary: 'Returns an access token for a character and EVE login that is valid for at least 60 seconds.',
        security: [['BearerAuth' => []]],
        tags: ['Application - ESI'],
        parameters: [
            new OA\Parameter(
                name: 'characterId',
                description: 'The EVE character ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\Parameter(
                name: 'eveLoginName',
                description: "Optional EVE login name, defaults to 'core.default'.",
                in: 'query',
                schema: new OA\Schema(type: 'string', maxLength: 20, pattern: '^[-._a-zA-Z0-9]+$'),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'Success',
                content: new OA\JsonContent(ref: '#/components/schemas/EsiAccessToken'),
            ),
            new OA\Response(
                response: '204',
                description: 'Invalid token.',
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(
                response: '403',
                description: 'Forbidden',
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(
                response: '404',
                description: 'ESI token not found.',
                content: new OA\JsonContent(type: 'string'),
            ),
        ],
    )]
    public function accessToken(
        int $characterId,
        ServerRequestInterface $request,
        OAuthToken $tokenService,
    ): ResponseInterface {
        $eveLoginName = $this->getQueryParam($request, 'eveLoginName', EveLogin::NAME_DEFAULT);

        $response = $this->validateTokenRequest($eveLoginName, $request);
        if ($response) {
            return $response;
        }

        $esiToken = $this->repositoryFactory->getEsiTokenRepository()->findOneBy([
            'character' => $characterId,
            'eveLogin' => $this->eveLogin?->getId(),
        ]);
        if (!$esiToken) {
            return $this->response->withStatus(404);
        }

        $token = $tokenService->updateEsiToken($esiToken);
        if (!$token || !$esiToken->getValidToken()) {
            return $this->response->withStatus(204);
        }

        return $this->withJson([
            'token' => $token->getToken(),
            'scopes' => $tokenService->getEveAuth($token)?->getScopes(), // Note: It's never null here
            'expires' => $token->getExpires(),
        ]);
    }

    #[OA\Get(
        path: '/app/v1/esi',
        operationId: 'esiV1',
        summary: 'See GET /app/v2/esi. The only difference are the return values in case of errors.',
        security: [['BearerAuth' => []]],
        tags: ['Application - ESI'],
        parameters: [
            new OA\Parameter(
                name: 'Neucore-EveCharacter',
                description: "The EVE character ID those token should be used. Has priority over the query " .
                    "parameter 'datasource'",
                in: 'header',
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'Neucore-EveLogin',
                description: 'The EVE login name from which the token should be used, defaults to core.default.',
                in: 'header',
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'esi-path-query',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'datasource',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: '',
                headers: [
                    new OA\Header(header: 'Expires', schema: new OA\Schema(type: 'string')),
                ],
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(
                response: '304',
                description: '',
                headers: [
                    new OA\Header(header: 'Expires', schema: new OA\Schema(type: 'string')),
                ],
            ),
            new OA\Response(response: '400', description: '', content: new OA\JsonContent(type: 'string')),
            new OA\Response(response: '401', description: '', content: new OA\JsonContent(type: 'string')),
            new OA\Response(response: '403', description: '', content: new OA\JsonContent(type: 'string')),
            new OA\Response(response: '420', description: '', content: new OA\JsonContent(type: 'string')),
            new OA\Response(
                response: '429',
                description: 'Too many errors, see reason phrase for more.',
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(response: '500', description: '', content: new OA\JsonContent(type: 'string')),
            new OA\Response(response: '503', description: '', content: new OA\JsonContent(type: 'string')),
            new OA\Response(response: '504', description: '', content: new OA\JsonContent(type: 'string')),
        ],
        deprecated: true,
    )]
    public function esiV1(ServerRequestInterface $request, ?string $path = null): ResponseInterface
    {
        return $this->esiRequest($request, 'GET', $path, 1);
    }

    #[OA\Get(
        path: '/app/v2/esi',
        operationId: 'esiV2',
        description: "Needs role: app-esi-proxy<br>" .
            "Either the header 'Neucore-EveCharacter' and optionally 'Neucore-EveLogin' or the query " .
            "parameter 'datasource' is required.<br> " .
            "Public ESI routes are not allowed.<br>" .
            "The following headers from ESI are passed through to the response if they exist: Content-Type, " .
            "Expires, X-Esi-Error-Limit-Remain, X-Esi-Error-Limit-Reset, X-Pages, X-Compatibility-Date, " .
            "warning, Warning.<br>" .
            "The HTTP status code from ESI is also passed through, so there may be more than the documented " .
            "ones.<br>" .
            "The ESI path and query parameters can alternatively be appended to the path of this endpoint, this " .
            "allows using OpenAPI clients generated for the EVE API (ESI), see doc/api-examples for more.",
        summary: 'Makes an ESI GET request on behalf on an EVE character and returns the result.',
        security: [['BearerAuth' => []]],
        tags: ['Application - ESI'],
        parameters: [
            new OA\Parameter(
                name: 'Neucore-EveCharacter',
                description: "The EVE character ID those token should be used. Has priority over the query " .
                    "parameter 'datasource'",
                in: 'header',
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'Neucore-EveLogin',
                description: 'The EVE login name from which the token should be used, defaults to core.default.',
                in: 'header',
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'X-Compatibility-Date',
                description: "The ESI compatibility date.",
                in: 'header',
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'Accept-Language',
                in: 'header',
                schema: new OA\Schema(type: 'string', enum: ['en', 'de', 'fr', 'ja', 'ru', 'zh', 'ko', 'es']),
            ),
            new OA\Parameter(
                name: 'esi-path-query',
                description: 'The ESI path and query string (without the datasource parameter).',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'datasource',
                description: 'The EVE character ID those token should be used from the default login to ' .
                    'make the ESI request. Optionally followed by a colon and the name of an EVE login to ' .
                    'use an alternative ESI token.',
                in: 'query',
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'The data from ESI.<br> Please note that the JSON schema type can be an object, ' .
                    'array or number etc., unfortunately, there is no way to document this.',
                headers: [
                    new OA\Header(
                        header: 'Expires',
                        description: 'RFC7231 formatted datetime string',
                        schema: new OA\Schema(type: 'string'),
                    ),
                ],
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(
                response: '304',
                description: 'Not modified',
                headers: [
                    new OA\Header(
                        header: 'Expires',
                        description: 'RFC7231 formatted datetime string',
                        schema: new OA\Schema(type: 'string'),
                    ),
                ],
            ),
            new OA\Response(
                response: '400',
                description: 'Bad request, see reason phrase and/or body for more.',
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(
                response: '401',
                description: 'Unauthorized',
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(
                response: '403',
                description: 'Forbidden',
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(
                response: '420',
                description: 'Error limited',
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(
                response: '429',
                description: 'Too many errors, see body for more.',
                headers: [
                    new OA\Header(
                        header: 'Retry-After',
                        description: 'Delay in seconds.',
                        schema: new OA\Schema(type: 'string'),
                    ),
                ],
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(
                response: '500',
                description: 'Internal server error',
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(
                response: '503',
                description: 'Service unavailable',
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(
                response: '504',
                description: 'Gateway timeout',
                content: new OA\JsonContent(type: 'string'),
            ),
        ],
    )]
    public function esiV2(ServerRequestInterface $request, ?string $path = null): ResponseInterface
    {
        return $this->esiRequest($request, 'GET', $path, 2);
    }

    #[OA\Post(
        path: '/app/v1/esi',
        operationId: 'esiPostV1',
        summary: 'See POST /app/v2/esi. The only difference are the return values in case of errors.',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            description: '',
            required: true,
            content: new OA\MediaType(mediaType: 'text/plain', schema: new OA\Schema(type: 'string')),
        ),
        tags: ['Application - ESI'],
        parameters: [
            new OA\Parameter(
                name: 'Neucore-EveCharacter',
                description: "The EVE character ID those token should be used. Has priority over the query " .
                    "parameter 'datasource'",
                in: 'header',
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'Neucore-EveLogin',
                description: 'The EVE login name from which the token should be used, defaults to core.default.',
                in: 'header',
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'esi-path-query',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'datasource',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: '',
                headers: [
                    new OA\Header(header: 'Expires', schema: new OA\Schema(type: 'string')),
                ],
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(
                response: '304',
                description: '',
                headers: [
                    new OA\Header(header: 'Expires', schema: new OA\Schema(type: 'string')),
                ],
            ),
            new OA\Response(response: '400', description: '', content: new OA\JsonContent(type: 'string')),
            new OA\Response(response: '401', description: '', content: new OA\JsonContent(type: 'string')),
            new OA\Response(response: '403', description: '', content: new OA\JsonContent(type: 'string')),
            new OA\Response(response: '420', description: '', content: new OA\JsonContent(type: 'string')),
            new OA\Response(
                response: '429',
                description: 'Too many errors, see reason phrase for more.',
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(response: '500', description: '', content: new OA\JsonContent(type: 'string')),
            new OA\Response(response: '503', description: '', content: new OA\JsonContent(type: 'string')),
            new OA\Response(response: '504', description: '', content: new OA\JsonContent(type: 'string')),
        ],
        deprecated: true,
    )]
    public function esiPostV1(ServerRequestInterface $request, ?string $path = null): ResponseInterface
    {
        return $this->esiRequest($request, 'POST', $path, 1);
    }

    #[OA\Post(
        path: '/app/v2/esi',
        operationId: 'esiPostV2',
        summary: 'Same as GET /app/v2/esi, but for POST requests.',
        security: [['BearerAuth' => []]],
        requestBody: new OA\RequestBody(
            description: 'JSON encoded data.',
            required: true,
            content: new OA\MediaType(mediaType: 'text/plain', schema: new OA\Schema(type: 'string')),
        ),
        tags: ['Application - ESI'],
        parameters: [
            new OA\Parameter(
                name: 'Neucore-EveCharacter',
                description: "The EVE character ID those token should be used. Has priority over the query " .
                    "parameter 'datasource'",
                in: 'header',
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'Neucore-EveLogin',
                description: 'The EVE login name from which the token should be used, defaults to core.default.',
                in: 'header',
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'X-Compatibility-Date',
                description: "The ESI compatibility date.",
                in: 'header',
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'Accept-Language',
                in: 'header',
                schema: new OA\Schema(type: 'string', enum: ['en', 'de', 'fr', 'ja', 'ru', 'zh', 'ko', 'es']),
            ),
            new OA\Parameter(
                name: 'esi-path-query',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'datasource',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: '',
                headers: [
                    new OA\Header(header: 'Expires', schema: new OA\Schema(type: 'string')),
                ],
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(
                response: '304',
                description: '',
                headers: [
                    new OA\Header(header: 'Expires', schema: new OA\Schema(type: 'string')),
                ],
            ),
            new OA\Response(response: '400', description: '', content: new OA\JsonContent(type: 'string')),
            new OA\Response(response: '401', description: '', content: new OA\JsonContent(type: 'string')),
            new OA\Response(response: '403', description: '', content: new OA\JsonContent(type: 'string')),
            new OA\Response(response: '420', description: '', content: new OA\JsonContent(type: 'string')),
            new OA\Response(
                response: '429',
                description: '',
                headers: [
                    new OA\Header(
                        header: 'Retry-After',
                        description: 'Delay in seconds.',
                        schema: new OA\Schema(type: 'string'),
                    ),
                ],
                content: new OA\JsonContent(type: 'string'),
            ),
            new OA\Response(response: '500', description: '', content: new OA\JsonContent(type: 'string')),
            new OA\Response(response: '503', description: '', content: new OA\JsonContent(type: 'string')),
            new OA\Response(response: '504', description: '', content: new OA\JsonContent(type: 'string')),
        ],
    )]
    public function esiPostV2(ServerRequestInterface $request, ?string $path = null): ResponseInterface
    {
        return $this->esiRequest($request, 'POST', $path, 2);
    }

    private function esiRequest(
        ServerRequestInterface $request,
        string $method,
        ?string $path,
        int $version,
    ): ResponseInterface {
        $this->app = $this->appAuth->getApp($request);

        if ($this->checkErrors($version)) {
            return $this->response;
        }

        // get/validate input
        $esiPath = $this->getEsiPathWithQueryParams($request, $path);
        $dataSource = $this->getDataSource($request);
        if (str_contains($dataSource, ':')) {
            $dataSourceTmp = explode(':', $dataSource);
            $characterId = $dataSourceTmp[0];
            $eveLoginName = !empty($dataSourceTmp[1]) ? $dataSourceTmp[1] : EveLogin::NAME_DEFAULT;
        } else {
            $characterId = $dataSource;
            $eveLoginName = EveLogin::NAME_DEFAULT;
        }
        if (empty($esiPath) || $this->isPublicPath($esiPath) || empty($characterId)) {
            if (empty($esiPath)) {
                $reason = 'Path cannot be empty.';
            } elseif ($this->isPublicPath($esiPath)) {
                $reason = 'Public ESI routes are not allowed.';
            } else { // empty($characterId)
                $reason = 'The Neucore-EveCharacter header and datasource parameter cannot both be empty, ' .
                    'one of them must contain an EVE character ID';
            }
            if ($version === 1) {
                return $this->response->withStatus(400, $reason);
            }
            return $this->withJson($reason, 400);
        }

        // Check app permission for EVE login
        if (!$this->hasEveLogin($eveLoginName)) {
            return $this->response->withStatus(403);
        }

        $body = $method === 'POST' ? $request->getBody()->__toString() : null;

        // Send request and handle errors.
        try {
            $esiResponse = $this->esiClient->request(
                $esiPath,
                $method,
                $body,
                (int) $characterId,
                $eveLoginName,
                compatibilityDate: $request->getHeader('X-Compatibility-Date')[0] ?? null,
                acceptLanguage: $request->getHeader('Accept-Language')[0] ?? null,
            );
        } catch (RuntimeException $e) {
            if ($e->getCode() === 568420) {
                $errorMessage = 'Character not found.';
                if ($version === 1) {
                    return $this->response->withStatus(400, $errorMessage);
                }
                return $this->withJson($errorMessage, 400);
            } elseif ($e->getCode() === 568421) {
                $errorMessage = 'Character has no valid token.';
                if ($version === 1) {
                    return $this->response->withStatus(400, $errorMessage);
                }
                return $this->withJson($errorMessage, 400);
            } else {
                // should not happen
                return $this->withJson('Unknown error.', 400);
            }
        } catch (ClientExceptionInterface $e) {
            $this->log->error(self::ERROR_MESSAGE_PREFIX . '(' . $this->appString() . '): ' . $e->getMessage());
            $esiResponse = $this->httpClientFactory->createResponse(
                500, // status
                [], // header
                $e->getMessage(), // body
            );
        }

        if ($esiResponse->getStatusCode() < 200 || $esiResponse->getStatusCode() > 299) {
            $message = $esiResponse->getBody()->getContents();
            $this->log->error(self::ERROR_MESSAGE_PREFIX . '(' . $this->appString() . ') ' . "$esiPath: $message");
        }

        return $this->buildResponse($esiResponse);
    }

    private function checkErrors(int $version): bool
    {
        // Check error limit.
        if (($retryAt1 = EsiClient::getErrorLimitWaitTime($this->storage, $this->errorLimitRemain)) > 0) {
            $errorMessage = 'Maximum permissible ESI error limit reached';
            $this->build429Response(
                "$errorMessage (X-Esi-Error-Limit-Remain <= $this->errorLimitRemain).",
                $retryAt1,
                $version,
                "$errorMessage.",
            );
            return true;
        }

        // Check 429 rate limit.
        if (($retryAt2 = EsiClient::getRateLimitWaitTime($this->storage)) > time()) {
            $this->build429Response('ESI rate limit reached.', $retryAt2, $version);
            return true;
        }

        // Check throttled.
        if (($retryAt3 = EsiClient::getThrottledWaitTime($this->storage)) > time()) {
            $this->build429Response(
                'Undefined 429 response. You have been temporarily throttled.',
                $retryAt3,
                $version,
            );
            return true;
        }

        return false;
    }

    private function build429Response(string $message, int $retryAfter, int $version, ?string $messageV1 = null): void
    {
        $this->log->warning(self::ERROR_MESSAGE_PREFIX . $this->appString() . ": $message");
        if ($version === 1) {
            $this->response = $this->response->withStatus(429, $messageV1 ?: $message);
        } else {
            $this->response = $this->response->withHeader('Retry-After', (string) max(1, $retryAfter - time()));
            $this->response = $this->withJson($message, 429);
        }
    }

    private function getEsiPathWithQueryParams(ServerRequestInterface $request, ?string $path): string
    {
        if (empty($path)) {
            // for URLs like: /api/app/v2/esi?esi-path-query=%2Fv3%2Fcharacters%2F96061222%2Fassets%2F%3Fpage%3D1
            $esiPath = $this->getQueryParam($request, 'esi-path-query', '');
        } else {
            // for URLs like /api/app/v2/esi/v3/characters/96061222/assets/?datasource=96061222&page=1
            $esiPath = $path;
            $esiParams = [];
            foreach ($request->getQueryParams() as $key => $value) {
                if ($key !== self::PARAM_DATASOURCE) {
                    $esiParams[] = $key . '=' . $value;
                }
            }
            if (!empty($esiParams)) {
                $esiPath .= '?' . implode('&', $esiParams);
            }
        }

        return $esiPath;
    }

    private function getDataSource(ServerRequestInterface $request): string
    {
        $character = $request->getHeader('Neucore-EveCharacter')[0] ?? '';
        $login = $request->getHeader('Neucore-EveLogin')[0] ?? '';
        if ($character !== '') {
            return "$character:$login";
        }

        return $this->getQueryParam($request, self::PARAM_DATASOURCE, '');
    }

    private function isPublicPath(string $esiPath): bool
    {
        $path = substr($esiPath, (int) strpos($esiPath, '/', 1));

        $publicPaths = Application::loadFile('esi-paths-public.php');

        foreach ($publicPaths as $pattern) {
            if (preg_match("@^$pattern$@", $path) === 1) {
                return true;
            }
        }

        return false;
    }

    private function buildResponse(ResponseInterface $esiResponse): ResponseInterface
    {
        $body = $esiResponse->getBody()->__toString();
        $this->response->getBody()->write($body);

        $response = $this->response->withStatus($esiResponse->getStatusCode(), $esiResponse->getReasonPhrase());

        $headerAllowList = [
            'Content-Type',
            'Expires',
            'X-Esi-Error-Limit-Remain',
            'X-Esi-Error-Limit-Reset',
            'X-Pages',
            'X-Compatibility-Date',
            'warning',
            'Warning',
            'Retry-After',
        ];
        foreach ($esiResponse->getHeaders() as $name => $value) {
            if (in_array($name, $headerAllowList)) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }

    private function appString(): string
    {
        if ($this->app) {
            return 'application ' . $this->app->getId() . ' "' . $this->app->getName() . '"';
        }
        return '';
    }

    private function validateTokenRequest(string $name, ServerRequestInterface $request): ?ResponseInterface
    {
        $this->eveLogin = $this->repositoryFactory->getEveLoginRepository()->findOneBy(['name' => $name]);
        if ($this->eveLogin === null) {
            return $this->response->withStatus(404);
        }

        $this->app = $this->appAuth->getApp($request);
        if (!$this->hasEveLogin($this->eveLogin->getName())) {
            return $this->response->withStatus(403);
        }

        return null;
    }

    private function hasEveLogin(string $eveLoginName): bool
    {
        if ($this->app === null) {
            return false;
        }

        foreach ($this->app->getEveLogins() as $login) {
            if ($login->getName() === $eveLoginName) {
                return true;
            }
        }

        return false;
    }
}
