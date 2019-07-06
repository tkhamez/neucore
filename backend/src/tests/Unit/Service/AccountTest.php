<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use Neucore\Entity\Character;
use Neucore\Entity\Corporation;
use Neucore\Entity\CorporationMember;
use Neucore\Entity\RemovedCharacter;
use Neucore\Entity\SystemVariable;
use Neucore\Repository\CharacterRepository;
use Neucore\Entity\Player;
use Neucore\Factory\RepositoryFactory;
use Neucore\Repository\CorporationMemberRepository;
use Neucore\Repository\RemovedCharacterRepository;
use Neucore\Service\Account;
use Neucore\Service\OAuthToken;
use Neucore\Service\ObjectManager;
use Brave\Sso\Basics\EveAuthentication;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Client\Token\AccessToken;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\TestCase;
use Tests\Helper;
use Tests\Logger;
use Tests\OAuthProvider;
use Tests\Client;

class AccountTest extends TestCase
{
    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var Logger
     */
    private $log;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var OAuthToken
     */
    private $token;

    /**
     * @var Account
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

    /**
     * @var CorporationMemberRepository
     */
    private $corpMemberRepo;

    public function setUp()
    {
        $this->helper = new Helper();
        $this->helper->emptyDb();
        $em = $this->helper->getEm();

        $this->log = new Logger('Test');
        $this->log->pushHandler(new TestHandler());

        $this->client = new Client();
        $this->token = new OAuthToken(new OAuthProvider($this->client), new ObjectManager($em, $this->log), $this->log);
        $this->service = new Account($this->log, new ObjectManager($em, $this->log), new RepositoryFactory($em));
        $this->charRepo = (new RepositoryFactory($em))->getCharacterRepository();
        $this->removedCharRepo = (new RepositoryFactory($em))->getRemovedCharacterRepository();
        $this->corpMemberRepo = (new RepositoryFactory($em))->getCorporationMemberRepository();
    }

    public function testCreateNewPlayerWithMain()
    {
        $character = $this->service->createNewPlayerWithMain(234, 'bcd');

        $this->assertTrue($character->getMain());
        $this->assertSame(234, $character->getId());
        $this->assertSame('bcd', $character->getName());
        $this->assertSame('bcd', $character->getPlayer()->getName());
        $this->assertSame([], $character->getPlayer()->getRoles());
        $this->assertLessThanOrEqual(time(), $character->getCreated()->getTimestamp());
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
        $this->assertSame($character->getPlayer(), $player->getRemovedCharacters()[0]->getNewPlayer());
        $this->assertSame(RemovedCharacter::REASON_MOVED, $player->getRemovedCharacters()[0]->getReason());
        $this->assertSame($character->getPlayer(), $player->getRemovedCharacters()[0]->getNewPlayer());
    }

    public function testUpdateAndStoreCharacterWithPlayer()
    {
        $player = (new Player())->setName('name');
        $char = (new Character())->setName('char name')->setId(12)->setMain(true);
        $char->setPlayer($player);
        $player->addCharacter($char);

        $expires = time() + (60 * 20);
        $result = $this->service->updateAndStoreCharacterWithPlayer(
            $char,
            new EveAuthentication(
                100,
                'char name changed',
                'character-owner-hash',
                new AccessToken(['access_token' => 'a-t', 'refresh_token' => 'r-t', 'expires' => $expires]),
                ['scope1', 'scope2']
            )
        );
        $this->assertTrue($result);

        $this->helper->getEm()->clear();

        $character = $this->charRepo->find(12);

        $this->assertSame('char name changed', $character->getName());
        $this->assertTrue($character->getMain());
        $this->assertSame('char name changed', $player->getName());

        $this->assertSame('character-owner-hash', $character->getCharacterOwnerHash());
        $this->assertSame('a-t', $character->getAccessToken());
        $this->assertSame('r-t', $character->getRefreshToken());
        $this->assertSame($expires, $character->getExpires());
        $this->assertSame('scope1 scope2', $character->getScopes());
        $this->assertTrue($character->getValidToken());
    }

