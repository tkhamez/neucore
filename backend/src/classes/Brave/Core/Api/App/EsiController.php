<?php declare(strict_types=1);

namespace Brave\Core\Api\App;

use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\Config;
use Brave\Core\Service\OAuthToken;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
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
     * @var string
     */
    private $datasource;

    /**
     * @var ClientInterface
     */
    private $httpClient;

    public function __construct(
        Response $response,
        RepositoryFactory $repositoryFactory,
        LoggerInterface $log,
        OAuthToken $token,
        Config $config,
        ClientInterface $httpClient
    ) {
        $this->response = $response;
        $this->repositoryFactory = $repositoryFactory;
        $this->log = $log;
        $this->token = $token;
        $this->httpClient = $httpClient;

        $this->datasource = $config->get('eve', 'datasource');
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/esi",
     *     operationId="esiV1",
     *     summary="Makes an ESI GET request on behalf on an EVE character and returns the result.",
     *     description="Needs role: app-esi<br>
     *         The following headers from ESI are passed through to the response:
               Content-Type Expires X-Esi-Error-Limit-Remain X-Esi-Error-Limit-Reset X-Pages warning<br>
     *         The HTTP status code from ESI is also passed through, so maybe there's more than the documented.",
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
    public function esiV1(Request $request, $path = null)
    {
        // get/validate input

        $esiParams = [];
        if (empty($path)) {
            // for URLs like: /api/app/v1/esi?esi-path-query=%2Fv3%2Fcharacters%2F96061222%2Fassets%2F%3Fpage%3D1
            $esiPath = $request->getParam('esi-path-query'); // this includes the ESI params
        } else {
            // for URLs like /api/app/v1/esi/v3/characters/96061222/assets/?datasource=96061222&page=1
            $esiPath = $path;
            foreach ($request->getQueryParams() as $key => $value) {
                if ($key !== 'datasource') {
                    $esiParams[] = $key . '=' . $value;
                }
            }
        }

        if (empty($esiPath)) {
            return $this->response->withStatus(400, 'Path cannot be empty.');
        }

        $characterId = $request->getParam('datasource', '');
        if (empty($characterId)) {
            return $this->response->withStatus(
                400,
                'The datasource parameter cannot be empty, it must contain an EVE character ID'
            );
        }

        $character = $this->repositoryFactory->getCharacterRepository()->find($characterId);
        if ($character === null) {
            return $this->response->withStatus(400, 'Character not found.');
        }

        // build ESI URL
        $url = 'https://esi.evetech.net' . $esiPath.
            (strpos($esiPath, '?') ? '&' : '?') . 'datasource=' . $this->datasource .
            (count($esiParams) > 0 ? '&' . implode('&', $esiParams) : '');

        // get the token and set header options
        $token = $this->token->getToken($character);
        $options = ['headers' => []];
        if ($token !== '') {
            $options['headers']['Authorization'] = 'Bearer ' . $token;
        }
        if ($request->hasHeader('If-None-Match')) {
            $options['headers']['If-None-Match'] = $request->getHeader('If-None-Match')[0];
        }

        // send the request
        $esiResponse = null;
        try {
            $esiResponse = $this->httpClient->request('GET', $url, $options);
        } catch (RequestException $re) {
            $esiResponse = $re->getResponse();
        } catch (GuzzleException $ge) {
            $esiResponse = new \GuzzleHttp\Psr7\Response(
                500, // status
                [], // header
                $ge->getMessage(), // body
                '1.1', // version
                null // reason
            );
        }

        // build the response

        $body = null;
        try {
            $body = $esiResponse->getBody()->getContents();
        } catch (\RuntimeException $runtimeEx) {
            $this->log->error('ApplicationController->esiV1(): ' . $runtimeEx->getMessage());
        }
        if ($body !== null) {
            $this->response->write($body);
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
        };

        return $response;
    }
}
