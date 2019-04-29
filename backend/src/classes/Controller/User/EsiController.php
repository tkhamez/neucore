<?php declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Service\Config;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\OAuthToken;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\MessageInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Swagger\Annotations as SWG;

/**
 * @SWG\Tag(
 *     name="ESI",
 *     description="ESI requests"
 * )
 */
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
     * @var OAuthToken
     */
    private $tokenService;

    /**
     * @var ClientInterface
     */
    private $httpClient;

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        Response $response,
        RepositoryFactory $repositoryFactory,
        OAuthToken $tokenService,
        ClientInterface $httpClient,
        Config $config
    ) {
        $this->response = $response;
        $this->repositoryFactory = $repositoryFactory;
        $this->tokenService = $tokenService;
        $this->httpClient = $httpClient;
        $this->config = $config;
    }

    /**
     * @SWG\Get(
     *     path="/user/esi/request",
     *     operationId="request",
     *     summary="ESI request.",
     *     description="Needs role: user-admin
     *                  Example route: /characters/{character_id}/stats/
     *                  Only for GET request.
     *                  Only the {character_id} placeholder is implemented.",
     *     tags={"ESI"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="character",
     *         in="query",
     *         description="EVE character ID.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="route",
     *         in="query",
     *         description="The ESI route.",
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="The result from ESI or an error message.",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Error.",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function request(Request $request): Response
    {
        $charId = $request->getParam('character', '');
        $route = $request->getParam('route', '');

        if ($route === '' || $charId === '') {
            return $this->response->withJson('Missing route and/or character parameter.', 400);
        }

        $character = $this->repositoryFactory->getCharacterRepository()->find($charId);
        if ($character === null) {
            return $this->response->withJson('Character not found.', 400);
        }

        $token = $this->tokenService->getToken($character);

        $baseUri = $this->config->get('eve', 'esi_host');
        $path = str_replace('{character_id}', (string) $character->getId(), $route);
        $path .= (strpos($path, '?') ? '&' : '?') . 'datasource=' . $this->config->get('eve', 'datasource');

        $response = null;
        try {
            $response = $this->httpClient->request('GET', $baseUri . $path, [
                'headers' => ['Authorization' => 'Bearer ' . $token]
            ]);
        } catch (ClientException $ce) {
            return $this->prepareResponse($ce->getMessage(), $ce->getResponse(), 400);
        } catch (GuzzleException $ge) {
            return $this->prepareResponse($ge->getMessage(), null, 400);
        }

        $json = null;
        try {
            $json = $response->getBody()->getContents();
        } catch (\RuntimeException $re) {
            return $this->prepareResponse($re->getMessage(), $response, 400);
        }

        $body = null;
        try {
            $body = \GuzzleHttp\json_decode($json);
        } catch (\InvalidArgumentException $iae) {
            return $this->prepareResponse($iae->getMessage(), $response, 400);
        }

        return $this->prepareResponse($body, $response);
    }

    private function prepareResponse($body, MessageInterface $response = null, $code = 200)
    {
        return $this->response->withJson([
            'headers' => $this->extractHeaders($response),
            'body' => $body,
        ], $code);
    }

    private function extractHeaders(MessageInterface $response = null): ?array
    {
        if ($response === null) {
            return null;
        }

        $remain = 'X-Esi-Error-Limit-Remain';
        $reset = 'X-Esi-Error-Limit-Reset';

        return [
            'Expires' => $response->hasHeader('Expires') ? $response->getHeader('Expires')[0] : null,
            $remain => $response->hasHeader($remain) ? $response->getHeader($remain)[0] : null,
            $reset => $response->hasHeader($reset) ? $response->getHeader($reset)[0] : null,
            'X-Pages' => $response->hasHeader('X-Pages') ? $response->getHeader('X-Pages')[0] : null,
            'warning' => $response->hasHeader('warning') ? $response->getHeader('warning')[0] : null,
        ];
    }
}