    public function testUpdateAndStoreCharacterWithPlayerNoToken()
    {
        $player = (new Player())->setName('p-name');
        $char = (new Character())->setName('c-name')->setId(12)->setPlayer($player);

        $result = $this->service->updateAndStoreCharacterWithPlayer(
            $char,
            new EveAuthentication(
                100,
                'name',
                'character-owner-hash',
                new AccessToken(['access_token' => 'a-t']),
                []
            )
        );
        $this->assertTrue($result);

        $this->helper->getEm()->clear();

        $character = $this->charRepo->find(12);
        $this->assertNull($character->getRefreshToken());
        $this->assertNull($character->getValidToken());
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
        $this->assertSame(Account::CHECK_CHAR_DELETED, $result);

        $em->clear();
        $character = $this->charRepo->find(31);
        $this->assertNull($character);

        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 31]);
        $this->assertSame(31, $removedChar->getCharacterId());
        $this->assertSame(RemovedCharacter::REASON_DELETED_BIOMASSED, $removedChar->getReason());
    }

    public function testCheckCharacterNoToken()
    {
        $char = (new Character())->setId(100)->setName('name')->setValidToken(false);
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_TOKEN_NA, $result);

        $this->assertNull($char->getValidToken()); // no token = NULL
    }

    public function testCheckCharacterInvalidToken()
    {
        $char = (new Character())
            ->setId(31)->setName('n31')
            ->setValidToken(false) // it's also the default
            ->setCharacterOwnerHash('hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires(time() - 1000);
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        $this->client->setResponse(
            // for refreshAccessToken()
            new Response(400, [], '{"error": "invalid_token"}')
        );

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_TOKEN_NOK, $result);
    }

    public function testCheckCharacterRequestError()
    {
        $this->client->setResponse(
            // for refreshAccessToken()
            new Response(200, [], '{"access_token": "new-at"}'),

            // for getResourceOwner()
            new Response(500)
        );

        $expires = time() - 1000;
        $char = (new Character())
            ->setId(31)->setName('n31')
            ->setValidToken(false) // it's also the default
            ->setCharacterOwnerHash('hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_REQUEST_ERROR, $result);
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

        $expires = time() - 1000;
        $char = (new Character())
            ->setId(31)->setName('n31')
            ->setValidToken(false) // it's also the default
            ->setCharacterOwnerHash('hash')
            ->setAccessToken('at')->setRefreshToken('rt')->setExpires($expires);
        $this->helper->addNewPlayerToCharacterAndFlush($char);

        $result = $this->service->checkCharacter($char, $this->token);
        $this->assertSame(Account::CHECK_TOKEN_OK, $result);

        $this->helper->getEm()->clear();
        $character = $this->charRepo->find(31);
        $this->assertTrue($character->getValidToken());
        $this->assertSame('at', $character->getAccessToken()); // not updated
        $this->assertSame('rt', $character->getRefreshToken()); // not updated
        $this->assertSame($expires, $character->getExpires()); // not updated
    }

    public function testCheckCharacterDeletesMovedChar()
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
        $this->assertSame(Account::CHECK_CHAR_DELETED, $result);

        $em->clear();
        $character = $this->charRepo->find(31);
        $this->assertNull($character);

        $removedChar = $this->removedCharRepo->findOneBy(['characterId' => 31]);
        $this->assertSame(31, $removedChar->getCharacterId());
        $this->assertSame(RemovedCharacter::REASON_DELETED_OWNER_CHANGED, $removedChar->getReason());
    }

    public function testRemoveCharacterFromPlayer()
    {
        $player = (new Player())->setName('player 1');
        $char = (new Character())->setId(10)->setName('char')->setPlayer($player);
        $player->addCharacter($char);
        $this->helper->getEm()->persist($player);
        $this->helper->getEm()->persist($char);
        $this->helper->getEm()->flush();

        $this->service->removeCharacterFromPlayer($char, $player);
        $this->helper->getEm()->flush();

        $this->helper->getEm()->clear();

        $this->assertSame(0, count($player->getCharacters()));
        $this->assertSame(10, $player->getRemovedCharacters()[0]->getCharacterId());
        $this->assertSame('char', $player->getRemovedCharacters()[0]->getCharacterName());
        $this->assertSame('player 1', $player->getRemovedCharacters()[0]->getPlayer()->getName());
        $this->assertEquals(time(), $player->getRemovedCharacters()[0]->getRemovedDate()->getTimestamp(), '', 10);
        $this->assertSame($player, $player->getRemovedCharacters()[0]->getNewPlayer());
        $this->assertSame(RemovedCharacter::REASON_MOVED, $player->getRemovedCharacters()[0]->getReason());

        // tests that the new object was persisted.
        $removedChars = $this->removedCharRepo->findBy([]);
        $this->assertSame(10, $removedChars[0]->getCharacterId());
    }

    public function testDeleteCharacter()
    {
        $player = (new Player())->setName('player 1');
        $char = (new Character())->setId(10)->setName('char')->setPlayer($player);
        $corp = (new Corporation())->setId(1)->setName('c');
        $member = (new CorporationMember())->setId(10)->setCharacter($char)->setCorporation($corp);
        $player->addCharacter($char);
        $this->helper->getEm()->persist($player);
        $this->helper->getEm()->persist($char);
        $this->helper->getEm()->persist($corp);
        $this->helper->getEm()->persist($member);
        $this->helper->getEm()->flush();

        $this->service->deleteCharacter($char, RemovedCharacter::REASON_DELETED_MANUALLY);
        $this->helper->getEm()->flush();

        $this->assertSame(0, count($player->getCharacters()));
        $this->assertSame(0, count($this->charRepo->findAll()));

        $corpMember = $this->corpMemberRepo->findBy([]);
        $this->assertSame(1, count($corpMember));
        $this->assertNull($corpMember[0]->getCharacter());

        $removedChars = $this->removedCharRepo->findBy([]);
        $this->assertSame(1, count($removedChars));
        $this->assertSame(10, $removedChars[0]->getCharacterId());
        $this->assertSame('char', $removedChars[0]->getCharacterName());
        $this->assertSame($player->getId(), $removedChars[0]->getPlayer()->getId());
        $this->assertSame('player 1', $removedChars[0]->getPlayer()->getName());
        $this->assertEquals(time(), $removedChars[0]->getRemovedDate()->getTimestamp(), '', 10);
        $this->assertNull($removedChars[0]->getNewPlayer());
        $this->assertSame(RemovedCharacter::REASON_DELETED_MANUALLY, $removedChars[0]->getReason());
    }

    public function testDeleteCharacterByAdmin()
    {
        $player = (new Player())->setName('player 1');
        $char = (new Character())->setId(10)->setName('char')->setPlayer($player);
        $corp = (new Corporation())->setId(1)->setName('c');
        $member = (new CorporationMember())->setId(10)->setCharacter($char)->setCorporation($corp);
        $player->addCharacter($char);
        $this->helper->getEm()->persist($player);
        $this->helper->getEm()->persist($char);
        $this->helper->getEm()->persist($corp);
        $this->helper->getEm()->persist($member);
        $this->helper->getEm()->flush();

        $this->service->deleteCharacter($char, RemovedCharacter::REASON_DELETED_BY_ADMIN);
        $this->helper->getEm()->flush();

        $corpMember = $this->corpMemberRepo->findBy([]);
        $this->assertSame(1, count($corpMember));
        $this->assertNull($corpMember[0]->getCharacter());

        $this->assertSame(0, count($player->getCharacters()));
        $this->assertSame(0, count($this->charRepo->findAll()));
        $this->assertSame(0, count($this->removedCharRepo->findAll()));

        $this->assertSame(
            'An admin deleted character "char" [10] from player "player 1" [' . $player->getId() . ']',
            $this->log->getHandler()->getRecords()[0]['message']
        );
    }

    public function testGroupsDeactivatedValidToken()
    {
        // activate "deactivated accounts"
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $this->helper->getEm()->persist($setting);
        $this->helper->getEm()->flush();

        $player = (new Player())->addCharacter(
            (new Character())->setValidToken(true)
        );

        $this->assertFalse($this->service->groupsDeactivated($player));
    }

    public function testGroupsDeactivatedInvalidToken()
    {
        // activate "deactivated accounts"
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $this->helper->getEm()->persist($setting);
        $this->helper->getEm()->flush();

        $player = (new Player())->addCharacter(
            (new Character())->setValidToken(false)
        );

        $this->assertTrue($this->service->groupsDeactivated($player));
    }

    public function testGroupsDeactivatedInvalidTokenManaged()
    {
        // activate "deactivated accounts"
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $this->helper->getEm()->persist($setting);
        $this->helper->getEm()->flush();

        $player = (new Player())
            ->setStatus(Player::STATUS_MANAGED)
            ->addCharacter((new Character())->setValidToken(false));

        $this->assertFalse($this->service->groupsDeactivated($player));
    }

    public function testGroupsDeactivatedInvalidTokenWithDelay()
    {
        // feature "deactivated accounts" is active, account has invalid token but only for a short time
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $delay = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_DELAY))->setValue('24');
        $this->helper->getEm()->persist($setting);
        $this->helper->getEm()->persist($delay);
        $this->helper->getEm()->flush();

        $player = (new Player())->addCharacter(
            (new Character())->setValidToken(false)->setValidTokenTime(new \DateTime("now -12 hours"))
        );

        $this->assertFalse($this->service->groupsDeactivated($player));
    }

    public function testGroupsDeactivatedInvalidTokenIgnoreDelay()
    {
        // feature "deactivated accounts" is active, account has invalid token but only for a short time
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('1');
        $delay = (new SystemVariable(SystemVariable::ACCOUNT_DEACTIVATION_DELAY))->setValue('24');
        $this->helper->getEm()->persist($setting);
        $this->helper->getEm()->persist($delay);
        $this->helper->getEm()->flush();

        $player = (new Player())->addCharacter(
            (new Character())->setValidToken(false)->setValidTokenTime(new \DateTime("now -12 hours"))
        );

        $this->assertTrue($this->service->groupsDeactivated($player, true));
    }

    public function testGroupsDeactivatedInvalidTokenSettingNotActive()
    {
        $player = (new Player())->addCharacter(
            (new Character())->setValidToken(false)
        );

        // test with missing setting
        $this->assertFalse($this->service->groupsDeactivated($player));

        // add "deactivated accounts" setting set to 0
        $setting = (new SystemVariable(SystemVariable::GROUPS_REQUIRE_VALID_TOKEN))->setValue('0');
        $this->helper->getEm()->persist($setting);
        $this->helper->getEm()->flush();

        $this->assertFalse($this->service->groupsDeactivated($player));
    }
}
