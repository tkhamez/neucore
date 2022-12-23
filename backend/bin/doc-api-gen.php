#!/usr/bin/env php
<?php

declare(strict_types=1);

use Neucore\Entity\Role;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__.'/../vendor/autoload.php';

$routesDef = include __DIR__ . '/../config/routes.php';
$securityDef = include __DIR__ . '/../config/security.php';
$apiDef = Yaml::parse(file_get_contents(__DIR__ . '/../../web/openapi-3.yaml'));
$result = file_get_contents(__DIR__ . '/../../doc/API.md.tpl');
$roles = new ReflectionClass(Role::class);

foreach ($roles->getConstants() as $role) {
    if (!is_string($role)) {
        continue;
    }
    $apiGroups = [];
    foreach (getRoutesForRole($role, $routesDef, $securityDef) as $route) {
        $routeDef = getApiForRoute($route, $apiDef['paths']);
        if ($routeDef !== null) {
            $apiGroups[$routeDef['group']][] = [
                $route[1] . ' ' . $route[0],
                $routeDef['desc']
            ];
        }
    }

    $docGroups = [];
    foreach ($apiGroups as $name => $routes) {
        $txt = $name . ' API' . "\n";
        $paths = [];
        foreach ($routes as $data) {
            $paths[] = '- ' .$data[1] . ' `' . $data[0] . '`';
        }
        $txt .= implode("\n", $paths);
        $docGroups[] = $txt;
    }

    $result = str_replace('{' . $role . '}', implode("\n\n", $docGroups), $result);
}

file_put_contents(__DIR__ . '/../../doc/API.md', $result);

echo "wrote doc/API.md", PHP_EOL;


/**
 * @see \Tkhamez\Slim\RoleAuth\SecureRouteMiddleware::__invoke()
 */
function getRoutesForRole(string $role, array $routes, array $securityDef): array
{
    $result = [];
    foreach ($routes as $pattern => $conf) {
        foreach ($securityDef as $secured => $roles) {
            if (!str_starts_with($pattern, $secured)) {
                continue;
            }
            if (in_array($role, $roles)) {
                $apiPath = substr($pattern, strlen('/api'));
                if (isset($conf[0])) { // e.g. ['GET', callable]
                    $result[] = [$apiPath, $conf[0]];
                } else {
                    foreach (array_keys($conf) as $method) { // e.g. ['GET' => callable]
                        $result[] = [$apiPath, $method];
                    }
                }
            }
            break;
        }
    }
    return $result;
}

function getApiForRoute(array $route, array $apiPaths): ?array
{
    $method = strtolower($route[1]);
    $result = null;
    foreach ($apiPaths as $apiPath => $def) {
        if ($route[0] !== $apiPath) { // also excludes ".../esi[{path:.*}]" because of the optional path at the end
            continue;
        }
        if (!isset($def[$method])) { // should always be set
            continue;
        }
        if ($def[$method]['deprecated'] ?? false) {
            continue;
        }
        $result = [
            'group' => $def[$method]['tags'][0],
            'desc' => $def[$method]['summary'],
        ];
    }
    return $result;
}
