<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
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

    public function testGetRolesNoAuth()
    {
        $roles = $this->service->getRoles($this->getRequest());

        $this->assertSame([], $roles);
    }

    public function testGetRoles()
    {
        $h = new Helper();
        $h->emptyDb();
        $appId = $h->addApp('Test App', 'my-test-secret', ['app'])->getId();

        $header = 'Bearer ' . base64_encode($appId . ':my-test-secret');
        $roles = $this->service->getRoles($this->getRequest($header));

        $this->assertSame(['app'], $roles);
    }

    public function testGetAppNoAuth()
    {
        $req = RequestFactory::createRequest();
        $app = $this->service->getApp($req);

        $this->assertNull($app);
    }

    public function testGetAppBrokenAuth()
    {
        $header = 'Bearer not:b64-encoded';
        $this->assertNull($this->service->getApp($this->getRequest($header)));
    }

    public function testGetAppBrokenAuth2()
    {
        $header = 'Bearer ' . base64_encode('no-id');
        $this->assertNull($this->service->getApp($this->getRequest($header)));
    }

    public function testGetAppInvalidPass()
    {
        $header = 'Bearer ' . base64_encode('1:invalid-secret');
        $this->assertNull($this->service->getApp($this->getRequest($header)));
    }

    public function testGetApp()
    {
        $h = new Helper();
        $h->emptyDb();
        $appId = $h->addApp('Test App', 'my-test-secret', ['app'])->getId();

        $header = 'Bearer ' . base64_encode($appId . ':my-test-secret');
        $app = $this->service->getApp($this->getRequest($header));

        $this->assertSame($appId, $app->getId());
        $this->assertSame('Test App', $app->getName());
    }

    public function testGetAppTokenWithNewLine()
    {
        $h = new Helper();
        $h->emptyDb();
        $appId = $h->addApp('Test App', 'my-test-secret', ['app'])->getId();

        $header = 'Bearer ' . base64_encode("$appId:my-test-secret\n");
        $app = $this->service->getApp($this->getRequest($header));

        $this->assertSame($appId, $app->getId());
        $this->assertSame('Test App', $app->getName());
    }

    public function testGetAppAuthenticateUpgradesPasswordHash()
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

    public function testIsPlayerAllowed()
    {
        $h = new Helper();
        [$appId, , $playerIn, $playerOut] = $this->createFixture($h);
        $this->authenticate($appId);

        // Player with a character in the app's corporation
        $this->assertTrue($this->service->isPlayerAllowed($playerIn->getId()));
        // Player without a character in the app's corporation
        $this->assertFalse($this->service->isPlayerAllowed($playerOut->getId()));
    }

    public function testIsPlayerAllowedNoApp()
    {
        $h = new Helper();
        [, , , $playerOut] = $this->createFixture($h);

        $this->assertFalse($this->service->isPlayerAllowed($playerOut->getId()));
    }

    public function testIsPlayerAllowedNoCorporations()
    {
        $h = new Helper();
        [$appId] = $this->createFixture($h, false);
        $this->authenticate($appId);

        $this->assertTrue($this->service->isPlayerAllowed(1));
        $this->assertTrue($this->service->isPlayerAllowed(2));
    }

    public function testIsCharacterAllowed()
    {
        $h = new Helper();
        [$appId, , , , $charIn, $charOut] = $this->createFixture($h);
        $this->authenticate($appId);

        $this->assertTrue($this->service->isCharacterAllowed($charIn->getId()));
        $this->assertFalse($this->service->isCharacterAllowed($charOut->getId()));
        $this->assertFalse($this->service->isCharacterAllowed(999)); // Character not found
    }

    public function testIsCharacterAllowedNoApp()
    {
        $h = new Helper();
        [, , , , , $charOut] = $this->createFixture($h);

        $this->assertFalse($this->service->isCharacterAllowed($charOut->getId()));
    }

    public function testIsCharacterAllowedNoCorporations()
    {
        $h = new Helper();
        [$appId, , , , , $charOut] = $this->createFixture($h, false);
        $this->authenticate($appId);

        $this->assertTrue($this->service->isCharacterAllowed($charOut->getId()));
    }

    public function testIsCorporationAllowed()
    {
        $h = new Helper();
        [$appId, $corp] = $this->createFixture($h);
        $this->authenticate($appId);

        $this->assertTrue($this->service->isCorporationAllowed($corp->getId()));
        $this->assertFalse($this->service->isCorporationAllowed(999));
    }

    public function testIsCorporationAllowedNoApp()
    {
        $h = new Helper();
        [, $corp] = $this->createFixture($h);

        $this->assertFalse($this->service->isCorporationAllowed($corp->getId()));
    }

    public function testIsCorporationAllowedNoCorporations()
    {
        $h = new Helper();
        [$appId, $corp] = $this->createFixture($h, false);
        $this->authenticate($appId);

        $this->assertTrue($this->service->isCorporationAllowed($corp->getId()));
    }

    public function testFilterAllowedPlayerIds()
    {
        $h = new Helper();
        [$appId, , $playerIn, $playerOut] = $this->createFixture($h);
        $this->authenticate($appId);

        $this->assertSame(
            [$playerIn->getId()],
            $this->service->filterAllowedPlayerIds([$playerIn->getId(), $playerOut->getId()]),
        );
    }

    public function testFilterAllowedPlayerIdsNoPlayers()
    {
        $this->assertSame([], $this->service->filterAllowedPlayerIds([]));
    }

    public function testFilterAllowedPlayerIdsNoApp()
    {
        $h = new Helper();
        [, , $playerIn] = $this->createFixture($h);

        $this->assertSame([], $this->service->filterAllowedPlayerIds([$playerIn->getId()]));
    }

    public function testFilterAllowedPlayerIdsNoCorporations()
    {
        $h = new Helper();
        [$appId, , $playerIn, $playerOut] = $this->createFixture($h, false);
        $this->authenticate($appId);

        $this->assertSame(
            [$playerIn->getId(), $playerOut->getId()],
            $this->service->filterAllowedPlayerIds([$playerIn->getId(), $playerOut->getId()]),
        );
    }

    public function testFilterAllowedPlayers()
    {
        $h = new Helper();
        [$appId, , $playerIn, $playerOut] = $this->createFixture($h);
        $this->authenticate($appId);

        $this->assertSame(
            [$playerIn],
            $this->service->filterAllowedPlayers([$playerIn, $playerOut]),
        );
    }

    public function testFilterAllowedPlayersNoApp()
    {
        $h = new Helper();
        [, , $playerIn] = $this->createFixture($h);

        $this->assertSame([], $this->service->filterAllowedPlayers([$playerIn]));
    }

    public function testFilterAllowedPlayersNoCorporations()
    {
        $h = new Helper();
        [$appId, , $playerIn, $playerOut] = $this->createFixture($h, false);
        $this->authenticate($appId);

        $this->assertSame(
            [$playerIn, $playerOut],
            $this->service->filterAllowedPlayers([$playerIn, $playerOut]),
        );
    }

    public function testFilterAllowedCharacterIds()
    {
        $h = new Helper();
        [$appId, , , , $charIn, $charOut] = $this->createFixture($h);
        $this->authenticate($appId);

        $this->assertSame(
            [$charIn->getId()],
            $this->service->filterAllowedCharacterIds([$charIn->getId(), $charOut->getId()]),
        );
    }

    public function testFilterAllowedCharacterIdsNoCharacters()
    {
        $this->assertSame([], $this->service->filterAllowedCharacterIds([]));
    }

    public function testFilterAllowedCharacterIdsNoApp()
    {
        $h = new Helper();
        [, , , , , $charOut] = $this->createFixture($h);

        $this->assertSame([], $this->service->filterAllowedCharacterIds([$charOut->getId()]));
    }

    public function testFilterAllowedCharacterIdsNoCorporations()
    {
        $h = new Helper();
        [$appId, , , , $charIn, $charOut] = $this->createFixture($h, false);
        $this->authenticate($appId);

        $this->assertSame(
            [$charIn->getId(), $charOut->getId()],
            $this->service->filterAllowedCharacterIds([$charIn->getId(), $charOut->getId()]),
        );
    }

    public function testFilterAllowedCharacters()
    {
        $h = new Helper();
        [$appId, , , , $charIn, $charOut] = $this->createFixture($h);
        $this->authenticate($appId);

        $this->assertSame(
            [$charIn],
            $this->service->filterAllowedCharacters([$charIn, $charOut]),
        );
    }

    public function testFilterAllowedCharactersNoApp()
    {
        $h = new Helper();
        [, , , , , $charOut] = $this->createFixture($h);

        $this->assertSame([], $this->service->filterAllowedCharacters([$charOut]));
    }

    public function testFilterAllowedCharactersNoCorporations()
    {
        $h = new Helper();
        [$appId, , , , $charIn, $charOut] = $this->createFixture($h, false);
        $this->authenticate($appId);

        $this->assertSame(
            [$charIn, $charOut],
            $this->service->filterAllowedCharacters([$charIn, $charOut]),
        );
    }

    /**
     * Creates a corporation and two players: one with a character in the corporation, one without.
     * The app has the corporation if $restrictApp is true.
     *
     * @return array{0: int, 1: Corporation, 2: Player, 3: Player, 4: Character, 5: Character}
     */
    private function createFixture(Helper $h, bool $restrictApp = true): array
    {
        $h->emptyDb();

        $corp = (new Corporation())->setId(10)->setTicker('t1')->setName('corp 1');
        $playerIn = (new Player())->setName('P1');
        $charIn = (new Character())->setId(1)->setName('C1')->setPlayer($playerIn)->setCorporation($corp);
        $playerIn->addCharacter($charIn);
        $playerOut = (new Player())->setName('P2');
        $charOut = (new Character())->setId(2)->setName('C2')->setPlayer($playerOut);
        $playerOut->addCharacter($charOut);

        $om = $h->getObjectManager();
        $om->persist($corp);
        $om->persist($playerIn);
        $om->persist($playerOut);
        $om->persist($charIn);
        $om->persist($charOut);

        $app = $h->addApp('A1', 's1', ['app']);
        if ($restrictApp) {
            $app->addCorporation($corp);
        }
        $om->flush();

        return [$app->getId(), $corp, $playerIn, $playerOut, $charIn, $charOut];
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
