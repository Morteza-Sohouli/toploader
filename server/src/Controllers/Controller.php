<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;

abstract class Controller
{
    protected function json(Response $response, $data, int $status = 200): Response
    {
        $payload = json_encode($data);
        $response->getBody()->write($payload);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
