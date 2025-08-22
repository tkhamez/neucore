#!/usr/bin/env php
<?php

declare(strict_types=1);

$compatDate = '2020-01-01'; // Same date as in settings.php.
$openapi = file_get_contents("https://esi.evetech.net/meta/openapi.json?compatibility_date=$compatDate");
if (!$openapi) {
    echo "Error reading openapi.json", PHP_EOL;
    exit(1);
}

$def = json_decode($openapi);

$public = [];
foreach ($def->paths as $path => $data) {
    if (
        (!isset($data->get) && !isset($data->post)) ||
        (isset($data->get->security)) ||
        (isset($data->post->security))
    ) {
        continue;
    }

    // change paths to regular expression
    // e.g. /alliances/{alliance_id}/corporations/
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
    ], '[0-9]+', $path);

    // It looks like this is a HEX value
    $regExp2 = str_replace('{killmail_hash}', '[0-9a-fA-F]+', $regExp);

    $public[] = $regExp2;
}

$result = var_export($public, true);
file_put_contents(__DIR__ . '/../config/esi-paths-public.php', "<?php\nreturn " . $result . ';');

echo "wrote config/esi-paths-public.php", PHP_EOL;
