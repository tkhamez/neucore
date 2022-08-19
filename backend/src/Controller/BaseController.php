<?php

declare(strict_types=1);

namespace Neucore\Controller;

use Neucore\Entity\Character;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\ObjectManager;
use Neucore\Service\UserAuth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class BaseController
{
    protected ResponseInterface $response;

    protected ObjectManager $objectManager;

    protected RepositoryFactory $repositoryFactory;

    public function __construct(
        ResponseInterface $response,
        ObjectManager $objectManager,
        RepositoryFactory $repositoryFactory
    ) {
        $this->response = $response;
        $this->objectManager = $objectManager;
        $this->repositoryFactory = $repositoryFactory;
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $key
     * @param  mixed $default
     * @return mixed
     */
    protected function getQueryParam(ServerRequestInterface $request, string $key, $default = null)
    {
        return $request->getQueryParams()[$key] ?? $default;
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $key
     * @param  mixed $default
     * @return mixed
     * @see \Slim\Psr7\Factory\ServerRequestFactory::createFromGlobals()
     */
    protected function getBodyParam(ServerRequestInterface $request, string $key, $default = null)
    {
        $params = $request->getParsedBody();
        if (is_array($params) && isset($params[$key])) {
            return $params[$key];
        } elseif (is_object($params) && property_exists($params, $key)) {
            return $params->$key;
        }

        return $default;
    }

    protected function getIntegerArrayFromBody(ServerRequestInterface $request): ?array
    {
        $ids = $request->getParsedBody();

        if (! is_array($ids)) {
            return null;
        }

        return array_unique(array_map('intVal', array_values($ids)));
    }

    /**
     * Removes all non-printable characters from the string.
     */
    protected function sanitizePrintable(string $string): string
    {
        return (string) preg_replace('/[^[:print:]]/', '', trim($string));
    }

    /**
     * @param mixed $data
     * @param int|null $status
     * @return ResponseInterface
     */
    protected function withJson($data, ?int $status = null): ResponseInterface
    {
        $json = (string) \json_encode($data);

        $this->response->getBody()->write($json);
        if (isset($status)) {
            $this->response = $this->response->withStatus($status);
        }

        return $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Length', (string) strlen($json));
    }

    /**
     * @param int $status
     * @param mixed|null $data
     * @return ResponseInterface
     */
    protected function flushAndReturn(int $status, $data = null): ResponseInterface
    {
        if (! $this->objectManager->flush()) {
            return $this->response->withStatus(500);
        }

        $this->response = $this->response->withStatus($status);
        if ($data !== null) {
            return $this->withJson($data);
        } else {
            return $this->response;
        }
    }

    /**
     * Returns the logged-in user or an empty user object with an empty player object.
     *
     * This method should only be called if there is a user logged-in. It will return an "empty" character
     * object with an empty player object attached if there is no logged-in user. Otherwise where would need
     * to be null-checks everywhere.
     */
    protected function getUser(UserAuth $userAuth): Character
    {
        $character = $userAuth->getUser();
        if (!$character) {
            $character = new Character();
            $character->setPlayer(new Player());
        }
        return $character;
    }

    protected function getBodyWithHomeLink(string $message): string
    {
        return $message.'<br><br><a href="/">Home</a>';
    }
}
