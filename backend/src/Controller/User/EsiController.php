<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\EveLogin;
use Neucore\Exception\RuntimeException;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\EsiClient;
use Neucore\Service\ObjectManager;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
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
    private EsiClient $esiClient;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory,
        EsiClient $esiClient,
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);

        $this->esiClient = $esiClient;
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

        // Send request and handle errors.
        try {
            $response = $this->esiClient->request($path, $method, $body, (int)$charId, $eveLoginName, $debug);
        } catch (RuntimeException $e) {
            if ($e->getCode() === 568420) {
                // should not happen because that was already checked above
                return $this->withJson('Character not found.', 400);
            } elseif ($e->getCode() === 568421) {
                return $this->withJson('Character has no valid token.', 400);
            } else {
                // should not happen
                return $this->withJson('Unknown error.', 400);
            }
        } catch (ClientExceptionInterface $e) {
            return $this->prepareResponse($e->getMessage(), $debug, null, 500);
        }

        // get body from response
        try {
            $bodyContent = $response->getBody()->getContents();
        } catch (RuntimeException $re) {
            return $this->prepareResponse($re->getMessage(), $debug, $response, 400);
        }
        $responseBody = \json_decode($bodyContent, false);
        if (\json_last_error() !== \JSON_ERROR_NONE) {
            $responseBody = $bodyContent;
        }

        return $this->prepareResponse($responseBody, $debug, $response, $response->getStatusCode());
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
        mixed $body,
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
