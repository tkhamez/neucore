<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use GuzzleHttp\Psr7\Response;
use Neucore\Exception\RuntimeException;
use Neucore\Service\EsiClient;
use Tests\Client;
use Tests\Helper;
use Tests\Logger;
use Tests\Unit\TestCase;

class EsiClientTest extends TestCase
{
    private Helper $helper;

    private Client $httpClient;

    private EsiClient $esiClient;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $this->httpClient = new Client();
        $logger = new Logger();
        $this->esiClient = Helper::getEsiClient($this->httpClient, $logger);

        $this->helper->emptyDb();
    }

    /**
     * @throws \Throwable
     */
    public function testRequest_NoCharacter()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(568420);
        $this->expectExceptionMessage('Character not found.');

        $this->esiClient->request('/latest/characters/102003000/', 'GET', null, 20300400);
    }

    /**
     * @throws \Throwable
     */
    public function testRequest_NoToken()
    {
        $this->helper->addCharacterMain('char name', 20300400, [], [], false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(568421);
        $this->expectExceptionMessage('Character has no valid token.');

        $this->esiClient->request('/latest/characters/102003000/', 'GET', null, 20300400);
    }

    /**
     * @throws \Throwable
     */
    public function testRequest_Ok()
    {
        // Create char with valid, not expired, ESI token.
        $this->helper->addCharacterMain('char name', 20300400, [], [], true, null, time() + 60, true);

        $this->httpClient->setResponse(new Response(200, [], '{"name": "char name", "corporation_id": 20}'));

        $response = $this->esiClient->request('/latest/characters/102003000/', 'GET', null, 20300400);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ['name' => 'char name', 'corporation_id' => 20],
            json_decode($response->getBody()->__toString(), true)
        );
    }
}
