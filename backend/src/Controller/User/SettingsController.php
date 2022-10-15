<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Group;
use Neucore\Entity\Role;
use Neucore\Entity\SystemVariable;
use Neucore\Service\Config;
use Neucore\Service\EveMail;
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

    private const VALID_SCOPES = [SystemVariable::SCOPE_PUBLIC, SystemVariable::SCOPE_SETTINGS];

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
            $scopes = self::VALID_SCOPES;
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
            ], [
                self::COLUMN_NAME => 'discord',
                self::COLUMN_VALUE => $config['discord']
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
    public function systemChange(string $name, ServerRequestInterface $request, EveMail $eveMail): ResponseInterface {
        $variable = $this->repositoryFactory->getSystemVariableRepository()->find($name);

        if ($variable === null || !in_array($variable->getScope(), self::VALID_SCOPES)) {
            return $this->response->withStatus(404);
        }

        if ($variable->getName() === SystemVariable::MAIL_CHARACTER) {
            // if the mail character has been removed, delete the corresponding token as well
            $variable->setValue(''); // only removal is allowed here
            $eveMail->deleteToken();
        } else {
            $variable->setValue((string) $this->getBodyParam($request, self::COLUMN_VALUE));
        }

        if (! $this->objectManager->flush()) {
            return $this->response->withStatus(500);
        }

        return $this->withJson($variable);
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
}
