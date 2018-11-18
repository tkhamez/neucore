<?php declare(strict_types=1);

namespace Tests;

use GuzzleHttp\ClientInterface;
use League\OAuth2\Client\Provider\GenericProvider;

class OAuthProvider extends GenericProvider
{
    public function __construct(ClientInterface $client = null)
    {
        parent::__construct([
            'urlAuthorize'            => 'http://localhost/auth',
            'urlAccessToken'          => 'http://localhost/token',
            'urlResourceOwnerDetails' => 'http://localhost/owner'
        ]);

        if ($client) {
            $this->setHttpClient($client);
        }
    }
}
