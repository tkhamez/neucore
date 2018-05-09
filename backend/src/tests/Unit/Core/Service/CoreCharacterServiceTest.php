<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\CharacterRepository;
use Brave\Core\Service\CoreCharacterService;
use Brave\Core\Service\OAuthToken;
use League\OAuth2\Client\Provider\GenericProvider;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\Helper;

class CoreCharacterServiceTest extends \PHPUnit\Framework\TestCase
{
    private $helper;

    private $service;

    public function setUp()
    {
        $this->helper = new Helper();
        $em = $this->helper->getEm();

        $log = new Logger('Test');
        $log->pushHandler(new TestHandler());

        $oauth = $this->createMock(GenericProvider::class);
        $token = new OAuthToken($oauth, $em, $log);

        $this->service = new CoreCharacterService($log, $em, $token);
    }

    public function testAddCharacter()
    {
        $this->helper->emptyDb();

        $result = $this->service->addCharacter(123, 'abc');
        $this->assertTrue($result);

        $this->helper->getEm()->clear();

        $charRepo = new CharacterRepository($this->helper->getEm());
        $character = $charRepo->find(123);

        $this->assertSame(123, $character->getId());
        $this->assertSame('abc', $character->getName());
        $this->assertTrue($character->getMain());

        $this->assertSame('abc', $character->getPlayer()->getName());
        $this->assertSame([], $character->getPlayer()->getRoles());
    }

    public function testCreateNewPlayerWithMain()
    {
        $this->markTestIncomplete();
    }

    public function testUpdateAndStoreCharacterWithPlayer()
    {
        $this->markTestIncomplete();
    }

    public function testCheckTokenUpdateCharacter()
    {
        $this->markTestIncomplete();
    }
}
