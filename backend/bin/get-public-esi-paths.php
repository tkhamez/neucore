#!/usr/bin/env php
<?php declare(strict_types=1);

$swagger = file_get_contents('https://esi.evetech.net/_latest/swagger.json');
$def = json_decode($swagger);

$public = [];
foreach ($def->paths as $path => $data) {
    if (! isset($data->get) || isset($data->get->security)) {
        continue;
    }

    // add path without the version path
    // e. g. /v5/characters/{character_id}/wallet/journal/
    //   =>     /characters/{character_id}/wallet/journal/
    $public[] = substr($path, strpos($path, '/', 1));
}

$result = var_export($public, true);
file_put_contents(__DIR__ . '/../config/public-esi-paths.php', "<?php\nreturn " . $result . ';');
