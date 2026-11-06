<?php

declare(strict_types=1);

use GlowChat\Controllers\HealthController;
use Slim\App;

return static function (App $app): void {
    $healthController = new HealthController();

    $app->get(
        '/api/v1/health',
        [$healthController, 'check']
    );
};
