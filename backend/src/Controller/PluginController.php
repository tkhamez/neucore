<?php

declare(strict_types=1);

namespace Neucore\Controller;

use Neucore\Log\Context;
use Neucore\Plugin\Exception;
use Neucore\Plugin\ServiceInterface;
use Neucore\Service\PluginService;
use Neucore\Service\UserAuth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

class PluginController extends BaseController
{
    /**
     * GET /plugin/{id}/{name}
     *
     * This URL is public.
     */
    public function request(
        string                 $id,
        string                 $name,
        ServerRequestInterface $request,
        PluginService          $pluginService,
        UserAuth               $userAuth,
        LoggerInterface        $logger
    ): ResponseInterface {
        $plugin = $this->repositoryFactory->getPluginRepository()->find((int) $id);
        if ($plugin === null) {
            $this->response->getBody()->write($this->getBodyWithHomeLink('Plugin not found.'));
            return $this->response->withStatus(404);
        }

        if (!$plugin->getConfigurationDatabase()?->active) {
            $this->response->getBody()->write($this->getBodyWithHomeLink('Plugin is not active.'));
            return $this->response->withStatus(404);
        }

        $player = $userAuth->getUser()?->getPlayer();

        if (!$userAuth->hasRequiredGroups($plugin, true)) {
            $this->response->getBody()->write($this->getBodyWithHomeLink('Not allowed to use this plugin.'));
            return $this->response->withStatus(403);
        }

        $implementation = $pluginService->getPluginImplementation($plugin);
        if (!$implementation) {
            $this->response->getBody()->write($this->getBodyWithHomeLink('Plugin implementation not found.'));
            return $this->response->withStatus(404);
        }

        try {
            return $implementation->request($name, $request, $this->response, $player?->getCoreAccount());
        } catch (Exception $e) {
            $logger->error($e->getMessage(), [Context::EXCEPTION => $e]);
            if ($implementation instanceof ServiceInterface) {
                return $this->response
                    ->withHeader('Location', '/#Service/' . $plugin->getId() . '/?message=Unknown%20error.')
                    ->withStatus(302);
            } else {
                $this->response->getBody()->write($this->getBodyWithHomeLink('Error from plugin.'));
                return $this->response;
            }
        }
    }
}
