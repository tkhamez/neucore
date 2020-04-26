#!/usr/bin/env php
<?php

declare(strict_types=1);

$swagger = file_get_contents('https://esi.evetech.net/_latest/swagger.json');
$def = json_decode($swagger);

$public = [];
foreach ($def->paths as $path => $data) {
    if ((! isset($data->get) && ! isset($data->post)) ||
        (isset($data->get) && isset($data->get->security)) ||
        (isset($data->post) && isset($data->post->security))
    ) {
        continue;
    }

    // strip the version information
    // e. g. /v1/alliances/{alliance_id}/corporations/
    //   =>     /alliances/{alliance_id}/corporations/
    $shortPath = substr($path, strpos($path, '/', 1));

    // change paths to regular expression
    // e. g. /alliances/{alliance_id}/corporations/
    //   =>  /alliances/[0-9]+/corporations/
    $regExp = str_replace([
        '{alliance_id}',
        '{character_id}',
        '{contract_id}',
        '{region_id}',
        '{corporation_id}',
        '{attribute_id}',
        '{type_id}',
        '{item_id}',
        '{killmail_id}',
        '{market_group_id}',
        '{task_id}',
        '{asteroid_belt_id}',
        '{category_id}',
        '{constellation_id}',
        '{graphic_id}',
        '{group_id}',
        '{moon_id}',
        '{planet_id}',
        '{schematic_id}',
        '{stargate_id}',
        '{star_id}',
        '{war_id}',
        '{effect_id}',
        '{station_id}',
        '{system_id}',
        '{fleet_id}',
        '{wing_id}',
        '{origin}',
        '{destination}',
    ], '[0-9]+', $shortPath);

    // looks like this is a HEX value
    $regExp2 = str_replace('{killmail_hash}', '[0-9a-fA-F]+', $regExp);

    $public[] = $regExp2;
}

$result = var_export($public, true);
file_put_contents(__DIR__ . '/../config/esi-paths-public.php', "<?php\nreturn " . $result . ';');

echo "wrote config/esi-paths-public.php", PHP_EOL;
