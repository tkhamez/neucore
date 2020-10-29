<?php

declare(strict_types=1);

namespace Neucore\Controller;

use Neucore\Entity\Character;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\ObjectManager;
use Neucore\Service\UserAuth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class BaseController
{
    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var RepositoryFactory
     */
    protected $repositoryFactory;

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

        return array_unique(array_map('intVal', $ids));
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
        $this->response->getBody()->write((string) \json_encode($data));
        if (isset($status)) {
            $this->response = $this->response->withStatus($status);
        }

        return $this->response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param int $status
     * @param mixed|null $data
     * @param string $reasonPhrase Ignored if data is not null
     * @return ResponseInterface
     */
    protected function flushAndReturn(int $status, $data = null, $reasonPhrase = ''): ResponseInterface
    {
        if (! $this->objectManager->flush()) {
            return $this->response->withStatus(500);
        }

        $this->response = $this->response->withStatus($status, $reasonPhrase);
        if ($data !== null) {
            return $this->withJson($data);
        } else {
            return $this->response;
        }
    }

    /**
     * Returns the logged in user.
     *
     * Don't call it if there is no user logged in, it will return null in that case. This
     * is not documented in the return type hint to avoid a lot of static code analysis error.
     * TODO find a better way
     */
    protected function getUser(UserAuth $userAuth): Character
    {
        return $userAuth->getUser();
    }
}
