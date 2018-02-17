<?php
namespace Tests\Functional;

use Brave\Core\Application;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\Environment;

/**
 * Runs the application.
 */
class BaseTestCase extends \PHPUnit\Framework\TestCase
{

    /**
     * Process the application given a request method and URI
     *
     * @param string $requestMethod the request method (e.g. GET, POST, etc.)
     * @param string $requestUri the request URI
     * @param array|object|null $requestData the request data
     * @param array|null $header
     * @return \Slim\Http\Response
     */
    protected function runApp($requestMethod, $requestUri, $requestData = null, array $headers = null)
    {
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

        // create app with test settings
        $app = new Application();
        $app->settings(true);
        $slimApp = $app->getApp();

        // Process the application
        $response = $slimApp->process($request, $response);

        // Return the response
        return $response;
    }

    protected function parseJsonBody(\Slim\Http\Response $response, $assoc = true)
    {
        $json = $response->getBody()->__toString();

        return json_decode($json, $assoc);
    }

    protected function loginUser($id)
    {
        $_SESSION['user_id'] = $id;
    }
}
