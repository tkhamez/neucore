<?php declare(strict_types=1);

namespace Tests\Functional;

use Neucore\Application;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Tests\RequestFactory;

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
     * @param array $mocks key/value paris for the dependency injection container
     * @param string[] $envVars var=value
     * @return ResponseInterface|null
     */
    protected function runApp(
        $requestMethod,
        $requestUri,
        $requestData = null,
        array $headers = null,
        array $mocks = [],
        array $envVars = [],
        $contentType = null
    ): ?ResponseInterface {
        // Set up a request object
        $request = RequestFactory::createRequest($requestMethod, $requestUri);

        // Add request data, if it exists
        if (isset($requestData)) {
            if ($requestMethod === 'POST' && $contentType !== 'application/json') {
                // Only for Content-Type: application/x-www-form-urlencoded or multipart/form-data
                $request = $request->withParsedBody($requestData);
            } elseif ($contentType === 'application/json') { // POST with Content-Type: application/json
                $body = $request->getBody();
                $body->write((string) \json_encode($requestData));
                $body->rewind();
                $request = $request->withBody($body);
            } else { // PUT
                $body = $request->getBody();
                $body->write(http_build_query($requestData));
                $body->rewind();
                $request = $request->withBody($body);
            }
        }

        // add header
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
        }

        // create app with test settings
        $app = new Application();
        $app->loadSettings(true);

        foreach ($envVars as $envVar) {
            putenv($envVar);
        }

        // Process the application
        try {
            $response = $app->getApp($mocks)->handle($request);
        } catch (\Throwable $e) {
            echo $e->getMessage();
            return null;
        }

        // Return the response
        return $response;
    }

    /**
     * @return mixed
     */
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
