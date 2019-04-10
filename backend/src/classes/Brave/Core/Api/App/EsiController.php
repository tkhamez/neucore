<?php declare(strict_types=1);

namespace Brave\Core\Api\App;

use Brave\Core\Application;
use Brave\Core\Entity\App;
use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\AppAuth;
use Brave\Core\Service\Config;
use Brave\Core\Service\OAuthToken;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

class EsiController
{
    /**
     * @var Response
     */
    private $response;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var LoggerInterface
     */
    private $log;

    /**
     * @var OAuthToken
     */
    private $token;

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
     * @var App|NULL
     */
    private $app;

    public function __construct(
        Response $response,
        RepositoryFactory $repositoryFactory,
        LoggerInterface $log,
        OAuthToken $token,
        Config $config,
        ClientInterface $httpClient,
        AppAuth $appAuth
    ) {
        $this->response = $response;
        $this->repositoryFactory = $repositoryFactory;
        $this->log = $log;
        $this->token = $token;
        $this->config = $config;
        $this->httpClient = $httpClient;
        $this->appAuth = $appAuth;
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/esi",
     *     operationId="esiV1",
     *     summary="Makes an ESI GET request on behalf on an EVE character and returns the result.",
     *     description="Needs role: app-esi<br>
     *         Public ESI routes are not allowed.<br>
     *         The following headers from ESI are passed through to the response:
               Content-Type Expires X-Esi-Error-Limit-Remain X-Esi-Error-Limit-Reset X-Pages warning<br>
     *         The HTTP status code from ESI is also passed through, so maybe there's more than the documented.<br>
     *         The ESI path and query parameters can alternatively be appended to the path of this endpoint,
               see doc/app-esi-examples.php for more.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="esi-path-query",
     *         in="query",
     *         required=true,
     *         description="The ESI path and query string (without the datasource parameter).",
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="datasource",
     *         in="query",
     *         required=true,
     *         description="The EVE character ID those token should be used to make the ESI request",
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="The data from ESI.",
     *         @SWG\Schema(type="string"),
     *         @SWG\Header(header="Expires",
     *             description="RFC7231 formatted datetime string",
     *             type="integer"
     *         )
     *     ),
     *     @SWG\Response(
     *         response="304",
     *         description="Not modified",
     *         @SWG\Header(header="Expires",
     *             description="RFC7231 formatted datetime string",
     *             type="integer"
     *         )
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Bad request, see reason phrase and/or body for more.",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="420",
     *         description="Error limited",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="429",
     *         description="Maximum permissible ESI error limit reached.",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="Internal server error",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="503",
     *         description="Service unavailable",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="504",
     *         description="Gateway timeout",
     *         @SWG\Schema(type="string")
     *     )
     * )
     */
    public function esiV1(Request $request, $path = null): ResponseInterface
    {
        return $this->esiRequest($request, 'GET', $path);
    }

    /**
     * @SWG\Post(
     *     path="/app/v1/esi",
     *     operationId="esiPostV1",
     *     summary="Makes an ESI POST request on behalf on an EVE character and returns the result.",
     *     description="Needs role: app-esi<br>
     *         Public ESI routes are not allowed.<br>
     *         The following headers from ESI are passed through to the response:
               Content-Type Expires X-Esi-Error-Limit-Remain X-Esi-Error-Limit-Reset X-Pages warning<br>
     *         The HTTP status code from ESI is also passed through, so maybe there's more than the documented.<br>
     *         The ESI path and query parameters can alternatively be appended to the path of this endpoint,
               see doc/app-esi-examples.php for more.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     consumes={"text/plain"},
     *     @SWG\Parameter(
     *         name="esi-path-query",
     *         in="query",
     *         required=true,
     *         description="The ESI path and query string (without the datasource parameter).",
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="datasource",
     *         in="query",
     *         required=true,
     *         description="The EVE character ID those token should be used to make the ESI request",
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="data",
     *         in="body",
     *         required=true,
     *         description="JSON encoded data.",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="The data from ESI.",
     *         @SWG\Schema(type="string"),
     *         @SWG\Header(header="Expires",
     *             description="RFC7231 formatted datetime string",
     *             type="integer"
     *         )
     *     ),
     *     @SWG\Response(
     *         response="304",
     *         description="Not modified",
     *         @SWG\Header(header="Expires",
     *             description="RFC7231 formatted datetime string",
     *             type="integer"
     *         )
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Bad request, see reason phrase and/or body for more.",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="401",
     *         description="Unauthorized",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Forbidden",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="420",
     *         description="Error limited",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="429",
     *         description="Maximum permissible ESI error limit reached.",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="500",
     *         description="Internal server error",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="503",
     *         description="Service unavailable",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="504",
     *         description="Gateway timeout",
     *         @SWG\Schema(type="string")
     *     )
     * )
     */
    public function esiPostV1(Request $request, $path = null): ResponseInterface
    {
        return $this->esiRequest($request, 'POST', $path);
    }

    private function esiRequest(Request $request, string $method, $path = null): ResponseInterface
    {
        $this->app = $this->appAuth->getApp($request);

        // check error limit
        if ($this->errorLimitReached()) {
            $this->log->error(
                'App\EsiController->esiV1(): ' . $this->appString().
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

        $characterId = $request->getParam('datasource', '');
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
        $token = $this->token->getToken($character);
        if ($token === '') {
            return $this->response->withStatus(400, 'Character has no token.');
        }

        $esiResponse = $this->sendRequest($esiPath, $esiParams, $token, $method, $body);

        return $this->buildResponse($esiResponse);
    }

    private function errorLimitReached()
    {
        $var = $this->repositoryFactory->getSystemVariableRepository()->find(SystemVariable::ESI_ERROR_LIMIT);
        if ($var === null) {
            return false;
        }

        $values = \json_decode($var->getValue());
        if (! $values instanceof \stdClass) {
            return false;
        }

        if ($values->updated + $values->reset < time()) {
            return false;
        }

        if ($values->remain <= 20) {
            return true;
        }

        return false;
    }

    private function getEsiPathAndQueryParams(Request $request, $path): array
    {
        $esiParams = [];

        if (empty($path)) {
            // for URLs like: /api/app/v1/esi?esi-path-query=%2Fv3%2Fcharacters%2F96061222%2Fassets%2F%3Fpage%3D1
            $esiPath = $request->getParam('esi-path-query');
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

    private function isPublicPath($esiPath): bool
    {
        $path = substr($esiPath, strpos($esiPath, '/', 1));

        /** @noinspection PhpIncludeInspection */
        $publicPaths = include Application::ROOT_DIR . '/config/esi-paths-public.php';

        foreach ($publicPaths as $pattern) {
            if (preg_match("@^$pattern$@", $path) === 1) {
                #$this->log->debug(print_r([$pattern, $path], true));
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $esiPath
     * @param array $esiParams
     * @param string $token
     * @param string $method GET or POST
     * @param string|null $body
     * @return ResponseInterface|null
     */
    private function sendRequest(
        string $esiPath,
        array $esiParams,
        string $token,
        string $method,
        string $body = null
    ): ?ResponseInterface {
        $url = $this->config->get('eve', 'esi_host') . $esiPath.
            (strpos($esiPath, '?') ? '&' : '?') . 'datasource=' . $this->config->get('eve', 'datasource') .
            (count($esiParams) > 0 ? '&' . implode('&', $esiParams) : '');
        $options = [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'body' => $body
        ];

        $esiResponse = null;
        try {
            $esiResponse = $this->httpClient->request($method, $url, $options);
        } catch (RequestException $re) {
            $this->log->error('App\EsiController->esiV1(): (' . $this->appString() . ') ' . $re->getMessage());
            $esiResponse = $re->getResponse(); // may still be null
        } catch (GuzzleException $ge) {
            $this->log->error('App\EsiController->esiV1(): (' . $this->appString() . ') ' . $ge->getMessage());
            $esiResponse = new \GuzzleHttp\Psr7\Response(
                500, // status
                [], // header
                $ge->getMessage(), // body
                '1.1', // version
                null // reason
            );
        }

        return $esiResponse;
    }

    private function buildResponse(ResponseInterface $esiResponse = null): ResponseInterface
    {
        if ($esiResponse !== null) {
            $body = null;
            try {
                $body = $esiResponse->getBody()->getContents();
            } catch (\RuntimeException $e) {
                $this->log->error('App\EsiController->esiV1(): (' . $this->appString() . ') ' . $e->getMessage());
            }
            if ($body !== null) {
                $this->response->write($body);
            }
        }

        $response = $this->response->withStatus($esiResponse->getStatusCode());

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
