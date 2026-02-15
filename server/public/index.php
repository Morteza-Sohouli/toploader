<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Factory\AppFactory;
use App\Controllers\UserController;
use App\Controllers\FileController;
use App\Controllers\StatsController;
use App\Controllers\AdminController;
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;

if (session_status() === PHP_SESSION_NONE)
{
    // Allow session cookie to be sent from Capacitor (cross-origin). Required for mobile app.
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'None',
    ]);
    session_start();
}

$app = AppFactory::create();

// Add routing middleware
$app->addRoutingMiddleware();

// Add body parsing middleware
$app->addBodyParsingMiddleware();

// Add error middleware
$app->addErrorMiddleware(true, true, true);

// ! only for development!!!!
// Add CORS middleware for development (must be last to execute first)
$app->add(function (Request $request, RequestHandler $handler)
{
    $allowedOrigins = array_filter([
        $_ENV['UPLOAD_URL'] ?? null,
        'http://localhost:3000',
        'https://localhost',
    ]);
    $origin = $request->getHeaderLine('Origin');
    $allowOrigin = in_array($origin, $allowedOrigins, true) ? $origin : null;

    // Handle preflight OPTIONS request
    if ($request->getMethod() === 'OPTIONS')
    {
        $response = new \Slim\Psr7\Response();
        $response = $response->withStatus(200);
    }
    else
    {
        $response = $handler->handle($request);
    }
    $response = $response->withHeader('Access-Control-Max-Age', '86400');
    if ($allowOrigin !== null)
    {
        $response = $response->withHeader('Access-Control-Allow-Origin', $allowOrigin);
    }
    $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
    $response = $response->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization');
    $response = $response->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');

    return $response;
});

// Basic Route
$app->get('/', function (Request $request, Response $response, $args)
{
    $response->getBody()->write("Welcome to Slim + Eloquent Project!");
    return $response;
});

// User Routes
$app->group('/users', function ($group)
{
    $group->get('/', [UserController::class, 'index']);
    $group->post('/register', [UserController::class, 'register']);
    $group->post('/login', [UserController::class, 'login']);
    $group->post('/logout', [UserController::class, 'logout']);
    $group->get('/me', [UserController::class, 'me'])->add(AuthMiddleware::class);
    // $group->get('/{id}', [UserController::class, 'show']);
});

// File Routes (protected by auth middleware)
$app->group('/files', function ($group)
{
    $group->post('/upload', [FileController::class, 'upload']);
    $group->get('/valid-mime-types', [FileController::class, 'getValidMimeTypes']);
    $group->get('/uploads', [FileController::class, 'getUserUploads']);
})->add(AuthMiddleware::class);

$app->get('/files/serve', [FileController::class, 'serveFile']);
$app->get('/files/serve/{id}', [FileController::class, 'serveFile']);

// WordPress wp-content/uploads: serve files and log downloads (path e.g. 2026/02/filename.rar)
$app->get('/wp-content/uploads/{path:.+}', [FileController::class, 'serveWpContentFile']);
$app->get('/uploads/{path:.+}', [FileController::class, 'serveWpContentFile']);

// Stats Route (protected by auth middleware)
$app->get('/stats', [StatsController::class, 'index'])->add(AuthMiddleware::class);

// Admin Routes (protected by admin middleware)
$app->group('/admin', function ($group)
{
    // File management
    $group->get('/files', [AdminController::class, 'getAllFiles']);
    $group->delete('/files/{id}', [AdminController::class, 'deleteFile']);

    // User management
    $group->get('/users', [AdminController::class, 'getAllUsers']);
    $group->post('/users', [AdminController::class, 'createUser']);
    $group->put('/users/{id}', [AdminController::class, 'updateUser']);
    $group->delete('/users/{id}', [AdminController::class, 'deleteUser']);

    // Stats
    $group->get('/stats', [AdminController::class, 'getStats']);
})->add(AdminMiddleware::class);

$app->run();
