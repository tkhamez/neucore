<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\Group;
use Neucore\Entity\GroupApplication;
use Neucore\Entity\Player;
use Neucore\Repository\AppRepository;
use Neucore\Factory\RepositoryFactory;
use Neucore\Service\AppAuth;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Helper;
use Tests\RequestFactory;

class AppAuthTest extends TestCase
{
    private AppAuth $service;

    private AppRepository $repo;

    protected function setUp(): void
    {
        $helper = new Helper();
        $repositoryFactory = new RepositoryFactory($helper->getObjectManager());
        $this->repo = $repositoryFactory->getAppRepository();

        $this->service = new AppAuth($repositoryFactory, $helper->getObjectManager());
    }

    public function testGetRolesNoAuth(): void
    {
        $roles = $this->service->getRoles($this->getRequest());

        $this->assertSame([], $roles);
    }

    public function testGetRoles(): void
    {
        $h = new Helper();
        $h->emptyDb();
        $appId = $h->addApp('Test App', 'my-test-secret', ['app'])->getId();

        $header = 'Bearer ' . base64_encode($appId . ':my-test-secret');
        $roles = $this->service->getRoles($this->getRequest($header));

        $this->assertSame(['app'], $roles);
    }

    public function testGetAppNoAuth(): void
    {
        $req = RequestFactory::createRequest();
        $app = $this->service->getApp($req);

        $this->assertNull($app);
    }

    public function testGetAppBrokenAuth(): void
    {
        $header = 'Bearer not:b64-encoded';
        $this->assertNull($this->service->getApp($this->getRequest($header)));
    }

    public function testGetAppBrokenAuth2(): void
    {
        $header = 'Bearer ' . base64_encode('no-id');
        $this->assertNull($this->service->getApp($this->getRequest($header)));
    }

    public function testGetAppInvalidPass(): void
    {
        $header = 'Bearer ' . base64_encode('1:invalid-secret');
        $this->assertNull($this->service->getApp($this->getRequest($header)));
    }

    public function testGetApp(): void
    {
        $h = new Helper();
        $h->emptyDb();
        $appId = $h->addApp('Test App', 'my-test-secret', ['app'])->getId();

        $header = 'Bearer ' . base64_encode($appId . ':my-test-secret');
        $app = $this->service->getApp($this->getRequest($header));

        $this->assertSame($appId, $app->getId());
        $this->assertSame('Test App', $app->getName());
    }

    public function testGetCurrentApp(): void
    {
        $h = new Helper();
        $h->emptyDb();
        $appId = $h->addApp('Test App', 'my-test-secret', ['app'])->getId();

        $this->assertNull($this->service->getCurrentApp());

        $header = 'Bearer ' . base64_encode($appId . ':my-test-secret');
        $app = $this->service->getApp($this->getRequest($header));

        $this->assertNotNull($app);
        $this->assertSame($app, $this->service->getCurrentApp());
    }

    public function testGetAppTokenWithNewLine(): void
    {
        $h = new Helper();
        $h->emptyDb();
        $appId = $h->addApp('Test App', 'my-test-secret', ['app'])->getId();

        $header = 'Bearer ' . base64_encode("$appId:my-test-secret\n");
        $app = $this->service->getApp($this->getRequest($header));

        $this->assertSame($appId, $app->getId());
        $this->assertSame('Test App', $app->getName());
    }

    public function testGetAppAuthenticateUpgradesPasswordHash(): void
    {
        $h = new Helper();
        $h->emptyDb();
        $appId = $h->addApp('Test App', 'my-test-secret', ['app'], [], 'md5')->getId();


        $oldHash = $this->repo->find($appId)->getSecret();
        $this->assertStringStartsWith('$1$', $oldHash);

        $header = 'Bearer ' . base64_encode($appId . ':my-test-secret');
        $this->service->getApp($this->getRequest($header));
        $h->getObjectManager()->clear();

        $newHash = $this->repo->find($appId)->getSecret();
        $this->assertStringStartsNotWith('$1$', $newHash);
    }

