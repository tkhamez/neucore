<?php declare(strict_types=1);

namespace Tests;

use Brave\Core\Application;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Environment;
use Slim\Http\Request;
use Slim\Http\Response;

/**
 * Runs the application.
 */
class WebTestCase extends TestCase
{
    /**
     * Process the application given a request method and URI
     *
     * @param string $requestMethod the request method (e.g. GET, POST, etc.)
     * @param string $requestUri the request URI
     * @param array|object|null $requestData the request data
     * @param array|null $headers
     * @param array|null $mocks key/value paris for the dependency injection container
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function runApp(
        $requestMethod,
        $requestUri,
        $requestData = null,
        array $headers = null,
        array $mocks = []
    ) {
        // Create a mock environment for testing with
        $environment = Environment::mock([
            'REQUEST_METHOD' => $requestMethod,
            'REQUEST_URI' => $requestUri
        ]);

        // Set up a request object based on the environment
        $request = Request::createFromEnvironment($environment);

        // Add request data, if it exists
        if (isset($requestData)) {
            $request = $request->withParsedBody($requestData);
        }

        // add header
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
        }

        // Set up a response object
        $response = new Response();

        // create app with test settings and without middleware
        $app = new Application();
        $app->loadSettings(true);

        // change dependencies in container
        try {
            $container = $app->getContainer();
        } catch (\Exception $e) {
            echo $e->getMessage();
            return null;
        }
        foreach ($mocks as $class => $obj) {
            $container->set($class, $obj);
        }

        // Process the application
        try {
            $response = $app->getApp()->process($request, $response);
        } catch (\Throwable $e) {
            echo $e->getMessage();
            return null;
        }

        // Return the response
        return $response;
    }

    protected function parseJsonBody(ResponseInterface $response, $assoc = true)
    {
        $json = $response->getBody()->__toString();

        return json_decode($json, $assoc);
    }

    protected function loginUser($id)
    {
        $_SESSION['character_id'] = $id;
    }
}
