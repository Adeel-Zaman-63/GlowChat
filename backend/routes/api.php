<?php

declare(strict_types=1);

use GlowChat\Controllers\HealthController;
use GlowChat\Controllers\LoginController;
use GlowChat\Controllers\RegistrationController;
use GlowChat\Controllers\RefreshController;
use Slim\App;

return static function (App $app): void {
    $healthController = new HealthController();
    $registrationController = new RegistrationController();
    $loginController = new LoginController();
    $refreshController = new RefreshController();

    $app->get(
        '/api/v1/health',
        [$healthController, 'check']
    );

    $app->post(
        '/api/v1/auth/login',
        [$loginController, 'login']
    );

    $app->post(
        '/api/v1/auth/refresh',
        [$refreshController, 'refresh']
    );

    $app->post(
        '/api/v1/auth/register',
        [$registrationController, 'register']
    );
};
