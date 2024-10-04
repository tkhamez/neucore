<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Entity\Group;
use Neucore\Entity\Plugin;
use Neucore\Entity\Role;
use Neucore\Entity\SystemVariable;
use Neucore\Plugin\Data\NavigationItem;
use Neucore\Service\AccountGroup;
use Neucore\Service\Config;
use Neucore\Service\EveMail;
use Neucore\Service\PluginService;
use Neucore\Service\UserAuth;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * Controller for system settings (and maybe user settings later).
 */
#[OA\Tag(name: 'Settings', description: 'System settings.')]
class SettingsController extends BaseController
{
    private const COLUMN_NAME = 'name';

    private const COLUMN_VALUE = 'value';

    private const VALID_SCOPES = [SystemVariable::SCOPE_PUBLIC, SystemVariable::SCOPE_SETTINGS];

    #[OA\Get(
        path: '/user/settings/system/list',
        operationId: 'systemList',
        description: "Some variables need the role 'settings'",
        summary: 'List all settings.',
        security: [['Session' => []]],
        tags: ['Settings'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of settings.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/SystemVariable')
                )
            )
        ],
    )]
    public function systemList(
        UserAuth $userAuth,
        Config $config,
        PluginService $pluginService,
        LoggerInterface $logger,
        AccountGroup $accountGroup,
    ): ResponseInterface {
        $settingsRepository = $this->repositoryFactory->getSystemVariableRepository();
        $groupRepository = $this->repositoryFactory->getGroupRepository();

        if (in_array(Role::SETTINGS, $userAuth->getRoles())) {
            $scopes = self::VALID_SCOPES;
        } else {
            $scopes = [SystemVariable::SCOPE_PUBLIC];
        }

        // Read plugin navigation items
        $services = [];
        $navigationItems = [];
        foreach ($pluginService->getActivePluginsWithImplementation() as $plugin) {
            if ($plugin->getServiceImplementation() && $userAuth->hasRequiredGroups($plugin)) {
                $services[] = $plugin;
            }
            if ($plugin->getGeneralImplementation() && $userAuth->hasRequiredGroups($plugin, true)) {
                $navigationItems = $this->getNavigationItems($navigationItems, $plugin, $userAuth, $accountGroup, $logger);
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
                self::COLUMN_NAME => 'navigationGeneralPlugins',
                self::COLUMN_VALUE => \json_encode($navigationItems)
            ], [
                self::COLUMN_NAME => 'repository',
                self::COLUMN_VALUE => $config['repository']
            ],
        ]);

        return $this->withJson($result);
    }

    /**
     * @see EveMail::deleteToken();
     */
    #[OA\Put(
        path: '/user/settings/system/change/{name}',
        operationId: 'systemChange',
        description: 'Needs role: settings',
        summary: 'Change a system settings variable.',
        security: [['Session' => [], 'CSRF' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['value'],
                    properties: [
                        new OA\Property(
                            property: 'value',
                            description: 'New value for the variable.',
                            type: 'string'
                        )
                    ],
                    type: 'object',
                )
            )
        ),
        tags: ['Settings'],
        parameters: [
            new OA\Parameter(
                name: 'name',
                description: 'Name of the variable.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string')
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'Variable value changed.',
                content: new OA\JsonContent(ref: '#/components/schemas/SystemVariable')
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Variable not found.')
        ],
    )]
    public function systemChange(string $name, ServerRequestInterface $request, EveMail $eveMail): ResponseInterface
    {
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

    #[OA\Post(
        path: '/user/settings/system/send-invalid-token-mail',
        operationId: 'sendInvalidTokenMail',
        description: 'Needs role: settings',
        summary: "Sends a 'invalid ESI token' test mail to the logged-in character.",
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Settings'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'Error message, if available.',
                content: new OA\JsonContent(type: 'string')
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
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

    #[OA\Post(
        path: '/user/settings/system/send-missing-character-mail',
        operationId: 'sendMissingCharacterMail',
        description: 'Needs role: settings',
        summary: "Sends a 'missing character' test mail to the logged-in character.",
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['Settings'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'Error message, if available.',
                content: new OA\JsonContent(type: 'string')
            ),
            new OA\Response(response: '403', description: 'Not authorized.')
        ],
    )]
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
     * @return NavigationItem[]
     */
    private function getNavigationItems(
        array $navigationItems,
        Plugin $plugin,
        UserAuth $userAuth,
        AccountGroup $accountGroup,
        LoggerInterface $logger,
    ): array {
        $player = $this->getUser($userAuth)->getPlayer(); // There may not be a logged-in user.
        $roles = $userAuth->getRoles(); // Contains Role::ANONYMOUS if there is no logged-in user.

        $validPositions = [
            NavigationItem::PARENT_ROOT,
            NavigationItem::PARENT_SERVICE,
            NavigationItem::PARENT_MANAGEMENT,
            NavigationItem::PARENT_ADMINISTRATION,
            NavigationItem::PARENT_MEMBER_DATA,
        ];

        $items = $plugin->getGeneralImplementation() ? $plugin->getGeneralImplementation()->getNavigationItems() : [];
        foreach ($items as $item) {
            if (!in_array($item->getParent(), $validPositions)) {
                $logger->warning(
                    'Plugin navigation item: invalid position "' . $item->getParent() . '", plugin ID ' .
                    $plugin->getId(),
                );
            } elseif (!str_starts_with($item->getUrl(), '/')) {
                $logger->warning(
                    'Plugin navigation item: invalid URL "' . $item->getUrl() . '", plugin ID ' .
                    $plugin->getId(),
                );
            } else {
                if (!empty($item->getRoles()) &&  empty(array_intersect($item->getRoles(), $roles))) {
                    continue;
                }
                $groupIds = $accountGroup->groupsDeactivated($player) ? [] : $player->getGroupIds();
                if (!empty($item->getGroups()) && empty(array_intersect($item->getGroups(), $groupIds))) {
                    continue;
                }
                if (
                    !empty($item->getManagerGroups()) &&
                    empty(array_intersect($item->getManagerGroups(), $player->getManagerGroupIds()))
                ) {
                    continue;
                }

                $navigationItems[] = [
                    'parent' => $item->getParent(),
                    'name' => $item->getName(),
                    'url' => '/plugin/' . $plugin->getId() . $item->getUrl(),
                    'target' => $item->getTarget(),
                ];
            }
        }

        return $navigationItems;
    }
}
