<?php

declare(strict_types=1);

namespace Neucore\Controller\App;

use Neucore\Application;
use Neucore\Controller\BaseController;
use Neucore\Entity\App;
use Neucore\Entity\EveLogin;
use Neucore\Factory\HttpClientFactoryInterface;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\AppAuth;
use Neucore\Service\Config;
use Neucore\Service\OAuthToken;
use Neucore\Service\ObjectManager;
use Neucore\Storage\StorageInterface;
use Neucore\Storage\Variables;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * @OA\Tag(
 *     name="Application - ESI"
 * )
 */
class EsiController extends BaseController
{
    private const ERROR_MESSAGE_PREFIX = 'App\EsiController: ';

    private const PARAM_DATASOURCE = 'datasource';

    private StorageInterface $storage;

    private LoggerInterface $log;

    private OAuthToken $tokenService;

    private Config $config;

    private HttpClientFactoryInterface $httpClientFactory;

    private AppAuth $appAuth;

    private ?App $app = null;

    private int $errorLimitRemain = 20;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        StorageInterface $storage,
        LoggerInterface $log,
        OAuthToken $tokenService,
        Config $config,
        HttpClientFactoryInterface $httpClientFactory,
        AppAuth $appAuth
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);

        $this->storage = $storage;
        $this->log = $log;
        $this->tokenService = $tokenService;
        $this->config = $config;
        $this->httpClientFactory = $httpClientFactory;
        $this->appAuth = $appAuth;
    }

    /**
     * @OA\Get(
     *     path="/app/v1/esi/eve-login/{name}/characters",
     *     operationId="esiEveLoginCharactersV1",
     *     summary="Returns character IDs of characters that have a valid ESI token of the specified EVE login.",
     *     description="Needs role: app-esi.",
     *     tags={"Application - ESI"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="name",
     *         in="path",
     *         required=true,
     *         description="EVE login name, 'core.default' is not allowed.",
     *         @OA\Schema(type="string", maxLength=20, pattern="^[-._a-zA-Z0-9]+$")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(type="array", @OA\Items(type="integer"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="EVE login not found.",
     *         @OA\JsonContent(type="string")
     *     )
     * )
     */
    public function eveLoginCharacters(string $name, ServerRequestInterface $request): ResponseInterface
    {
        if ($name === EveLogin::NAME_DEFAULT) {
            return $this->response->withStatus(403);
        }

        $eveLogin = $this->repositoryFactory->getEveLoginRepository()->findOneBy(['name' => $name]);
        if ($eveLogin === null) {
            return $this->response->withStatus(404);
        }

        $this->app = $this->appAuth->getApp($request);
        if (!$this->hasEveLogin($eveLogin)) {
            return $this->response->withStatus(404);
        }

        $charIds = [];
        foreach ($eveLogin->getEsiTokens() as $token) {
            if ($token->getCharacter() !== null) {
                $charIds[] = $token->getCharacter()->getId();
            }
        }

        return $this->withJson($charIds);
    }

    /**
     * @OA\Get(
     *     path="/app/v1/esi",
     *     deprecated=true,
     *     operationId="esiV1",
     *     summary="See GET /app/v2/esi",
     *     tags={"Application - ESI"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="esi-path-query",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="datasource",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(type="string"),
     *         @OA\Header(
     *             header="Expires",
     *             @OA\Schema(type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response="304",
     *         description="",
     *         @OA\Header(
     *             header="Expires",
     *             @OA\Schema(type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="420",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="429",
     *         description="",
     *         description="Too many errors, see reason phrase for more.",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="503",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="504",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     )
     * )
     */
    public function esiV1(ServerRequestInterface $request, ?string $path = null): ResponseInterface
    {
        return $this->esiRequest($request, 'GET', $path, 1);
    }

    /**
     * @OA\Get(
     *     path="/app/v2/esi",
     *     operationId="esiV2",
     *     summary="Makes an ESI GET request on behalf on an EVE character and returns the result.",
     *     description="Needs role: app-esi<br>
     *         Public ESI routes are not allowed.<br>
     *         The following headers from ESI are passed through to the response if they exist:
               Content-Type Expires X-Esi-Error-Limit-Remain X-Esi-Error-Limit-Reset X-Pages warning, Warning<br>
     *         The HTTP status code from ESI is also passed through, so there may be more than the documented ones.<br>
     *         The ESI path and query parameters can alternatively be appended to the path of this endpoint,
               this allows to use OpenAPI clients that were generated for the ESI API,
               see doc/app-esi-examples.php for more.",
     *     tags={"Application - ESI"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="esi-path-query",
     *         in="query",
     *         required=true,
     *         description="The ESI path and query string (without the datasource parameter).",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="datasource",
     *         in="query",
     *         required=true,
     *         description="The EVE character ID those token should be used to make the ESI request. Optionally
                            followed by a colon and the name of an EVE login to use an alternative ESI token.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The data from ESI.<br>
                            Please note that the JSON schema type can be an object, array or number etc.,
                            unfortunately there is no way to document this.",
     *         @OA\JsonContent(type="string"),
     *         @OA\Header(
     *             header="Expires",
     *             description="RFC7231 formatted datetime string",
     *             @OA\Schema(type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response="304",
     *         description="Not modified",
     *         @OA\Header(
     *             header="Expires",
     *             description="RFC7231 formatted datetime string",
     *             @OA\Schema(type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Bad request, see reason phrase and/or body for more.",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Forbidden",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="420",
     *         description="Error limited",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="429",
     *         description="Too many errors, see body for more.",
     *         @OA\JsonContent(type="string"),
     *         @OA\Header(
     *             header="Retry-After",
     *             description="Delay in seconds.",
     *             @OA\Schema(type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Internal server error",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="503",
     *         description="Service unavailable",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="504",
     *         description="Gateway timeout",
     *         @OA\JsonContent(type="string")
     *     )
     * )
     * @noinspection PhpUnused
     */
    public function esiV2(ServerRequestInterface $request, ?string $path = null): ResponseInterface
    {
        return $this->esiRequest($request, 'GET', $path, 2);
    }

    /**
     * @OA\Post(
     *     path="/app/v1/esi",
     *     deprecated=true,
     *     operationId="esiPostV1",
     *     summary="See POST /app/v2/esi",
     *     tags={"Application - ESI"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="esi-path-query",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="datasource",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="",
     *         @OA\MediaType(
     *             mediaType="text/plain",
     *             @OA\Schema(type="string")
     *         ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(type="string"),
     *         @OA\Header(
     *             header="Expires",
     *             @OA\Schema(type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response="304",
     *         description="",
     *         @OA\Header(
     *             header="Expires",
     *             @OA\Schema(type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="420",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="429",
     *         description="",
     *         description="Too many errors, see reason phrase for more.",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="503",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="504",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     )
     * )
     */
    public function esiPostV1(ServerRequestInterface $request, ?string $path = null): ResponseInterface
    {
        return $this->esiRequest($request, 'POST', $path, 1);
    }

    /**
     * @OA\Post(
     *     path="/app/v2/esi",
     *     operationId="esiPostV2",
     *     summary="Same as GET /app/v2/esi, but for POST requests.",
     *     tags={"Application - ESI"},
     *     security={{"BearerAuth"={}}},
     *     @OA\Parameter(
     *         name="esi-path-query",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="datasource",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="JSON encoded data.",
     *         @OA\MediaType(
     *             mediaType="text/plain",
     *             @OA\Schema(type="string")
     *         ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="",
     *         @OA\JsonContent(type="string"),
     *         @OA\Header(
     *             header="Expires",
     *             @OA\Schema(type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response="304",
     *         description="",
     *         @OA\Header(
     *             header="Expires",
     *             @OA\Schema(type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="420",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="429",
     *         description="",
     *         @OA\JsonContent(type="string"),
     *         @OA\Header(
     *             header="Retry-After",
     *             description="Delay in seconds.",
     *             @OA\Schema(type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="503",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="504",
     *         description="",
     *         @OA\JsonContent(type="string")
     *     )
     * )
     * @noinspection PhpUnused
     */
    public function esiPostV2(ServerRequestInterface $request, ?string $path = null): ResponseInterface
    {
        return $this->esiRequest($request, 'POST', $path, 2);
    }

    private function esiRequest(
        ServerRequestInterface $request,
        string $method,
        ?string $path,
        int $version
    ): ResponseInterface {
        $this->app = $this->appAuth->getApp($request);

        if ($this->checkErrors($version)) {
            return $this->response;
        }

        // get/validate input
        list($esiPath, $esiParams) = $this->getEsiPathAndQueryParams($request, $path);
        $dataSource = $this->getQueryParam($request, self::PARAM_DATASOURCE, '');
        if (strpos($dataSource, ':') !== false) {
            $dataSourceTmp = explode(':', $dataSource);
            $characterId = $dataSourceTmp[0];
            $eveLoginName = isset($dataSourceTmp[1]) && !empty($dataSourceTmp[1]) ?
                $dataSourceTmp[1] : EveLogin::NAME_DEFAULT;
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
                $reason = 'The datasource parameter cannot be empty, it must contain an EVE character ID';
            }
            if ($version === 1) {
                return $this->response->withStatus(400, $reason);
            }
            return $this->withJson($reason, 400);
        }

        // get character
        $character = $this->repositoryFactory->getCharacterRepository()->find($characterId);
        if ($character === null) {
            $errorMessage = 'Character not found.';
            if ($version === 1) {
                return $this->response->withStatus(400, $errorMessage);
            }
            return $this->withJson($errorMessage, 400);
        }

        // Get the token - This executes an ESI request to refresh the token as needed.
        $token = $this->tokenService->getToken($character, $eveLoginName);
        if ($token === '') {
            $errorMessage = 'Character has no valid token.';
            if ($version === 1) {
                return $this->response->withStatus(400, $errorMessage);
            }
            return $this->withJson($errorMessage, 400);
        }

        $body = $method === 'POST' ? $request->getBody()->__toString() : null;
        $esiResponse = $this->sendRequest($esiPath, $esiParams, $token, $eveLoginName, $method, $body);

        return $this->buildResponse($esiResponse);
    }

    private function checkErrors(int $version): bool
    {
        // Check error limit.
        if (($retryAt1 = $this->errorLimitRemaining()) > 0) {
            $errorMessage = 'Maximum permissible ESI error limit reached';
            $this->build429Response(
                "$errorMessage (X-Esi-Error-Limit-Remain <= $this->errorLimitRemain).",
                $retryAt1,
                $version,
                "$errorMessage."
            );
            return true;
        }

        // Check 429 rate limit.
        if (($retryAt2 = (int)$this->storage->get(Variables::ESI_RATE_LIMIT)) > time()) {
            $this->build429Response('ESI rate limit reached.', $retryAt2, $version);
            return true;
        }

        // Check throttled.
        if (($retryAt3 = (int)$this->storage->get(Variables::ESI_THROTTLED)) > time()) {
            $this->build429Response(
                'Undefined 429 response. You have been temporarily throttled.',
                $retryAt3,
                $version
            );
            return true;
        }

        return false;
    }

    private function errorLimitRemaining(): int
    {
        $var = $this->storage->get(Variables::ESI_ERROR_LIMIT);
        $values = \json_decode((string) $var);

        if (! $values instanceof \stdClass) {
            return 0;
        }

        $resetTime = (int)$values->updated + (int)$values->reset;

        if ($resetTime < time()) {
            return 0;
        }

        if ($values->remain <= $this->errorLimitRemain) {
            return $resetTime;
        }

        return 0;
    }

    private function build429Response(string $message, int $retryAfter, int $version, ?string $messageV1 = null): void
    {
        $this->log->warning(self::ERROR_MESSAGE_PREFIX . $this->appString(). ": $message");
        if ($version === 1) {
            $this->response = $this->response->withStatus(429, $messageV1 ?: $message);
        } else {
            $this->response = $this->response->withHeader('Retry-After', (string)max(1, $retryAfter - time()));
            $this->response = $this->withJson($message, 429);
        }
    }

    private function getEsiPathAndQueryParams(ServerRequestInterface $request, ?string $path): array
    {
        $esiParams = [];

        if (empty($path)) {
            // for URLs like: /api/app/v1/esi?esi-path-query=%2Fv3%2Fcharacters%2F96061222%2Fassets%2F%3Fpage%3D1
            $esiPath = $this->getQueryParam($request, 'esi-path-query');
        } else {
            // for URLs like /api/app/v1/esi/v3/characters/96061222/assets/?datasource=96061222&page=1
            $esiPath = $path;
            foreach ($request->getQueryParams() as $key => $value) {
                if ($key !== self::PARAM_DATASOURCE) {
                    $esiParams[] = $key . '=' . $value;
                }
            }
        }

        return [$esiPath, $esiParams];
    }

    private function isPublicPath(string $esiPath): bool
    {
        $path = substr($esiPath, (int) strpos($esiPath, '/', 1));

        /** @noinspection PhpIncludeInspection */
        $publicPaths = include Application::ROOT_DIR . '/config/esi-paths-public.php';

        foreach ($publicPaths as $pattern) {
            if (preg_match("@^$pattern$@", $path) === 1) {
                return true;
            }
        }

        return false;
    }

    private function sendRequest(
        string $esiPath,
        array $esiParams,
        string $token,
        string $cacheKey,
        string $method,
        string $body = null
    ): ResponseInterface {
        $eveConfig = $this->config['eve'];
        $url = $eveConfig['esi_host'] . $esiPath.
            (strpos($esiPath, '?') ? '&' : '?') .
            self::PARAM_DATASOURCE . '=' . $eveConfig['datasource'] .
            (! empty($esiParams) ? '&' . implode('&', $esiParams) : '');

        $request = $this->httpClientFactory->createRequest(
            $method,
            $url,
            ['Authorization' => 'Bearer ' . $token],
            $body
        );
        try {
            $esiResponse = $this->httpClientFactory->get($cacheKey)->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            $this->log->error(self::ERROR_MESSAGE_PREFIX . '(' . $this->appString() . '): ' . $e->getMessage());
            $esiResponse =  $this->httpClientFactory->createResponse(
                500, // status
                [], // header
                $e->getMessage() // body
            );
        }

        if ($esiResponse->getStatusCode() < 200 || $esiResponse->getStatusCode() > 299) {
            $message = $esiResponse->getBody()->getContents();
            $this->log->error(self::ERROR_MESSAGE_PREFIX . '(' . $this->appString() . ') ' . "$url: $message");
        }

        return $esiResponse;
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
            'warning',
            'Warning',
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

    private function hasEveLogin(EveLogin $eveLogin): bool
    {
        if ($this->app === null) {
            return false;
        }

        foreach ($this->app->getEveLogins() as $login) {
            if ($eveLogin->getId() === $login->getId()) {
                return true;
            }
        }

        return false;
    }
}
