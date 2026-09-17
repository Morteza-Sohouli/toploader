<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Models\File;
use App\Service\FileLinkService;
use App\Service\SecureLinkService;

putenv('SECURE_LINK_SECRET=test-only-secret-with-at-least-32-characters');

function expectTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectRuntimeException(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        return;
    }

    throw new RuntimeException($message);
}

function testFile(string $path, int $id, ?string $host = null): File
{
    $file = new File();
    $file->id = $id;
    $file->name = basename($path);
    $file->path = $path;
    $file->host = $host;
    return $file;
}

$uploadRoot = FileLinkService::getNativeUploadRoot();
$newDirectory = $uploadRoot . '/new/2099/12/31';
$legacyDirectory = $uploadRoot . '/42/2098/01/02';
$newPath = $newDirectory . '/report file_abc.pdf';
$legacyPath = $legacyDirectory . '/legacy_xyz.zip';

@mkdir($newDirectory, 0755, true);
@mkdir($legacyDirectory, 0755, true);
file_put_contents($newPath, 'new');
file_put_contents($legacyPath, 'legacy');

try {
    $newUrl = FileLinkService::generateSecureFileLink(
        testFile($newPath, 100),
        'https://uploads.example.test',
        ''
    );
    expectTrue(
        str_starts_with($newUrl, 'https://uploads.example.test/uploads/new/2099/12/31/report%20file_abc.pdf?'),
        'New native files must use the canonical /uploads/new path.'
    );
    $newAdminUrl = FileLinkService::generateUnsignedFileLink(
        testFile($newPath, 100),
        'https://uploads.example.test'
    );
    expectTrue(
        $newAdminUrl === 'https://uploads.example.test/uploads/new/2099/12/31/report%20file_abc.pdf',
        'Admin native URLs must not contain signing or authentication query values.'
    );

    $legacyUrl = FileLinkService::generateSecureFileLink(
        testFile($legacyPath, 101),
        'https://uploads.example.test',
        ''
    );
    expectTrue(
        str_starts_with($legacyUrl, 'https://uploads.example.test/uploads/42/2098/01/02/legacy_xyz.zip?'),
        'Legacy native files must preserve the user-id path.'
    );

    $parts = parse_url($newUrl);
    parse_str($parts['query'] ?? '', $query);
    expectTrue(
        SecureLinkService::verifySecureLink($parts['path'], $query['md5'], $query['expires'], ''),
        'Generated native path signature must verify.'
    );
    expectTrue(
        !SecureLinkService::verifySecureLink($parts['path'] . '-tampered', $query['md5'], $query['expires'], ''),
        'A signature must not verify for a modified path.'
    );
    expectTrue(
        !SecureLinkService::verifySecureLink($parts['path'], $query['md5'], (string)(time() - 1), ''),
        'An expired signature must not verify.'
    );

    putenv('SECURE_LINK_SECRET');
    expectRuntimeException(
        static fn() => SecureLinkService::assertConfigured(),
        'A missing secure-link secret must fail closed.'
    );
    putenv('SECURE_LINK_SECRET=too-short');
    expectRuntimeException(
        static fn() => SecureLinkService::assertConfigured(),
        'A weak secure-link secret must fail closed.'
    );
    putenv('SECURE_LINK_SECRET=test-only-secret-with-at-least-32-characters');

    expectTrue(
        FileLinkService::resolveNativePath('../new/2099/12/31/report file_abc.pdf') === null,
        'Traversal outside the native upload root must be rejected.'
    );
    $legacyLexicalPath = __DIR__ . '/../src/Controllers/../../uploads/new/2099/12/31/report file_abc.pdf';
    expectTrue(
        FileLinkService::canonicalizeNativeFilePath($legacyLexicalPath) === realpath($newPath),
        'Legacy non-canonical database paths must resolve to the canonical native file path.'
    );
    $outsideNativePath = tempnam(sys_get_temp_dir(), 'topload-native-path-test-');
    try {
        expectTrue(
            FileLinkService::canonicalizeNativeFilePath($outsideNativePath) === null,
            'Paths outside the native upload root must not be accepted as native files.'
        );
    } finally {
        @unlink($outsideNativePath);
    }
    expectTrue(
        FileLinkService::normalizeHost('wp.example.test@attacker.test') === '',
        'Malformed Host headers must not be accepted for storage mapping.'
    );

    $wpRoot = sys_get_temp_dir() . '/topload-wp-link-test-' . bin2hex(random_bytes(4));
    $wpDirectory = $wpRoot . '/2026/09';
    $wpPath = $wpDirectory . '/فایل نمونه.zip';
    mkdir($wpDirectory, 0755, true);
    file_put_contents($wpPath, 'wordpress');
    $mapProperty = new ReflectionProperty(FileLinkService::class, 'wpDomainsMap');
    $mapProperty->setAccessible(true);
    $mapProperty->setValue(null, ['wp.example.test' => $wpRoot]);

    try {
        $wpUrl = FileLinkService::generateSecureFileLink(
            testFile($wpPath, 102, 'wp.example.test'),
            'https://uploads.example.test',
            ''
        );
        expectTrue(
            str_starts_with($wpUrl, 'https://wp.example.test/wp-content/uploads/2026/09/'),
            'WordPress files must use their validated recorded host and canonical prefix.'
        );
        expectTrue(
            str_contains($wpUrl, rawurlencode('فایل نمونه.zip')),
            'WordPress path segments must be URL encoded.'
        );
        $wpAdminUrl = FileLinkService::generateUnsignedFileLink(
            testFile($wpPath, 102, 'wp.example.test'),
            'https://uploads.example.test'
        );
        expectTrue(
            $wpAdminUrl === 'https://wp.example.test/wp-content/uploads/2026/09/' . rawurlencode('فایل نمونه.zip'),
            'Admin WordPress URLs must be canonical and unsigned.'
        );
        $wpTarget = FileLinkService::resolveWpPath('2026/09/فایل نمونه.zip', 'wp.example.test:443');
        expectTrue(
            $wpTarget !== null && str_starts_with($wpTarget['internal_uri'], '/internal_wp_wp_example_test/'),
            'WordPress paths must resolve only through the mapped request host.'
        );
    } finally {
        @unlink($wpPath);
        @rmdir($wpDirectory);
        @rmdir(dirname($wpDirectory));
        @rmdir($wpRoot);
    }

    $outsidePath = tempnam(sys_get_temp_dir(), 'topload-link-test-');
    file_put_contents($outsidePath, 'outside');
    try {
        $fallbackUrl = FileLinkService::generateSecureFileLink(
            testFile($outsidePath, 777),
            'https://uploads.example.test',
            ''
        );
        expectTrue(
            str_starts_with($fallbackUrl, 'https://uploads.example.test/files/serve/777?'),
            'Files outside configured roots must fall back to an ID link.'
        );
        expectTrue(
            FileLinkService::generateUnsignedFileLink(
                testFile($outsidePath, 777),
                'https://uploads.example.test'
            ) === 'https://uploads.example.test/files/serve/777',
            'Admin fallback ID URLs must not contain credentials.'
        );
    } finally {
        @unlink($outsidePath);
    }

    echo "FileLinkService tests passed\n";
} finally {
    @unlink($newPath);
    @unlink($legacyPath);
    @rmdir($newDirectory);
    @rmdir(dirname($newDirectory));
    @rmdir(dirname(dirname($newDirectory)));
    @rmdir(dirname(dirname(dirname($newDirectory))));
    @rmdir($legacyDirectory);
    @rmdir(dirname($legacyDirectory));
    @rmdir(dirname(dirname($legacyDirectory)));
    @rmdir(dirname(dirname(dirname($legacyDirectory))));
}
