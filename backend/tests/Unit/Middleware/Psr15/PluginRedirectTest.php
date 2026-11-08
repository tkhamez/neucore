<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware\Psr15;

use Neucore\Data\PluginConfigurationFile;
use Neucore\Entity\Plugin;
use Neucore\Middleware\Psr15\PluginRedirect;
use Neucore\Service\PluginService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Tests\RequestFactory;
use Tests\RequestHandler;

class PluginRedirectTest extends TestCase
{
    private PluginRedirect $middleware;

    private PluginService&MockObject $pluginService;

    protected function setUp(): void
    {
        $this->pluginService = $this->createMock(PluginService::class);
        $this->middleware = new PluginRedirect(new ResponseFactory(), $this->pluginService);
    }

    private function mockPlugin(?Plugin $plugin): void
    {
        $this->pluginService->method('getPlugin')->willReturn($plugin);
    }

    /**
     * @param string[] $redirectRequestNames
     */
    private function createPlugin(array $redirectRequestNames): Plugin
    {
        $configuration = new PluginConfigurationFile();
        $configuration->redirectRequestNames = $redirectRequestNames;
        return (new Plugin())->setConfigurationFile($configuration);
    }

    public function testConstant()
    {
        $this->assertSame('/plugin', PluginRedirect::PLUGIN_PREFIX);
    }

    public function testPassesThroughNonPluginPath()
    {
        $request = RequestFactory::createRequest('GET', '/other-path');
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($request, $handler->getRequest());
    }

    public function testPassesThroughWrongPrefix()
    {
        $request = RequestFactory::createRequest('GET', '/plugins/17/callback');
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($request, $handler->getRequest());
    }

    public function testPassesThroughMissingId()
    {
        $request = RequestFactory::createRequest('GET', '/plugin');
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($request, $handler->getRequest());
    }

    public function testPassesThroughMissingRequestName()
    {
        $request = RequestFactory::createRequest('GET', '/plugin/17');
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($request, $handler->getRequest());
    }

    public function testPassesThroughUnknownPlugin()
    {
        $this->mockPlugin(null);
        $request = RequestFactory::createRequest('GET', '/plugin/17/callback');
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($request, $handler->getRequest());
    }

    public function testPassesThroughPluginWithoutConfigurationFile()
    {
        $this->mockPlugin(new Plugin());
        $request = RequestFactory::createRequest('GET', '/plugin/17/callback');
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($request, $handler->getRequest());
    }

    public function testPassesThroughEmptyRedirectRequestNames()
    {
        $this->mockPlugin($this->createPlugin([]));
        $request = RequestFactory::createRequest('GET', '/plugin/17/callback');
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($request, $handler->getRequest());
    }

    public function testPassesThroughRequestNameNotInList()
    {
        $this->mockPlugin($this->createPlugin(['other']));
        $request = RequestFactory::createRequest('GET', '/plugin/17/callback');
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($request, $handler->getRequest());
    }

    public function testPassesThroughRedirectedFlag()
    {
        $this->mockPlugin($this->createPlugin(['callback']));
        $request = RequestFactory::createRequest('GET', '/plugin/17/callback?redirected=1');
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($request, $handler->getRequest());
    }

    public function testMetaRefreshWithoutQuery()
    {
        $this->mockPlugin($this->createPlugin(['callback']));
        $request = RequestFactory::createRequest('GET', '/plugin/17/callback');
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/html', $response->getHeader('Content-Type')[0]);
        $this->assertStringContainsString(
            '<meta http-equiv="refresh" content="0; url=/plugin/17/callback?redirected=1">',
            (string) $response->getBody(),
        );
    }

    public function testMetaRefreshWithQuery()
    {
        $this->mockPlugin($this->createPlugin(['callback']));
        $request = RequestFactory::createRequest('GET', '/plugin/17/callback?foo=bar&baz=qux');
        $handler = new RequestHandler();

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(
            '<meta http-equiv="refresh" content="0; url=/plugin/17/callback?foo=bar&amp;baz=qux&amp;redirected=1">',
            (string) $response->getBody(),
        );
    }
}
