<?php declare(strict_types=1);

namespace Neucore\Controller;

use Neucore\Factory\RepositoryFactory;
use Neucore\Service\ObjectManager;
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
        $params = $request->getQueryParams();
        return $params[$key] ?? $default;
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $key
     * @param  mixed $default
     * @return mixed
     */
    protected function getParsedBodyParam(ServerRequestInterface $request, string $key, $default = null)
    {
        $postParams = $request->getParsedBody();
        if (is_array($postParams) && isset($postParams[$key])) {
            return $postParams[$key];
        } elseif (is_object($postParams) && property_exists($postParams, $key)) {
            return $postParams->$key;
        }
        return $default;
    }

    /**
     * @param mixed $data
     * @return ResponseInterface
     */
    protected function withJson($data, $status = null): ResponseInterface
    {
        $this->response->getBody()->write((string)json_encode($data));
        if (isset($status)) {
            $this->response = $this->response->withStatus($status);
        }
        return $this->response->withHeader('Content-Type', 'application/json');
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
}
