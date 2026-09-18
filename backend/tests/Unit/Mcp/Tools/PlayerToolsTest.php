<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp\Tools;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Neucore\Data\ServiceAccount;
use Neucore\Entity\Alliance;
use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Mcp\ResponseBuilder;
use Neucore\Mcp\Tools\PlayerTools;
use Neucore\Repository\AllianceRepository;
use Neucore\Repository\CharacterRepository;
use Neucore\Repository\CorporationRepository;
use Neucore\Repository\GroupRepository;
use Neucore\Repository\PlayerRepository;
use Neucore\Service\PluginService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlayerToolsTest extends TestCase
{
    private PlayerTools $tools;

    private RepositoryFactory&MockObject $repositoryFactory;

    private ResponseBuilder&MockObject $responseBuilder;

    private PluginService&MockObject $pluginService;

    protected function setUp(): void
    {
        $this->repositoryFactory = $this->createMock(RepositoryFactory::class);
        $this->responseBuilder = $this->createMock(ResponseBuilder::class);
        $this->pluginService = $this->createMock(PluginService::class);

        $this->tools = new PlayerTools(
            $this->repositoryFactory,
            $this->responseBuilder,
            $this->pluginService,
        );
    }

    #[Test]
    public function testGetPlayer_success(): void
    {
        $player = $this->createMock(Player::class);
        $player->method('jsonSerialize')->willReturn([
            'id' => 1,
            'name' => 'Test Player',
            'status' => 'active',
            'roles' => [],
            'characters' => [],
            'groups' => [],
            'managerGroups' => [],
            'managerApps' => [],
        ]);

        $this->repositoryFactory
            ->method('getPlayerRepository')
            ->willReturn($this->createPlayerRepository($player));

        $this->responseBuilder
            ->expects(self::once())
            ->method('success')
            ->with([
                'id' => 1,
                'name' => 'Test Player',
                'status' => 'active',
                'roles' => [],
                'characters' => [],
                'groups' => [],
                'managerGroups' => [],
                'managerApps' => [],
            ])
            ->willReturn([
                'success' => true,
                'statusCode' => 200,
                'body' => ['id' => 1, 'name' => 'Test Player'],
                'headers' => [],
            ]);

        $result = $this->tools->getPlayer(1);

        self::assertTrue($result['success']);
        self::assertSame('Test Player', $result['body']['name']);
    }

    #[Test]
    public function testGetPlayer_notFound(): void
    {
        $this->repositoryFactory
            ->method('getPlayerRepository')
            ->willReturn($this->createPlayerRepository(null));

        $this->responseBuilder
            ->expects(self::once())
            ->method('error')
            ->with(0, 'Player not found')
            ->willReturn([
                'success' => false,
                'statusCode' => 0,
                'error' => 'Player not found',
                'headers' => [],
            ]);

        $result = $this->tools->getPlayer(99999);

        self::assertFalse($result['success']);
        self::assertSame('Player not found', $result['error']);
    }

    #[Test]
    public function testFindPlayers_success(): void
    {
        $player1 = $this->createMock(Player::class);
        $player2 = $this->createMock(Player::class);
        $player1->method('jsonSerialize')->with(true)->willReturn(['id' => 1, 'name' => 'Test Player']);
        $player2->method('jsonSerialize')->with(true)->willReturn(['id' => 2, 'name' => 'Another Player']);

        $repository = $this->createMock(PlayerRepository::class);
        $query = $this->createMock(Query::class);
        $qb = $this->createMock(QueryBuilder::class);

        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([$player1, $player2]);

        $repository->method('createQueryBuilder')->with('p')->willReturn($qb);

        $this->repositoryFactory
            ->method('getPlayerRepository')
            ->willReturn($repository);

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->findPlayers('Test');

        self::assertTrue($result['success']);
        self::assertCount(2, $result['body']);
        self::assertSame('Test Player', $result['body'][0]['name']);
        self::assertSame('Another Player', $result['body'][1]['name']);
    }

    #[Test]
    public function testFindPlayers_emptyResult(): void
    {
        $repository = $this->createMock(PlayerRepository::class);
        $query = $this->createMock(Query::class);
        $qb = $this->createMock(QueryBuilder::class);

        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([]);

        $repository->method('createQueryBuilder')->with('p')->willReturn($qb);

        $this->repositoryFactory
            ->method('getPlayerRepository')
            ->willReturn($repository);

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->findPlayers('Nonexistent');

        self::assertTrue($result['success']);
        self::assertEmpty($result['body']);
    }

    #[Test]
    public function testFindPlayers_tooShortName(): void
    {
        $this->responseBuilder
            ->expects(self::once())
            ->method('error')
            ->with(0, 'Name must be at least 3 characters long')
            ->willReturn([
                'success' => false,
                'statusCode' => 0,
                'error' => 'Name must be at least 3 characters long',
                'headers' => [],
            ]);

        $result = $this->tools->findPlayers('A');

        self::assertFalse($result['success']);
        self::assertSame('Name must be at least 3 characters long', $result['error']);
    }

    #[Test]
    public function testFindCharacters_success(): void
    {
        $character1 = $this->createMock(Character::class);
        $character2 = $this->createMock(Character::class);
        $character1->method('jsonSerialize')
            ->with(true, true, false, false, true, true)
            ->willReturn([
                'id' => 12345,
                'name' => 'Test Character',
                'corporation' => ['id' => 101, 'name' => 'Test Corp'],
                'playerId' => 1,
                'main' => true,
            ]);
        $character2->method('jsonSerialize')
            ->with(true, true, false, false, true, true)
            ->willReturn([
                'id' => 67890,
                'name' => 'Another Character',
                'corporation' => null,
                'playerId' => 2,
                'main' => false,
            ]);

        $repository = $this->createMock(CharacterRepository::class);
        $query = $this->createMock(Query::class);
        $qb = $this->createMock(QueryBuilder::class);

        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([$character1, $character2]);

        $repository->method('createQueryBuilder')->with('c')->willReturn($qb);

        $this->repositoryFactory
            ->method('getCharacterRepository')
            ->willReturn($repository);

        $this->responseBuilder
            ->expects(self::once())
            ->method('success')
            ->with([
                [
                    'id' => 12345,
                    'name' => 'Test Character',
                    'corporation' => ['id' => 101, 'name' => 'Test Corp'],
                    'playerId' => 1,
                    'main' => true,
                ],
                [
                    'id' => 67890,
                    'name' => 'Another Character',
                    'corporation' => null,
                    'playerId' => 2,
                    'main' => false,
                ],
            ])
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->findCharacters('Test');

        self::assertTrue($result['success']);
        self::assertSame([
            [
                'id' => 12345,
                'name' => 'Test Character',
                'corporation' => ['id' => 101, 'name' => 'Test Corp'],
                'playerId' => 1,
                'main' => true,
            ],
            [
                'id' => 67890,
                'name' => 'Another Character',
                'corporation' => null,
                'playerId' => 2,
                'main' => false,
            ],
        ], $result['body']);
    }

    #[Test]
    public function testFindCharacters_emptyResult(): void
    {
        $repository = $this->createMock(CharacterRepository::class);
        $query = $this->createMock(Query::class);
        $qb = $this->createMock(QueryBuilder::class);

        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $query->method('getResult')->willReturn([]);

        $repository->method('createQueryBuilder')->with('c')->willReturn($qb);

        $this->repositoryFactory
            ->method('getCharacterRepository')
            ->willReturn($repository);

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->findCharacters('Nonexistent');

        self::assertTrue($result['success']);
        self::assertEmpty($result['body']);
    }

    #[Test]
    public function testFindCharacters_tooShortName(): void
    {
        $this->responseBuilder
            ->expects(self::once())
            ->method('error')
            ->with(0, 'Name must be at least 3 characters long')
            ->willReturn([
                'success' => false,
                'statusCode' => 0,
                'error' => 'Name must be at least 3 characters long',
                'headers' => [],
            ]);

        $result = $this->tools->findCharacters('Ab');

        self::assertFalse($result['success']);
        self::assertSame('Name must be at least 3 characters long', $result['error']);
    }

    #[Test]
    public function testGetCharacter_success(): void
    {
        $character = $this->createMock(Character::class);
        $character->method('jsonSerialize')
            ->with(true, true, false, false, true, true)
            ->willReturn([
                'id' => 12345,
                'name' => 'Test Character',
                'corporation' => null,
            ]);

        $this->repositoryFactory
            ->method('getCharacterRepository')
            ->willReturn($this->createCharacterRepository($character));

        $this->responseBuilder
            ->expects(self::once())
            ->method('success')
            ->with([
                'id' => 12345,
                'name' => 'Test Character',
                'corporation' => null,
            ])
            ->willReturn([
                'success' => true,
                'statusCode' => 200,
                'body' => ['id' => 12345, 'name' => 'Test Character'],
                'headers' => [],
            ]);

        $result = $this->tools->getCharacter(12345);

        self::assertTrue($result['success']);
        self::assertSame('Test Character', $result['body']['name']);
    }

    #[Test]
    public function testGetCharacter_notFound(): void
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

        $result = $this->tools->getCharacter(99999);

        self::assertFalse($result['success']);
        self::assertSame('Character not found', $result['error']);
    }

    #[Test]
    public function testGetCorporation_success(): void
    {
        $corporation = $this->createMock(Corporation::class);
        $corporation->method('jsonSerialize')->willReturn([
            'id' => 101,
            'name' => 'Test Corporation',
            'ticker' => 'TEST',
            'alliance' => null,
        ]);

        $this->repositoryFactory
            ->method('getCorporationRepository')
            ->willReturn($this->createCorporationRepository($corporation));

        $this->responseBuilder
            ->expects(self::once())
            ->method('success')
            ->with([
                'id' => 101,
                'name' => 'Test Corporation',
                'ticker' => 'TEST',
                'alliance' => null,
            ])
            ->willReturn([
                'success' => true,
                'statusCode' => 200,
                'body' => ['id' => 101, 'name' => 'Test Corporation'],
                'headers' => [],
            ]);

        $result = $this->tools->getCorporation(101);

        self::assertTrue($result['success']);
        self::assertSame('Test Corporation', $result['body']['name']);
    }

    #[Test]
    public function testGetCorporation_notFound(): void
    {
        $this->repositoryFactory
            ->method('getCorporationRepository')
            ->willReturn($this->createCorporationRepository(null));

        $this->responseBuilder
            ->expects(self::once())
            ->method('error')
            ->with(0, 'Corporation not found')
            ->willReturn([
                'success' => false,
                'statusCode' => 0,
                'error' => 'Corporation not found',
                'headers' => [],
            ]);

        $result = $this->tools->getCorporation(99999);

        self::assertFalse($result['success']);
        self::assertSame('Corporation not found', $result['error']);
    }

    #[Test]
    public function testGetAlliance_success(): void
    {
        $alliance = $this->createMock(Alliance::class);
        $alliance->method('jsonSerialize')->willReturn([
            'id' => 201,
            'name' => 'Test Alliance',
            'ticker' => 'TA',
        ]);

        $this->repositoryFactory
            ->method('getAllianceRepository')
            ->willReturn($this->createAllianceRepository($alliance));

        $this->responseBuilder
            ->expects(self::once())
            ->method('success')
            ->with([
                'id' => 201,
                'name' => 'Test Alliance',
                'ticker' => 'TA',
            ])
            ->willReturn([
                'success' => true,
                'statusCode' => 200,
                'body' => ['id' => 201, 'name' => 'Test Alliance'],
                'headers' => [],
            ]);

        $result = $this->tools->getAlliance(201);

        self::assertTrue($result['success']);
        self::assertSame('Test Alliance', $result['body']['name']);
    }

    #[Test]
    public function testGetAlliance_notFound(): void
    {
        $this->repositoryFactory
            ->method('getAllianceRepository')
            ->willReturn($this->createAllianceRepository(null));

        $this->responseBuilder
            ->expects(self::once())
            ->method('error')
            ->with(0, 'Alliance not found')
            ->willReturn([
                'success' => false,
                'statusCode' => 0,
                'error' => 'Alliance not found',
                'headers' => [],
            ]);

        $result = $this->tools->getAlliance(99999);

        self::assertFalse($result['success']);
        self::assertSame('Alliance not found', $result['error']);
    }

    #[Test]
    public function testGetGroups_success(): void
    {
        $player = $this->createMock(Player::class);
        $group1 = $this->createMock(Group::class);
        $group2 = $this->createMock(Group::class);
        $group1->method('jsonSerialize')->with(true)->willReturn(['id' => 1, 'name' => 'Group 1']);
        $group2->method('jsonSerialize')->with(true)->willReturn(['id' => 2, 'name' => 'Group 2']);
        $player->method('getGroups')->willReturn([$group1, $group2]);

        $this->repositoryFactory
            ->method('getPlayerRepository')
            ->willReturn($this->createPlayerRepository($player));

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->getGroups(1);

        self::assertTrue($result['success']);
        self::assertCount(2, $result['body']);
        self::assertSame('Group 1', $result['body'][0]['name']);
        self::assertSame('Group 2', $result['body'][1]['name']);
    }

    #[Test]
    public function testGetGroups_emptyGroups(): void
    {
        $player = $this->createMock(Player::class);
        $player->method('getGroups')->willReturn([]);

        $this->repositoryFactory
            ->method('getPlayerRepository')
            ->willReturn($this->createPlayerRepository($player));

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->getGroups(1);

        self::assertTrue($result['success']);
        self::assertEmpty($result['body']);
    }

    #[Test]
    public function testGetGroups_playerNotFound(): void
    {
        $this->repositoryFactory
            ->method('getPlayerRepository')
            ->willReturn($this->createPlayerRepository(null));

        $this->responseBuilder
            ->expects(self::once())
            ->method('error')
            ->with(0, 'Player not found')
            ->willReturn([
                'success' => false,
                'statusCode' => 0,
                'error' => 'Player not found',
                'headers' => [],
            ]);

        $result = $this->tools->getGroups(99999);

        self::assertFalse($result['success']);
        self::assertSame('Player not found', $result['error']);
    }

    #[Test]
    public function testGetGroupMembers_success(): void
    {
        $player1 = $this->createMock(Player::class);
        $player2 = $this->createMock(Player::class);
        $player1->method('getId')->willReturn(1);
        $player1->method('getName')->willReturn('Player One');
        $player2->method('getId')->willReturn(2);
        $player2->method('getName')->willReturn('Player Two');

        $group = $this->createMock(Group::class);
        $group->method('getPlayers')->willReturn([$player1, $player2]);

        $this->repositoryFactory
            ->method('getGroupRepository')
            ->willReturn($this->createGroupRepository($group));

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->getGroupMembers(1);

        self::assertTrue($result['success']);
        self::assertSame([
            ['id' => 1, 'name' => 'Player One'],
            ['id' => 2, 'name' => 'Player Two'],
        ], $result['body']);
    }

    #[Test]
    public function testGetGroupMembers_empty(): void
    {
        $group = $this->createMock(Group::class);
        $group->method('getPlayers')->willReturn([]);

        $this->repositoryFactory
            ->method('getGroupRepository')
            ->willReturn($this->createGroupRepository($group));

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->getGroupMembers(1);

        self::assertTrue($result['success']);
        self::assertEmpty($result['body']);
    }

    #[Test]
    public function testGetGroupMembers_notFound(): void
    {
        $this->repositoryFactory
            ->method('getGroupRepository')
            ->willReturn($this->createGroupRepository(null));

        $this->responseBuilder
            ->expects(self::once())
            ->method('error')
            ->with(0, 'Group not found')
            ->willReturn([
                'success' => false,
                'statusCode' => 0,
                'error' => 'Group not found',
                'headers' => [],
            ]);

        $result = $this->tools->getGroupMembers(99999);

        self::assertFalse($result['success']);
        self::assertSame('Group not found', $result['error']);
    }

    private function createPlayerRepository(?Player $player): PlayerRepository
    {
        $repository = $this->createMock(PlayerRepository::class);
        $repository->method('find')->willReturn($player);
        return $repository;
    }

    private function createCharacterRepository(?Character $character): CharacterRepository
    {
        $repository = $this->createMock(CharacterRepository::class);
        $repository->method('find')->willReturn($character);
        return $repository;
    }

    private function createCorporationRepository(?Corporation $corporation): CorporationRepository
    {
        $repository = $this->createMock(CorporationRepository::class);
        $repository->method('find')->willReturn($corporation);
        return $repository;
    }

    private function createAllianceRepository(?Alliance $alliance): AllianceRepository
    {
        $repository = $this->createMock(AllianceRepository::class);
        $repository->method('find')->willReturn($alliance);
        return $repository;
    }

    #[Test]
    public function testGetServiceAccounts_success(): void
    {
        $player = $this->createMock(Player::class);

        $account1 = new ServiceAccount(1, 'S1', 101, 'u1', 'Active', 'Account 1');
        $account2 = new ServiceAccount(2, 'S2', 102, 'u2', 'Pending', 'Account 2');

        $this->pluginService
            ->method('getActiveServiceAccounts')
            ->willReturn([$account1, $account2]);

        $this->repositoryFactory
            ->method('getPlayerRepository')
            ->willReturn($this->createPlayerRepository($player));

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->getServiceAccounts(1);

        self::assertTrue($result['success']);
        self::assertEquals([
            ['serviceId' => 1, 'serviceName' => 'S1', 'characterId' => 101, 'username' => 'u1', 'status' => 'Active', 'name' => 'Account 1'],
            ['serviceId' => 2, 'serviceName' => 'S2', 'characterId' => 102, 'username' => 'u2', 'status' => 'Pending', 'name' => 'Account 2'],
        ], $result['body']);
    }

    #[Test]
    public function testGetServiceAccounts_empty(): void
    {
        $player = $this->createMock(Player::class);

        $this->pluginService
            ->method('getActiveServiceAccounts')
            ->willReturn([]);

        $this->repositoryFactory
            ->method('getPlayerRepository')
            ->willReturn($this->createPlayerRepository($player));

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->getServiceAccounts(1);

        self::assertTrue($result['success']);
        self::assertEmpty($result['body']);
    }

    #[Test]
    public function testGetServiceAccounts_playerNotFound(): void
    {
        $this->repositoryFactory
            ->method('getPlayerRepository')
            ->willReturn($this->createPlayerRepository(null));

        $this->responseBuilder
            ->expects(self::once())
            ->method('error')
            ->with(0, 'Player not found')
            ->willReturn([
                'success' => false,
                'statusCode' => 0,
                'error' => 'Player not found',
                'headers' => [],
            ]);

        $result = $this->tools->getServiceAccounts(99999);

        self::assertFalse($result['success']);
        self::assertSame('Player not found', $result['error']);
    }

    private function createGroupRepository(?Group $group): GroupRepository
    {
        $repository = $this->createMock(GroupRepository::class);
        $repository->method('find')->willReturn($group);
        return $repository;
    }
}
