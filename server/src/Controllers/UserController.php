<?php

namespace App\Controllers;

use App\Models\User;
use App\Service\LoginRateLimitStore;
use App\Util\RequestHelper;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController extends Controller
{
    private function index(Request $request, Response $response): Response
    {
        $users = User::all();
        return $this->json($response, $users);
    }

    private function show(Request $request, Response $response, array $args): Response
    {
        $user = User::find($args['id']);
        if (!$user)
        {
            return $this->json($response, ['error' => 'User not found'], 404);
        }
        return $this->json($response, $user);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password))
        {
            return $this->json($response, ['error' => 'Username and password are required'], 400);
        }

        $user = User::where('username', $username)->first();

        if (!$user || !password_verify($password, $user->password))
        {
            $ip = RequestHelper::getClientIp($request);
            LoginRateLimitStore::recordFailedAttempt($ip);
            return $this->json($response, ['error' => 'Invalid credentials'], 401);
        }

        LoginRateLimitStore::reset(RequestHelper::getClientIp($request));
        $_SESSION['user_id'] = $user->id;
        $_SESSION['username'] = $user->username;

        return $this->json($response, [
            'message' => 'Login successful',
            'user' => [
                'id' => $user->id,
                'username' => $user->username
            ]
        ]);
    }

    public function logout(Request $request, Response $response): Response
    {
        session_destroy();
        return $this->json($response, ['message' => 'Logged out successfully']);
    }

    public function me(Request $request, Response $response): Response
    {

        $user = User::find($_SESSION['user_id']);
        if (!$user)
        {
            return $this->json($response, ['error' => 'User not found'], 404);
        }

        return $this->json($response, $user);
    }

    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password))
        {
            return $this->json($response, ['error' => 'Username and password are required'], 400);
        }

        if (User::where('username', $username)->exists())
        {
            return $this->json($response, ['error' => 'User already exists'], 400);
        }

        $user = User::create([
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'allowedFileTypes' => 'jpg,png,pdf'
        ]);

        return $this->json($response, [
            'message' => 'User registered successfully',
            'user' => $user
        ], 201);
    }
}
