<?php

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        // Check if user is authenticated via session
        if (!isset($_SESSION['user_id'])) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode([
                'error' => 'Unauthorized. Please login first.'
            ]));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(401);
        }

        // User is authenticated, proceed to next middleware/controller
        return $handler->handle($request);
    }
}
