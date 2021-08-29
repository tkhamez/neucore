<?php

declare(strict_types=1);

include __DIR__ . '/vendor/autoload.php';

/**
 * The endpoint /api/app/v1/esi can be used for any protected ESI GET or POST request.
 *
 * There are two ways to call it, the following examples are for the ESI path and query string:
 * /v3/characters/96061222/assets/?page=1
 *
 * 1. Compatible with generated OpenAPI clients from the ESI definition file (see example below),
 *    simply append the ESI path to the Neucore path and add the ESI query parameters:
 *    https://neucore.tld/api/app/v1/esi/v3/characters/96061222/assets/?page=1&datasource=96061222
 *
 * 2. Compatible with generated OpenAPI clients from the Neucore interface definition file (see example below),
 *    the query parameter "esi-path-query" contains the url-encoded ESI path and query string:
 *    https://neucore.tld/api/app/v1/esi?esi-path-query=%2Fv3%2Fcharacters%2F96061222%2Fassets%2F%3Fpage%3D1&datasource=96061222
 *
 * Both use the "datasource" parameter to tell Neucore from which character the ESI token should be used for the
 * request. The ESI datasource (tranquility) is decided by the Neucore configuration.
 *
 * See doc/Documentation.md -> "Authentication of applications" for details about the token.
 */


//
// Configuration, adjust with your values
//

$coreHttpScheme = 'http';
$coreDomain = 'neucore_http'; // works with docker-compose
$coreAppToken = base64_encode('1:secret');
$coreCharId = '96061222'; // Character with token in Neucore


//
// Example 1: making a GET request using a generated OpenAPI client from the ESI API file
//            (e. g. https://packagist.org/packages/tkhamez/swagger-eve-php)
//

// Change the host to the Neucore domain including the API path and add the app token
$configuration = new \Swagger\Client\Eve\Configuration();
$configuration->setHost($coreHttpScheme .'://'. $coreDomain . '/api/app/v1/esi');
$configuration->setAccessToken($coreAppToken);

$assetsApiInstance = new Swagger\Client\Eve\Api\AssetsApi(null, $configuration);
$itemId = 0; // used in example 2
try {
    // The first parameter (character_id) is the EVE character ID for ESI,
    // the second (datasource) the EVE character ID for Neucore for the ESI token.
    $result = $assetsApiInstance->getCharactersCharacterIdAssets($coreCharId, $coreCharId);

    $itemId = $result[0]->getItemId();
    echo 'item id: ', $itemId, PHP_EOL;
} catch (\Swagger\Client\Eve\ApiException $e) {
    echo $e->getMessage(), PHP_EOL;
}
echo PHP_EOL;


//
// Example 2: making a POST request using a generated OpenAPI client from the Neucore API file
//            (e. g. https://packagist.org/packages/bravecollective/neucore-api)

// Change the host to the Neucore domain including the API path and add the app token
$config = Brave\NeucoreApi\Configuration::getDefaultConfiguration();
$config->setHost($coreHttpScheme .'://'. $coreDomain . '/api');
$config->setAccessToken($coreAppToken);

$esiApiInstance = new Brave\NeucoreApi\Api\ApplicationESIApi(null, $config);
try {
    $result = $esiApiInstance->esiPostV1WithHttpInfo(
        '/latest/characters/'.$coreCharId.'/assets/names/',
        $coreCharId,
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
    echo 'Exception when calling ApplicationApi->esiV1: ', $e->getMessage(), PHP_EOL;
}
echo PHP_EOL;


// Example 2a: Public ESI routes are not passed through:

try {
    $esiApiInstance->esiV1('/lastest/alliances/', $coreCharId);
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
$configuration->esi_host = $coreDomain . '/api/app/v1/esi';

// Create an authorization object with the Core app token that does not expire
$authentication = new \Seat\Eseye\Containers\EsiAuthentication([
    'access_token'  => $coreAppToken,
    'token_expires' => date('Y-m-d H:i:s', time() + 3600),
    'scopes' => [
        // must contain all scopes that are used or Eseye will not make the request
       'esi-assets.read_assets.v1'
    ],
]);

$esi = new \Seat\Eseye\Eseye($authentication);
try {
    $result = $esi->setQueryString([
        'page' => 1,
    ])->invoke('get', '/characters/{character_id}/assets/', [
        'character_id' => $coreCharId,
    ]);

    echo 'Status: ', $result->getErrorCode(), PHP_EOL;
    echo 'Headers: ';
    print_r($result->headers);
    echo 'item id: ', $result[0]->item_id, PHP_EOL;
} catch (Exception $e) {
    echo ((string) $e), PHP_EOL;
}
echo PHP_EOL;
