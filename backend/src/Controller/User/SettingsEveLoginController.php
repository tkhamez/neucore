<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\EveLogin;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Swagger\Client\Eve\Model\GetCharactersCharacterIdRolesOk;

class SettingsEveLoginController extends BaseController
{
    /**
     * @var string
     */
    private $namePattern = "/^[-._a-zA-Z0-9]+$/";

    /**
     * @OA\Post(
     *     path="/user/settings/eve-login/{name}",
     *     operationId="userSettingsEveLoginCreate",
     *     summary="Create a new login.",
     *     description="Needs role: settings",
     *     tags={"Settings"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="name",
     *         in="path",
     *         required=true,
     *         description="The new login name.",
     *         @OA\Schema(type="string", maxLength=20, pattern="^[-._a-zA-Z0-9]+$")
     *     ),
     *     @OA\Response(
     *         response="201",
     *         description="The new login.",
     *         @OA\JsonContent(ref="#/components/schemas/EveLogin")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Login name is invalid."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="409",
     *         description="A login with this ID already exists."
     *     )
     * )
     */
    public function create(string $name): ResponseInterface
    {
        if (!preg_match($this->namePattern, $name) || strpos($name, EveLogin::INTERNAL_LOGIN_PREFIX) === 0) {
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

    /**
     * @OA\Delete(
     *     path="/user/settings/eve-login/{id}",
     *     operationId="userSettingsEveLoginDelete",
     *     summary="Delete login.",
     *     description="Needs role: settings",
     *     tags={"Settings"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The login ID.",
     *         @OA\Schema(type="string", maxLength=20, pattern="^[-._a-zA-Z0-9]+$")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Login was deleted."
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Protected login."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Login not found."
     *     )
     * )
     */
    public function delete(string $id): ResponseInterface
    {
        $login = $this->repositoryFactory->getEveLoginRepository()->find((int)$id);
        if (!$login) {
            return $this->response->withStatus(404);
        }

        if (in_array($login->getName(), EveLogin::INTERNAL_LOGIN_NAMES)) {
            return $this->response->withStatus(400);
        }

        $this->objectManager->remove($login);

        return $this->flushAndReturn(204);
    }

    /**
     * @OA\Get(
     *     path="/user/settings/eve-login/list",
     *     operationId="userSettingsEveLoginList",
     *     summary="List all logins.",
     *     description="Needs role: anonymous or user",
     *     tags={"Settings"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of logins.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/EveLogin"))
     *     )
     * )
     */
    public function list(): ResponseInterface
    {
        $logins = $this->repositoryFactory->getEveLoginRepository()->findBy([], ['name' => 'ASC']);
        return $this->withJson($logins);
    }

    /**
     * @OA\Put(
     *     path="/user/settings/eve-login",
     *     operationId="userSettingsEveLoginUpdate",
     *     summary="Update login.",
     *     description="Needs role: settings",
     *     tags={"Settings"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="JSON encoded data.",
     *         @OA\MediaType(mediaType="application/json", @OA\Schema(ref="#/components/schemas/EveLogin"))
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The updated login.",
     *         @OA\JsonContent(ref="#/components/schemas/EveLogin")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Invalid body or invalid login name."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Login not found."
     *     )
     * )
     */
    public function update(ServerRequestInterface $request): ResponseInterface
    {
        $data = $request->getParsedBody();
        if (!$data instanceof \stdClass || !EveLogin::isValidObject($data) || empty($data->id) || empty($data->name)) {
            return $this->response->withStatus(400);
        }

        $login = $this->repositoryFactory->getEveLoginRepository()->find((int)$data->id);
        if (!$login) {
            return $this->response->withStatus(404);
        }

        if (
            !preg_match($this->namePattern, $data->name) ||
            strpos($data->name, EveLogin::INTERNAL_LOGIN_PREFIX) === 0
        ) {
            return $this->response->withStatus(400);
        }

        $login->setName($data->name);
        $login->setDescription($data->description);
        $login->setEsiScopes($data->esiScopes);
        $login->setEveRoles($data->eveRoles);

        return $this->flushAndReturn(200, $login);
    }

    /**
     * @OA\Get(
     *     path="/user/settings/eve-login/roles",
     *     operationId="userSettingsEveLoginRoles",
     *     summary="List in-game roles (without HQ, base and other 'Hangar Access' and 'Container Access' roles).",
     *     description="Needs role: settings",
     *     tags={"Settings"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of roles.",
     *         @OA\JsonContent(type="array", @OA\Items(type="string"))
     *     )
     * )
     */
    public function roles(): ResponseInterface
    {
        return $this->withJson((new GetCharactersCharacterIdRolesOk())->getRolesAllowableValues());
    }
}
