<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\Env\TestScope;

/**
 * TestScope is the token gate that authorizes a single isolated test scope
 * against any environment (incl. prod) without the dev-dir requirement.
 *
 * The gate's inputs are all real-world side channels — an on-disk
 * `.allow_tests` file, the `HTTP_RUN_TEST_GARNET_TEAM` header, and the
 * `GARNET_TEST_TOKEN` env var — so the spec drives them directly rather
 * than stubbing. We point GarnetEnv at a throwaway app dir via the
 * `GARNET_APP_DIR` override so `tokenFilePath()` resolves into our temp
 * sandbox.
 */
describe('TestScope', function (): void {
    $TOKEN = 'sekret_token_abcdef0123456789';

    beforeEach(function () use ($TOKEN): void {
        // Throwaway "app dir" GarnetEnv will resolve the token file inside.
        $this->appDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_testscope_' . uniqid('', true);
        mkdir($this->appDir, 0o777, true);
        file_put_contents($this->appDir . DIRECTORY_SEPARATOR . '.env', "APP_NAME=MyApp\n");
        $this->tokenFile = $this->appDir . DIRECTORY_SEPARATOR . TestScope::TOKEN_FILE;

        // Resolve the app dir (and thus the token file) into our sandbox.
        $this->prevAppDirEnv = getenv('GARNET_APP_DIR');
        putenv('GARNET_APP_DIR=' . $this->appDir);

        // Start from a closed gate: no header, no env token.
        $this->prevEnvToken = getenv(TestScope::ENV_TOKEN);
        putenv(TestScope::ENV_TOKEN);
        unset($_SERVER[TestScope::HEADER_KEY]);

        $this->token = $TOKEN;
    });

    afterEach(function (): void {
        // Restore env + superglobal.
        if ($this->prevAppDirEnv === false) {
            putenv('GARNET_APP_DIR');
        } else {
            putenv('GARNET_APP_DIR=' . $this->prevAppDirEnv);
        }

        if ($this->prevEnvToken === false) {
            putenv(TestScope::ENV_TOKEN);
        } else {
            putenv(TestScope::ENV_TOKEN . '=' . $this->prevEnvToken);
        }
        unset($_SERVER[TestScope::HEADER_KEY]);

        // Best-effort sandbox cleanup.
        @unlink($this->tokenFile);
        @unlink($this->appDir . DIRECTORY_SEPARATOR . '.env');
        @rmdir($this->appDir);
    });

    describe('tokenFilePath()', function (): void {
        it('resolves into the active app dir and ends with the token file name', function (): void {
            $path = TestScope::tokenFilePath();
            expect($path)->toBe($this->tokenFile);
            expect(str_ends_with((string)$path, TestScope::TOKEN_FILE))->toBe(true);
        });
    });

    describe('isActive() — gate closed by default', function (): void {
        it('is false when no .allow_tests file exists', function (): void {
            expect(TestScope::isActive())->toBe(false);
        });

        it('is false when the token file is empty even with a matching header', function (): void {
            file_put_contents($this->tokenFile, '');
            $_SERVER[TestScope::HEADER_KEY] = $this->token;
            expect(TestScope::isActive())->toBe(false);
        });
    });

    describe('isActive() — HTTP header path', function (): void {
        it('is true when the header matches the on-disk token', function (): void {
            file_put_contents($this->tokenFile, $this->token);
            $_SERVER[TestScope::HEADER_KEY] = $this->token;
            expect(TestScope::isActive())->toBe(true);
        });

        it('is false when the header does not match', function (): void {
            file_put_contents($this->tokenFile, $this->token);
            $_SERVER[TestScope::HEADER_KEY] = $this->token . '_WRONG';
            expect(TestScope::isActive())->toBe(false);
        });

        it('trims surrounding whitespace in the on-disk token', function (): void {
            file_put_contents($this->tokenFile, "  {$this->token}\n");
            $_SERVER[TestScope::HEADER_KEY] = $this->token;
            expect(TestScope::isActive())->toBe(true);
        });
    });

    describe('isActive() — CLI env-token path', function (): void {
        it('is true when GARNET_TEST_TOKEN matches (CLI context)', function (): void {
            file_put_contents($this->tokenFile, $this->token);
            putenv(TestScope::ENV_TOKEN . '=' . $this->token);
            expect(TestScope::isActive())->toBe(true);
        });

        it('is false when GARNET_TEST_TOKEN does not match and no header is set', function (): void {
            file_put_contents($this->tokenFile, $this->token);
            putenv(TestScope::ENV_TOKEN . '=' . $this->token . '_WRONG');
            expect(TestScope::isActive())->toBe(false);
        });
    });

    describe('uploadSubDir()', function (): void {
        it('returns the live dir when the gate is closed', function (): void {
            expect(TestScope::uploadSubDir())->toBe(TestScope::UPLOAD_SUBDIR_LIVE);
        });

        it('returns the isolated test dir when the gate is open', function (): void {
            file_put_contents($this->tokenFile, $this->token);
            $_SERVER[TestScope::HEADER_KEY] = $this->token;
            expect(TestScope::uploadSubDir())->toBe(TestScope::UPLOAD_SUBDIR);
        });
    });

    describe('constants', function (): void {
        it('pins a single worker prefix and a separate upload dir', function (): void {
            expect(TestScope::WORKER_PREFIX)->toBe('test_worker_0');
            expect(TestScope::UPLOAD_SUBDIR)->toBe('UploadTest');
            expect(TestScope::UPLOAD_SUBDIR_LIVE)->toBe('Upload');
            expect(TestScope::UPLOAD_SUBDIR)->not->toBe(TestScope::UPLOAD_SUBDIR_LIVE);
        });
    });
});
