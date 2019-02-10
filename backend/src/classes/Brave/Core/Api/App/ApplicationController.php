<?php declare(strict_types=1);

namespace Brave\Core\Api\App;

use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\Account;
use Brave\Core\Service\AppAuth;
use Brave\Core\Service\Config;
use Brave\Core\Service\OAuthToken;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * @SWG\Tag(
 *     name="Application",
 *     description="API for 3rd party apps.",
 * )
 *
 * @SWG\SecurityScheme(
 *     securityDefinition="Bearer",
 *     type="apiKey",
 *     name="Authorization",
 *     in="header",
 *     description="Example: Bearer ABC"
 * )
 *
 * @SWG\Definition(
 *     definition="CharacterGroups",
 *     required={"character", "groups"},
 *     @SWG\Property(
 *         property="character",
 *         ref="#/definitions/Character"
 *     ),
 *     @SWG\Property(
 *         property="groups",
 *         type="array",
 *         @SWG\Items(ref="#/definitions/Group")
 *     )
 * )
 */
class ApplicationController
{
    /**
     * @var Response
     */
    private $response;

    /**
     * @var AppAuth
     */
    private $appAuthService;

    /**
     * @var RepositoryFactory
     */
    private $repositoryFactory;

    /**
     * @var Account
     */
    private $accountService;

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
        AppAuth $appAuthService,
        RepositoryFactory $repositoryFactory,
        Account $accountService,
        LoggerInterface $log,
        OAuthToken $token,
        Config $config,
        ClientInterface $httpClient
    ) {
        $this->response = $response;
        $this->appAuthService = $appAuthService;
        $this->repositoryFactory = $repositoryFactory;
        $this->accountService = $accountService;
        $this->log = $log;
        $this->token = $token;
        $this->httpClient = $httpClient;

        $this->datasource = $config->get('eve', 'datasource');
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/show",
     *     operationId="showV1",
     *     summary="Show app information.",
     *     description="Needs role: app",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="The app information",
     *         @SWG\Schema(ref="#/definitions/App")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function showV1(ServerRequestInterface $request): Response
    {
        return $this->response->withJson($this->appAuthService->getApp($request));
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/groups/{cid}",
     *     operationId="groupsV1",
     *     summary="Return groups of the character's player account.",
     *     description="Needs role: app<br>Returns only groups that have been added to the app as well.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of groups.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Group"))
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Character not found. (default reason phrase)"
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function groupsV1(string $cid, ServerRequestInterface $request): Response
    {
        $appGroups = $this->appAuthService->getApp($request)->getGroups();
        $result = $this->getGroupsForPlayer((int) $cid, $appGroups);

        if ($result === null) {
            return $this->response->withStatus(404);
        }

        return $this->response->withJson($result['groups']);
    }

    /**
     * @SWG\Get(
     *     path="/app/v2/groups/{cid}",
     *     operationId="groupsV2",
     *     summary="Return groups of the character's player account.",
     *     description="Needs role: app<br>Returns only groups that have been added to the app as well.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of groups.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Group"))
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Reason phrase: Character not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function groupsV2(string $cid, ServerRequestInterface $request): Response
    {
        $this->response = $this->groupsV1($cid, $request);

        if ($this->response->getStatusCode() === 404) {
            $this->response = $this->response->withStatus(404, 'Character not found.');
        }

        return $this->response;
    }

    /**
     * @SWG\Post(
     *     path="/app/v1/groups",
     *     operationId="groupsBulkV1",
     *     summary="Return groups of multiple players, identified by one of their character IDs.",
     *     description="Needs role: app.
     *                  Returns only groups that have been added to the app as well.
     *                  Skips characters that are not found in the local database.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="ids",
     *         in="body",
     *         required=true,
     *         description="EVE character IDs array.",
     *         @SWG\Schema(type="array", @SWG\Items(type="integer"))
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of characters (id, name and corporation properties only) with groups.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/CharacterGroups"))
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Invalid body."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function groupsBulkV1(ServerRequestInterface $request): Response
    {
        return $this->groupsBulkFor('Player', $request);
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/corp-groups/{cid}",
     *     operationId="corpGroupsV1",
     *     summary="Return groups of the corporation.",
     *     description="Needs role: app<br>Returns only groups that have been added to the app as well.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="EVE corporation ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of groups.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Group"))
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Corporation not found. (default reason phrase)"
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function corpGroupsV1(string $cid, ServerRequestInterface $request): Response
    {
        return $this->corpOrAllianceGroups($cid, 'Corporation', $request);
    }

    /**
     * @SWG\Get(
     *     path="/app/v2/corp-groups/{cid}",
     *     operationId="corpGroupsV2",
     *     summary="Return groups of the corporation.",
     *     description="Needs role: app<br>Returns only groups that have been added to the app as well.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="EVE corporation ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of groups.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Group"))
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Reason phrase: Corporation not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function corpGroupsV2(string $cid, ServerRequestInterface $request): Response
    {
        $this->response = $this->corpGroupsV1($cid, $request);

        if ($this->response->getStatusCode() === 404) {
            $this->response = $this->response->withStatus(404, 'Corporation not found.');
        }

        return $this->response;
    }

    /**
     * @SWG\Post(
     *     path="/app/v1/corp-groups",
     *     operationId="corpGroupsBulkV1",
     *     summary="Return groups of multiple corporations.",
     *     description="Needs role: app.
     *                  Returns only groups that have been added to the app as well.
     *                  Skips corporations that are not found in the local database.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="ids",
     *         in="body",
     *         required=true,
     *         description="EVE corporation IDs array.",
     *         @SWG\Schema(type="array", @SWG\Items(type="integer"))
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of corporations with groups but without alliance.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Corporation"))
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Invalid body."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function corpGroupsBulkV1(ServerRequestInterface $request): Response
    {
        return $this->groupsBulkFor('Corporation', $request);
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/alliance-groups/{aid}",
     *     operationId="allianceGroupsV1",
     *     summary="Return groups of the alliance.",
     *     description="Needs role: app<br>Returns only groups that have been added to the app as well.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="aid",
     *         in="path",
     *         required=true,
     *         description="EVE alliance ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of groups.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Group"))
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Alliance not found. (default reason phrase)"
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function allianceGroupsV1(string $aid, ServerRequestInterface $request): Response
    {
        return $this->corpOrAllianceGroups($aid, 'Alliance', $request);
    }

    /**
     * @SWG\Get(
     *     path="/app/v2/alliance-groups/{aid}",
     *     operationId="allianceGroupsV2",
     *     summary="Return groups of the alliance.",
     *     description="Needs role: app<br>Returns only groups that have been added to the app as well.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="aid",
     *         in="path",
     *         required=true,
     *         description="EVE alliance ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of groups.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Group"))
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Reason phrase: Alliance not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function allianceGroupsV2(string $aid, ServerRequestInterface $request): Response
    {
        $this->response = $this->allianceGroupsV1($aid, $request);

        if ($this->response->getStatusCode() === 404) {
            $this->response = $this->response->withStatus(404, 'Alliance not found.');
        }

        return $this->response;
    }

    /**
     * @SWG\Post(
     *     path="/app/v1/alliance-groups",
     *     operationId="allianceGroupsBulkV1",
     *     summary="Return groups of multiple alliances.",
     *     description="Needs role: app.
     *                  Returns only groups that have been added to the app as well.
     *                  Skips alliances that are not found in the local database.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="ids",
     *         in="body",
     *         required=true,
     *         description="EVE alliance IDs array.",
     *         @SWG\Schema(type="array", @SWG\Items(type="integer"))
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="List of alliances with groups.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Alliance"))
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Invalid body."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function allianceGroupsBulkV1(ServerRequestInterface $request): Response
    {
        return $this->groupsBulkFor('Alliance', $request);
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/main/{cid}",
     *     operationId="mainV1",
     *     summary="Returns the main character of the player account to which the character ID belongs.",
     *     description="Needs role: app<br>It is possible that an account has no main character.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="The main character",
     *         @SWG\Schema(ref="#/definitions/Character")
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="No main character found."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Character not found. (default reason phrase)"
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function mainV1(string $cid): Response
    {
        $char = $this->repositoryFactory->getCharacterRepository()->find((int) $cid);
        if ($char === null) {
            return $this->response->withStatus(404);
        }

        $main = $char->getPlayer()->getMain();
        if ($main === null) {
            return $this->response->withStatus(204);
        }

        return $this->response->withJson($main);
    }

    /**
     * @SWG\Get(
     *     path="/app/v2/main/{cid}",
     *     operationId="mainV2",
     *     summary="Return the main character of the player account to which the character ID belongs.",
     *     description="Needs role: app<br>It is possible that an account has no main character.",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="cid",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="The main character",
     *         @SWG\Schema(ref="#/definitions/Character")
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="No main character found."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Reason phrase: Character not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function mainV2(string $cid): Response
    {
        $this->response = $this->mainV1($cid);

        if ($this->response->getStatusCode() === 404) {
            $this->response = $this->response->withStatus(404, 'Character not found.');
        }

        return $this->response;
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/characters/{characterId}",
     *     operationId="charactersV1",
     *     summary="Return all characters of the player account to which the character ID belongs.",
     *     description="Needs role: app",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="characterId",
     *         in="path",
     *         required=true,
     *         description="EVE character ID.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="All characters from the player account.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/Character"))
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Character not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function charactersV1(string $characterId): Response
    {
        $char = $this->repositoryFactory->getCharacterRepository()->find((int) $characterId);

        if ($char === null) {
            return $this->response->withStatus(404, 'Character not found.');
        }

        $result = [];
        foreach ($char->getPlayer()->getCharacters() as $character) {
            $result[] = $character;
        }

        return $this->response->withJson($result);
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/corporation/{id}/member-tracking",
     *     operationId="memberTrackingV1",
     *     summary="Return corporation member tracking data.",
     *     description="Needs role: app-tracking",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="EVE corporation ID.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="inactive",
     *         in="query",
     *         description="Limit to members who have been inactive for x days or longer.",
     *         type="integer"
     *     ),
     *     @SWG\Parameter(
     *         name="active",
     *         in="query",
     *         description="Limit to members who were active in the last x days.",
     *         type="integer"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="Members ordered by logonDate descending (character and player properties excluded).",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/CorporationMember"))
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function memberTrackingV1(string $id, Request $request)
    {
        $inactive = (int) $request->getParam('inactive', 0);
        $active = (int) $request->getParam('active', 0);

        try {
            $members = $this->repositoryFactory->getCorporationMemberRepository()
                ->findByLogonDate((int) $id, $inactive, $active);
        } catch (\Exception $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
            $members = [];
        }

        $result = [];
        foreach ($members as $member) {
            $result[] = $member->jsonSerialize(false);
        }

        return $this->response->withJson($result);
    }

    /**
     * @SWG\Get(
     *     path="/app/v1/esi",
     *     operationId="esiV1",
     *     summary="Makes an ESI request and returns the result.",
     *     description="Needs role: app-esi
     *                  Instead of the path parameter, you can also simply append it to the URL,
                        but that does not work with OpenAPI clients since path parameters are always URL encoded.
     *                  This supports the 'If-None-Match' header.
     *                  The following headers from ESI are passed through to the response:
                        Content-Type ETag Expires X-Esi-Error-Limit-Remain X-Esi-Error-Limit-Reset X-Pages warning",
     *     tags={"Application"},
     *     security={{"Bearer"={}}},
     *     @SWG\Parameter(
     *         name="path",
     *         in="query",
     *         required=true,
     *         description="The ESI path.",
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="datasource",
     *         in="query",
     *         required=true,
     *         description="The EVE character ID those token is used to make the ESI request",
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="page",
     *         in="query",
     *         description="Passed through to ESI",
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="The data from ESI.",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @SWG\Response(
     *         response="400",
     *         description="Request error, see reason phrase."
     *     )
     * )
     */
    public function esiV1(Request $request, $path = null)
    {
        // validate input

        if (empty($path)) {
            $path = $request->getParam('path', '');
        }
        if (empty($path)) {
            return $this->response->withStatus(400, 'Path cannot be empty.');
        }

        $characterId = $request->getParam('datasource', '');
        if (empty($characterId)) {
            return $this->response->withStatus(
                400,
                'The datasource parameter cannot be empty, it must contain an EVE character IDs'
            );
        }

        $character = $this->repositoryFactory->getCharacterRepository()->find($characterId);
        if ($character === null) {
            return $this->response->withStatus(400, 'Character not found.');
        }

        // build URL and add optional parameters
        $url = 'https://esi.evetech.net' . $path.
            (strpos($path, '?') ? '&' : '?') . 'datasource=' . $this->datasource;
        if ($request->getParam('page', '') !== '') {
            $url .= '&page=' . $request->getParam('page');
        }

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
            'ETag',
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

    private function corpOrAllianceGroups(string $id, string $type, ServerRequestInterface $request)
    {
        $appGroups = $this->appAuthService->getApp($request)->getGroups();
        $result = $this->getGroupsFor($type, (int) $id, $appGroups);

        if ($result === null) {
            return $this->response->withStatus(404);
        }

        return $this->response->withJson($result['groups']);
    }

    /**
     * @param string $type "Player", "Corporation" or "Alliance"
     * @param ServerRequestInterface $request
     * @return Response
     */
    private function groupsBulkFor(string $type, ServerRequestInterface $request): Response
    {
        $ids = $this->getIntegerArrayFromBody($request);
        if ($ids === null) {
            return $this->response->withStatus(400);
        }
        if (count($ids) === 0) {
            return $this->response->withJson([]);
        }

        $appGroups = $this->appAuthService->getApp($request)->getGroups();

        $result = [];
        foreach ($ids as $id) {
            if ($id <= 0) {
                continue;
            }

            if ($type === 'Player') {
                $groups = $this->getGroupsForPlayer($id, $appGroups);
            } else {
                $groups = $this->getGroupsFor($type, $id, $appGroups);
            }
            if ($groups === null) {
                continue;
            }

            $result[] = $groups;
        }

        return $this->response->withJson($result);
    }

    private function getIntegerArrayFromBody(ServerRequestInterface $request)
    {
        $ids = $request->getParsedBody();

        if (! is_array($ids)) {
            return null;
        }

        $ids = array_map('intVal', $ids);
        $ids = array_unique($ids);

        return $ids;
    }

    /**
     * @param int $characterId
     * @param \Brave\Core\Entity\Group[] $appGroups
     * @return null|array Returns NULL if character was not found.
     */
    private function getGroupsForPlayer(int $characterId, array $appGroups)
    {
        $char = $this->repositoryFactory->getCharacterRepository()->find($characterId);
        if ($char === null) {
            return null;
        }

        $result = [
            'character' => [
                'id' => $char->getId(),
                'name' => $char->getName(),
                'corporation' => $char->getCorporation(),
            ],
            'groups' => []
        ];

        if ($this->accountService->groupsDeactivated($char->getPlayer())) {
            return $result;
        }

        foreach ($appGroups as $appGroup) {
            foreach ($char->getPlayer()->getGroups() as $playerGroup) {
                if ($appGroup->getId() === $playerGroup->getId()) {
                    $result['groups'][] = $playerGroup;
                }
            }
        }

        return $result;
    }

    /**
     * Get groups of corporation or alliance.
     *
     * Returns data from jsonSerialize() of a Corporation or Alliance object
     * plus all of it's groups that also belongs to the app.
     *
     * @param string $entityName "Corporation" or "Alliance"
     * @param int $entityId
     * @param \Brave\Core\Entity\Group[] $appGroups
     * @return null|array Returns NULL if corporation was not found.
     * @see \Brave\Core\Entity\Corporation::jsonSerialize()
     * @see \Brave\Core\Entity\Alliance::jsonSerialize()
     * @see \Brave\Core\Entity\Group::jsonSerialize()
     */
    private function getGroupsFor(string $entityName, int $entityId, array $appGroups)
    {
        $repository = $entityName === 'Corporation' ?
            $this->repositoryFactory->getCorporationRepository() :
            $this->repositoryFactory->getAllianceRepository();

        $entity = $repository->find($entityId);
        if ($entity === null) {
            return null;
        }

        $result = $entity->jsonSerialize();
        if (array_key_exists('alliance', $result)) {
            unset($result['alliance']);
        }
        $result['groups'] = [];

        foreach ($appGroups as $appGroup) {
            foreach ($entity->getGroups() as $corpGroup) {
                if ($appGroup->getId() === $corpGroup->getId()) {
                    $result['groups'][] = $corpGroup->jsonSerialize();
                }
            }
        }

        return $result;
    }
}
