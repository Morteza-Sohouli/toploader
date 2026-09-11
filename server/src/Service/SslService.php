<?php

namespace App\Service;

class SslService
{
  private string $sslDir;

  public function __construct(?string $sslDir = null)
  {
    $this->sslDir = rtrim($sslDir ?? (getenv('SSL_DIR') ?: '/ssl'), '/');
  }

  public function getSslDir(): string
  {
    return $this->sslDir;
  }

  public function getCertPath(): string
  {
    return $this->sslDir . '/cert.pem';
  }

  public function getKeyPath(): string
  {
    return $this->sslDir . '/key.pem';
  }

  /**
   * @return array<string, mixed>
   */
  public function getInfo(): array
  {
    $certPath = $this->getCertPath();
    $keyPath = $this->getKeyPath();

    $hasCert = is_file($certPath);
    $hasKey = is_file($keyPath);

    if (!$hasCert || !$hasKey)
    {
      return [
        'configured' => false,
        'has_cert' => $hasCert,
        'has_key' => $hasKey,
      ];
    }

    $certContent = file_get_contents($certPath);
    if ($certContent === false)
    {
      throw new \RuntimeException('Unable to read certificate file');
    }

    $parsed = openssl_x509_parse($certContent);
    if ($parsed === false)
    {
      throw new \RuntimeException('Unable to parse certificate');
    }

    $expiresAt = (int)$parsed['validTo_time_t'];
    $validFrom = (int)$parsed['validFrom_time_t'];
    $now = time();
    $secondsRemaining = $expiresAt - $now;
    $daysRemaining = (int)floor($secondsRemaining / 86400);
    $isExpired = $secondsRemaining < 0;

    $domains = $this->extractDomains($parsed);

    return [
      'configured' => true,
      'has_cert' => true,
      'has_key' => $hasKey,
      'subject' => $parsed['subject']['CN'] ?? null,
      'issuer' => $this->formatIssuer($parsed['issuer'] ?? []),
      'domains' => $domains,
      'valid_from' => date('c', $validFrom),
      'expires_at' => date('c', $expiresAt),
      'days_remaining' => $daysRemaining,
      'is_expired' => $isExpired,
      'is_expiring_soon' => !$isExpired && $daysRemaining <= 30,
      'key_matches' => $this->keysMatch($certContent, file_get_contents($keyPath) ?: ''),
    ];
  }

  /**
   * @return array{success: bool, reloaded: bool, message: string}
   */
  public function saveCertificates(string $certContent, string $keyContent): array
  {
    $certContent = trim($certContent);
    $keyContent = trim($keyContent);

    if ($certContent === '' || $keyContent === '')
    {
      throw new \InvalidArgumentException('Certificate and private key are required');
    }

    if (!str_contains($certContent, '-----BEGIN CERTIFICATE-----'))
    {
      throw new \InvalidArgumentException('Invalid certificate format (expected PEM)');
    }

    if (!str_contains($keyContent, '-----BEGIN') || !str_contains($keyContent, 'PRIVATE KEY-----'))
    {
      throw new \InvalidArgumentException('Invalid private key format (expected PEM)');
    }

    $parsed = openssl_x509_parse($certContent);
    if ($parsed === false)
    {
      throw new \InvalidArgumentException('Unable to parse certificate');
    }

    $privateKey = openssl_pkey_get_private($keyContent);
    if ($privateKey === false)
    {
      throw new \InvalidArgumentException('Unable to parse private key');
    }

    $cert = openssl_x509_read($certContent);
    if ($cert === false)
    {
      throw new \InvalidArgumentException('Unable to read certificate');
    }

    if (!openssl_x509_check_private_key($cert, $privateKey))
    {
      throw new \InvalidArgumentException('Certificate and private key do not match');
    }

    if (!is_dir($this->sslDir) && !mkdir($this->sslDir, 0750, true) && !is_dir($this->sslDir))
    {
      throw new \RuntimeException('Unable to create SSL directory');
    }

    $certPath = $this->getCertPath();
    $keyPath = $this->getKeyPath();

    // Backup existing files
    if (is_file($certPath))
    {
      @copy($certPath, $certPath . '.bak');
    }
    if (is_file($keyPath))
    {
      @copy($keyPath, $keyPath . '.bak');
    }

    if (file_put_contents($certPath, $certContent . "\n") === false)
    {
      throw new \RuntimeException('Failed to write certificate file');
    }
    chmod($certPath, 0644);

    if (file_put_contents($keyPath, $keyContent . "\n") === false)
    {
      @unlink($certPath);
      throw new \RuntimeException('Failed to write private key file');
    }
    chmod($keyPath, 0600);

    $reloaded = $this->reloadNginxProxy();

    return [
      'success' => true,
      'reloaded' => $reloaded,
      'message' => $reloaded
        ? 'SSL certificates uploaded and nginx reloaded successfully'
        : 'SSL certificates uploaded. Restart the proxy container to apply changes.',
    ];
  }