    public function testIsPlayerAllowed(): void
    {
        $h = new Helper();
        [$appId, , , $playerCorp, $playerGroup, $playerOut, , , , $playerApplied] = $this->createFixture($h);
        $this->authenticate($appId);

        // Player with a character in the app's corporation
        $this->assertTrue($this->service->isPlayerAllowed($playerCorp->getId()));
        // Player that is a member of a group, but the app has no access groups
        $this->assertFalse($this->service->isPlayerAllowed($playerGroup->getId()));
        // Player that applied to a group, but the app has no access groups
        $this->assertFalse($this->service->isPlayerAllowed($playerApplied->getId()));
        // Player without a character in the app's corporation
        $this->assertFalse($this->service->isPlayerAllowed($playerOut->getId()));
    }

    public function testIsPlayerAllowedNoApp(): void
    {
        $h = new Helper();
        [, , , , , $playerOut] = $this->createFixture($h);

        $this->assertFalse($this->service->isPlayerAllowed($playerOut->getId()));
    }

    public function testIsPlayerAllowedNoRestrictions(): void
    {
        $h = new Helper();
        [$appId] = $this->createFixture($h, false);
        $this->authenticate($appId);

        $this->assertTrue($this->service->isPlayerAllowed(1));
        $this->assertTrue($this->service->isPlayerAllowed(2));
    }

    public function testIsPlayerAllowedAccessGroupOnly(): void
    {
        $h = new Helper();
        [$appId, , , $playerCorp, $playerGroup, $playerOut, , , , $playerApplied]
            = $this->createFixture($h, false, true);
        $this->authenticate($appId);

        // Player that is a member of the app's access group
        $this->assertTrue($this->service->isPlayerAllowed($playerGroup->getId()));
        // Player with a pending application for the app's access group
        $this->assertTrue($this->service->isPlayerAllowed($playerApplied->getId()));
        // Player with a character in a corporation, but the app has no corporations
        $this->assertFalse($this->service->isPlayerAllowed($playerCorp->getId()));
        // Player that is not a member of the app's access group and has no application
        $this->assertFalse($this->service->isPlayerAllowed($playerOut->getId()));
    }

    public function testIsPlayerAllowedApplicationStatuses(): void
    {
        $h = new Helper();
        [$appId, , $group, , , , , , , $playerApplied] = $this->createFixture($h, false, true);

        $om = $h->getObjectManager();
        $playerAccepted = (new Player())->setName('P5');
        $applicationAccepted = (new GroupApplication())
            ->setPlayer($playerAccepted)
            ->setGroup($group)
            ->setStatus(GroupApplication::STATUS_ACCEPTED)
            ->setCreated(new \DateTime());
        $playerAccepted->addGroupApplication($applicationAccepted);
        $playerDenied = (new Player())->setName('P6');
        $applicationDenied = (new GroupApplication())
            ->setPlayer($playerDenied)
            ->setGroup($group)
            ->setStatus(GroupApplication::STATUS_DENIED)
            ->setCreated(new \DateTime());
        $playerDenied->addGroupApplication($applicationDenied);
        $om->persist($playerAccepted);
        $om->persist($applicationAccepted);
        $om->persist($playerDenied);
        $om->persist($applicationDenied);
        $om->flush();

        $this->authenticate($appId);

        // The application status is irrelevant: pending, accepted and denied are all counted
        $this->assertTrue($this->service->isPlayerAllowed($playerApplied->getId()));
        $this->assertTrue($this->service->isPlayerAllowed($playerAccepted->getId()));
        $this->assertTrue($this->service->isPlayerAllowed($playerDenied->getId()));
    }

    public function testIsPlayerAllowedCorporationAndAccessGroup(): void
    {
        $h = new Helper();
        [$appId, , , $playerCorp, $playerGroup, $playerOut, , , , $playerApplied]
            = $this->createFixture($h, true, true);
        $this->authenticate($appId);

        // Player with a character in the app's corporation
        $this->assertTrue($this->service->isPlayerAllowed($playerCorp->getId()));
        // Player that is a member of the app's access group
        $this->assertTrue($this->service->isPlayerAllowed($playerGroup->getId()));
        // Player with a pending application for the app's access group
        $this->assertTrue($this->service->isPlayerAllowed($playerApplied->getId()));
        // Player that matches neither restriction
        $this->assertFalse($this->service->isPlayerAllowed($playerOut->getId()));
    }

