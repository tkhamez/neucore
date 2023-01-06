<?php
/** @noinspection DuplicatedCode */

declare(strict_types=1);

namespace Tests\Unit\Plugin\Core;

use GuzzleHttp\Psr7\Response;
use Neucore\Data\EsiErrorLimit;
use Neucore\Factory\RepositoryFactory;
use Neucore\Plugin\Core\EsiClient;
use Neucore\Plugin\Core\EsiClientInterface;
use Neucore\Plugin\Exception;
use Neucore\Service\ObjectManager;
use Neucore\Storage\StorageInterface;
use Neucore\Storage\SystemVariableStorage;
use Neucore\Storage\Variables;
use PHPUnit\Framework\TestCase;
use Tests\Client;
use Tests\Helper;
use Tests\HttpClientFactory;
use Tests\Logger;

class EsiClientTest extends TestCase
{
    private Helper $helper;

    private Client $httpClient;

    private StorageInterface $storage;

    private EsiClient $esiClient;

    protected function setUp(): void
    {
        $this->helper = new Helper();
        $om = $this->helper->getObjectManager();
        $logger = new Logger();
        $this->httpClient = new Client();
        $this->storage = new SystemVariableStorage(new RepositoryFactory($om), new ObjectManager($om, $logger));
        $this->esiClient = new EsiClient(
            Helper::getEsiClientService($this->httpClient, $logger),
            new HttpClientFactory($this->httpClient),
            $this->storage,
        );
        $this->helper->emptyDb();
    }

    public function testGetErrorLimitRemaining()
    {
        $this->assertSame(15, $this->esiClient->getErrorLimitRemaining());
    }

    public function testRequest_ErrorLimit()
    {
        $time = time();
        $this->storage->set(Variables::ESI_ERROR_LIMIT, (string)json_encode(new EsiErrorLimit($time, 10, 45)));

        $this->expectException(Exception::class);
        $this->expectExceptionCode($time + 45);
        $this->expectExceptionMessage(EsiClientInterface::ERROR_ERROR_LIMIT_REACHED);

        $this->esiClient->request('/latest/characters/102003000/', 'GET', null, 20300400);
    }

    public function testRequest_RateLimit()
    {
        $time = time();
        $this->storage->set(Variables::ESI_RATE_LIMIT, (string)($time + 20));

        $this->expectException(Exception::class);
        $this->expectExceptionCode($time + 20);
        $this->expectExceptionMessage(EsiClientInterface::ERROR_RATE_LIMIT_REACHED);

        $this->esiClient->request('/latest/characters/102003000/', 'GET', null, 20300400);
    }

    public function testRequest_Throttled()
    {
        $time = time();
        $this->storage->set(Variables::ESI_THROTTLED, (string)($time + 50));

        $this->expectException(Exception::class);
        $this->expectExceptionCode($time + 50);
        $this->expectExceptionMessage(EsiClientInterface::ERROR_TEMPORARILY_THROTTLED);

        $this->esiClient->request('/latest/characters/102003000/', 'GET', null, 20300400);
    }

    public function testRequest_CharNotFound()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage(EsiClientInterface::ERROR_CHARACTER_NOT_FOUND);

        $this->esiClient->request('/latest/characters/102003000/', 'GET', null, 20300400);
    }

    public function testRequest_TokenInvalid()
    {
        $this->helper->addCharacterMain('char name', 20300400, [], [], false);

        $this->expectException(Exception::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage(EsiClientInterface::ERROR_INVALID_TOKEN);

        $this->esiClient->request('/latest/characters/102003000/', 'GET', null, 20300400);
    }

    /**
     * @throws Exception
     */
    public function testRequest_OK()
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
