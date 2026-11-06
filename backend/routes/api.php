<?php

declare(strict_types=1);

use GlowChat\Controllers\HealthController;
use GlowChat\Controllers\RegistrationController;
use Slim\App;

return static function (App $app): void {
    $healthController = new HealthController();
    $registrationController = new RegistrationController();

    $app->get(
        '/api/v1/health',
        [$healthController, 'check']
    );

    $app->post(
        '/api/v1/auth/register',
        [$registrationController, 'register']
    );
};