    public function testIsCharacterAllowed(): void
    {
        $h = new Helper();
        [$appId, , , , , , $charCorp, $charGroup, $charOut, , $charApplied] = $this->createFixture($h);
        $this->authenticate($appId);

        $this->assertTrue($this->service->isCharacterAllowed($charCorp->getId()));
        $this->assertFalse($this->service->isCharacterAllowed($charGroup->getId()));
        $this->assertFalse($this->service->isCharacterAllowed($charOut->getId()));
        // Character of a player that applied to a group, but the app has no access groups
        $this->assertFalse($this->service->isCharacterAllowed($charApplied->getId()));
        $this->assertFalse($this->service->isCharacterAllowed(999)); // Character not found
    }

    public function testIsCharacterAllowedNoApp(): void
    {
        $h = new Helper();
        [, , , , , , , , $charOut] = $this->createFixture($h);

        $this->assertFalse($this->service->isCharacterAllowed($charOut->getId()));
    }

    public function testIsCharacterAllowedNoRestrictions(): void
    {
        $h = new Helper();
        [$appId, , , , , , $charCorp, $charGroup, $charOut] = $this->createFixture($h, false);
        $this->authenticate($appId);

        $this->assertTrue($this->service->isCharacterAllowed($charCorp->getId()));
        $this->assertTrue($this->service->isCharacterAllowed($charGroup->getId()));
        $this->assertTrue($this->service->isCharacterAllowed($charOut->getId()));
    }

    public function testIsCharacterAllowedAccessGroupOnly(): void
    {
        $h = new Helper();
        [$appId, , , , , , $charCorp, $charGroup, $charOut, , $charApplied] = $this->createFixture($h, false, true);
        $this->authenticate($appId);

        // Character of a player that is a member of the app's access group
        $this->assertTrue($this->service->isCharacterAllowed($charGroup->getId()));
        // Character of a player with a pending application for the app's access group
        $this->assertTrue($this->service->isCharacterAllowed($charApplied->getId()));
        // Character of a player that is not a member of the app's access group
        $this->assertFalse($this->service->isCharacterAllowed($charCorp->getId()));
        $this->assertFalse($this->service->isCharacterAllowed($charOut->getId()));
    }

    public function testIsCharacterAllowedCorporationAndAccessGroup(): void
    {
        $h = new Helper();
        [$appId, , , , , , $charCorp, $charGroup, $charOut, , $charApplied] = $this->createFixture($h, true, true);
        $this->authenticate($appId);

        $this->assertTrue($this->service->isCharacterAllowed($charCorp->getId()));
        $this->assertTrue($this->service->isCharacterAllowed($charGroup->getId()));
        $this->assertTrue($this->service->isCharacterAllowed($charApplied->getId()));
        $this->assertFalse($this->service->isCharacterAllowed($charOut->getId()));
    }

    public function testIsCorporationAllowed(): void
    {
        $h = new Helper();
        [$appId, $corp] = $this->createFixture($h);
        $this->authenticate($appId);

        $this->assertTrue($this->service->isCorporationAllowed($corp->getId()));
        $this->assertFalse($this->service->isCorporationAllowed(999));
    }

    public function testIsCorporationAllowedNoApp(): void
    {
        $h = new Helper();
        [, $corp] = $this->createFixture($h);

        $this->assertFalse($this->service->isCorporationAllowed($corp->getId()));
    }

    public function testIsCorporationAllowedNoRestrictions(): void
    {
        $h = new Helper();
        [$appId, $corp] = $this->createFixture($h, false);
        $this->authenticate($appId);

        $this->assertTrue($this->service->isCorporationAllowed($corp->getId()));
    }

    public function testIsCorporationAllowedAccessGroupOnly(): void
    {
        $h = new Helper();
        [$appId, $corp] = $this->createFixture($h, false, true);
        $this->authenticate($appId);

        // The app has access groups but no corporations, so it may not access
        // any corporation data (e.g. member tracking).
        $this->assertFalse($this->service->isCorporationAllowed($corp->getId()));
        $this->assertFalse($this->service->isCorporationAllowed(999));
    }

