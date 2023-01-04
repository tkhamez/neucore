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
/* @phan-suppress-next-line PhanUnreferencedUseNormal */
use OpenApi\Annotations as OA;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/**
 * @OA\Tag(
 *     name="PluginAdmin",
 *     description="Plugin administration."
 * )
 */
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

    /**
     * @OA\Get(
     *     path="/user/plugin-admin/{id}/get",
     *     operationId="pluginAdminGet",
     *     summary="Returns plugin.",
     *     description="Needs role: plugin-admin",
     *     tags={"PluginAdmin"},
     *     security={{"Session"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the plugin.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="The service.",
     *         @OA\JsonContent(ref="#/components/schemas/Plugin")
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Plugin not found."
     *     )
     * )
     */
    public function get(string $id): ResponseInterface
    {
        // get service with data from plugin.yml
        $plugin = $this->pluginService->getPlugin((int) $id);

        if (!$plugin) {
            return $this->response->withStatus(404);
        }

        return $this->withJson($plugin->jsonSerialize(false, true, false));
    }

    /**
     * @OA\Get(
     *     path="/user/plugin-admin/list",
     *     operationId="pluginAdminList",
     *     summary="Lists all plugins.",
     *     description="Needs role: plugin-admin",
     *     tags={"PluginAdmin"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of plugins.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/Plugin"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
    public function list(): ResponseInterface
    {
        return $this->withJson($this->repositoryFactory->getPluginRepository()->findBy([], ['name' => 'ASC']));
    }

    /**
     * @OA\Get(
     *     path="/user/plugin-admin/configurations",
     *     operationId="pluginAdminConfigurations",
     *     summary="Returns data from plugin.yml files and their directory.",
     *     description="Needs role: plugin-admin",
     *     tags={"PluginAdmin"},
     *     security={{"Session"={}}},
     *     @OA\Response(
     *         response="200",
     *         description="List of files.",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/PluginConfigurationFile"))
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="When a YAML file could not be parsed."
     *     )
     * )
     */
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

            $configurations[] = $pluginConfig;
        }

        uasort($configurations, function (PluginConfigurationFile $a, PluginConfigurationFile $b) {
            $compareA = "$a->type|$a->name|$a->directoryName";
            $compareB = "$b->type|$b->name|$a->directoryName";
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

    /**
     * @OA\Post(
     *     path="/user/plugin-admin/create",
     *     operationId="pluginAdminCreate",
     *     summary="Creates a plugin.",
     *     description="Needs role: plugin-admin",
     *     tags={"PluginAdmin"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"name"},
     *                 @OA\Property(
     *                     property="name",
     *                     description="Name of the plugin.",
     *                     type="string",
     *                     maxLength=255,
     *                 )
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response="201",
     *         description="The new plugin.",
     *         @OA\JsonContent(ref="#/components/schemas/Plugin")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Name is missing."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     )
     * )
     */
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

    /**
     * @OA\Put(
     *     path="/user/plugin-admin/{id}/rename",
     *     operationId="pluginAdminRename",
     *     summary="Renames a plugin.",
     *     description="Needs role: plugin-admin",
     *     tags={"PluginAdmin"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the plugin.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 type="object",
     *                 required={"name"},
     *                 @OA\Property(
     *                     property="name",
     *                     description="New name for the plugin.",
     *                     type="string",
     *                     maxLength=255
     *                 )
     *             ),
     *         ),
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Plugin was renamed.",
     *         @OA\JsonContent(ref="#/components/schemas/Plugin")
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Name is missing."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Plugin not found."
     *     )
     * )
     */
    public function rename(string $id, ServerRequestInterface $request): ResponseInterface
    {
        $plugin = $this->repositoryFactory->getPluginRepository()->find((int)$id);
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

    /**
     * @OA\Delete(
     *     path="/user/plugin-admin/{id}/delete",
     *     operationId="pluginAdminDelete",
     *     summary="Deletes a plugin.",
     *     description="Needs role: plugin-admin",
     *     tags={"PluginAdmin"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the plugin.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Plugin was deleted."
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Not authorized."
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Plugin not found."
     *     )
     * )
     */
    public function delete(string $id): ResponseInterface
    {
        $plugin = $this->repositoryFactory->getPluginRepository()->find((int)$id);
        if ($plugin === null) {
            return $this->response->withStatus(404);
        }

        $this->objectManager->remove($plugin);

        return $this->flushAndReturn(204);
    }

    /**
     * @noinspection PhpUnused
     * @OA\Put(
     *     path="/user/plugin-admin/{id}/save-configuration",
     *     operationId="pluginAdminSaveConfiguration",
     *     summary="Saves the plugin configuration.",
     *     description="Needs role: plugin-admin",
     *     tags={"PluginAdmin"},
     *     security={{"Session"={}, "CSRF"={}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the plugin.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="application/x-www-form-urlencoded",
     *             @OA\Schema(
     *                 type="object",
     *                 @OA\Property(
     *                     property="configuration",
     *                     ref="#/components/schemas/PluginConfigurationDatabase"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response="204",
     *         description="Configuration changed.",
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Invalid input."
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
     */
    public function saveConfiguration(
        string                 $id,
        ServerRequestInterface $request,
        PluginService          $pluginService,
        LoggerInterface        $logger,
    ): ResponseInterface {
        $plugin = $this->repositoryFactory->getPluginRepository()->find((int)$id);
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
