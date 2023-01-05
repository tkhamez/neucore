<?php

declare(strict_types=1);

namespace Neucore\Plugin;

use Neucore\Exception\RuntimeException;
use Neucore\Factory\HttpClientFactoryInterface;
use Neucore\Plugin\Core\EsiClientInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\ResponseInterface;

class EsiClient implements EsiClientInterface
{
    public function __construct(
        private \Neucore\Service\EsiClient $esiClient,
        private HttpClientFactoryInterface $httpClientFactory
    ) {
    }

    public function request(
        string $esiPath,
        string $method = 'GET',
        string $body = null,
        int $characterId = null,
        string $eveLoginName = self::DEFAULT_LOGIN_NAME,
        bool $debug = false,
    ): ResponseInterface {
        try {
            $response = $this->esiClient->request($esiPath, $method, $body, $characterId, $eveLoginName, $debug);
        } catch (RuntimeException $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        } catch (ClientExceptionInterface $e) {
            $response = $this->httpClientFactory->createResponse(
                500, // status
                [], // header
                $e->getMessage() // body
            );
        }
        return $response;
    }
}
