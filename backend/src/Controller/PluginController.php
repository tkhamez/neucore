<?php

declare(strict_types=1);

namespace Neucore\Controller;

use Neucore\Log\Context;
use Neucore\Plugin\Exception;
use Neucore\Service\ServiceRegistration;
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
        string $id,
        string $name,
        ServerRequestInterface $request,
        ServiceRegistration $serviceRegistration,
        UserAuth $userAuth,
        LoggerInterface $logger
    ): ResponseInterface {
        $user = $userAuth->getUser();
        if (!$user) {
            $this->response->getBody()->write($this->getBodyWithHomeLink('Not logged in.'));
            return $this->response->withStatus(403);
        }
        $player = $user->getPlayer();

        $service = $this->repositoryFactory->getServiceRepository()->find((int) $id);
        if ($service === null) {
            $this->response->getBody()->write($this->getBodyWithHomeLink('Service not found.'));
            return $this->response->withStatus(404);
        }

        if (!$serviceRegistration->hasRequiredGroups($service)) {
            $this->response->getBody()->write($this->getBodyWithHomeLink('Not allowed to use this service.'));
            return $this->response->withStatus(403);
        }

        $implementation = $serviceRegistration->getServiceImplementation($service);
        if (!$implementation) {
            $this->response->getBody()->write($this->getBodyWithHomeLink('Service implementation not found.'));
            return $this->response->withStatus(404);
        }

        if ($player->getMain() !== null) {
            $coreCharacter = $player->getMain()->toCoreCharacter();
        } else {
            $this->response->getBody()->write(
                $this->getBodyWithHomeLink('Player or main character account not found.')
            );
            return $this->response->withStatus(404);
        }

        try {
            return $implementation->request($coreCharacter, $name, $request, $this->response, $player->getCoreGroups());
        } catch (Exception $e) {
            $logger->error($e->getMessage(), [Context::EXCEPTION => $e]);
            return $this->response
                ->withHeader('Location', '/#Service/' . $service->getId() . '/?message=Unknown%20error.')
                ->withStatus(302);
        }
    }
}
