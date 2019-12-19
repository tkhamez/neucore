<?php declare(strict_types=1);

namespace Neucore\Controller\App;

use GuzzleHttp\Psr7\Response;
use Neucore\Application;
use Neucore\Controller\BaseController;
use Neucore\Entity\App;
use Neucore\Entity\SystemVariable;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\AppAuth;
use Neucore\Service\Config;
use Neucore\Service\OAuthToken;
use Neucore\Service\ObjectManager;
use OpenApi\Annotations as OA;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class EsiController extends BaseController
{
    const ERROR_MESSAGE_PREFIX = 'App\EsiController->esiV1()';

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var OAuthToken
     */
    private $tokenService;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var ClientInterface
     */
    private $httpClient;

    /**
     * @var AppAuth
     */
    private $appAuth;

    /**
     * @var App|null
     */
    private $app;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        LoggerInterface $log,
        OAuthToken $tokenService,
        Config $config,
        ClientInterface $httpClient,
        AppAuth $appAuth
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);
        
        $this->log = $log;
        $this->tokenService = $tokenService;
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->appAuth = $appAuth;
    }

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/app/v1/esi",
     *     operationId="esiV1",
     *     summary="Makes an ESI GET request on behalf on an EVE character and returns the result.",
     *     description="Needs role: app-esi<br>
     *         Public ESI routes are not allowed.<br>
     *         The following headers from ESI are passed through to the response:
               Content-Type Expires X-Esi-Error-Limit-Remain X-Esi-Error-Limit-Reset X-Pages warning<br>
     *         The HTTP status code from ESI is also passed through, so maybe there's more than the documented.<br>
     *         The ESI path and query parameters can alternatively be appended to the path of this endpoint,
               this allows to use OpenAPI clients that were generated for the ESI API,
               see doc/app-esi-examples.php for more.",
     *     tags={"Application"},
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
     *         description="The EVE character ID those token should be used to make the ESI request",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The data from ESI.<br>
                            Please note that the JSON schema type can be an object, array or number etc.,
                            unfortunately there is no way to document this.",
     *         @OA\JsonContent(type="string"),
     *         @OA\Header(header="Expires",
     *             description="RFC7231 formatted datetime string",
     *             @OA\Schema(type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response="304",
     *         description="Not modified",
     *         @OA\Header(header="Expires",
     *             description="RFC7231 formatted datetime string",
     *             @OA\Schema(type="integer")
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
     *         description="Maximum permissible ESI error limit reached (X-Esi-Error-Limit-Remain <= 20).",
     *         @OA\JsonContent(type="string")
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
     */
    public function esiV1(ServerRequestInterface $request, ?string $path = null): ResponseInterface
    {
        return $this->esiRequest($request, 'GET', $path);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Post(
     *     path="/app/v1/esi",
     *     operationId="esiPostV1",
     *     summary="Same as GET ​/app​/v1​/esi, but for POST requests.",
     *     tags={"Application"},
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
     *         description="The EVE character ID those token should be used to make the ESI request",
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
     *         description="Same as GET ​/app​/v1​/esi, see there for details.",
     *         @OA\JsonContent(type="string"),
     *         @OA\Header(header="Expires",
     *             description="RFC7231 formatted datetime string",
     *             @OA\Schema(type="integer")
     *         )
     *     ),
     *     @OA\Response(
     *         response="304",
     *         description="Not modified",
     *         @OA\Header(header="Expires",
     *             description="RFC7231 formatted datetime string",
     *             @OA\Schema(type="integer")
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
     *         description="Maximum permissible ESI error limit reached (X-Esi-Error-Limit-Remain <= 20).",
     *         @OA\JsonContent(type="string")
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
     */
    public function esiPostV1(ServerRequestInterface $request, ?string $path = null): ResponseInterface
    {
        return $this->esiRequest($request, 'POST', $path);
    }

    private function esiRequest(
        ServerRequestInterface $request,
        string $method,
        ?string $path = null
    ): ResponseInterface {
        $this->app = $this->appAuth->getApp($request);

        // check error limit
        if ($this->errorLimitReached()) {
            $this->log->error(
                self::ERROR_MESSAGE_PREFIX . ': ' . $this->appString().
                ' exceeded the maximum permissible ESI error limit'
            );
            return $this->response->withStatus(429, 'Maximum permissible ESI error limit reached.');
        }

        // get/validate input

        list($esiPath, $esiParams) = $this->getEsiPathAndQueryParams($request, $path);

        $body = null;
        if ($method === 'POST') {
            $body = $request->getBody()->__toString();
        }

        if (empty($esiPath)) {
            return $this->response->withStatus(400, 'Path cannot be empty.');
        }

        if ($this->isPublicPath($esiPath)) {
            return $this->response->withStatus(400, 'Public ESI routes are not allowed.');
        }

        $characterId = $this->getQueryParam($request, 'datasource', '');
        if (empty($characterId)) {
            return $this->response->withStatus(
                400,
                'The datasource parameter cannot be empty, it must contain an EVE character ID'
            );
        }

        // get character
        $character = $this->repositoryFactory->getCharacterRepository()->find($characterId);
        if ($character === null) {
            return $this->response->withStatus(400, 'Character not found.');
        }

        // get the token
        $token = $this->tokenService->getToken($character);
        if ($token === '') {
            return $this->response->withStatus(400, 'Character has no valid token.');
        }

        $esiResponse = $this->sendRequest($esiPath, $esiParams, $token, $method, $body);

        return $this->buildResponse($esiResponse);
    }

    private function errorLimitReached(): bool
    {
        $var = $this->repositoryFactory->getSystemVariableRepository()->find(SystemVariable::ESI_ERROR_LIMIT);
        if ($var === null) {
            return false;
        }

        $values = \json_decode($var->getValue());
        if (! $values instanceof \stdClass) {
            return false;
        }

        if ((int) $values->updated + $values->reset < time()) {
            return false;
        }

        if ($values->remain <= 20) {
            return true;
        }

        return false;
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
                if ($key !== 'datasource') {
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
        string $method,
        string $body = null
    ): ResponseInterface {
        $url = $this->config['eve']['esi_host'] . $esiPath.
            (strpos($esiPath, '?') ? '&' : '?') . 'datasource=' . $this->config['eve']['datasource'] .
            (count($esiParams) > 0 ? '&' . implode('&', $esiParams) : '');
        $options = [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'body' => $body
        ];

        $esiResponse = null;
        try {
            $esiResponse = $this->httpClient->request($method, $url, $options);
        } catch (RequestException $re) {
            $this->log->error(self::ERROR_MESSAGE_PREFIX . ': (' . $this->appString() . ') ' . $re->getMessage());
            $esiResponse = $re->getResponse(); // may still be null
        } catch (GuzzleException $ge) {
            $this->log->error(self::ERROR_MESSAGE_PREFIX . ': (' . $this->appString() . ') ' . $ge->getMessage());
            $esiResponse = new Response(
                500, // status
                [], // header
                $ge->getMessage() // body
            );
        }

        return $esiResponse ?: $this->response->withStatus(500, 'Unknown error.');
    }

    private function buildResponse(ResponseInterface $esiResponse): ResponseInterface
    {
        $body = null;
        try {
            $body = $esiResponse->getBody()->getContents();
        } catch (\RuntimeException $e) {
            $this->log->error(self::ERROR_MESSAGE_PREFIX . ': (' . $this->appString() . ') ' . $e->getMessage());
        }
        if ($body !== null) {
            $this->response->getBody()->write($body);
        }

        $response = $this->response->withStatus($esiResponse->getStatusCode(), $esiResponse->getReasonPhrase());

        $headerWhiteList = [
            'Content-Type',
            'Expires',
            'X-Esi-Error-Limit-Remain',
            'X-Esi-Error-Limit-Reset',
            'X-Pages',
            'warning',
        ];
        foreach ($esiResponse->getHeaders() as $name => $value) {
            if (in_array($name, $headerWhiteList)) {
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
}
