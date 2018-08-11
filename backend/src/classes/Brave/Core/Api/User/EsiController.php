<?php declare(strict_types=1);

namespace Brave\Core\Api\User;

use Brave\Core\Config;
use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Service\OAuthToken;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
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
     * @var CharacterRepository
     */
    private $characterRepository;

    /**
     * @var OAuthToken
     */
    private $token;

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * @var Config
     */
    private $config;

    public function __construct(
        Response $response,
        CharacterRepository $characterRepository,
        OAuthToken $token,
        Client $httpClient,
        Config $config
    ) {
        $this->response = $response;
        $this->characterRepository = $characterRepository;
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

        $character = $this->characterRepository->find($charId);
        if ($character === null) {
            return $this->response->withJson('Character not found.', 400);
        }

        $this->token->setCharacter($character);
        $token = $this->token->getToken();

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
            return $this->response->withJson($ce->getMessage(), 400);
        }

        $json = null;
        try {
            $json = $response->getBody()->getContents();
        } catch (\RuntimeException $re) {
            return $this->response->withJson($re->getMessage(), 400);
        }

        $result = null;
        try {
            $result = \GuzzleHttp\json_decode($json);
        } catch (\InvalidArgumentException $iae) {
            return $this->response->withJson($iae->getMessage(), 400);
        }

        return $this->response->withJson($result, null, JSON_PRETTY_PRINT);
    }
}
