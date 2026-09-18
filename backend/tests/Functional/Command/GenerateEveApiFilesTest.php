<?php

declare(strict_types=1);

namespace Tests\Functional\Command;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Neucore\Application;
use Neucore\Factory\HttpClientFactoryInterface;
use Neucore\Service\Config;
use Psr\Log\LoggerInterface;
use Tests\Client;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;
use Tests\HttpClientFactory;
use Tests\Logger;

class GenerateEveApiFilesTest extends ConsoleTestCase
{
    private Client $client;

    private HttpClientFactory $clientFactory;

    private Logger $log;

    private Config $config;

    protected function setUp(): void
    {
        $helper = new Helper();
        $helper->emptyDb();
        $this->client = new Client();
        $this->clientFactory = new HttpClientFactory($this->client);
        $this->log = new Logger();
        $this->config = new Config([
            'monolog' => ['path' => Application::ROOT_DIR . '/var/logs'],
            'eve' => Helper::getFullEveConfig(),
            'esi' => [
                'files' => [
                    'esi_rate_limits'     => __DIR__ . '/esi-rate-limits.php',
                    'esi_paths_public'    => __DIR__ . '/esi-paths-public.php',
                    'esi_paths_http_get'  => __DIR__ . '/esi-paths-http-get.json',
                    'esi_paths_http_post' => __DIR__ . '/esi-paths-http-post.json',
                ],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->config['esi']['files'] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testExecute_Success(): void
    {
        $openApiSpec = json_encode([
            'paths' => [
                '/meta/openapi.json' => [],
                '/alliances/{alliance_id}/' => [
                    'get' => [
                        'operationId' => 'getAlliancesAllianceId',
                        'x-rate-limit' => [
                            'group' => 'Alliance',
                            'max-tokens' => 120,
                            'window-size' => 3600,
                        ],
                    ],
                ],
                '/characters/{character_id}/' => [
                    'get' => [
                        'operationId' => 'getCharactersCharacterId',
                        'security' => [['evesso' => []]],
                    ],
                ],
                '/corporations/{corporation_id}/' => [
                    'post' => [
                        'operationId' => 'postCorporations',
                        'x-rate-limit' => [
                            'group' => 'Corporation',
                            'max-tokens' => 60,
                            'window-size' => 60,
                        ],
                    ],
                ],
                '/universe/killmails/{killmail_id}/{killmail_hash}/' => [
                    'get' => [
                        'operationId' => 'getKillmails',
                        'x-rate-limit' => [
                            'group' => 'Killmail',
                            'max-tokens' => 30,
                            'window-size' => 60,
                        ],
                    ],
                ],
            ],
        ]);

        $this->client->setResponse(new Response(200, [], (string) $openApiSpec));

        $output = $this->runConsoleApp('generate-eve-api-files', [], [
            HttpClientFactoryInterface::class => $this->clientFactory,
            LoggerInterface::class => $this->log,
            Config::class => $this->config,
        ]);

        $this->assertStringContainsString('All done.', $output);

        // esi-paths-public.php: paths without security (no get/post security)
        $publicContent = require $this->config['esi']['files']['esi_paths_public'];
        $this->assertIsArray($publicContent);
        $this->assertContains('/alliances/[0-9]+/', $publicContent);
        $this->assertContains('/corporations/[0-9]+/', $publicContent);
        $this->assertContains('/universe/killmails/[0-9]+/[0-9a-fA-F]+/', $publicContent);
        foreach ($publicContent as $path) {
            $this->assertStringNotContainsString('characters', $path);
        }

        // esi-paths-http-get.json: all paths with get method
        $getJson = json_decode((string) file_get_contents($this->config['esi']['files']['esi_paths_http_get']), true);
        $this->assertIsArray($getJson);
        $this->assertContains('/alliances/{alliance_id}/', $getJson);
        $this->assertContains('/characters/{character_id}/', $getJson);
        $this->assertContains('/universe/killmails/{killmail_id}/{killmail_hash}/', $getJson);

        // esi-paths-http-post.json: all paths with post method
        $postJson = json_decode((string) file_get_contents($this->config['esi']['files']['esi_paths_http_post']), true);
        $this->assertIsArray($postJson);
        $this->assertContains('/corporations/{corporation_id}/', $postJson);
        $this->assertNotContains('/alliances/{alliance_id}/', $postJson);

        // esi-rate-limits.php: paths with x-rate-limit (placeholders replaced)
        $rateLimitsContent = require $this->config['esi']['files']['esi_rate_limits'];
        $this->assertIsArray($rateLimitsContent);
        $this->assertArrayHasKey('/alliances/[0-9]+/', $rateLimitsContent);
        $this->assertEquals([
            'group' => 'Alliance',
            'maxTokens' => 120,
            'windowSize' => 3600,
        ], $rateLimitsContent['/alliances/[0-9]+/']['get']);
        $this->assertArrayHasKey('/corporations/[0-9]+/', $rateLimitsContent);
        $this->assertEquals([
            'group' => 'Corporation',
            'maxTokens' => 60,
            'windowSize' => 60,
        ], $rateLimitsContent['/corporations/[0-9]+/']['post']);
        $this->assertArrayHasKey('/universe/killmails/[0-9]+/[0-9a-fA-F]+/', $rateLimitsContent);
        $this->assertEquals([
            'group' => 'Killmail',
            'maxTokens' => 30,
            'windowSize' => 60,
        ], $rateLimitsContent['/universe/killmails/[0-9]+/[0-9a-fA-F]+/']['get']);
    }

    public function testExecute_InvalidJson(): void
    {
        $this->client->setResponse(new Response(200, [], 'not valid json {{{'));

        $output = $this->runConsoleApp('generate-eve-api-files', [], [
            HttpClientFactoryInterface::class => $this->clientFactory,
            LoggerInterface::class => $this->log,
            Config::class => $this->config,
        ]);

        $this->assertStringContainsString('Error decoding openapi.json', $output);
    }

    public function testExecute_EmptyResponse(): void
    {
        $this->client->setResponse(new Response(200, [], ''));

        $output = $this->runConsoleApp('generate-eve-api-files', [], [
            HttpClientFactoryInterface::class => $this->clientFactory,
            LoggerInterface::class => $this->log,
            Config::class => $this->config,
        ]);

        $this->assertStringContainsString('Error reading openapi.json', $output);
    }

    public function testExecute_HttpError(): void
    {
        $this->client->setResponse(new Response(404, [], 'Not Found'));

        $output = $this->runConsoleApp('generate-eve-api-files', [], [
            HttpClientFactoryInterface::class => $this->clientFactory,
            LoggerInterface::class => $this->log,
            Config::class => $this->config,
        ]);

        $this->assertStringContainsString('Error reading openapi.json', $output);
    }

    public function testExecute_ClientException(): void
    {
        $this->client->setMiddleware(function () {
            return function () {
                throw new ConnectException(
                    'Connection refused',
                    new Request('GET', '/test'),
                );
            };
        });

        $output = $this->runConsoleApp('generate-eve-api-files', [], [
            HttpClientFactoryInterface::class => $this->clientFactory,
            LoggerInterface::class => $this->log,
            Config::class => $this->config,
        ]);

        $this->assertStringContainsString('Error reading openapi.json', $output);
    }
}
