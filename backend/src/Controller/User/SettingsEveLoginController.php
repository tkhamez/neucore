<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tkhamez\Eve\API\Model\CharactersCharacterIdRolesGet;

class SettingsEveLoginController extends BaseController
{
    private string $namePattern = "/^[-._a-zA-Z0-9]+$/";

    #[OA\Post(
        path: '/user/settings/eve-login/{name}',
        operationId: 'userSettingsEveLoginCreate',
        description: 'Needs role: settings',
        summary: 'Create a new login.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Settings'],
        parameters: [
            new OA\Parameter(
                name: 'name',
                description: 'The new login name.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', maxLength: 20, pattern: '^[-._a-zA-Z0-9]+$'),
            ),
        ],
        responses: [
            new OA\Response(
                response: '201',
                description: 'The new login.',
                content: new OA\JsonContent(ref: '#/components/schemas/EveLogin'),
            ),
            new OA\Response(response: '400', description: 'Login name is invalid.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '409', description: 'A login with this ID already exists.'),
        ],
    )]
    public function create(string $name): ResponseInterface
    {
        if (!preg_match($this->namePattern, $name) || str_starts_with($name, EveLogin::INTERNAL_LOGIN_PREFIX)) {
            return $this->response->withStatus(400);
        }

        $existingLogin = $this->repositoryFactory->getEveLoginRepository()->findOneBy(['name' => $name]);
        if ($existingLogin) {
            return $this->response->withStatus(409);
        }

        $login = (new EveLogin())->setName($name);
        $this->objectManager->persist($login);

        return $this->flushAndReturn(201, $login);
    }

    #[OA\Delete(
        path: '/user/settings/eve-login/{id}',
        operationId: 'userSettingsEveLoginDelete',
        description: 'Needs role: settings',
        summary: 'Delete login.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Settings'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'The login ID.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', maxLength: 20, pattern: '^[-._a-zA-Z0-9]+$'),
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Login was deleted.'),
            new OA\Response(response: '400', description: 'Protected login.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Login not found.'),
        ],
    )]
    public function delete(string $id): ResponseInterface
    {
        $login = $this->repositoryFactory->getEveLoginRepository()->find((int) $id);
        if (!$login) {
            return $this->response->withStatus(404);
        }

        if (in_array($login->getName(), EveLogin::INTERNAL_LOGIN_NAMES)) {
            return $this->response->withStatus(400);
        }

        $this->objectManager->remove($login);

        return $this->flushAndReturn(204);
    }

    #[OA\Get(
        path: '/user/settings/eve-login/{id}/tokens',
        operationId: 'userSettingsEveLoginTokens',
        description: 'Needs role: settings',
        summary: 'List ESI tokens from an EVE login.',
        security: [['Session' => []]],
        tags: ['Settings'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'The login ID. The default login is not allowed.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', maxLength: 20, pattern: '^[-._a-zA-Z0-9]+$'),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of tokens.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/EsiToken'),
                ),
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Login not found.'),
        ],
    )]
    public function tokens(string $id): ResponseInterface
    {
        $login = $this->repositoryFactory->getEveLoginRepository()->find((int) $id);
        if (!$login) {
            return $this->response->withStatus(404);
        }

        if ($login->getName() === EveLogin::NAME_DEFAULT) {
            return $this->response->withStatus(403);
        }

        // For ~43k tokens this will result in an out-of-memory error from Doctrine with a 256MB limit.
        $tokens = array_map(function (EsiToken $esiToken) {
            return $esiToken->jsonSerialize(true);
        }, $login->getEsiTokens());

        return $this->withJson($tokens);
    }

    #[OA\Get(
        path: '/user/settings/eve-login/list',
        operationId: 'userSettingsEveLoginList',
        description: 'Needs role: user',
        summary: 'List all logins.',
        security: [['Session' => []]],
        tags: ['Settings'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of logins.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/EveLogin'),
                ),
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
        ],
    )]
    public function list(): ResponseInterface
    {
        $logins = $this->repositoryFactory->getEveLoginRepository()->findBy([], ['name' => 'ASC']);
        return $this->withJson($logins);
    }

    #[OA\Put(
        path: '/user/settings/eve-login',
        operationId: 'userSettingsEveLoginUpdate',
        description: 'Needs role: settings',
        summary: 'Update login.',
        security: [['Session' => [], 'CSRF' => []]],
        requestBody: new OA\RequestBody(
            description: 'JSON encoded data.',
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(ref: '#/components/schemas/EveLogin'),
            ),
        ),
        tags: ['Settings'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'The updated login.',
                content: new OA\JsonContent(ref: '#/components/schemas/EveLogin'),
            ),
            new OA\Response(response: '400', description: 'Invalid body or invalid login name.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Login not found.'),
        ],
    )]
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        if (!$data instanceof \stdClass || !EveLogin::isValidObject($data) || empty($data->id) || empty($data->name)) {
            return $this->response->withStatus(400);
        }

        $login = $this->repositoryFactory->getEveLoginRepository()->find((int) $data->id);
        if (!$login) {
            return $this->response->withStatus(404);
        }

        if (
            !preg_match($this->namePattern, $data->name) ||
            str_starts_with($data->name, EveLogin::INTERNAL_LOGIN_PREFIX)
        ) {
            return $this->response->withStatus(400);
        }

        $login->setName($data->name);
        $login->setDescription($data->description);
        $login->setEsiScopes($data->esiScopes);
        $login->setEveRoles($data->eveRoles);

        return $this->flushAndReturn(200, $login);
    }

    #[OA\Get(
        path: '/user/settings/eve-login/roles',
        operationId: 'userSettingsEveLoginRoles',
        description: 'Needs role: settings',
        summary: "List in-game roles (without HQ, base and other 'Hangar Access' and 'Container Access' roles).",
        security: [['Session' => []]],
        tags: ['Settings'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of roles.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(type: 'string')),
            ),
        ],
    )]
    public function roles(): ResponseInterface
    {
        return $this->withJson((new CharactersCharacterIdRolesGet())->getRolesAllowableValues());
    }
}
