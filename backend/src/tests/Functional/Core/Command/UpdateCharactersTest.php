<?php declare(strict_types=1);

namespace Tests\Functional\Core\Command;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\CharacterRepository;
use Swagger\Client\Eve\Api\CharacterApi;
use Swagger\Client\Eve\Api\CorporationApi;
use Swagger\Client\Eve\Model\GetCharactersCharacterIdOk;
use Swagger\Client\Eve\Model\GetCorporationsCorporationIdOk;
use Tests\Functional\ConsoleTestCase;
use Tests\Helper;

class UpdateCharactersTest extends ConsoleTestCase
{
    public function testExecute()
    {
        // setup
        $h = new Helper();
        $h->emptyDb();
        $em = $h->getEm();

        $c1 = (new Character())->setId(1122)->setName('c11')
            ->setMain(false)->setCharacterOwnerHash('coh11')->setAccessToken('at11');
        $c2 = (new Character())->setId(2233)->setName('c22')
            ->setMain(false)->setCharacterOwnerHash('coh22')->setAccessToken('at22');

        $em->persist($c1);
        $em->persist($c2);
        $em->flush();

        // mock API
        $charApi = $this->createMock(CharacterApi::class);
        $charApi->method('getCharactersCharacterId')->willReturn(new GetCharactersCharacterIdOk([
            'name' => 'char xx', 'corporation_id' => 234
        ]));
        $corpApi = $this->createMock(CorporationApi::class);
        $corpApi->method('getCorporationsCorporationId')->willReturn(new GetCorporationsCorporationIdOk([
            'name' => 'The Corp.', 'ticker' => '-T-T-', 'alliance_id' => null
        ]));

        // run
        $output = $this->runConsoleApp('update-chars', ['--sleep' => 0], [
            CharacterApi::class => $charApi,
            CorporationApi::class => $corpApi,
        ]);

        $em->clear();

        $expectedOutput = [
            'Updated 1122',
            'Updated 2233',
            'All done.',
        ];
        $this->assertSame(implode("\n", $expectedOutput)."\n", $output);

        # read result
        $actual = (new CharacterRepository($em))->findAll();
        $this->assertSame(1122, $actual[0]->getId());
        $this->assertSame(2233, $actual[1]->getId());
        $this->assertNotNull($actual[0]->getLastUpdate());
        $this->assertNotNull($actual[1]->getLastUpdate());
        $this->assertSame(234, $actual[0]->getCorporation()->getId());
        $this->assertSame(234, $actual[1]->getCorporation()->getId());
        $this->assertNull($actual[0]->getCorporation()->getAlliance());
        $this->assertNull($actual[1]->getCorporation()->getAlliance());
    }
}