    public function testFilterAllowedPlayerIds(): void
    {
        $h = new Helper();
        [$appId, , , $playerCorp, $playerGroup, $playerOut, , , , $playerApplied] = $this->createFixture($h);
        $this->authenticate($appId);

        $this->assertSame(
            [$playerCorp->getId()],
            $this->service->filterAllowedPlayerIds([
                $playerCorp->getId(), $playerGroup->getId(), $playerApplied->getId(), $playerOut->getId(),
            ]),
        );
    }

    public function testFilterAllowedPlayerIdsNoPlayers(): void
    {
        $this->assertSame([], $this->service->filterAllowedPlayerIds([]));
    }

    public function testFilterAllowedPlayerIdsNoApp(): void
    {
        $h = new Helper();
        [, , , $playerCorp] = $this->createFixture($h);

        $this->assertSame([], $this->service->filterAllowedPlayerIds([$playerCorp->getId()]));
    }

    public function testFilterAllowedPlayerIdsNoRestrictions(): void
    {
        $h = new Helper();
        [$appId, , , $playerCorp, $playerGroup, $playerOut] = $this->createFixture($h, false);
        $this->authenticate($appId);

        $this->assertSame(
            [$playerCorp->getId(), $playerGroup->getId(), $playerOut->getId()],
            $this->service->filterAllowedPlayerIds([$playerCorp->getId(), $playerGroup->getId(), $playerOut->getId()]),
        );
    }

    public function testFilterAllowedPlayerIdsAccessGroupOnly(): void
    {
        $h = new Helper();
        [$appId, , , $playerCorp, $playerGroup, $playerOut, , , , $playerApplied]
            = $this->createFixture($h, false, true);
        $this->authenticate($appId);

        $this->assertSame(
            [$playerGroup->getId(), $playerApplied->getId()],
            $this->service->filterAllowedPlayerIds([
                $playerCorp->getId(), $playerGroup->getId(), $playerApplied->getId(), $playerOut->getId(),
            ]),
        );
    }

    public function testFilterAllowedPlayerIdsCorporationAndAccessGroup(): void
    {
        $h = new Helper();
        [$appId, , , $playerCorp, $playerGroup, $playerOut, , , , $playerApplied]
            = $this->createFixture($h, true, true);
        $this->authenticate($appId);

        $this->assertSame(
            [$playerCorp->getId(), $playerGroup->getId(), $playerApplied->getId()],
            $this->service->filterAllowedPlayerIds([
                $playerCorp->getId(), $playerGroup->getId(), $playerApplied->getId(), $playerOut->getId(),
            ]),
        );
    }

    public function testFilterAllowedPlayers(): void
    {
        $h = new Helper();
        [$appId, , , $playerCorp, $playerGroup, $playerOut, , , , $playerApplied] = $this->createFixture($h);
        $this->authenticate($appId);

        $this->assertSame(
            [$playerCorp],
            $this->service->filterAllowedPlayers([$playerCorp, $playerGroup, $playerApplied, $playerOut]),
        );
    }

    public function testFilterAllowedPlayersNoApp(): void
    {
        $h = new Helper();
        [, , , $playerCorp] = $this->createFixture($h);

        $this->assertSame([], $this->service->filterAllowedPlayers([$playerCorp]));
    }

    public function testFilterAllowedPlayersNoRestrictions(): void
    {
        $h = new Helper();
        [$appId, , , $playerCorp, $playerGroup, $playerOut] = $this->createFixture($h, false);
        $this->authenticate($appId);

        $this->assertSame(
            [$playerCorp, $playerGroup, $playerOut],
            $this->service->filterAllowedPlayers([$playerCorp, $playerGroup, $playerOut]),
        );
    }

    public function testFilterAllowedPlayersAccessGroupOnly(): void
    {
        $h = new Helper();
        [$appId, , , $playerCorp, $playerGroup, $playerOut, , , , $playerApplied]
            = $this->createFixture($h, false, true);
        $this->authenticate($appId);

        $this->assertSame(
            [$playerGroup, $playerApplied],
            $this->service->filterAllowedPlayers([$playerCorp, $playerGroup, $playerApplied, $playerOut]),
        );
    }

