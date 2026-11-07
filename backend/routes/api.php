<?php

declare(strict_types=1);

use GlowChat\Config\Database;
use GlowChat\Controllers\HealthController;
use GlowChat\Controllers\LoginController;
use GlowChat\Controllers\RegistrationController;
use GlowChat\Controllers\RefreshController;
use GlowChat\Controllers\SessionController;
use GlowChat\Controllers\ConversationController;
use GlowChat\Controllers\MessageController;
use GlowChat\Middleware\AuthMiddleware;
use GlowChat\Repositories\SessionRepository;
use GlowChat\Services\SessionAuthenticationService;
use GlowChat\Services\TokenService;
use Slim\App;

return static function (App $app): void {
    $healthController = new HealthController();
    $registrationController = new RegistrationController();
    $loginController = new LoginController();
    $refreshController = new RefreshController();
    $sessionController = new SessionController();
    $conversationController = new ConversationController();
    $messageController = new MessageController();

    $pdo = Database::connect();

    $authMiddleware = new AuthMiddleware(
        new SessionAuthenticationService(
            new SessionRepository($pdo),
            new TokenService()
        )
    );

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

    $app->get(
        '/api/v1/conversations',
        [$conversationController, 'list']
    )->add($authMiddleware);

    $app->get(
        '/api/v1/conversations/{conversationId}/messages',
        [$messageController, 'list']
    )->add($authMiddleware);

    $app->post(
        '/api/v1/conversations/{conversationId}/messages',
        [$messageController, 'send']
    )->add($authMiddleware);

    $app->post(
        '/api/v1/conversations/{conversationId}/messages/{messageId}/delivered',
        [$messageController, 'acknowledgeDelivered']
    )->add($authMiddleware);

    $app->post(
        '/api/v1/conversations/{conversationId}/messages/{messageId}/read',
        [$messageController, 'acknowledgeRead']
    )->add($authMiddleware);

    $app->post(
        '/api/v1/conversations/direct',
        [$conversationController, 'createDirect']
    )->add($authMiddleware);

    $app->post(
        '/api/v1/conversations/group',
        [$conversationController, 'createGroup']
    )->add($authMiddleware);

    $app->post(
        '/api/v1/conversations/{conversationId}/participants',
        [$conversationController, 'addGroupParticipants']
    )->add($authMiddleware);

    $app->patch(
        '/api/v1/conversations/{conversationId}/participants/{participantPublicId}/role',
        [$conversationController, 'changeGroupParticipantRole']
    )->add($authMiddleware);

    $app->delete(
        '/api/v1/conversations/{conversationId}/participants/{participantPublicId}',
        [$conversationController, 'removeGroupParticipant']
    )->add($authMiddleware);

    $app->post(
        '/api/v1/conversations/{conversationId}/leave',
        [$conversationController, 'leaveGroupConversation']
    )->add($authMiddleware);

    $app->post(
        '/api/v1/auth/logout',
        [$sessionController, 'logout']
    )->add($authMiddleware);

    $app->get(
        '/api/v1/auth/sessions',
        [$sessionController, 'list']
    )->add($authMiddleware);

    $app->post(
        '/api/v1/auth/sessions/revoke-others',
        [$sessionController, 'revokeOthers']
    )->add($authMiddleware);

    $app->delete(
        '/api/v1/auth/sessions/{sessionId}',
        [$sessionController, 'revoke']
    )->add($authMiddleware);
};
