<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use Seat\Eseye\Containers\EsiAuthentication;
use Seat\Eseye\Eseye;
use Swagger\Client\Eve\Api\AssetsApi;
use Swagger\Client\Eve\ApiException;
use Swagger\Client\Eve\Configuration;

include __DIR__ . '/vendor/autoload.php';

/**
 * The endpoint /api/app/v2/esi can be used for any protected ESI GET or POST request.
 *
 * There are two ways to call it, the following examples are for the ESI path and query string:
 * /v5/characters/96061222/assets/?page=1
 *
 * 1. Compatible with generated OpenAPI clients from the ESI definition file (see example below),
 *    simply append the ESI path to the Neucore path and add the ESI query parameters:
 *    https://neucore.tld/api/app/v2/esi/v5/characters/96061222/assets/?page=1&datasource=96061222
 *
 * 2. Compatible with generated OpenAPI clients from the Neucore interface definition file (see example below),
 *    the query parameter "esi-path-query" contains the url-encoded ESI path and query string:
 *    https://neucore.tld/api/app/v2/esi?esi-path-query=%2Fv5%2Fcharacters%2F96061222%2Fassets%2F%3Fpage%3D1&datasource=96061222
 *
 * Both can use the "datasource" parameter to tell Neucore from which character and EVE login the ESI token
 * should be used for the request. The ESI datasource (tranquility) is decided by the Neucore configuration.
 * Alternatively, the EVE character can be defined with the HTTP header Neucore-EveCharacter and the EVE login
 * from which the token is to be used with Neucore-EveLogin.
 * The header has priority over the query parameter!
 *
 * See doc/Documentation.md -> "Authentication of applications" for details about the token.
 */


//
// Configuration, adjust with your values
//

$coreHttpScheme = 'http';
$coreDomain = 'neucore_http'; // Works with compose.yaml.
$coreAppToken = base64_encode('1:secret');
$coreCharId = '96061222'; // Character with token in Neucore


//
// Example 1: A GET request using a generated OpenAPI client from the ESI API file
//            (e.g. https://packagist.org/packages/tkhamez/swagger-eve-php)
//

// Change the host to the Neucore domain including the API path and add the app token.
$configuration = new Configuration();
$configuration->setHost("$coreHttpScheme://$coreDomain/api/app/v2/esi");
$configuration->setAccessToken($coreAppToken);

// Set default headers to define the EVE character and login for the token to be used.
$client = new Client(['headers' => [
    'Neucore-EveCharacter' => $coreCharId,
    'Neucore-EveLogin' => 'core.default', // This header is optional, core.default is the default value for it.
]]);

$assetsApiInstance = new AssetsApi($client, $configuration);
$itemId = 0; // used in example 2
try {
    $result = $assetsApiInstance->getCharactersCharacterIdAssets($coreCharId);
    $itemId = $result[0]->getItemId();
    echo 'item id: ', $itemId, PHP_EOL;
} catch (ApiException $e) {
    echo $e->getMessage(), PHP_EOL;
}
echo PHP_EOL;


//
// Example 2: A POST request using a generated OpenAPI client from the Neucore API file
//            (e.g. https://packagist.org/packages/bravecollective/neucore-api)

// Change the host to the Neucore domain including the API path and add the app token
$config = Brave\NeucoreApi\Configuration::getDefaultConfiguration();
$config->setHost("$coreHttpScheme://$coreDomain/api");
$config->setAccessToken($coreAppToken);

$esiApiInstance = new Brave\NeucoreApi\Api\ApplicationESIApi(null, $config);
try {
    $result = $esiApiInstance->esiPostV2WithHttpInfo(
        "/latest/characters/$coreCharId/assets/names/",
        $coreCharId, // EVE character to choose the ESI token (from core.default login).
        json_encode([$itemId])
    );

    echo 'Status: ', $result[1], PHP_EOL;
    echo 'Headers: ';
    $header = [];
    foreach ($result[2] as $name => $value) {
        $header[$name] = $value[0];
    }
    print_r($header);
    echo 'name: ', json_decode($result[0], true)[0]['name'], PHP_EOL;
} catch (Exception $e) {
    echo 'Exception when calling ApplicationApi->esiV2: ', $e->getMessage(), PHP_EOL;
}
echo PHP_EOL;


// Example 2a: Public ESI routes are not passed through:

try {
    $esiApiInstance->esiV2('/lastest/alliances/', $coreCharId);
} catch (\Brave\NeucoreApi\ApiException $e) {
    echo $e->getMessage(), PHP_EOL;
}
echo PHP_EOL;


//
// Example using Eseye
//

// Set the EVE character ID as the datasource
$configuration = \Seat\Eseye\Configuration::getInstance();
$configuration->datasource = $coreCharId;
$configuration->esi_scheme = $coreHttpScheme;
$configuration->esi_host = "$coreDomain/api/app/v2/esi";

// Create an authorization object with the Core app token that does not expire
$authentication = new EsiAuthentication([
    'access_token'  => $coreAppToken,
    'token_expires' => date('Y-m-d H:i:s', time() + 3600),
    'scopes' => [
        // must contain all scopes that are used or Eseye will not make the request
       'esi-assets.read_assets.v1'
    ],
]);

$esi = new Eseye($authentication);
try {
    $result = $esi
        ->setQueryString(['page' => 1])
        ->invoke('get', '/characters/{character_id}/assets/', ['character_id' => $coreCharId]);

    echo 'Status: ', $result->getErrorCode(), PHP_EOL;
    echo 'Headers: ';
    print_r($result->headers);
    echo 'item id: ', $result[0]->item_id, PHP_EOL;
} catch (Exception $e) {
    echo ((string) $e), PHP_EOL;
}
echo PHP_EOL;
