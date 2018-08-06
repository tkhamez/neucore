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
    )
    {
        $this->response = $response;
        $this->characterRepository = $characterRepository;
        $this->token = $token;
        $this->httpClient = $httpClient;
        $this->config = $config;
    }

    public function request(Request $request): Response
    {
        $route = $request->getParam('route');
        $charId = $request->getParam('character');

        #$route = '/characters/{character_id}/stats/';
        #$route = '/characters/{character_id}/standings/';

        if ($route === null || $charId === null) {
            return $this->response->withJson('Missing route and/or character parameter.');
        }

        $character = $this->characterRepository->find($charId);
        if ($character === null) {
            return $this->response->withJson('Character not found.');
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

        $result = null;

        $response = null;
        try {
            $response = $this->httpClient->request('GET', $baseUri . $path, [
                'headers' => ['Authorization' => 'Bearer ' . $token]
            ]);
        } catch (ClientException $ce) {
            $result = $ce->getMessage();
        }

        $json = null;
        if ($response) {
            try {
                $json = $response->getBody()->getContents();
            } catch (\RuntimeException $re) {
                $result = $re->getMessage();
            }
        }

        if ($json) {
            try {
                $result = \GuzzleHttp\json_decode($json);
            } catch (\InvalidArgumentException $iae) {
                $result = $iae->getMessage();
            }
        }

        return $this->response->withJson($result, null, JSON_PRETTY_PRINT);
    }
}
