<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\EveLogin;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SettingsEveLoginController extends BaseController
{
    /**
     * @var string
     */
    private $idPattern = "/^[-._a-zA-Z0-9]+$/";

    /**
     * @OA\Post(
     *     path="/user/settings/eve-login/{id}",
     *     operationId="userSettingsEveLoginCreate",
     *     summary="Create a new login.",
     *     description="Needs role: settings",
     *     tags={"Settings"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="The new login ID.",
     *         @OA\Schema(type="string", maxLength=20, pattern="^[-._a-zA-Z0-9]+$")
     *     ),
     *     @OA\Response(
     *         response="201",
     *         description="The new login.",
     *         @OA\JsonContent(ref="#/components/schemas/EveLogin")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Login ID is invalid."
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
    public function create(string $id): ResponseInterface
    {
        if (!preg_match($this->idPattern, $id) || strpos($id, EveLogin::INTERNAL_LOGINS_PREFIX) === 0) {
            return $this->response->withStatus(400);
        }

        $existingLogin = $this->repositoryFactory->getEveLoginRepository()->find($id);
        if ($existingLogin) {
            return $this->response->withStatus(409);
        }

        $login = (new EveLogin())->setId($id);
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
     *         description="Protected login ID."
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
        if (in_array($id, EveLogin::INTERNAL_LOGINS)) {
            return $this->response->withStatus(400);
        }

        $login = $this->repositoryFactory->getEveLoginRepository()->find($id);
        if (!$login) {
            return $this->response->withStatus(404);
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
        $logins = $this->repositoryFactory->getEveLoginRepository()->findBy([]);
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
     *         description="Invalid body."
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
        if (!$data instanceof \stdClass || !EveLogin::isValidObject($data) || empty($data->id)) {
            return $this->response->withStatus(400);
        }

        $login = $this->repositoryFactory->getEveLoginRepository()->find($data->id);
        if (!$login) {
            return $this->response->withStatus(404);
        }

        $login->setName($data->name);
        $login->setDescription($data->description);
        $login->setEsiScopes($data->esiScopes);
        $login->setEveRoles($data->eveRoles);

        return $this->flushAndReturn(200, $login);
    }
}
