<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CharacterNameChangeRepository;
use Neucore\Service\Character;
use Neucore\Service\ObjectManager;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;

class CharacterTest extends TestCase
{
    /**
     * @var ObjectManager
     */
    private $om;

    /**
     * @var Character
     */
    private $service;

    /**
     * @var CharacterNameChangeRepository
     */
    private $repository;

    protected function setUp(): void
    {
        $helper = new Helper();
        $logger = new Logger('test');
        $om = $helper->getObjectManager();
        $this->om = new ObjectManager($om, $logger);
        $this->service = new Character($this->om);
        $this->repository = RepositoryFactory::getInstance($om)->getCharacterNameChangeRepository();
    }

    public function testSetCharacterName_NoChange()
    {
        $character = new \Neucore\Entity\Character();
        $character->setName('old name');

        $this->service->setCharacterName($character, '');
        $this->assertSame('old name', $character->getName());
    }

    public function testSetCharacterName_NoChangedEntry()
    {
        $character = new \Neucore\Entity\Character();
        $character->setName('old name');

        $this->service->setCharacterName($character, 'old name');

        $this->om->flush();

        $actual = $this->repository->findBy([]);
        $this->assertSame(0, count($actual));
    }

    public function testSetCharacterName_Changed()
    {
        $player = new Player();
        $character = new \Neucore\Entity\Character();
        $character->setId(100);
        $character->setName('old name');
        $character->setPlayer($player);

        $this->service->setCharacterName($character, 'new name');

        $this->assertSame('new name', $character->getName());

        $this->om->persist($character);
        $this->om->persist($player);
        $this->om->flush();

        $actual = $this->repository->findBy([]);
        $this->assertSame(1, count($actual));
        $this->assertSame('old name', $actual[0]->getOldName());
    }
}
