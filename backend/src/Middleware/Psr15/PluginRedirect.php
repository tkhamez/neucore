<?php

declare(strict_types=1);

namespace Neucore\Middleware\Psr15;

use Neucore\Service\PluginService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Issues a redirect for plugins.
 */
class PluginRedirect extends AbstractRedirect implements MiddlewareInterface
{
    public const PLUGIN_PREFIX = '/plugin';

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly PluginService $pluginService,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        $pathParts = explode('/', $path);

        // Example plugin path: /plugin/17/callback
        $prefix = $pathParts[1] ?? '';
        $id = $pathParts[2] ?? '';
        $requestName = $pathParts[3] ?? '';

        if ("/$prefix" !== self::PLUGIN_PREFIX || $id === '' || $requestName === '') {
            return $handler->handle($request);
        }

        $plugin = $this->pluginService->getPlugin((int) $id);
        if (!$plugin || !($configuration = $plugin->getConfigurationFile())) {
            return $handler->handle($request);
        }

        if (!in_array($requestName, $configuration->redirectRequestNames)) {
            return $handler->handle($request);
        }

        return $this->respond($request, $this->responseFactory, $handler);
    }
}
