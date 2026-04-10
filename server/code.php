<?php
// function computeMd5(string $path, string $secret, string $expires, string $userIp): string
// {
//     $path2 = urldecode($path);
//     $toHash = $expires . $path2 . $userIp . ' ' . $secret;
//     $md5 = md5($toHash, true);
//     $md5 = base64_encode($md5);
//     $md5 = strtr($md5, '+/', '-_');
//     return str_replace('=', '', $md5);
// }
function buildSecureLink(string $baseUrl, string $path, int $expire, string $userIp = ''): string
{
    $secret = 'xswtvbbny4k';
    $path2 = urldecode($path);
    $expires = (string) $expire;
    $toHash = $expires . $path2 . $userIp . ' ' . $secret;
    $md5 = md5($toHash, true);
    $md5 = base64_encode($md5);
    $md5 = strtr($md5, '+/', '-_');
    $md5 = str_replace('=', '', $md5);
    $url = rtrim($baseUrl, '/') . $path;
    $sep = strpos($url, '?') !== false ? '&' : '?';
    return $url . $sep . 'md5=' . $md5 . '&expires=' . $expires;
}
echo "\n";

echo buildSecureLink('updl.top-gsm.ir', '/files/serve/773',  '2406974559', '');
echo "\n";
echo buildSecureLink('updl.top-gsm.ir', '/files/serve/774',  '2406974559', '');
echo "\n";
echo buildSecureLink('updl.top-gsm.ir', '/files/serve/775',  '2406974559', '');
echo "\n";