  public function reloadNginxProxy(): bool
  {
    $socket = '/var/run/docker.sock';
    if (!is_readable($socket))
    {
      return false;
    }

    $filters = urlencode(json_encode(['name' => ['proxy']]));
    $containers = $this->dockerApi('GET', '/containers/json?filters=' . $filters);
    if (!is_array($containers) || empty($containers))
    {
      return false;
    }

    $containerId = $containers[0]['Id'] ?? null;
    if (!$containerId)
    {
      return false;
    }

    $exec = $this->dockerApi('POST', '/containers/' . $containerId . '/exec', [
      'AttachStdout' => true,
      'AttachStderr' => true,
      'Cmd' => ['nginx', '-s', 'reload'],
    ]);

    if (!is_array($exec) || empty($exec['Id']))
    {
      return false;
    }

    $this->dockerApi('POST', '/exec/' . $exec['Id'] . '/start', [
      'Detach' => true,
      'Tty' => false,
    ]);

    return true;
  }

  /**
   * @param array<string, mixed> $parsed
   * @return string[]
   */
  private function extractDomains(array $parsed): array
  {
    $domains = [];

    if (!empty($parsed['subject']['CN']))
    {
      $domains[] = $parsed['subject']['CN'];
    }

    if (!empty($parsed['extensions']['subjectAltName']))
    {
      $parts = explode(',', $parsed['extensions']['subjectAltName']);
      foreach ($parts as $part)
      {
        $part = trim($part);
        if (str_starts_with($part, 'DNS:'))
        {
          $domain = trim(substr($part, 4));
          if ($domain !== '' && !in_array($domain, $domains, true))
          {
            $domains[] = $domain;
          }
        }
      }
    }

    return $domains;
  }

  /**
   * @param array<string, string> $issuer
   */
  private function formatIssuer(array $issuer): string
  {
    if (isset($issuer['O']) && isset($issuer['CN']))
    {
      return $issuer['O'] . ' (' . $issuer['CN'] . ')';
    }
    return $issuer['CN'] ?? $issuer['O'] ?? 'Unknown';
  }

  private function keysMatch(string $certContent, string $keyContent): bool
  {
    if ($keyContent === '')
    {
      return false;
    }

    $cert = openssl_x509_read($certContent);
    $key = openssl_pkey_get_private($keyContent);
    if ($cert === false || $key === false)
    {
      return false;
    }

    return openssl_x509_check_private_key($cert, $key);
  }

  /**
   * @return mixed
   */
  private function dockerApi(string $method, string $path, ?array $body = null)
  {
    $ch = curl_init('http://localhost' . $path);
    curl_setopt_array($ch, [
      CURLOPT_UNIX_SOCKET_PATH => '/var/run/docker.sock',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_TIMEOUT => 10,
    ]);

    if ($body !== null)
    {
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
      curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $result = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($result === false || $httpCode >= 400)
    {
      return null;
    }

    $decoded = json_decode($result, true);
    return $decoded ?? $result;
  }
}