    public function testFilterAllowedPlayersCorporationAndAccessGroup(): void
    {
        $h = new Helper();
        [$appId, , , $playerCorp, $playerGroup, $playerOut, , , , $playerApplied]
            = $this->createFixture($h, true, true);
        $this->authenticate($appId);

        $this->assertSame(
            [$playerCorp, $playerGroup, $playerApplied],
            $this->service->filterAllowedPlayers([$playerCorp, $playerGroup, $playerApplied, $playerOut]),
        );
    }

    public function testFilterAllowedCharacterIds(): void
    {
        $h = new Helper();
        [$appId, , , , , , $charCorp, $charGroup, $charOut, , $charApplied] = $this->createFixture($h);
        $this->authenticate($appId);

        $this->assertSame(
            [$charCorp->getId()],
            $this->service->filterAllowedCharacterIds([
                $charCorp->getId(), $charGroup->getId(), $charApplied->getId(), $charOut->getId(),
            ]),
        );
    }

    public function testFilterAllowedCharacterIdsNoCharacters(): void
    {
        $this->assertSame([], $this->service->filterAllowedCharacterIds([]));
    }

    public function testFilterAllowedCharacterIdsNoApp(): void
    {
        $h = new Helper();
        [, , , , , , , , $charOut] = $this->createFixture($h);

        $this->assertSame([], $this->service->filterAllowedCharacterIds([$charOut->getId()]));
    }

    public function testFilterAllowedCharacterIdsNoRestrictions(): void
    {
        $h = new Helper();
        [$appId, , , , , , $charCorp, $charGroup, $charOut] = $this->createFixture($h, false);
        $this->authenticate($appId);

        $this->assertSame(
            [$charCorp->getId(), $charGroup->getId(), $charOut->getId()],
            $this->service->filterAllowedCharacterIds([$charCorp->getId(), $charGroup->getId(), $charOut->getId()]),
        );
    }

    public function testFilterAllowedCharacterIdsAccessGroupOnly(): void
    {
        $h = new Helper();
        [$appId, , , , , , $charCorp, $charGroup, $charOut, , $charApplied] = $this->createFixture($h, false, true);
        $this->authenticate($appId);

        $this->assertSame(
            [$charGroup->getId(), $charApplied->getId()],
            $this->service->filterAllowedCharacterIds([
                $charCorp->getId(), $charGroup->getId(), $charApplied->getId(), $charOut->getId(),
            ]),
        );
    }

    public function testFilterAllowedCharacterIdsCorporationAndAccessGroup(): void
    {
        $h = new Helper();
        [$appId, , , , , , $charCorp, $charGroup, $charOut, , $charApplied] = $this->createFixture($h, true, true);
        $this->authenticate($appId);

        $this->assertSame(
            [$charCorp->getId(), $charGroup->getId(), $charApplied->getId()],
            $this->service->filterAllowedCharacterIds([
                $charCorp->getId(), $charGroup->getId(), $charApplied->getId(), $charOut->getId(),
            ]),
        );
    }

    public function testFilterAllowedCharacters(): void
    {
        $h = new Helper();
        [$appId, , , , , , $charCorp, $charGroup, $charOut, , $charApplied] = $this->createFixture($h);
        $this->authenticate($appId);

        $this->assertSame(
            [$charCorp],
            $this->service->filterAllowedCharacters([$charCorp, $charGroup, $charApplied, $charOut]),
        );
    }

    public function testFilterAllowedCharactersNoApp(): void
    {
        $h = new Helper();
        [, , , , , , , , $charOut] = $this->createFixture($h);

        $this->assertSame([], $this->service->filterAllowedCharacters([$charOut]));
    }

    public function testFilterAllowedCharactersNoRestrictions(): void
    {
        $h = new Helper();
        [$appId, , , , , , $charCorp, $charGroup, $charOut] = $this->createFixture($h, false);
        $this->authenticate($appId);

        $this->assertSame(
            [$charCorp, $charGroup, $charOut],
            $this->service->filterAllowedCharacters([$charCorp, $charGroup, $charOut]),
        );
    }

