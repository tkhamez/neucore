<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Entity\Player;
use Brave\Core\Service\CoreCharacter;
use Brave\Core\Service\OAuthToken;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\GenericResourceOwner;
use League\OAuth2\Client\Token\AccessToken;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\Helper;

class CoreCharacterTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    private $oauthProvider;

    /**
     * @var CoreCharacter
     */
    private $service;

    public function setUp()
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $em = $this->helper->getEm();

        $log = new Logger('Test');
        $log->pushHandler(new TestHandler());

        $this->oauthProvider = $this->createMock(GenericProvider::class);
        $token = new OAuthToken($this->oauthProvider, $em, $log);

        $this->service = new CoreCharacter($log, $em, $token);
    }

    public function testCreateCharacter()
    {
        $result = $this->service->createCharacter(123, 'abc');
        $this->assertTrue($result);

        $this->helper->getEm()->clear();

        $charRepo = new CharacterRepository($this->helper->getEm());
        $character = $charRepo->find(123);

        $this->assertSame('abc', $character->getName());
        $this->assertTrue($character->getMain());
        $this->assertSame('abc', $character->getPlayer()->getName());
        $this->assertSame([], $character->getPlayer()->getRoles());
    }

    public function testCreateNewPlayerWithMain()
    {
        $character = $this->service->createNewPlayerWithMain(234, 'bcd');

        $this->assertTrue($character->getMain());
        $this->assertSame(234, $character->getId());
        $this->assertSame('bcd', $character->getName());
        $this->assertSame('bcd', $character->getPlayer()->getName());
        $this->assertSame([], $character->getPlayer()->getRoles());
    }

    public function testUpdateAndStoreCharacterWithPlayerMinimalData()
    {
        $player = (new Player())->setName('eman');
        $expires = time();
        $char = (new Character())->setId(11)->setName('name')->setPlayer($player)
        ->setCharacterOwnerHash('c-o-h')
            ->setAccessToken('acto') ->setRefreshToken('reto')->setExpires($expires)
            ->setScopes('s1 s2');

        $result = $this->service->updateAndStoreCharacterWithPlayer($char);
        $this->assertTrue($result);

        $this->helper->getEm()->clear();

        $charRepo = new CharacterRepository($this->helper->getEm());
        $character = $charRepo->find(11);

        $this->assertSame('name', $character->getName());
        $this->assertFalse($character->getMain());
        $this->assertSame('eman', $character->getPlayer()->getName());

        $this->assertNull($character->getCharacterOwnerHash());
        $this->assertSame('acto', $character->getAccessToken());
        $this->assertSame('reto', $character->getRefreshToken());
        $this->assertSame($expires, $character->getExpires());
        $this->assertNull($character->getScopes());
    }

    public function testUpdateAndStoreCharacterWithPlayerMaximumData()
    {
        $player = (new Player())->setName('eman');
        $char = (new Character())->setId(12)->setName('name')->setPlayer($player);

        $expires = time() + (60 * 20);
        $result = $this->service->updateAndStoreCharacterWithPlayer(
            $char,
            'character-owner-hash',
            new AccessToken(['access_token' => 'a-t', 'refresh_token' => 'r-t', 'expires' => $expires]),
            'scope1 scope2'
        );
        $this->assertTrue($result);

        $this->helper->getEm()->clear();

        $charRepo = new CharacterRepository($this->helper->getEm());
        $character = $charRepo->find(12);

        $this->assertSame('name', $character->getName());
        $this->assertFalse($character->getMain());
        $this->assertSame('eman', $character->getPlayer()->getName());

        $this->assertSame('character-owner-hash', $character->getCharacterOwnerHash());
        $this->assertSame('a-t', $character->getAccessToken());
        $this->assertSame('r-t', $character->getRefreshToken());
        $this->assertSame($expires, $character->getExpires());
        $this->assertSame('scope1 scope2', $character->getScopes());
    }

    public function testCheckTokenUpdateCharacterInvalid()
    {
        // can't really test the difference between no token and revoked token
        // here, but that is done in OAuthTokenTest

        $this->oauthProvider->method('getResourceOwner')->willReturn(null);

        $result = $this->service->checkTokenUpdateCharacter(new Character());
        $this->assertFalse($result);
    }

    public function testCheckTokenUpdateCharacterValid()
    {
        $this->oauthProvider->method('getResourceOwner')->willReturn(
            new GenericResourceOwner(['CharacterOwnerHash' => 'new-hash'], 'id'));

        $em = $this->helper->getEm();
        $expires = time() - 1000;
        $char = (new Character())->setId(31)->setName('n31')
            ->setCharacterOwnerHash('old-hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $em->persist($char);
        $em->flush();

        $result = $this->service->checkTokenUpdateCharacter($char);
        $this->assertTrue($result);

        $em->clear();

        $charRepo = new CharacterRepository($em);
        $character = $charRepo->find(31);

        $this->assertSame('new-hash', $character->getCharacterOwnerHash()); // updated
        $this->assertSame('at', $character->getAccessToken()); // not updated
        $this->assertSame('rt', $character->getRefreshToken()); // not updated
        $this->assertSame($expires, $character->getExpires()); // not updated
    }
}
