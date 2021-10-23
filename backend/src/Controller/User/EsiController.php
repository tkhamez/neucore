<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\EveLogin;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\HttpClientFactoryInterface;
use Neucore\Service\Config;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\OAuthToken;
use Neucore\Service\ObjectManager;
use OpenApi\Annotations as OA;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @OA\Tag(
 *     name="ESI",
 *     description="ESI requests"
 * )
 */
class EsiController extends BaseController
{
    /**
     * @var OAuthToken
     */
    private $tokenService;

    /**
     * @var HttpClientFactoryInterface
     */
    private $httpClientFactory;

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        OAuthToken $tokenService,
        HttpClientFactoryInterface $httpFactory,
        Config $config
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);

        $this->tokenService = $tokenService;
        $this->httpClientFactory = $httpFactory;
        $this->config = $config;
    }

    /**
     * @OA\Get(
     *     path="/user/esi/request",
     *     operationId="request",
     *     summary="ESI request.",
     *     description="Needs role: user-admin<br>
     *                  Example route: /characters/{character_id}/stats/<br>
     *                  Only for GET request.<br>
     *                  {character_id}, {corporation_id} and {alliance_id} are automatically replaced with the
                        corresponding IDs of the selected character",
     *     tags={"ESI"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="character",
     *         in="query",
     *         description="EVE character ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="login",
     *         in="query",
     *         description="The EVE login name.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="route",
     *         in="query",
     *         description="The ESI route.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="debug",
     *         in="query",
     *         description="Show all headers, do not use cache",
     *         @OA\Schema(type="string", enum={"true", "false"})
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The result from ESI or an error message.",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error.",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function request(ServerRequestInterface $request, string $method = 'GET'): ResponseInterface
    {
        $charId = $this->getQueryParam($request, 'character', '');
        $eveLoginName = $this->getQueryParam($request, 'login', EveLogin::NAME_DEFAULT);
        $route = $this->getQueryParam($request, 'route', '');
        $debug = $this->getQueryParam($request, 'debug') === 'true';

        $body = null;
        if ($method === 'POST') {
            $body = $request->getBody()->__toString();
        }

        // validate input
        if ($route === '' || $charId === '') {
            return $this->withJson('Missing route and/or character parameter.', 400);
        }
        $character = $this->repositoryFactory->getCharacterRepository()->find($charId);
        if ($character === null) {
            return $this->withJson('Character not found.', 400);
        }

        // replace placeholders
        $baseUri = $this->config['eve']['esi_host'];
        $corp = $character->getCorporation();
        $path = str_replace(
            ['{character_id}', '{corporation_id}', '{alliance_id}'],
            [
                $character->getId(),
                $corp ? $corp->getId() : '',
                $corp && $corp->getAlliance() !== null ? $corp->getAlliance()->getId() : ''
            ],
            $route
        );
        $path .= (strpos($path, '?') ? '&' : '?') . 'datasource=' . $this->config['eve']['datasource'];

        if ($debug) {
            $httpClient = $this->httpClientFactory->get(null);
        } else {
            $httpClient = $this->httpClientFactory->get($eveLoginName);
        }

        // make request
        $token = $this->tokenService->getToken($character, $eveLoginName);
        if ($token === '') {
            return $this->withJson('Character has no valid token.', 400);
        }
        $request = $this->httpClientFactory->createRequest(
            $method,
            $baseUri . $path,
            ['Authorization' => 'Bearer ' . $token],
            $body
        );
        try {
            $response = $httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            return $this->prepareResponse($e->getMessage(), $debug, null, 500);
        }

        // get body from response
        try {
            $json = $response->getBody()->getContents();
        } catch (RuntimeException $re) {
            return $this->prepareResponse($re->getMessage(), $debug, $response, 400);
        }
        $body = \json_decode($json, false);
        if (\JSON_ERROR_NONE !== \json_last_error()) {
            return $this->prepareResponse('json_decode error: ' . \json_last_error_msg(), $debug, $response, 400);
        }

        return $this->prepareResponse($body, $debug, $response, $response->getStatusCode());
    }

    /**
     * @OA\Post(
     *     path="/user/esi/request",
     *     operationId="requestPost",
     *     summary="Same as GET /user/esi/request, but for POST requests.",
     *     tags={"ESI"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="character",
     *         in="query",
     *         description="EVE character ID.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="route",
     *         in="query",
     *         description="The ESI route.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="debug",
     *         in="query",
     *         description="Show all headers, do not use cache",
     *         @OA\Schema(type="string", enum={"true", "false"})
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
     *         description="The result from ESI or an error message.",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Error.",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function requestPost(ServerRequestInterface $request): ResponseInterface
    {
        return $this->request($request, 'POST');
    }

    /**
     * @param mixed $body
     * @param bool $debug
     * @param ResponseInterface|null $response
     * @param int $code
     * @return ResponseInterface
     */
    private function prepareResponse(
        $body,
        bool $debug,
        ResponseInterface $response = null,
        int $code = 200
    ): ResponseInterface {
        return $this->withJson([
            'headers' => $this->extractHeaders($debug, $response),
            'body' => $body,
        ], $code);
    }

    private function extractHeaders(bool $debug, ResponseInterface $response = null): ?array
    {
        if ($response === null) {
            return null;
        }

        $result = [];

        if ($debug) {
            foreach ($response->getHeaders() as $name => $values) {
                foreach ($values as $value) {
                    $result[] = [$name, $value];
                }
            }
        } else {
            $headers = [
                'Expires',
                'X-Esi-Error-Limit-Remain',
                'X-Esi-Error-Limit-Reset',
                'X-Pages',
                'warning',
                'Warning',
            ];
            foreach ($headers as $header) {
                if ($response->hasHeader($header)) {
                    $result[] = [$header, $response->getHeader($header)[0]];
                }
            }
        }

        return $result;
    }
}