    public function testFilterAllowedCharactersAccessGroupOnly(): void
    {
        $h = new Helper();
        [$appId, , , , , , $charCorp, $charGroup, $charOut, , $charApplied] = $this->createFixture($h, false, true);
        $this->authenticate($appId);

        $this->assertSame(
            [$charGroup, $charApplied],
            $this->service->filterAllowedCharacters([$charCorp, $charGroup, $charApplied, $charOut]),
        );
    }

    public function testFilterAllowedCharactersCorporationAndAccessGroup(): void
    {
        $h = new Helper();
        [$appId, , , , , , $charCorp, $charGroup, $charOut, , $charApplied] = $this->createFixture($h, true, true);
        $this->authenticate($appId);

        $this->assertSame(
            [$charCorp, $charGroup, $charApplied],
            $this->service->filterAllowedCharacters([$charCorp, $charGroup, $charApplied, $charOut]),
        );
    }

    /**
     * Creates a corporation, a group and four players:
     * - $playerCorp: has a character in the corporation, is not a member of the group
     * - $playerGroup: is a member of the group, has a character outside the corporation
     * - $playerOut: has no character in the corporation and is not a member of the group
     * - $playerApplied: has a pending application for the group
     * The app is assigned the corporation and/or the group depending on the flags.
     *
     * @return array{0: int, 1: Corporation, 2: Group, 3: Player, 4: Player, 5: Player,
     *               6: Character, 7: Character, 8: Character, 9: Player, 10: Character}
     */
    private function createFixture(Helper $h, bool $appCorp = true, bool $appGroup = false): array
    {
        $h->emptyDb();

        $corp = (new Corporation())->setId(10)->setTicker('t1')->setName('corp 1');
        $group = (new Group())->setName('g1');

        $playerCorp = (new Player())->setName('P1');
        $charCorp = (new Character())->setId(1)->setName('C1')->setPlayer($playerCorp)->setCorporation($corp);
        $playerCorp->addCharacter($charCorp);

        $playerGroup = (new Player())->setName('P2');
        $charGroup = (new Character())->setId(2)->setName('C2')->setPlayer($playerGroup);
        $playerGroup->addCharacter($charGroup);
        $playerGroup->addGroup($group);

        $playerOut = (new Player())->setName('P3');
        $charOut = (new Character())->setId(3)->setName('C3')->setPlayer($playerOut);
        $playerOut->addCharacter($charOut);

        $playerApplied = (new Player())->setName('P4');
        $charApplied = (new Character())->setId(4)->setName('C4')->setPlayer($playerApplied);
        $playerApplied->addCharacter($charApplied);
        $application = (new GroupApplication())
            ->setPlayer($playerApplied)
            ->setGroup($group)
            ->setCreated(new \DateTime()); // status is pending by default
        $playerApplied->addGroupApplication($application);

        $om = $h->getObjectManager();
        $om->persist($corp);
        $om->persist($group);
        $om->persist($playerCorp);
        $om->persist($playerGroup);
        $om->persist($playerOut);
        $om->persist($playerApplied);
        $om->persist($application);
        $om->persist($charCorp);
        $om->persist($charGroup);
        $om->persist($charOut);
        $om->persist($charApplied);

        $app = $h->addApp('A1', 's1', ['app']);
        if ($appCorp) {
            $app->addCorporation($corp);
        }
        if ($appGroup) {
            $app->addAccessGroup($group);
        }
        $om->flush();

        return [
            $app->getId(), $corp, $group, $playerCorp, $playerGroup, $playerOut,
            $charCorp, $charGroup, $charOut, $playerApplied, $charApplied,
        ];
    }

    private function authenticate(int $appId): void
    {
        $this->service->getApp($this->getRequest('Bearer ' . base64_encode($appId . ':s1')));
    }

    private function getRequest(?string $authHeader = null): ServerRequestInterface
    {
        $request = RequestFactory::createRequest();
        if ($authHeader !== null) {
            $request = $request->withHeader('Authorization', $authHeader);
        }
        return $request;
    }
}
