<?php declare(strict_types=1);

namespace Brave\Core\Api\User;

use Brave\Core\Config;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\OAuthToken;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\MessageInterface;
use Slim\Http\Request;
use Slim\Http\Response;

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
    private $token;

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
        OAuthToken $token,
        ClientInterface $httpClient,
        Config $config
    ) {
        $this->response = $response;
        $this->repositoryFactory = $repositoryFactory;
        $this->token = $token;
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

        $token = $this->token->getToken($character);

        $baseUri = 'https://esi.evetech.net';
        $path = '/latest' . str_replace('{character_id}', $character->getId(), $route);
        if (strpos($path, '?') === false) {
            $path .= '?datasource=tranquility';
        } else {
            $path .= '&datasource=tranquility';
        }

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

    private function extractHeaders(MessageInterface $response = null)
    {
        if ($response === null) {
            return null;
        }

        return [
            'X-Esi-Error-Limit-Remain' => $response->hasHeader('X-Esi-Error-Limit-Remain') ?
                $response->getHeader('X-Esi-Error-Limit-Remain')[0] : null,
            'X-Esi-Error-Limit-Reset' => $response->hasHeader('X-Esi-Error-Limit-Reset') ?
                $response->getHeader('X-Esi-Error-Limit-Reset')[0] : null,
            'X-Pages' => $response->hasHeader('X-Pages') ? $response->getHeader('X-Pages')[0] : null,
        ];
    }
}
