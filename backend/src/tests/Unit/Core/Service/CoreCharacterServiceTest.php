<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Service\CoreCharacterService;
use Monolog\Logger;
use Monolog\Handler\TestHandler;
use Tests\Helper;
use Brave\Core\Entity\CharacterRepository;

class CoreCharacterServiceTest extends \PHPUnit\Framework\TestCase
{
    private $helper;

    private $service;

    public function setUp()
    {
        $this->helper = new Helper();

        $log = new Logger('Test');
        $log->pushHandler(new TestHandler());

        $this->service = new CoreCharacterService($log, $this->helper->getEm());
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
}
