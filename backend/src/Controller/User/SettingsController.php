<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\EveLogin;
use Neucore\Entity\Group;
use Neucore\Entity\Role;
use Neucore\Entity\SystemVariable;
use Neucore\Service\Config;
use Neucore\Service\EsiData;
use Neucore\Service\EveMail;
use Neucore\Service\MemberTracking;
use Neucore\Service\UserAuth;
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for system settings (and maybe user settings later).
 *
 * @OA\Tag(
 *     name="Settings",
 *     description="System settings."
 * )
 */
class SettingsController extends BaseController
{
    private const COLUMN_NAME = 'name';

    private const COLUMN_VALUE = 'value';

    private array $validScopes = [SystemVariable::SCOPE_PUBLIC, SystemVariable::SCOPE_SETTINGS];

    /**
     * @noinspection PhpUnused
     * @OA\Get(
     *     path="/user/settings/system/list",
     *     operationId="systemList",
     *     summary="List all settings.",
     *     description="Some variables need the role 'settings'",
     *     tags={"Settings"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of settings.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/SystemVariable"))
     *     )
     * )
     */
    public function systemList(UserAuth $userAuth, Config $config): ResponseInterface
    {
        $settingsRepository = $this->repositoryFactory->getSystemVariableRepository();
        $groupRepository = $this->repositoryFactory->getGroupRepository();

        if (in_array(Role::SETTINGS, $userAuth->getRoles())) {
            $scopes = $this->validScopes;
        } else {
            $scopes = [SystemVariable::SCOPE_PUBLIC];
        }

        $services = [];
        foreach ($this->repositoryFactory->getServiceRepository()->findBy([], ['name' => 'asc']) as $service) {
            if ($userAuth->hasRequiredGroups($service)) {
                $services[] = $service;
            }
        }

        $result = $settingsRepository->findBy(['scope' => $scopes], [self::COLUMN_NAME => 'ASC']);
        $result = array_merge($result, [
            [
                self::COLUMN_NAME => 'esiDataSource',
                self::COLUMN_VALUE => $config['eve']['datasource']
            ], [
                self::COLUMN_NAME => 'esiHost',
                self::COLUMN_VALUE => $config['eve']['esi_host']
            ], [
                self::COLUMN_NAME => 'navigationShowGroups',
                self::COLUMN_VALUE => $groupRepository->count(['visibility' => Group::VISIBILITY_PUBLIC]) > 0 ?
                    '1' : '0'
            ], [
                self::COLUMN_NAME => 'navigationServices',
                self::COLUMN_VALUE => \json_encode($services)
            ], [
                self::COLUMN_NAME => 'repository',
                self::COLUMN_VALUE => $config['repository']
            ],
        ]);

        return $this->withJson($result);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/settings/system/change/{name}",
     *     operationId="systemChange",
     *     summary="Change a system settings variable.",
     *     description="Needs role: settings",
     *     tags={"Settings"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="name",
     *         in="path",
     *         required=true,
     *         description="Name of the variable.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"value"},
     *                 @OA\Property(
     *                     property="value",
     *                     description="New value for the variable.",
     *                     type="string",
     *                 )
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Variable value changed.",
     *         @OA\JsonContent(ref="#/components/schemas/SystemVariable")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Variable removed."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Variable not found."
     *     )
     * )
     *
     * @see EveMail::deleteToken();
     */
    public function systemChange(
        string $name,
        ServerRequestInterface $request,
        MemberTracking $memberTracking,
        EveMail $eveMail
    ): ResponseInterface {
        $variable = $this->repositoryFactory->getSystemVariableRepository()->find($name);

        if ($variable === null || ! in_array($variable->getScope(), $this->validScopes)) {
            return $this->response->withStatus(404);
        }

        if ($variable->getName() === SystemVariable::MAIL_CHARACTER) {
            // if the mail character has been removed, delete the corresponding token as well
            $variable->setValue(''); // only removal is allowed here
            $eveMail->deleteToken();
        } elseif (strpos($variable->getName(), SystemVariable::DIRECTOR_CHAR) !== false) {
            if ($memberTracking->removeDirector($variable)) {
                $variable = null;
            }
        } else {
            $variable->setValue((string) $this->getBodyParam($request, self::COLUMN_VALUE));
        }

        if (! $this->objectManager->flush()) {
            return $this->response->withStatus(500);
        }

        if ($variable !== null) {
            return $this->withJson($variable);
        } else {
            return $this->response->withStatus(204);
        }
    }

    /**
     * @noinspection PhpUnused
     * @OA\Post(
     *     path="/user/settings/system/send-invalid-token-mail",
     *     operationId="sendInvalidTokenMail",
     *     summary="Sends a 'invalid ESI token' test mail to the logged-in character.",
     *     description="Needs role: settings",
     *     tags={"Settings"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="Error message, if available.",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function sendInvalidTokenMail(EveMail $eveMail, UserAuth $userAuth): ResponseInterface
    {
        $charId = $this->getUser($userAuth)->getId();

        $result = $eveMail->invalidTokenIsActive();
        if ($result === '') {
            $result = $eveMail->invalidTokenMaySend($charId, true);
        }
        if ($result === '') {
            $result = $eveMail->invalidTokenSend($charId);
        }

        return $this->withJson($result);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Post(
     *     path="/user/settings/system/send-missing-character-mail",
     *     operationId="sendMissingCharacterMail",
     *     summary="Sends a 'missing character' test mail to the logged-in character.",
     *     description="Needs role: settings",
     *     tags={"Settings"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="Error message, if available.",
     *         @OA\JsonContent(type="string")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function sendMissingCharacterMail(EveMail $eveMail, UserAuth $userAuth): ResponseInterface
    {
        $charId = $this->getUser($userAuth)->getId();

        $result = $eveMail->missingCharacterIsActive();
        if ($result === '') {
            $result = $eveMail->missingCharacterMaySend($charId, true);
        }
        if ($result === '') {
            $result = $eveMail->missingCharacterSend($charId);
        }

        return $this->withJson($result);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/settings/system/validate-director/{name}",
     *     operationId="validateDirector",
     *     summary="Validates ESI token from a director and updates name and corporation.",
     *     description="Needs role: settings",
     *     tags={"Settings"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="name",
     *         in="path",
     *         required=true,
     *         description="Name of the director variable.",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="True if the access token is valid, otherwise false",
     *         @OA\JsonContent(type="boolean")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function validateDirector(string $name, MemberTracking $memberTracking, EsiData $esiData): ResponseInterface
    {
        $success = $memberTracking->updateDirector($name);
        if (! $success) {
            return $this->withJson(false);
        }

        $valid = false;

        $accessToken = null;
        $tokenData = $memberTracking->getDirectorTokenVariableData($name);
        if ($tokenData) {
            $accessToken = $memberTracking->refreshDirectorToken($tokenData);
        }
        if ($accessToken !== null) {
            $valid = $esiData->verifyRoles(
                [EveLogin::ROLE_DIRECTOR],
                (int) $accessToken->getResourceOwnerId(),
                $accessToken->getToken()
            );
        }

        return $this->withJson($valid);
    }
}
