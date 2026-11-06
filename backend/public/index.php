<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use GlowChat\Config\AppConfig;
use GlowChat\Exceptions\ApiErrorHandler;
use Slim\Factory\AppFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

date_default_timezone_set(AppConfig::timezone());

$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(
    AppConfig::debug(),
    true,
    true
);

$errorMiddleware->setDefaultErrorHandler(
    new ApiErrorHandler()
);

$registerRoutes = require dirname(__DIR__) . '/routes/api.php';
$registerRoutes($app);

$app->run();
