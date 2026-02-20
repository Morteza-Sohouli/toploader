<?php

namespace App\Util;

use Psr\Http\Message\ServerRequestInterface;

final class RequestHelper
{
    /**
     * Get client IP: X-Real-IP (e.g. from nginx) if present, otherwise REMOTE_ADDR.
     */
    public static function getClientIp(ServerRequestInterface $request): string
    {
        $ip = $request->getHeaderLine('X-Real-IP');
        if ($ip !== '') {
            return trim($ip);
        }
        return $request->getServerParams()['REMOTE_ADDR'] ?? '';
    }
}
