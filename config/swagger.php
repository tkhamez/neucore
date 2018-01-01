<?php
use Brave\Core\Application;

require_once __DIR__.'/../vendor/autoload.php';

$env = (new Application())->getEnv();

define("BRAVE_CORE_API_HOST", ($env === Application::ENV_PROD) ? "brvneucore.herokuapp.com" : "localhost");
