<?php declare(strict_types=1);

namespace Tests;

use GuzzleHttp\ClientInterface;
use League\OAuth2\Client\Provider\GenericProvider;

class OAuthTestProvider extends GenericProvider
{
    public function __construct(ClientInterface $client = null)
    {
        parent::__construct([
            'urlAuthorize'            => 'http://localhost',
            'urlAccessToken'          => 'http://localhost',
            'urlResourceOwnerDetails' => 'http://localhost'
        ]);

        if ($client) {
            $this->setHttpClient($client);
        }
    }
}
