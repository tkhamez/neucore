<?php declare(strict_types=1);

namespace Brave\Core\Api\User;

use Brave\Core\Entity\Role;
use Brave\Core\Entity\SystemVariable;
use Brave\Core\Factory\RepositoryFactory;
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
            $scopes = [SystemVariable::SCOPE_PUBLIC, SystemVariable::SCOPE_SETTINGS];
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
     *         response="404",
     *         description="Variable not found."
     *     ),
     *     @SWG\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function systemChange(string $name, Request $request): Response
    {
        $variable = $this->repositoryFactory->getSystemVariableRepository()->find($name);

        if ($variable === null) {
            return $this->response->withStatus(404);
        }

        $variable->setValue((string) $request->getParam('value'));

        if (! $this->objectManager->flush()) {
            return $this->response->withStatus(500);
        }

        return $this->response->withJson($variable);
    }
}
