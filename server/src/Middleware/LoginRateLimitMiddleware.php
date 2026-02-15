<?php

namespace App\Middleware;

use App\Service\LoginRateLimitStore;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as SlimResponse;

class LoginRateLimitMiddleware implements MiddlewareInterface
{
    private static function getClientIp(Request $request): string
    {
        return $request->getServerParams()['REMOTE_ADDR'] ?? '';
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $ip = self::getClientIp($request);

        if (LoginRateLimitStore::isBlocked($ip))
        {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode([
                'error' => 'Too many failed login attempts. Try again later.'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(429);
        }

        return $handler->handle($request);
    }
}
