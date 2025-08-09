<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);

include __DIR__ . '/vendor/autoload.php';

/**
 * The endpoint /api/app/v2/esi can be used for any protected ESI GET or POST request.
 *
 * There are two ways to call it, the following examples are for the ESI path and query string:
 * /characters/96061222/assets/?page=1
 *
 * 1. Compatible with generated OpenAPI clients from the ESI definition file (see example below),
 *    append the ESI path to the Neucore path and add the ESI query parameters:
 *    https://neucore.tld/api/app/v2/esi/characters/96061222/assets/?page=1
 *
 * 2. Compatible with generated OpenAPI clients from the Neucore API definition file (see
 *    example below). The query parameter "esi-path-query" contains the url-encoded ESI path and query
 *    string:
 *    https://neucore.tld/api/app/v2/esi?esi-path-query=%2Fcharacters%2F96061222%2Fassets%2F%3Fpage%3D1
 *
 * The EVE character must be defined with the HTTP header "Neucore-EveCharacter". The EVE login
 * from which the token is to be used can be changed from "core.default" with the "Neucore-EveLogin"
 * header. Alternatively, the "datasource" parameter can be used instead of the headers, but the headers
 * have priority over it.
 *
 * See doc/Documentation.md -> "Authentication of applications" for details about the token.
 */


//
// Configuration, adjust with your values
//

$coreHttpScheme = 'http';
$coreDomain = 'neucore_http'; // Works with compose.yaml.
$coreAppToken = base64_encode('1:secret');
$coreCharId = 96061222; // Character with token in Neucore


//
// Example 1: A GET request using a generated OpenAPI client from the ESI API file,
//            https://packagist.org/packages/tkhamez/eve-api.
//

echo PHP_EOL, 'Example 1:', PHP_EOL, PHP_EOL;

// Change the host to the Neucore domain including the API path and add the app token.
$configuration = new \Tkhamez\Eve\API\Configuration();
$configuration->setHost("$coreHttpScheme://$coreDomain/api/app/v2/esi");
$configuration->setAccessToken($coreAppToken);

// Set default headers to define the EVE character and login for the token to be used.
$client = new \GuzzleHttp\Client(['headers' => [
    'Neucore-EveCharacter' => "$coreCharId",
    'Neucore-EveLogin' => 'core.default', // Optional, "core.default" is the default.
]]);

$assetsApiInstance = new \Tkhamez\Eve\API\Api\AssetsApi($client, $configuration);
$itemId = 0; // used in example 2
try {
    $result = $assetsApiInstance->getCharactersCharacterIdAssets($coreCharId);
    $itemId = $result[0]->getItemId();
    echo 'item id: ', $itemId, PHP_EOL;
} catch (\Tkhamez\Eve\API\ApiException $e) {
    echo $e->getMessage(), PHP_EOL;
}
echo PHP_EOL;


//
// Example 2: A POST request using a generated OpenAPI client from the Neucore API file,
//            https://packagist.org/packages/bravecollective/neucore-api.
//

echo PHP_EOL, 'Example 2:', PHP_EOL, PHP_EOL;

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
    $header = array_map(function ($value) {
        return $value[0];
    }, $result[2]);
    print_r($header);
    echo 'name: ', json_decode($result[0], true)[0]['name'], PHP_EOL;
} catch (Exception $e) {
    echo 'Exception when calling ApplicationApi->esiV2: ', $e->getMessage(), PHP_EOL;
}
echo PHP_EOL;
