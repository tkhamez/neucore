<?php declare(strict_types=1);

include __DIR__ . '/vendor/autoload.php';


// configuration, adjust with your values
$coreHttpScheme = 'http';
$coreDomain = 'core.localhost';
$coreAppToken = base64_encode('1:secret');
$charId = '96061222'; // Character with token in Core


//
// Examples
//
// The datasource parameter tells Core which character should be used to make the ESI request.
//
// Option 1: simply append the ESI path to the Core API path and add parameters, e. g.:
// https://neucore.tdl/api/app/v1/esi/latest/characters/96061222/assets/?page=1&datasource=96061222
//
// Option 2: use the path parameter with an url encoded ESI path
// https://neucore.tdl/api/app/v1/esi?path=%latest%2Fcharacters%2F96061222%2Fassets%2F&page=1&datasource=96061222
//



//
// Simple example using the path parameter
//

$result = file_get_contents(
    $coreHttpScheme . '://' . $coreDomain . '/api/app/v1/esi' .
        '?path=' . urlencode('/latest/characters/'.$charId.'/assets/') .
        '&page=1&datasource=' . $charId,
    false,
    stream_context_create(['http' => [
        'method' => 'GET',
        'header' => 'Authorization: Bearer ' . $coreAppToken
    ]])
);
echo 'item id: ', \json_decode($result, true)[0]['item_id'], PHP_EOL;
echo PHP_EOL;



//
// Example using curl with ESI path appended to Core path
//

$ch = curl_init(
    $coreHttpScheme . '://' . $coreDomain .
    '/api/app/v1/esi/latest/characters/'.$charId.'/assets/?page=1&datasource=' . $charId
);
curl_setopt($ch, CURLOPT_HEADER, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $coreAppToken,
    'If-None-Match: 686897696a7c876b7e'
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

$header = trim(substr($response, 0, $headerSize));
$body = substr($response, $headerSize);

echo 'Status: ', $httpCode, PHP_EOL;
echo 'Headers: ', PHP_EOL, $header, PHP_EOL;
echo 'item id: ', \json_decode($body, true)[0]['item_id'], PHP_EOL;
echo PHP_EOL;



//
// Example using bravecollective/neucore-api
//

$config = Brave\NeucoreApi\Configuration::getDefaultConfiguration();
$config->setApiKey('Authorization', $coreAppToken);
$config->setApiKeyPrefix('Authorization', 'Bearer');
$config->setHost($coreHttpScheme .'://'. $coreDomain . '/api');

$apiInstance = new Brave\NeucoreApi\Api\ApplicationApi(null, $config);

try {
    $result = $apiInstance->esiV1('/latest/characters/'.$charId.'/assets/', $charId, 1);
    echo 'item id: ', \json_decode($result, true)[0]['item_id'], PHP_EOL;

} catch (Exception $e) {
    echo 'Exception when calling ApplicationApi->esiV1: ', $e->getMessage(), PHP_EOL;
}
echo PHP_EOL;



//
// Example using Eseye
//

// The only way to change the ESI domain is to extend Eseye at the moment.
class EseyeClient extends \Seat\Eseye\Eseye
{
    public function setHost($scheme, $host)
    {
        $this->esi = ['scheme' => $scheme, 'host' => $host];
    }
}

// Set the EVE character ID as the datasource
$configuration = \Seat\Eseye\Configuration::getInstance();
$configuration->datasource = $charId;

// Create an authorization object with the Core app token that does not expire
$authentication = new \Seat\Eseye\Containers\EsiAuthentication([
    'access_token'  => $coreAppToken,
    'token_expires' => date('Y-m-d H:i:s', time() + 3600),
    'scopes' => [
        // must contain all scopes that are used or Eseye will not make the request
       'esi-assets.read_assets.v1'
    ],
]);

$esi = new EseyeClient($authentication);
$esi->setHost($coreHttpScheme, $coreDomain . '/api/app/v1/esi');
try {
    $result = $esi->invoke('get', '/characters/{character_id}/assets/', [
        'character_id' => $charId,
    ]);

    echo 'Status: ', $result->getErrorCode(), PHP_EOL;
    echo 'Headers: ';
    print_r($result->headers);
    echo 'item id: ', reset($result)->item_id, PHP_EOL;

} catch (\Exception $e) {
    echo (string) $e, PHP_EOL;
}
echo PHP_EOL;



//
// Example using an auto generated OpenAPI client
//


// 1. Request for a protected endpoint

// Change the host and add the Core app token
$configuration = new \Swagger\Client\Eve\Configuration();
$configuration->setHost($coreHttpScheme .'://'. $coreDomain . '/api/app/v1/esi');
$configuration->setAccessToken($coreAppToken);

$apiInstance = new Swagger\Client\Eve\Api\AssetsApi(null, $configuration);

try {
    // We use the $datasource parameter to tell Core which character should be used to make the ESI request.
    // The actual datasource (tranquility or singularity) is decided by the Core configuration.
    $result = $apiInstance->getCharactersCharacterIdAssetsWithHttpInfo($charId, $charId);

    echo 'Status: ', $result[1], PHP_EOL;
    echo 'Headers: ';
    $header = [];
    foreach ($result[2] as $name => $value) {
        $header[$name] = $value[0];
    }
    print_r($header);
    $body = $result[0]; /* @var $body \Swagger\Client\Eve\Model\GetCharactersCharacterIdAssets200Ok[] */
    echo 'item id: ', $body[0]->getItemId(), PHP_EOL;

} catch (\Swagger\Client\Eve\ApiException $e) {
    echo $e->getMessage(), PHP_EOL;
}
echo PHP_EOL;


// 2. Request for a public endpoint

// It is not possible to use Core for public endpoints with this client
// because it does not set the Authorization header in this case,
// so this goes directly to ESI.

$apiInstance = new Swagger\Client\Eve\Api\CharacterApi();
try {
    $result = $apiInstance->getCharactersCharacterId($charId);
    echo 'Name: ' , $result->getName(), PHP_EOL;
} catch (\Swagger\Client\Eve\ApiException $e) {
    echo $e->getMessage(), PHP_EOL;
}
