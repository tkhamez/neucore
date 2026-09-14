<?php

declare(strict_types=1);

namespace Tests\Unit\Mcp\Tools;

use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Factory\RepositoryFactory;
use Neucore\Mcp\ResponseBuilder;
use Neucore\Mcp\Tools\TrackingTools;
use Neucore\Repository\CorporationMemberRepository;
use Neucore\Repository\CorporationRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TrackingToolsTest extends TestCase
{
    private TrackingTools $tools;

    private RepositoryFactory&MockObject $repositoryFactory;

    private ResponseBuilder&MockObject $responseBuilder;

    protected function setUp(): void
    {
        $this->repositoryFactory = $this->createMock(RepositoryFactory::class);
        $this->responseBuilder = $this->createMock(ResponseBuilder::class);

        $this->tools = new TrackingTools(
            $this->repositoryFactory,
            $this->responseBuilder,
        );
    }

    #[Test]
    public function testGetMemberTracking_success_noFilter(): void
    {
        $corporation = $this->createMock(Corporation::class);
        $member1 = $this->createMock(CorporationMember::class);
        $member2 = $this->createMock(CorporationMember::class);
        $member1->method('jsonSerialize')->willReturn(['id' => 1, 'name' => 'Member 1']);
        $member2->method('jsonSerialize')->willReturn(['id' => 2, 'name' => 'Member 2']);

        $repository = $this->createMock(CorporationMemberRepository::class);
        $repository->expects(self::once())
            ->method('resetCriteria');
        $repository->expects(self::once())
            ->method('findMatching')
            ->with(100)
            ->willReturn([$member1, $member2]);

        $this->repositoryFactory
            ->method('getCorporationRepository')
            ->willReturn($this->createCorporationRepository($corporation));
        $this->repositoryFactory
            ->method('getCorporationMemberRepository')
            ->willReturn($repository);

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->getMemberTracking(100);

        self::assertTrue($result['success']);
        self::assertCount(2, $result['body']);
        self::assertSame('Member 1', $result['body'][0]['name']);
    }

    #[Test]
    public function testGetMemberTracking_success_withActiveFilter(): void
    {
        $corporation = $this->createMock(Corporation::class);

        $repository = $this->createMock(CorporationMemberRepository::class);
        $repository->expects(self::once())
            ->method('resetCriteria');
        $repository->expects(self::once())
            ->method('setActive')
            ->with(30);
        $repository->expects(self::once())
            ->method('findMatching')
            ->with(100)
            ->willReturn([]);

        $this->repositoryFactory
            ->method('getCorporationRepository')
            ->willReturn($this->createCorporationRepository($corporation));
        $this->repositoryFactory
            ->method('getCorporationMemberRepository')
            ->willReturn($repository);

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->getMemberTracking(100, ['active' => 30]);

        self::assertTrue($result['success']);
        self::assertEmpty($result['body']);
    }

    #[Test]
    public function testGetMemberTracking_success_withInactiveFilter(): void
    {
        $corporation = $this->createMock(Corporation::class);

        $repository = $this->createMock(CorporationMemberRepository::class);
        $repository->expects(self::once())
            ->method('resetCriteria');
        $repository->expects(self::once())
            ->method('setInactive')
            ->with(90);
        $repository->expects(self::once())
            ->method('findMatching')
            ->with(100)
            ->willReturn([]);

        $this->repositoryFactory
            ->method('getCorporationRepository')
            ->willReturn($this->createCorporationRepository($corporation));
        $this->repositoryFactory
            ->method('getCorporationMemberRepository')
            ->willReturn($repository);

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->getMemberTracking(100, ['inactive' => 90]);

        self::assertTrue($result['success']);
        self::assertEmpty($result['body']);
    }

    #[Test]
    public function testGetMemberTracking_success_withAccountFilter(): void
    {
        $corporation = $this->createMock(Corporation::class);

        $repository = $this->createMock(CorporationMemberRepository::class);
        $repository->expects(self::once())
            ->method('resetCriteria');
        $repository->expects(self::once())
            ->method('setAccount')
            ->with(true);
        $repository->expects(self::once())
            ->method('findMatching')
            ->with(100)
            ->willReturn([]);

        $this->repositoryFactory
            ->method('getCorporationRepository')
            ->willReturn($this->createCorporationRepository($corporation));
        $this->repositoryFactory
            ->method('getCorporationMemberRepository')
            ->willReturn($repository);

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->getMemberTracking(100, ['account' => true]);

        self::assertTrue($result['success']);
        self::assertEmpty($result['body']);
    }

    #[Test]
    public function testGetMemberTracking_success_withAccountFilterFalse(): void
    {
        $corporation = $this->createMock(Corporation::class);

        $repository = $this->createMock(CorporationMemberRepository::class);
        $repository->expects(self::once())
            ->method('resetCriteria');
        $repository->expects(self::once())
            ->method('setAccount')
            ->with(false);
        $repository->expects(self::once())
            ->method('findMatching')
            ->with(100)
            ->willReturn([]);

        $this->repositoryFactory
            ->method('getCorporationRepository')
            ->willReturn($this->createCorporationRepository($corporation));
        $this->repositoryFactory
            ->method('getCorporationMemberRepository')
            ->willReturn($repository);

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->getMemberTracking(100, ['account' => false]);

        self::assertTrue($result['success']);
        self::assertEmpty($result['body']);
    }

    #[Test]
    public function testGetMemberTracking_success_withAccountFilterNull(): void
    {
        $corporation = $this->createMock(Corporation::class);

        $repository = $this->createMock(CorporationMemberRepository::class);
        $repository->expects(self::once())
            ->method('resetCriteria');
        $repository->expects(self::never())
            ->method('setAccount');
        $repository->expects(self::once())
            ->method('findMatching')
            ->with(100)
            ->willReturn([]);

        $this->repositoryFactory
            ->method('getCorporationRepository')
            ->willReturn($this->createCorporationRepository($corporation));
        $this->repositoryFactory
            ->method('getCorporationMemberRepository')
            ->willReturn($repository);

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->getMemberTracking(100, ['account' => null]);

        self::assertTrue($result['success']);
        self::assertEmpty($result['body']);
    }

    #[Test]
    public function testGetMemberTracking_success_withTokenStatusFilter(): void
    {
        $corporation = $this->createMock(Corporation::class);

        $repository = $this->createMock(CorporationMemberRepository::class);
        $repository->expects(self::once())
            ->method('resetCriteria');
        $repository->expects(self::once())
            ->method('setTokenStatus')
            ->with(1);
        $repository->expects(self::once())
            ->method('findMatching')
            ->with(100)
            ->willReturn([]);

        $this->repositoryFactory
            ->method('getCorporationRepository')
            ->willReturn($this->createCorporationRepository($corporation));
        $this->repositoryFactory
            ->method('getCorporationMemberRepository')
            ->willReturn($repository);

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->getMemberTracking(100, ['tokenStatus' => 1]);

        self::assertTrue($result['success']);
        self::assertEmpty($result['body']);
    }

    #[Test]
    public function testGetMemberTracking_success_withTokenChangedFilter(): void
    {
        $corporation = $this->createMock(Corporation::class);

        $repository = $this->createMock(CorporationMemberRepository::class);
        $repository->expects(self::once())
            ->method('resetCriteria');
        $repository->expects(self::once())
            ->method('setTokenChanged')
            ->with(14);
        $repository->expects(self::once())
            ->method('findMatching')
            ->with(100)
            ->willReturn([]);

        $this->repositoryFactory
            ->method('getCorporationRepository')
            ->willReturn($this->createCorporationRepository($corporation));
        $this->repositoryFactory
            ->method('getCorporationMemberRepository')
            ->willReturn($repository);

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->getMemberTracking(100, ['tokenChanged' => 14]);

        self::assertTrue($result['success']);
        self::assertEmpty($result['body']);
    }

    #[Test]
    public function testGetMemberTracking_success_withMailCountFilter(): void
    {
        $corporation = $this->createMock(Corporation::class);

        $repository = $this->createMock(CorporationMemberRepository::class);
        $repository->expects(self::once())
            ->method('resetCriteria');
        $repository->expects(self::once())
            ->method('setMailCount')
            ->with(3);
        $repository->expects(self::once())
            ->method('findMatching')
            ->with(100)
            ->willReturn([]);

        $this->repositoryFactory
            ->method('getCorporationRepository')
            ->willReturn($this->createCorporationRepository($corporation));
        $this->repositoryFactory
            ->method('getCorporationMemberRepository')
            ->willReturn($repository);

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->getMemberTracking(100, ['mailCount' => 3]);

        self::assertTrue($result['success']);
        self::assertEmpty($result['body']);
    }

    #[Test]
    public function testGetMemberTracking_success_withMultipleFilters(): void
    {
        $corporation = $this->createMock(Corporation::class);

        $repository = $this->createMock(CorporationMemberRepository::class);
        $repository->expects(self::once())
            ->method('resetCriteria');
        $repository->expects(self::once())
            ->method('setActive')
            ->with(30);
        $repository->expects(self::once())
            ->method('setInactive')
            ->with(90);
        $repository->expects(self::once())
            ->method('setAccount')
            ->with(true);
        $repository->expects(self::once())
            ->method('setTokenStatus')
            ->with(1);
        $repository->expects(self::once())
            ->method('setTokenChanged')
            ->with(14);
        $repository->expects(self::once())
            ->method('setMailCount')
            ->with(3);
        $repository->expects(self::once())
            ->method('findMatching')
            ->with(100)
            ->willReturn([]);

        $this->repositoryFactory
            ->method('getCorporationRepository')
            ->willReturn($this->createCorporationRepository($corporation));
        $this->repositoryFactory
            ->method('getCorporationMemberRepository')
            ->willReturn($repository);

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->getMemberTracking(100, [
            'active' => 30,
            'inactive' => 90,
            'account' => true,
            'tokenStatus' => 1,
            'tokenChanged' => 14,
            'mailCount' => 3,
        ]);

        self::assertTrue($result['success']);
        self::assertEmpty($result['body']);
    }

    #[Test]
    public function testGetMemberTracking_corporationNotFound(): void
    {
        $this->repositoryFactory
            ->method('getCorporationRepository')
            ->willReturn($this->createCorporationRepository(null));

        $repository = $this->createMock(CorporationMemberRepository::class);
        $repository->expects(self::never())
            ->method('resetCriteria');
        $this->repositoryFactory
            ->method('getCorporationMemberRepository')
            ->willReturn($repository);

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

        $result = $this->tools->getMemberTracking(99999);

        self::assertFalse($result['success']);
        self::assertSame('Corporation not found', $result['error']);
    }

    #[Test]
    public function testGetMemberTracking_emptyFilterDoesNotSetCriteria(): void
    {
        $corporation = $this->createMock(Corporation::class);

        $repository = $this->createMock(CorporationMemberRepository::class);
        $repository->expects(self::once())
            ->method('resetCriteria');
        $repository->expects(self::never())
            ->method('setActive');
        $repository->expects(self::never())
            ->method('setInactive');
        $repository->expects(self::never())
            ->method('setAccount');
        $repository->expects(self::never())
            ->method('setTokenStatus');
        $repository->expects(self::never())
            ->method('setTokenChanged');
        $repository->expects(self::never())
            ->method('setMailCount');
        $repository->expects(self::once())
            ->method('findMatching')
            ->with(100)
            ->willReturn([]);

        $this->repositoryFactory
            ->method('getCorporationRepository')
            ->willReturn($this->createCorporationRepository($corporation));
        $this->repositoryFactory
            ->method('getCorporationMemberRepository')
            ->willReturn($repository);

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->getMemberTracking(100);

        self::assertTrue($result['success']);
    }

    #[Test]
    public function testGetMemberTracking_zeroValuesIgnored(): void
    {
        $corporation = $this->createMock(Corporation::class);

        $repository = $this->createMock(CorporationMemberRepository::class);
        $repository->expects(self::once())
            ->method('resetCriteria');
        // 0 values should be treated as falsy and not set
        $repository->expects(self::never())
            ->method('setActive');
        $repository->expects(self::never())
            ->method('setInactive');
        $repository->expects(self::never())
            ->method('setTokenStatus');
        $repository->expects(self::never())
            ->method('setTokenChanged');
        $repository->expects(self::never())
            ->method('setMailCount');
        $repository->expects(self::once())
            ->method('findMatching')
            ->with(100)
            ->willReturn([]);

        $this->repositoryFactory
            ->method('getCorporationRepository')
            ->willReturn($this->createCorporationRepository($corporation));
        $this->repositoryFactory
            ->method('getCorporationMemberRepository')
            ->willReturn($repository);

        $this->responseBuilder
            ->method('success')
            ->willReturnCallback(fn($body) => [
                'success' => true,
                'statusCode' => 200,
                'body' => $body,
                'headers' => [],
            ]);

        $result = $this->tools->getMemberTracking(100, [
            'active' => 0,
            'inactive' => 0,
            'tokenStatus' => 0,
            'tokenChanged' => 0,
            'mailCount' => 0,
        ]);

        self::assertTrue($result['success']);
    }

    #[Test]
    public function testGetMemberTracking_resultsSerialized(): void
    {
        $corporation = $this->createMock(Corporation::class);
        $member1 = $this->createMock(CorporationMember::class);
        $member2 = $this->createMock(CorporationMember::class);
        $member1->method('jsonSerialize')->willReturn(['id' => 1, 'name' => 'Member 1']);
        $member2->method('jsonSerialize')->willReturn(['id' => 2, 'name' => 'Member 2']);

        $repository = $this->createMock(CorporationMemberRepository::class);
        $repository->expects(self::once())
            ->method('resetCriteria');
        $repository->expects(self::once())
            ->method('findMatching')
            ->with(100)
            ->willReturn([$member1, $member2]);

        $this->repositoryFactory
            ->method('getCorporationRepository')
            ->willReturn($this->createCorporationRepository($corporation));
        $this->repositoryFactory
            ->method('getCorporationMemberRepository')
            ->willReturn($repository);

        $this->responseBuilder
            ->expects(self::once())
            ->method('success')
            ->with([
                ['id' => 1, 'name' => 'Member 1'],
                ['id' => 2, 'name' => 'Member 2'],
            ])
            ->willReturn([
                'success' => true,
                'statusCode' => 200,
                'body' => [['id' => 1, 'name' => 'Member 1'], ['id' => 2, 'name' => 'Member 2']],
                'headers' => [],
            ]);

        $result = $this->tools->getMemberTracking(100);

        self::assertTrue($result['success']);
        self::assertCount(2, $result['body']);
    }

    private function createCorporationRepository(?Corporation $corporation): CorporationRepository
    {
        $repository = $this->createMock(CorporationRepository::class);
        $repository->method('find')->willReturn($corporation);
        return $repository;
    }
}
