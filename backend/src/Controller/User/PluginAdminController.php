<?php

declare(strict_types=1);

namespace Neucore\Controller\User;

use Neucore\Controller\BaseController;
use Neucore\Data\PluginConfigurationFile;
use Neucore\Entity\Plugin;
use Neucore\Data\PluginConfigurationDatabase;
use Neucore\Factory\RepositoryFactory;
use Neucore\Log\Context;
use Neucore\Plugin\Exception;
use Neucore\Service\Config;
use Neucore\Service\ObjectManager;
use Neucore\Service\PluginService;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

#[OA\Tag(name: 'PluginAdmin', description: 'Plugin administration.')]
class PluginAdminController extends BaseController
{
    private PluginService $pluginService;

    public function __construct(
        ResponseInterface $response,
        ObjectManager     $objectManager,
        RepositoryFactory $repositoryFactory,
        PluginService     $pluginService,
    ) {
        parent::__construct($response, $objectManager, $repositoryFactory);
        $this->pluginService = $pluginService;
    }

    #[OA\Get(
        path: '/user/plugin-admin/{id}/get',
        operationId: 'pluginAdminGet',
        description: 'Needs role: plugin-admin',
        summary: 'Returns plugin.',
        security: [['Session' => []]],
        tags: ['PluginAdmin'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the plugin.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'The service.',
                content: new OA\JsonContent(ref: '#/components/schemas/Plugin'),
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Plugin not found.'),
        ],
    )]
    public function get(string $id): ResponseInterface
    {
        // Get service with data from plugin.yml
        $plugin = $this->pluginService->getPlugin((int) $id, true);

        if (!$plugin) {
            return $this->response->withStatus(404);
        }

        // Load implementation to fill the "types" property of the "configurationFile" property of the plugin.
        $this->pluginService->getPluginImplementation($plugin);

        return $this->withJson($plugin->jsonSerialize(false, true, false));
    }

    #[OA\Get(
        path: '/user/plugin-admin/list',
        operationId: 'pluginAdminList',
        description: 'Needs role: plugin-admin',
        summary: 'Lists all plugins.',
        security: [['Session' => []]],
        tags: ['PluginAdmin'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of plugins.',
                content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/Plugin')),
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
        ],
    )]
    public function list(): ResponseInterface
    {
        return $this->withJson($this->repositoryFactory->getPluginRepository()->findBy([], ['name' => 'ASC']));
    }

    #[OA\Get(
        path: '/user/plugin-admin/configurations',
        operationId: 'pluginAdminConfigurations',
        description: 'Needs role: plugin-admin',
        summary: 'Returns data from plugin.yml files and their directory.',
        security: [['Session' => []]],
        tags: ['PluginAdmin'],
        responses: [
            new OA\Response(
                response: '200',
                description: 'List of files.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/PluginConfigurationFile'),
                ),
            ),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '500', description: 'When a YAML file could not be parsed.'),
        ],
    )]
    public function configurations(Config $config, PluginService $pluginService): ResponseInterface
    {
        $basePath = is_string($config['plugins_install_dir']) ? $config['plugins_install_dir'] : '';

        if (empty($basePath) || !is_dir($basePath)) {
            return $this->withJson([]);
        }

        $configurations = [];
        foreach (new \DirectoryIterator($basePath) as $fileInfo) {
            /* @var $fileInfo \DirectoryIterator */

            if (!$fileInfo->isDir() || $fileInfo->isDot()) {
                continue;
            }

            if (!file_exists("$basePath/" . $fileInfo->getFilename() . '/plugin.yml')) {
                continue;
            }

            $pluginConfig = $pluginService->getConfigurationFromConfigFile($fileInfo->getFilename());
            if (!$pluginConfig) {
                return $this->response->withStatus(500);
            }

            // Load implementation to fill the "types" property of the "configurationFile" property of the plugin.
            $pluginService->loadPluginImplementation($pluginConfig);

            $configurations[] = $pluginConfig;
        }

        uasort($configurations, function (PluginConfigurationFile $a, PluginConfigurationFile $b) {
            $compareA = implode(',', $a->types) . "$a->name|$a->directoryName";
            $compareB = implode(',', $b->types) . "$b->name|$a->directoryName";
            if ($compareA < $compareB) {
                return -1;
            } elseif ($compareA > $compareB) {
                return 1;
            }
            return 0;
        });

        return $this->withJson(array_map(function (PluginConfigurationFile $configuration) {
            return $configuration->jsonSerialize(true, false);
        }, array_values($configurations)));
    }

    #[OA\Post(
        path: '/user/plugin-admin/create',
        operationId: 'pluginAdminCreate',
        description: 'Needs role: plugin-admin',
        summary: 'Creates a plugin.',
        security: [['Session' => [], 'CSRF' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['name'],
                    properties: [
                        new OA\Property(
                            property: 'name',
                            description: 'Name of the plugin.',
                            type: 'string',
                            maxLength: 255,
                        ),
                    ],
                    type: 'object',
                ),
            ),
        ),
        tags: ['PluginAdmin'],
        responses: [
            new OA\Response(
                response: '201',
                description: 'The new plugin.',
                content: new OA\JsonContent(ref: '#/components/schemas/Plugin'),
            ),
            new OA\Response(response: '400', description: 'Name is missing.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
        ],
    )]
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $name = $this->getBodyParam($request, 'name', '');
        if (empty($name)) {
            return $this->response->withStatus(400);
        }

        $plugin = (new Plugin())->setName($name);
        $this->objectManager->persist($plugin);

        return $this->flushAndReturn(201, $plugin);
    }

    #[OA\Put(
        path: '/user/plugin-admin/{id}/rename',
        operationId: 'pluginAdminRename',
        description: 'Needs role: plugin-admin',
        summary: 'Renames a plugin.',
        security: [['Session' => [], 'CSRF' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['name'],
                    properties: [
                        new OA\Property(
                            property: 'name',
                            description: 'New name for the plugin.',
                            type: 'string',
                            maxLength: 255,
                        ),
                    ],
                    type: 'object',
                ),
            ),
        ),
        tags: ['PluginAdmin'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the plugin.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: '200',
                description: 'Plugin was renamed.',
                content: new OA\JsonContent(ref: '#/components/schemas/Plugin'),
            ),
            new OA\Response(response: '400', description: 'Name is missing.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Plugin not found.'),
        ],
    )]
    public function rename(string $id, ServerRequestInterface $request): ResponseInterface
    {
        $plugin = $this->repositoryFactory->getPluginRepository()->find((int) $id);
        if ($plugin === null) {
            return $this->response->withStatus(404);
        }

        $name = $this->getBodyParam($request, 'name', '');
        if (empty($name)) {
            return $this->response->withStatus(400);
        }

        $plugin->setName($name);

        return $this->flushAndReturn(200, $plugin);
    }

    #[OA\Delete(
        path: '/user/plugin-admin/{id}/delete',
        operationId: 'pluginAdminDelete',
        description: 'Needs role: plugin-admin',
        summary: 'Deletes a plugin.',
        security: [['Session' => [], 'CSRF' => []]],
        tags: ['PluginAdmin'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the plugin.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Plugin was deleted.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Plugin not found.'),
        ],
    )]
    public function delete(string $id): ResponseInterface
    {
        $plugin = $this->repositoryFactory->getPluginRepository()->find((int) $id);
        if ($plugin === null) {
            return $this->response->withStatus(404);
        }

        $this->objectManager->remove($plugin);

        return $this->flushAndReturn(204);
    }

    #[OA\Put(
        path: '/user/plugin-admin/{id}/save-configuration',
        operationId: 'pluginAdminSaveConfiguration',
        description: 'Needs role: plugin-admin',
        summary: 'Saves the plugin configuration.',
        security: [['Session' => [], 'CSRF' => []]],
        requestBody: new OA\RequestBody(
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(
                            property: 'configuration',
                            ref: '#/components/schemas/PluginConfigurationDatabase',
                        ),
                    ],
                    type: 'object',
                ),
            ),
        ),
        tags: ['PluginAdmin'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the plugin.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(response: '204', description: 'Configuration changed.'),
            new OA\Response(response: '400', description: 'Invalid input.'),
            new OA\Response(response: '403', description: 'Not authorized.'),
            new OA\Response(response: '404', description: 'Variable not found.'),
        ],
    )]
    public function saveConfiguration(
        string                 $id,
        ServerRequestInterface $request,
        PluginService          $pluginService,
        LoggerInterface        $logger,
    ): ResponseInterface {
        $plugin = $this->repositoryFactory->getPluginRepository()->find((int) $id);
        if ($plugin === null) {
            return $this->response->withStatus(404);
        }

        $configuration = $this->getBodyParam($request, 'configuration', '');

        if (!is_string($configuration)) {
            return $this->response->withStatus(400);
        }
        $data = \json_decode($configuration, true);
        if (is_array($data)) {
            $configRequest = PluginConfigurationDatabase::fromArray($data);
            $plugin->setConfigurationDatabase($configRequest);
        } else {
            return $this->response->withStatus(400);
        }

        $response = $this->flushAndReturn(204);

        if ($response->getStatusCode() !== 500) {
            $implementation = $pluginService->getPluginImplementation($plugin);
            try {
                $implementation?->onConfigurationChange();
            } catch (Exception $e) {
                $logger->error($e->getMessage(), [Context::EXCEPTION => $e]);
            }
        }

        return $response;
    }
}
