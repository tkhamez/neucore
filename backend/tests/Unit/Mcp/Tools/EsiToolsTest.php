<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp\Tools;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Neucore\Entity\Character;
use Neucore\Entity\EsiToken;
use Neucore\Entity\EveLogin;
use Neucore\Factory\RepositoryFactory;
use Neucore\Mcp\ResponseBuilder;
use Neucore\Mcp\Tools\EsiTools;
use Neucore\Repository\CharacterRepository;
use Neucore\Service\EsiClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class EsiToolsTest extends TestCase
{
    private EsiTools $tools;

    private RepositoryFactory&MockObject $repositoryFactory;

    private EsiClient&MockObject $esiClient;

    private ResponseBuilder&MockObject $responseBuilder;

    protected function setUp(): void
    {
        $this->repositoryFactory = $this->createMock(RepositoryFactory::class);
        $this->esiClient = $this->createMock(EsiClient::class);
        $this->responseBuilder = $this->createMock(ResponseBuilder::class);

        $this->tools = new EsiTools(
            $this->repositoryFactory,
            $this->esiClient,
            $this->responseBuilder,
        );
    }

    #[Test]
    public function testEsiRequest_success(): void
    {
        $character = $this->createCharacterWithToken(valid: true);
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects(self::once())
            ->method('getContents')
            ->willReturn(json_encode(['result' => 'data']));
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeader')->willReturn([]);

        $this->repositoryFactory
            ->method('getCharacterRepository')
            ->willReturn($this->createCharacterRepository($character));

        $this->esiClient
            ->expects(self::once())
            ->method('request')
            ->with(
                '/universe/stations/1',
                'GET',
                null,
                12345,
                EveLogin::NAME_DEFAULT,
                false,
                self::callback(fn($date) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1),
            )
            ->willReturn($response);

        $this->responseBuilder
            ->expects(self::once())
            ->method('success')
            ->with(['result' => 'data'], [])
            ->willReturn([
                'success' => true,
                'statusCode' => 200,
                'body' => ['result' => 'data'],
                'headers' => [],
            ]);

        $result = $this->tools->esiRequest(12345, '/universe/stations/1');

        self::assertTrue($result['success']);
        self::assertSame(200, $result['statusCode']);
        self::assertSame(['result' => 'data'], $result['body']);
    }

    #[Test]
    public function testEsiRequest_success_withCustomMethodAndBody(): void
    {
        $character = $this->createCharacterWithToken(valid: true);
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn(json_encode(['created' => true]));
        $response->method('getStatusCode')->willReturn(201);
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeader')->willReturn([]);

        $this->repositoryFactory
            ->method('getCharacterRepository')
            ->willReturn($this->createCharacterRepository($character));

        $this->esiClient
            ->expects(self::once())
            ->method('request')
            ->with(
                '/corporations/1/blueprints',
                'POST',
                (string) json_encode(['count' => 5]),
                12345,
                EveLogin::NAME_DEFAULT,
                false,
                self::callback(fn($date) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1),
            )
            ->willReturn($response);

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body, $headers) => [
                'success' => true,
                'statusCode' => 201,
                'body' => $body,
                'headers' => $headers,
            ]);

        $result = $this->tools->esiRequest(12345, '/corporations/1/blueprints', 'POST', (string) json_encode(['count' => 5]));

        self::assertTrue($result['success']);
        self::assertSame(201, $result['statusCode']);
        self::assertSame(['created' => true], $result['body']);
    }

    #[Test]
    public function testEsiRequest_success_withHeaders(): void
    {
        $character = $this->createCharacterWithToken(valid: true);
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn(json_encode(['data']));
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeader')
            ->willReturnCallback(function ($headerName) {
                $headerValues = [
                    EsiClient::HEADER_PAGES => ['5'],
                    EsiClient::HEADER_COMPATIBILITY_DATE => ['2025-01-01'],
                    EsiClient::HEADER_WARNING => ['1 test warning'],
                    EsiClient::HEADER_RATE_LIMIT_GROUP => ['tranquility'],
                    EsiClient::HEADER_RATE_LIMIT_REMAINING => ['149'],
                    EsiClient::HEADER_RATE_LIMIT_LIMIT => ['150/15m'],
                    EsiClient::HEADER_RATE_LIMIT_USED => ['1'],
                    EsiClient::HEADER_RETRY_AFTER => ['30'],
                    EsiClient::HEADER_ERROR_LIMIT_REMAIN => ['99'],
                    EsiClient::HEADER_ERROR_LIMIT_RESET => ['45'],
                    EsiClient::HEADER_BEFORE => ['prev-token'],
                    EsiClient::HEADER_AFTER => ['next-token'],
                ];
                return $headerValues[$headerName] ?? [];
            });

        $this->repositoryFactory
            ->method('getCharacterRepository')
            ->willReturn($this->createCharacterRepository($character));

        $this->esiClient
            ->expects(self::once())
            ->method('request')
            ->willReturn($response);

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(function ($body, $headers) {
                return [
                    'success' => true,
                    'statusCode' => 200,
                    'body' => $body,
                    'headers' => $headers,
                ];
            });

        $result = $this->tools->esiRequest(12345, '/universe/stations/1');

        self::assertTrue($result['success']);
        self::assertArrayHasKey(EsiClient::HEADER_PAGES, $result['headers']);
        self::assertSame('5', $result['headers'][EsiClient::HEADER_PAGES]);
        self::assertArrayHasKey(EsiClient::HEADER_COMPATIBILITY_DATE, $result['headers']);
        self::assertArrayHasKey(EsiClient::HEADER_WARNING, $result['headers']);
        self::assertArrayHasKey(EsiClient::HEADER_RATE_LIMIT_GROUP, $result['headers']);
        self::assertArrayHasKey(EsiClient::HEADER_RATE_LIMIT_REMAINING, $result['headers']);
        self::assertArrayHasKey(EsiClient::HEADER_RATE_LIMIT_LIMIT, $result['headers']);
        self::assertArrayHasKey(EsiClient::HEADER_RATE_LIMIT_USED, $result['headers']);
        self::assertArrayHasKey(EsiClient::HEADER_RETRY_AFTER, $result['headers']);
        self::assertArrayHasKey(EsiClient::HEADER_ERROR_LIMIT_REMAIN, $result['headers']);
        self::assertArrayHasKey(EsiClient::HEADER_ERROR_LIMIT_RESET, $result['headers']);
        self::assertArrayHasKey(EsiClient::HEADER_BEFORE, $result['headers']);
        self::assertArrayHasKey(EsiClient::HEADER_AFTER, $result['headers']);
    }

    #[Test]
    public function testEsiRequest_success_withCustomCompatibilityDate(): void
    {
        $character = $this->createCharacterWithToken(valid: true);
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn(json_encode([]));
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeader')->willReturn([]);

        $this->repositoryFactory
            ->method('getCharacterRepository')
            ->willReturn($this->createCharacterRepository($character));

        $this->esiClient
            ->expects(self::once())
            ->method('request')
            ->with('/universe/stations/1', 'GET', null, 12345, EveLogin::NAME_DEFAULT, false, '2025-06-15')
            ->willReturn($response);

        $this->responseBuilder
            ->method('success')
            ->willReturn(['success' => true, 'statusCode' => 200, 'body' => [], 'headers' => []]);

        $result = $this->tools->esiRequest(12345, '/universe/stations/1', 'GET', null, '2025-06-15');

        self::assertTrue($result['success']);
    }

    #[Test]
    public function testEsiRequest_invalidCharacterId(): void
    {
        $this->responseBuilder
            ->expects(self::once())
            ->method('error')
            ->with(0, 'Character ID must be a positive integer')
            ->willReturn([
                'success' => false,
                'statusCode' => 0,
                'error' => 'Character ID must be a positive integer',
                'headers' => [],
            ]);

        $result = $this->tools->esiRequest(0, '/universe/stations/1');

        self::assertFalse($result['success']);
        self::assertSame(0, $result['statusCode']);
        self::assertSame('Character ID must be a positive integer', $result['error']);
    }

    #[Test]
    public function testEsiRequest_negativeCharacterId(): void
    {
        $this->responseBuilder
            ->expects(self::once())
            ->method('error')
            ->with(0, 'Character ID must be a positive integer')
            ->willReturn([
                'success' => false,
                'statusCode' => 0,
                'error' => 'Character ID must be a positive integer',
                'headers' => [],
            ]);

        $result = $this->tools->esiRequest(-1, '/universe/stations/1');

        self::assertFalse($result['success']);
        self::assertSame('Character ID must be a positive integer', $result['error']);
    }

    #[Test]
    public function testEsiRequest_characterNotFound(): void
    {
        $this->repositoryFactory
            ->method('getCharacterRepository')
            ->willReturn($this->createCharacterRepository(null));

        $this->responseBuilder
            ->expects(self::once())
            ->method('error')
            ->with(0, 'Character not found')
            ->willReturn([
                'success' => false,
                'statusCode' => 0,
                'error' => 'Character not found',
                'headers' => [],
            ]);

        $result = $this->tools->esiRequest(99999, '/universe/stations/1');

        self::assertFalse($result['success']);
        self::assertSame('Character not found', $result['error']);
    }

    #[Test]
    public function testEsiRequest_noValidToken(): void
    {
        $character = $this->createCharacterWithToken(valid: false);
        $this->repositoryFactory
            ->method('getCharacterRepository')
            ->willReturn($this->createCharacterRepository($character));

        $this->responseBuilder
            ->expects(self::once())
            ->method('error')
            ->with(0, 'Character has no valid ESI token for the default login')
            ->willReturn([
                'success' => false,
                'statusCode' => 0,
                'error' => 'Character has no valid ESI token for the default login',
                'headers' => [],
            ]);

        $result = $this->tools->esiRequest(12345, '/universe/stations/1');

        self::assertFalse($result['success']);
        self::assertSame('Character has no valid ESI token for the default login', $result['error']);
    }

    #[Test]
    public function testEsiRequest_noTokenAtAll(): void
    {
        $character = $this->createMock(Character::class);
        $character->expects(self::once())
            ->method('getEsiToken')
            ->with(EveLogin::NAME_DEFAULT)
            ->willReturn(null);

        $this->repositoryFactory
            ->method('getCharacterRepository')
            ->willReturn($this->createCharacterRepository($character));

        $this->responseBuilder
            ->expects(self::once())
            ->method('error')
            ->with(0, 'Character has no valid ESI token for the default login')
            ->willReturn([
                'success' => false,
                'statusCode' => 0,
                'error' => 'Character has no valid ESI token for the default login',
                'headers' => [],
            ]);

        $result = $this->tools->esiRequest(12345, '/universe/stations/1');

        self::assertFalse($result['success']);
        self::assertSame('Character has no valid ESI token for the default login', $result['error']);
    }

    #[Test]
    public function testEsiRequest_clientException(): void
    {
        $character = $this->createCharacterWithToken(valid: true);
        $exception = new ConnectException('Connection refused', $this->createMock(Request::class));

        $this->repositoryFactory
            ->method('getCharacterRepository')
            ->willReturn($this->createCharacterRepository($character));

        $this->esiClient
            ->expects(self::once())
            ->method('request')
            ->willReturn($this->createMock(ResponseInterface::class))
            ->willThrowException($exception);

        $this->responseBuilder
            ->expects(self::once())
            ->method('error')
            ->with(0, 'ESI request failed: Connection refused')
            ->willReturn([
                'success' => false,
                'statusCode' => 0,
                'error' => 'ESI request failed: Connection refused',
                'headers' => [],
            ]);

        $result = $this->tools->esiRequest(12345, '/universe/stations/1');

        self::assertFalse($result['success']);
        self::assertStringStartsWith('ESI request failed: Connection refused', $result['error']);
    }

    #[Test]
    public function testEsiRequest_serverError(): void
    {
        $character = $this->createCharacterWithToken(valid: true);
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn('Internal Server Error');
        $response->method('getStatusCode')->willReturn(500);
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeader')->willReturn([]);

        $this->repositoryFactory
            ->method('getCharacterRepository')
            ->willReturn($this->createCharacterRepository($character));

        $this->esiClient
            ->expects(self::once())
            ->method('request')
            ->willReturn($response);

        $this->responseBuilder
            ->expects(self::once())
            ->method('error')
            ->with(500, 'ESI error (500): Internal Server Error', [])
            ->willReturn([
                'success' => false,
                'statusCode' => 500,
                'error' => 'ESI error (500): Internal Server Error',
                'headers' => [],
            ]);

        $result = $this->tools->esiRequest(12345, '/universe/stations/1');

        self::assertFalse($result['success']);
        self::assertSame(500, $result['statusCode']);
        self::assertStringStartsWith('ESI error (500): Internal Server Error', $result['error']);
    }

    #[Test]
    public function testEsiRequest_clientError(): void
    {
        $character = $this->createCharacterWithToken(valid: true);
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn('Not found');
        $response->method('getStatusCode')->willReturn(404);
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeader')->willReturn([]);

        $this->repositoryFactory
            ->method('getCharacterRepository')
            ->willReturn($this->createCharacterRepository($character));

        $this->esiClient
            ->expects(self::once())
            ->method('request')
            ->willReturn($response);

        $this->responseBuilder
            ->expects(self::once())
            ->method('error')
            ->with(404, 'ESI error (404): Not found', [])
            ->willReturn([
                'success' => false,
                'statusCode' => 404,
                'error' => 'ESI error (404): Not found',
                'headers' => [],
            ]);

        $result = $this->tools->esiRequest(12345, '/universe/stations/99999');

        self::assertFalse($result['success']);
        self::assertSame(404, $result['statusCode']);
        self::assertSame('ESI error (404): Not found', $result['error']);
    }

    #[Test]
    public function testEsiRequest_invalidJsonResponse(): void
    {
        $character = $this->createCharacterWithToken(valid: true);
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn('not valid json {{{');
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeader')->willReturn([]);

        $this->repositoryFactory
            ->method('getCharacterRepository')
            ->willReturn($this->createCharacterRepository($character));

        $this->esiClient
            ->expects(self::once())
            ->method('request')
            ->willReturn($response);

        $this->responseBuilder
            ->expects(self::once())
            ->method('error')
            ->with(200, 'Failed to decode body.', [])
            ->willReturn([
                'success' => false,
                'statusCode' => 200,
                'error' => 'Failed to decode body.',
                'headers' => [],
            ]);

        $result = $this->tools->esiRequest(12345, '/universe/stations/1');

        self::assertFalse($result['success']);
        self::assertSame(200, $result['statusCode']);
        self::assertSame('Failed to decode body.', $result['error']);
    }

    #[Test]
    public function testEsiRequest_emptyBodyResponse(): void
    {
        $character = $this->createCharacterWithToken(valid: true);
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('getContents')->willReturn('');
        $response->method('getStatusCode')->willReturn(204);
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeader')->willReturn([]);

        $this->repositoryFactory
            ->method('getCharacterRepository')
            ->willReturn($this->createCharacterRepository($character));

        $this->esiClient
            ->expects(self::once())
            ->method('request')
            ->willReturn($response);

        $this->responseBuilder
            ->method('error')
            ->willReturnCallback(fn($code, $msg, $headers) => [
                'success' => false,
                'statusCode' => $code,
                'error' => $msg,
                'headers' => $headers,
            ]);

        // Empty string decodes to null, but json_decode('') returns null with JSON_ERROR_NONE
        // Actually, json_decode('') returns null with error JSON_ERROR_SYNTAX
        // Let me just verify this test works with the actual behavior
        $result = $this->tools->esiRequest(12345, '/universe/stations/1');

        // Empty body should fail to decode
        self::assertFalse($result['success']);
        self::assertSame('Failed to decode body.', $result['error']);
    }

    private function createCharacterWithToken(bool $valid): Character
    {
        $character = $this->createMock(Character::class);
        $token = $this->createMock(EsiToken::class);
        $token->method('getValidToken')->willReturn($valid);
        $character->expects(self::once())
            ->method('getEsiToken')
            ->with(EveLogin::NAME_DEFAULT)
            ->willReturn($token);
        return $character;
    }

    private function createCharacterRepository(?Character $character): CharacterRepository
    {
        $repository = $this->createMock(CharacterRepository::class);
        $repository->expects(self::once())
            ->method('find')
            ->willReturn($character);
        return $repository;
    }
}
