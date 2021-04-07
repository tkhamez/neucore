<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Entity\CharacterNameChange;
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
     * @var Helper
     */
    private $helper;

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
        $this->helper = new Helper();
        $logger = new Logger('test');
        $om = $this->helper->getObjectManager();
        $this->om = new ObjectManager($om, $logger);
        $repositoryFactory = RepositoryFactory::getInstance($om);
        $this->service = new Character($this->om, $repositoryFactory);
        $this->repository = $repositoryFactory->getCharacterNameChangeRepository();
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
        $this->helper->emptyDb();

        $character = new \Neucore\Entity\Character();
        $character->setName('old name');

        $this->service->setCharacterName($character, 'old name');

        $this->om->flush();

        $actual = $this->repository->findBy([]);
        $this->assertSame(0, count($actual));
    }

    public function testSetCharacterName_Changed()
    {
        $this->helper->emptyDb();

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

    public function testAddCharacterNameChange_NoChange()
    {
        $this->helper->emptyDb();

        $player = new Player();
        $character = new \Neucore\Entity\Character();
        $character->setId(100);
        $character->setName('new name');
        $character->setPlayer($player);
        $this->om->persist($player);
        $this->om->persist($character);

        $this->service->addCharacterNameChange($character, 'new name');
        $this->om->flush();

        $actual = $this->repository->findBy([]);
        $this->assertSame(0, count($actual));
    }

    public function testAddCharacterNameChange_ExistingRecord()
    {
        $this->helper->emptyDb();

        $player = new Player();
        $character = new \Neucore\Entity\Character();
        $character->setId(100);
        $character->setName('new name');
        $character->setPlayer($player);
        $nameChange = (new CharacterNameChange())->setCharacter($character)->setOldName('old name')
            ->setChangeDate(new \DateTime());
        $character->addCharacterNameChange($nameChange);
        $this->om->persist($player);
        $this->om->persist($character);
        $this->om->persist($nameChange);
        $this->om->flush();

        $this->service->addCharacterNameChange($character, 'old name');
        $this->om->flush();

        $actual = $this->repository->findBy([]);
        $this->assertSame(1, count($actual));
        $this->assertSame('old name', $actual[0]->getOldName());
    }

    public function testAddCharacterNameChange_AddRecord()
    {
        $this->helper->emptyDb();

        $player = new Player();
        $character = new \Neucore\Entity\Character();
        $character->setId(100);
        $character->setName('new name');
        $character->setPlayer($player);
        $this->om->persist($player);
        $this->om->persist($character);

        $this->service->addCharacterNameChange($character, 'old name');
        $this->om->flush();

        $actual = $this->repository->findBy([]);
        $this->assertSame(1, count($actual));
        $this->assertSame('old name', $actual[0]->getOldName());
    }
}
