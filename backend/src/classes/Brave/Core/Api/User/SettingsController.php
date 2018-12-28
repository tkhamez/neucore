<?php declare(strict_types=1);

namespace Brave\Core\Api\User;

use Brave\Core\Entity\Role;
use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Service\EveMail;
use Brave\Core\Service\MemberTracking;
use Brave\Core\Service\ObjectManager;
use Brave\Core\Service\UserAuth;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Controller for system settings (and maybe user settings later).
 *
 * @SWG\Tag(
 *     name="Settings",
 *     description="System settings."
 * )
 */
class SettingsController
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
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var UserAuth
     */
    private $userAuth;

    /**
     * @var array
     */
    private $validScopes = [SystemVariable::SCOPE_PUBLIC, SystemVariable::SCOPE_SETTINGS];

    public function __construct(
        Response $response,
        RepositoryFactory $repositoryFactory,
        ObjectManager $objectManager,
        UserAuth $userAuth
    ) {
        $this->response = $response;
        $this->repositoryFactory = $repositoryFactory;
        $this->objectManager = $objectManager;
        $this->userAuth = $userAuth;
    }

    /**
     * @SWG\Get(
     *     path="/user/settings/system/list",
     *     operationId="systemList",
     *     summary="List all settings.",
     *     description="Some variables need the role 'settings'",
     *     tags={"Settings"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="List of settings.",
     *         @SWG\Schema(type="array", @SWG\Items(ref="#/definitions/SystemVariable"))
     *     )
     * )
     */
    public function systemList(): Response
    {
        $repository = $this->repositoryFactory->getSystemVariableRepository();
        if (in_array(Role::SETTINGS, $this->userAuth->getRoles())) {
            $scopes = $this->validScopes;
        } else {
            $scopes = [SystemVariable::SCOPE_PUBLIC];
        }

        return $this->response->withJson($repository->findBy(['scope' => $scopes], ['name' => 'ASC']));
    }

    /**
     * @SWG\Put(
     *     path="/user/settings/system/change/{name}",
     *     operationId="systemChange",
     *     summary="Change a system settings variable.",
     *     description="Needs role: settings",
     *     tags={"Settings"},
     *     security={{"Session"={}}},
     *     consumes={"application/x-www-form-urlencoded"},
     *     @SWG\Parameter(
     *         name="name",
     *         in="path",
     *         required=true,
     *         description="Name of the variable.",
     *         type="string"
     *     ),
     *     @SWG\Parameter(
     *         name="value",
     *         in="formData",
     *         required=true,
     *         description="New value for the variable.",
     *         type="string",
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="Variable value changed.",
     *         @SWG\Schema(ref="#/definitions/SystemVariable")
     *     ),
     *     @SWG\Response(
     *         response="204",
     *         description="Variable removed."
     *     ),
     *     @SWG\Response(
     *         response="404",
     *         description="Variable not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function systemChange(string $name, Request $request, MemberTracking $memberTracking): Response
    {
        $variable = $this->repositoryFactory->getSystemVariableRepository()->find($name);

        if ($variable === null || ! in_array($variable->getScope(), $this->validScopes)) {
            return $this->response->withStatus(404);
        }

        if ($variable->getName() === SystemVariable::MAIL_CHARACTER) {
            // if the mail character has been removed, delete the corresponding token as well
            $variable->setValue(''); // only removal is allowed here
            $var2 = $this->repositoryFactory->getSystemVariableRepository()->find(SystemVariable::MAIL_TOKEN);
            $var2->setValue('');
        } elseif (strpos($variable->getName(), SystemVariable::DIRECTOR_CHAR) !== false) {
            if ($memberTracking->removeDirector($variable)) {
                $variable = null;
            }
        } else {
            $variable->setValue((string) $request->getParam('value'));
        }

        if (! $this->objectManager->flush()) {
            return $this->response->withStatus(500);
        }

        if ($variable !== null) {
            return $this->response->withJson($variable);
        } else {
            return $this->response->withStatus(204);
        }
    }

    /**
     * @SWG\Post(
     *     path="/user/settings/system/send-account-disabled-mail",
     *     operationId="sendAccountDisabledMail",
     *     summary="Sends a 'Account disabled' test mail to the logged-in character.",
     *     description="Needs role: settings",
     *     tags={"Settings"},
     *     security={{"Session"={}}},
     *     @SWG\Response(
     *         response="200",
     *         description="Error message, if available.",
     *         @SWG\Schema(type="string")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function sendAccountDisabledMail(EveMail $eveMail): Response
    {
        $charId = $this->userAuth->getUser()->getId();

        $result = $eveMail->accountDeactivatedIsActive();
        if ($result === '') {
            $result = $eveMail->accountDeactivatedMaySend($charId, true);
        }
        if ($result === '') {
            $result = $eveMail->accountDeactivatedSend($charId);
        }

        return $this->response->withJson($result);
    }

    /**
     * @SWG\Put(
     *     path="/user/settings/system/validate-director/{name}",
     *     operationId="validateDirector",
     *     summary="Validates ESI token from a director and updates name and corporation.",
     *     description="Needs role: settings",
     *     tags={"Settings"},
     *     security={{"Session"={}}},
     *     @SWG\Parameter(
     *         name="name",
     *         in="path",
     *         required=true,
     *         description="Name of the director variable.",
     *         type="string"
     *     ),
     *     @SWG\Response(
     *         response="200",
     *         description="True if the access token is valid, otherwise false",
     *         @SWG\Schema(type="boolean")
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function validateDirector(string $name, MemberTracking $memberTracking): Response
    {
        $success = $memberTracking->updateDirector($name);
        if (! $success) {
            return $this->response->withJson(false);
        }

        $valid = false;

        $accessToken = $memberTracking->refreshDirectorToken($name);
        if ($accessToken !== null) {
            $valid = $memberTracking->verifyDirectorRole(
                (int) $accessToken->getResourceOwnerId(),
                $accessToken->getToken()
            );
        }

        return $this->response->withJson($valid);
    }
}
