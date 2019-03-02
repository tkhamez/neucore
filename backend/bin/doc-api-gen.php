#!/usr/bin/env php
<?php declare(strict_types=1);

require_once __DIR__.'/../vendor/autoload.php';

$routesDef = include __DIR__ . '/../config/routes.php';
$securityDef = include __DIR__ . '/../config/security.php';
$apiDef = json_decode(file_get_contents(__DIR__ . '/../../web/swagger.json'), true);
$result = file_get_contents(__DIR__ . '/../../doc/API.md.tpl');
$roles = new ReflectionClass(\Brave\Core\Entity\Role::class);

foreach ($roles->getConstants() as $role) {
    $apiGroups = [];
    foreach (getRoutesForRole($role, $routesDef, $securityDef) as $route) {
        $routeDef = getApiForRoute($route, $apiDef['paths']);
        if ($routeDef !== null) {
            $apiGroups[$routeDef['group']][] = [
                $route[0],
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
 * @@see \Tkhamez\Slim\RoleAuth\SecureRouteMiddleware::__invoke()
 */
function getRoutesForRole(string $role, array $routes, array $securityDef): array
{
    $result = [];
    foreach ($routes as $pattern => $conf) {
        foreach ($securityDef as $secured => $roles) {
            if (strpos($pattern, $secured) !== 0) {
                continue;
            }
            if (in_array($role, $roles) && isset($conf[0])) { // skips "/api/app/v1/esi"
                $apiPath = substr($pattern, strlen('/api'));
                $result[] = [$apiPath, $conf[0]];
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
        if ($route[0] !== $apiPath) {
            continue;
        }
        if (! isset($def[$method])) { // should always be set
            continue;
        }
        $result = [
            'group' => $def[$method]['tags'][0],
            'desc' => $def[$method]['summary'],
        ];
    }
    return $result;
}
