<?php declare(strict_types=1);

namespace Tests\Unit\Core\Service;

use Brave\Core\Entity\Character;
use Brave\Core\Entity\Corporation;
use Brave\Core\Repository\CharacterRepository;
use Brave\Core\Entity\Player;
use Brave\Core\Factory\RepositoryFactory;
use Brave\Core\Repository\RemovedCharacterRepository;
use Brave\Core\Service\CharacterService;
use Brave\Core\Service\OAuthToken;
use Brave\Core\Service\ObjectManager;
use Brave\Sso\Basics\EveAuthentication;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessToken;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\Helper;
use Tests\OAuthProvider;
use Tests\Client;

class CharacterServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var OAuthToken
     */
    private $token;

    /**
     * @var CharacterService
     */
    private $service;

    /**
     * @var CharacterRepository
     */
    private $charRepo;

    /**
     * @var RemovedCharacterRepository
     */
    private $removedCharRepo;

    public function setUp()
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $em = $this->helper->getEm();

        $log = new Logger('Test');
        $log->pushHandler(new TestHandler());

        $this->client = new Client();
        $this->token = new OAuthToken(new OAuthProvider($this->client), new ObjectManager($em, $log), $log);
        $this->service = new CharacterService($log, new ObjectManager($em, $log));
        $this->charRepo = (new RepositoryFactory($em))->getCharacterRepository();
        $this->removedCharRepo = (new RepositoryFactory($em))->getRemovedCharacterRepository();
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

    public function testMoveCharacterToNewPlayer()
    {
        $char = new Character();
        $char->setId(100);
        $char->setName('char name');
        $player = new Player();
        $player->setName($char->getName());
        $player->addCharacter($char);
        $char->setPlayer($player);

        $character = $this->service->moveCharacterToNewAccount($char);

        $this->assertSame($char, $character);
        $this->assertNotSame($player, $character->getPlayer());
        $this->assertSame('char name', $character->getPlayer()->getName());

        $this->assertSame(100, $player->getRemovedCharacters()[0]->getCharacterId());
    }

    public function testUpdateAndStoreCharacterWithPlayer()
    {
        $player = (new Player())->setName('name');
        $char = (new Character())->setId(12)->setPlayer($player);

        $expires = time() + (60 * 20);
        $result = $this->service->updateAndStoreCharacterWithPlayer(
            $char,
            new EveAuthentication(
                null,
                'name',
                'character-owner-hash',
                new AccessToken(['access_token' => 'a-t', 'refresh_token' => 'r-t', 'expires' => $expires]),
                ['scope1', 'scope2']
            )
        );
        $this->assertTrue($result);

        $this->helper->getEm()->clear();

        $character = $this->charRepo->find(12);

        $this->assertSame('name', $character->getName());
        $this->assertFalse($character->getMain());
        $this->assertSame('name', $character->getPlayer()->getName());

        $this->assertSame('character-owner-hash', $character->getCharacterOwnerHash());
        $this->assertSame('a-t', $character->getAccessToken());
        $this->assertSame('r-t', $character->getRefreshToken());
        $this->assertSame($expires, $character->getExpires());
        $this->assertSame('scope1 scope2', $character->getScopes());
    }

    public function testCheckTokenUpdateCharacterDeletesBiomassedChar()
    {
        $em = $this->helper->getEm();
        $corp = (new Corporation())->setId(1000001); // Doomheim
        $player = (new Player())->setName('p');
        $char = (new Character())->setId(31)->setName('n31')->setCorporation($corp)->setPlayer($player);
        $em->persist($corp);
        $em->persist($player);
        $em->persist($char);
        $em->flush();

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(CharacterService::CHECK_CHAR_DELETED, $result);

        $em->clear();
        $character = $this->charRepo->find(31);
        $this->assertNull($character);

        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 31]);
        $this->assertSame(31, $removedChar->getCharacterId());
    }

    public function testCheckCharacterNoToken()
    {
        $result = $this->service->checkCharacter(new Character(), $this->token);
        $this->assertSame(CharacterService::CHECK_TOKEN_NOK, $result);
    }

    public function testCheckCharacterInvalidToken()
    {
        $em = $this->helper->getEm();
        $char = (new Character())
            ->setId(31)->setName('n31')
            ->setValidToken(false) // it's also the default
            ->setCharacterOwnerHash('hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires(time() - 1000);
        $em->persist($char);
        $em->flush();

        $this->client->setResponse(
            // for refreshAccessToken()
            new Response(400, [], '{"error": "invalid_token"}')
        );

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(CharacterService::CHECK_TOKEN_NOK, $result);
    }

    public function testCheckCharacterRequestError()
    {
        $this->client->setResponse(
            // for refreshAccessToken()
            new Response(200, [], '{"access_token": "new-at"}'),

            // for getResourceOwner()
            new Response(500)
        );

        $em = $this->helper->getEm();
        $expires = time() - 1000;
        $char = (new Character())
            ->setId(31)->setName('n31')
            ->setValidToken(false) // it's also the default
            ->setCharacterOwnerHash('hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $em->persist($char);
        $em->flush();

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(CharacterService::CHECK_REQUEST_ERROR, $result);
    }

    public function testCheckCharacterValid()
    {
        $this->client->setResponse(
            // for refreshAccessToken()
            new Response(200, [], '{
                "access_token": "new-at"
            }'),

            // for getResourceOwner()
            new Response(200, [], '{
                "CharacterOwnerHash": "hash"
            }')
        );

        $em = $this->helper->getEm();
        $expires = time() - 1000;
        $char = (new Character())
            ->setId(31)->setName('n31')
            ->setValidToken(false) // it's also the default
            ->setCharacterOwnerHash('hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $em->persist($char);
        $em->flush();

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(CharacterService::CHECK_TOKEN_OK, $result);

        $em->clear();
        $character = $this->charRepo->find(31);
        $this->assertTrue($character->getValidToken());
        $this->assertSame('at', $character->getAccessToken()); // not updated
        $this->assertSame('rt', $character->getRefreshToken()); // not updated
        $this->assertSame($expires, $character->getExpires()); // not updated
    }

    public function testCheckTokenUpdateCharacterDeletesMovedChar()
    {
        $this->client->setResponse(
        // for refreshAccessToken()
            new Response(200, [], '{
                "access_token": "new-at"
            }'),

            // for getResourceOwner()
            new Response(200, [], '{
                "CharacterOwnerHash": "new-hash"
            }')
        );

        $em = $this->helper->getEm();
        $expires = time() - 1000;
        $player = (new Player())->setName('p');
        $char = (new Character())
            ->setPlayer($player)
            ->setId(31)->setName('n31')
            ->setCharacterOwnerHash('old-hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $em->persist($player);
        $em->persist($char);
        $em->flush();

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(CharacterService::CHECK_CHAR_DELETED, $result);

        $em->clear();
        $character = $this->charRepo->find(31);
        $this->assertNull($character);

        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 31]);
        $this->assertSame(31, $removedChar->getCharacterId());
    }

    public function testRemoveCharacterFromPlayer()
    {
        $player = (new Player())->setName('player 1');
        $char = (new Character())->setId(10)->setName('char')->setPlayer($player);
        $player->addCharacter($char);
        $this->helper->getEm()->persist($player);
        $this->helper->getEm()->persist($char);
        $this->helper->getEm()->flush();

        $this->service->removeCharacterFromPlayer($char);
        $this->helper->getEm()->flush();

        $this->helper->getEm()->clear();

        $this->assertSame(0, count($player->getCharacters()));
        $this->assertSame(10, $player->getRemovedCharacters()[0]->getCharacterId());
        $this->assertSame('char', $player->getRemovedCharacters()[0]->getCharacterName());
        $this->assertSame('player 1', $player->getRemovedCharacters()[0]->getPlayer()->getName());
        $this->assertLessThanOrEqual(time(), $player->getRemovedCharacters()[0]->getRemovedDate()->getTimestamp());

        // tests that the new object was persisted.
        $removedChars = $this->removedCharRepo->findBy([]);
        $this->assertSame(10, $removedChars[0]->getCharacterId());
    }

    public function testDeleteCharacter()
    {
        $player = (new Player())->setName('player 1');
        $char = (new Character())->setId(10)->setName('char')->setPlayer($player);
        $player->addCharacter($char);
        $this->helper->getEm()->persist($player);
        $this->helper->getEm()->persist($char);
        $this->helper->getEm()->flush();

        $this->service->deleteCharacter($char);
        $this->helper->getEm()->flush();

        $this->assertSame(0, count($player->getCharacters()));

        $chars = $this->charRepo->findBy([]);
        $this->assertSame(0, count($chars));
        $removedChars = $this->removedCharRepo->findBy([]);
        $this->assertSame(1, count($removedChars));
        $this->assertSame(10, $removedChars[0]->getCharacterId());
        $this->assertSame('char', $removedChars[0]->getCharacterName());
        $this->assertSame($player->getId(), $removedChars[0]->getPlayer()->getId());
        $this->assertSame('player 1', $removedChars[0]->getPlayer()->getName());
        $this->assertLessThanOrEqual(time(), $removedChars[0]->getRemovedDate()->getTimestamp());
    }
}
