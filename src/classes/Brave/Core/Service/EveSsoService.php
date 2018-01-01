<?php
namespace Brave\Core\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Psr\Log\LoggerInterface;

/**
 * Stuff necessary for EVE SSO.
 *
 * https://eveonline-third-party-documentation.readthedocs.io/en/latest/sso/index.html
 */
class EveSsoService
{

    private $config;

    private $client;

    private $log;

    private $baseUrl = 'https://login.eveonline.com/oauth/';

    /**
     *
     * @param array $config with keys: client_id, secret_key and callback_url
     * @param Client $client
     * @param LoggerInterface $log
     */
    public function __construct(array $config, Client $client, LoggerInterface $log)
    {
        $this->config = $config;
        $this->client = $client;
        $this->log = $log;
    }

    /**
     *
     * @param string $oauthState
     * @return string
     */
    public function getLoginUrl(string $oauthState)
    {
        $scopes = [];

        $url = $this->baseUrl . "authorize" .
            "?response_type=code" .
            "&redirect_uri=" . urlencode($this->config['callback_url']) .
            "&client_id=" . $this->config['client_id'] .
            "&state=" . $oauthState
        ;

        if (count($scopes) > 0) {
            $url .= "&scope=" . implode('%20', $scopes);
        }

        return $url;
    }

    /**
     *
     * @param string $code
     * @return NULL|array
     */
    public function requestToken(string $code)
    {
        $body = [
            "grant_type" => "authorization_code",
            "code" => $code
        ];

        $encodedAuth = base64_encode($this->config['client_id'] .':'. $this->config['secret_key']);
        $headers = [
            'Authorization' => 'Basic ' . $encodedAuth,
            #'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        return $this->request('POST', $this->baseUrl . "token", $headers, $body);
    }

    /**
     *
     * @param string $accessToken
     * @return NULL|array
     */
    public function requestVerify(string $accessToken)
    {
        $headers = [
            "Authorization" => "Bearer " . $accessToken
        ];

        return $this->request('GET', $this->baseUrl . "verify", $headers);
    }

    private function request($method, $url, $headers, $formParams = null)
    {
        /* @var $response \GuzzleHttp\Psr7\Response */
        $response = null;
        try {
            if ($formParams) {
                $response = $this->client->request($method, $url, [
                    'form_params' => $formParams,
                    'headers' => $headers
                ]);
            } else {
                $response = $this->client->request($method, $url, ['headers' => $headers]);
            }
        } catch (TransferException $e) {
            $this->log->error($e->getMessage());
        }

        if ($response !== null) {
            $result = null;
            try {
                $result = json_decode($response->getBody()->getContents(), true);
            } catch (\RuntimeException $e) {
                $this->log->error($e->getMessage());
            }
        }

        return is_array($result) ? $result : null;
    }
}
