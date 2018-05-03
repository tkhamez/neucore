<?php declare(strict_types=1);

namespace Tests\Functional\Core\Command;

use Tests\Functional\ConsoleTestCase;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Provider\GenericResourceOwner;

class TokenVerifyTest extends ConsoleTestCase
{
    public function testExecute()
    {
        $oauth = $this->createMock(GenericProvider::class);
        $oauth->method('getResourceOwner')->willReturn(new GenericResourceOwner([
            'CharacterID' => '123',
            'CharacterName' => 'char name',
            'ExpiresOn' => '2018-05-03T20:27:38.7999223',
            'CharacterOwnerHash' => 'coh',
        ], 'id'));

        $output = $this->runConsoleApp('token-verify', ['token' => 'tok'], [
            GenericProvider::class => $oauth
        ]);

        $this->assertContains('CharacterID', $output);
    }

    public function testExecuteError()
    {
        $oauth = $this->createMock(GenericProvider::class);

        $output = $this->runConsoleApp('token-verify', ['token' => 'tok'], [
            GenericProvider::class => $oauth
        ]);

        $this->assertSame('Error, check log.'."\n", $output);
    }
}
