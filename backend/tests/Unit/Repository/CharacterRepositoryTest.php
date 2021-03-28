<?php

declare(strict_types=1);

namespace Tests\Unit\Repository;

use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CharacterRepository;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Neucore\Entity\Character;

class CharacterRepositoryTest extends TestCase
{
    /**
     * @var CharacterRepository
     */
    private static $repository;

    public static function setUpBeforeClass(): void
    {
        $h = new Helper();
        $h->emptyDb();
        $om = $h->getObjectManager();

        $char1 = (new Character())->setId(10)->setName('char two')->setMain(true);
        $char2 = (new Character())->setId(20)->setName('char one');
        $char3 = (new Character())->setId(30)->setName('three');

        $h->addNewPlayerToCharacterAndFlush($char1);
        $h->addNewPlayerToCharacterAndFlush($char2);
        $h->addNewPlayerToCharacterAndFlush($char3);

        self::$repository = (new RepositoryFactory($om))->getCharacterRepository();
    }

    public function testFindMainByNamePartialMatch()
    {
        $actual = self::$repository->findMainByNamePartialMatch('har');
        $this->assertSame(1, count($actual));
        $this->assertSame('char two', $actual[0]->getName());
        $this->assertSame(10, $actual[0]->getID());
    }
}
