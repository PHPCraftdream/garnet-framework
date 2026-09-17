<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Deploy\DeployDiff {
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\CliTokens;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Build\GarnetBundleCommand;
    use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\GarnetRunner;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use PHPCraftdream\Garnet\Kernel\Io\Ssh\SshClient;
    use RuntimeException;
    use Throwable;

    /**
     * Всё, что делается на хосте, и отметки о том, что там теперь стоит.
     *
     * Заливка батчей, проверка загрузки приложения после подмены файлов, сброс
     * opcache, синхронизация раннера и две отметки: последний выложенный коммит
     * и версия фреймворка. Вторая появилась после того, как выкладка новой альфы
     * дважды прошла «успешно», не доехав до хоста вовсе.
     */
    trait DeployDiffRemoteTrait {
        /**
         * POST /sys/opcache-reset/~run on the live site with the shared secret
         * from `opcache_token` in app.ini. The endpoint refuses anything without
         * that header — leaking the token here would be the only risk, so it's
         * only ever read from the local app.ini (kept out of version control).
         *
         * Best-effort: any failure is logged but does NOT fail the deploy. The
         * site already serves the new code; the FPM workers will pick it up on
         * their next idle recycle even without the explicit reset.
         */
        private static function tryOpcacheReset(): void {
            try {
                $appCfg = IniConfig::app();
                $token = trim($appCfg->paramString('opcache_token', ''));

                if ($token === '') {
                    echo "  · opcache reset: skipped (no `opcache_token` in app.ini)\n";

                    return;
                }
                $baseUrl = rtrim($appCfg->paramString('base_url', ''), '/');

                if ($baseUrl === '') {
                    echo "  · opcache reset: skipped (no `base_url` in app.ini)\n";

                    return;
                }

                $url = $baseUrl . '/sys/opcache-reset/~run';
                $ch = curl_init($url);

                if ($ch === false) {
                    echo "  · opcache reset: skipped (curl_init failed)\n";

                    return;
                }
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => '',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 8,
                    CURLOPT_HTTPHEADER => ['X-Garnet-Opcache-Token: ' . $token],
                ]);
                $body = curl_exec($ch);
                $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err = (string)curl_error($ch);
                curl_close($ch);

                if ($status >= 200 && $status < 300) {
                    echo "  [OK] opcache reset → {$status}\n";
                } else {
                    $tail = $err !== '' ? " ({$err})" : ($body !== false ? ' ' . mb_substr((string)$body, 0, 200) : '');
                    echo "  · opcache reset failed → {$status}{$tail}\n";
                }
            } catch (Throwable $e) {
                echo '  · opcache reset error: ' . $e->getMessage() . "\n";
            }
        }

        /**
         * Версия фреймворка, установленная локально, — в виде
         * `v0.1.0-alpha73@0a1b2c3` (ссылка укорочена, если она вообще есть).
         *
         * Читается из composer.lock приложения. Нужна затем, что в vendor-режиме
         * фреймворк приезжает на хост не как пакет: composer на хосте не
         * запускается, и обновление версии САМО не доезжает — deploy:diff шлёт
         * только файлы, затронутые коммитами, а файлы пакета в коммитах не
         * лежат. Без этой сверки выкладка новой альфы выглядела успешной, а хост
         * оставался на старой (проверено дважды за одну сессию).
         */
        private static function localFrameworkRef(): ?string {
            $lock = self::gitRepoRoot() . DIRECTORY_SEPARATOR . 'composer.lock';

            if (!is_file($lock)) {
                return null;
            }
            $raw = @file_get_contents($lock);

            if (!is_string($raw) || $raw === '') {
                return null;
            }
            $data = json_decode($raw, true);

            if (!is_array($data)) {
                return null;
            }

            foreach (['packages', 'packages-dev'] as $section) {
                foreach (($data[$section] ?? []) as $pkg) {
                    if (($pkg['name'] ?? '') !== self::FRAMEWORK_PACKAGE) {
                        continue;
                    }

                    return self::formatFrameworkRef(
                        (string)($pkg['version'] ?? ''),
                        (string)($pkg['source']['reference'] ?? $pkg['dist']['reference'] ?? ''),
                    );
                }
            }

            return null;
        }

        /** `версия@ссылка` (ссылка до 7 знаков) либо только версия. */
        private static function formatFrameworkRef(string $version, string $reference): ?string {
            $version = trim($version);

            if ($version === '') {
                return null;
            }
            $reference = trim($reference);

            return $reference === '' ? $version : $version . '@' . substr($reference, 0, 7);
        }

        /** Reads `framework_dir/.framework-ref` over SSH. Returns trimmed ref or null. */
        private static function readRemoteFrameworkRef(SshClient $ssh, array $layout): ?string {
            $remoteFile = rtrim($layout['remote_path'], '/') . '/' . $layout['framework_dir'] . '/' . self::FRAMEWORK_REF_FILE;
            $res = $ssh->run('cat ' . escapeshellarg($remoteFile) . ' 2>/dev/null || true');
            $ref = trim($res->stdout);

            return $ref === '' ? null : $ref;
        }

        /**
         * Записывает маркер версии фреймворка на хост. Вызывает тот, кто
         * выложил фреймворк ЦЕЛИКОМ (deploy:full): маркер утверждает «на хосте
         * эта версия», и точечная досылка отдельных файлов такого права не даёт.
         */
        public static function writeRemoteFrameworkRef(SshClient $ssh, array $layout, ?string $ref = null): void {
            $ref ??= self::localFrameworkRef();

            if ($ref === null || $ref === '') {
                return;
            }
            $remoteFile = rtrim($layout['remote_path'], '/') . '/' . $layout['framework_dir'] . '/' . self::FRAMEWORK_REF_FILE;
            $cmd = 'mkdir -p ' . escapeshellarg(dirname($remoteFile))
                 . " && printf '%s\\n' " . escapeshellarg($ref)
                 . ' > ' . escapeshellarg($remoteFile);
            $res = $ssh->run($cmd);
            $err = trim($res->stderr);

            if ($err !== '') {
                echo "\nwarn: could not write remote framework ref: {$err}\n";

                return;
            }
            echo "  remote framework ref → {$ref}\n";
        }

        /**
         * Сверяет версию фреймворка на хосте с локальной и говорит вслух, если
         * они разошлись. Предупреждает, не блокирует: точечная досылка файлов
         * пакета — законный способ жить, если знать, что делаешь.
         *
         * @return list<string>
         */
        public static function frameworkRefWarnings(?string $localRef, ?string $remoteRef, bool $composerTouched): array {
            if ($localRef === null) {
                return [];
            }

            if ($remoteRef === null) {
                if (!$composerTouched) {
                    return [];
                }

                return [
                    'версия пакета фреймворка изменилась, а на хосте нет отметки о выложенной версии. '
                    . 'composer на хосте не запускается, и файлы пакета в коммитах не лежат — сам он не приедет. '
                    . 'Выложите фреймворк целиком: php garnet deploy:full (она же поставит отметку).',
                ];
            }

            if ($remoteRef === $localRef) {
                return [];
            }

            return [
                "фреймворк на хосте — {$remoteRef}, локально — {$localRef}. deploy:diff шлёт только файлы, "
                . 'затронутые коммитами, поэтому смена версии пакета так не доедет. Либо php garnet deploy:full, '
                . 'либо точечно: --files=vendor/phpcraftdream/garnet-framework/<путь>.',
            ];
        }

        /** Reads `runtime_dir/WorkDir/.deploy-sha` over SSH. Returns trimmed sha or null. */
        private static function readRemoteDeploySha(SshClient $ssh, array $layout): ?string {
            $remoteFile = rtrim($layout['remote_path'], '/') . '/' . $layout['runtime_dir'] . '/' . self::DEPLOY_SHA_FILE;
            $cmd = 'cat ' . escapeshellarg($remoteFile) . ' 2>/dev/null || true';
            $res = $ssh->run($cmd);
            $sha = trim($res->stdout);

            if ($sha === '' || !preg_match('/^[0-9a-f]{7,40}$/i', $sha)) {
                return null;
            }

            return $sha;
        }

        /**
         * Writes the sha to `runtime_dir/WorkDir/.deploy-sha` over SSH. Non-fatal
         * on failure. Also called by GarnetDeployFullCommand after a successful
         * ship+migrate — that command ships via bundle+atomic-swap, not this
         * class's own git-diff pipeline, so it has no other way to advance the
         * marker deploy:diff's own auto-resume selector depends on.
         */
        public static function writeRemoteDeploySha(SshClient $ssh, array $layout, string $sha): void {
            $sha = trim($sha);

            if ($sha === '') {
                return;
            }
            $remoteFile = rtrim($layout['remote_path'], '/') . '/' . $layout['runtime_dir'] . '/' . self::DEPLOY_SHA_FILE;
            $remoteDir = dirname($remoteFile);
            // mkdir -p is harmless if the dir already exists; WorkDir always should.
            $cmd = 'mkdir -p ' . escapeshellarg($remoteDir)
                 . " && printf '%s\\n' " . escapeshellarg($sha)
                 . ' > ' . escapeshellarg($remoteFile);
            $res = $ssh->run($cmd);
            $err = trim($res->stderr);

            if ($err !== '') {
                echo "\nwarn: could not write remote deploy-sha: {$err}\n";

                return;
            }
            echo "  remote deploy-sha → {$sha}\n";
        }

        /**
         * Post-apply safety net: boot the app on the host (`php garnet noop`) and
         * confirm it doesn't fatal. Catches the most damaging failure mode — a
         * shipped file set that leaves the app unbootable (e.g. a file referencing
         * a class from an earlier commit that was never deployed, which is exactly
         * what a cherry-picked `--commit=` can do). Returns true when clean.
         */
        private static function remoteBootCheck(SshClient $ssh, array $layout): bool {
            $target = rtrim($layout['remote_path'], '/') . '/' . $layout['runtime_dir'];
            $cmd = 'cd ' . escapeshellarg($target) . ' && php garnet noop 2>&1';

            echo "\nboot check  (php garnet noop on host)\n";
            $res = $ssh->run($cmd);

            if ($res->exitCode === 0) {
                echo "  [OK] app boots cleanly on the host\n";

                return true;
            }

            $out = trim($res->stdout . "\n" . $res->stderr);
            echo "  [FAIL] the app does NOT boot after this deploy — the site is\n";
            echo "         very likely returning HTTP 500.\n";

            foreach (array_filter(explode("\n", $out)) as $line) {
                echo "    {$line}\n";
            }
            echo "  Likely cause: a shipped file references code from an earlier\n";
            echo "  commit that was never deployed. Re-run with NO selector to ship every\n";
            echo "  commit since the remote marker:  php garnet deploy:diff --apply\n";
            echo "  …or roll back by re-deploying the previous commit's files.\n";

            return false;
        }

        /**
         * Keep the remote runtime `garnet` dispatcher in sync with the repo's
         * `./garnet` on every apply. The runtime dispatcher is a path-rewritten
         * copy that deploy:diff otherwise never touches, so newly-added
         * garnet-level commands (cache, snapshot, maintenance:remote, …) used to
         * silently 404 on the box ("command not found") until hand-patched.
         *
         * Uploads to `garnet.new`, validates it actually boots (`php garnet.new
         * noop`), and only then atomically swaps it in — a broken regenerate can
         * never replace a working dispatcher. Non-fatal: a failure leaves the
         * current dispatcher untouched.
         */
        private static function syncRemoteRuntimeGarnet(SshClient $ssh, array $layout, string $appName): void {
            $isVendorMode = self::isVendorMode();
            $garnetBin = $isVendorMode
                ? GarnetRunner::$appDir . DIRECTORY_SEPARATOR . 'garnet'
                : GARNET_ROOT . DIRECTORY_SEPARATOR . 'garnet';
            $contents = GarnetBundleCommand::renderRuntimeGarnet(
                $garnetBin,
                (string)$layout['app_dir'],
                $appName,
                (string)$layout['framework_dir'],
                $isVendorMode,
            );

            if ($contents === null) {
                return; // no repo ./garnet to mirror
            }

            $tmp = tempnam(sys_get_temp_dir(), 'garnet_rt_');
            file_put_contents($tmp, $contents);

            $rd = rtrim($layout['remote_path'], '/') . '/' . $layout['runtime_dir'];
            $remoteNew = $rd . '/garnet.new';

            echo "\nruntime dispatcher  (sync ./garnet routes to the host)\n";
            $put = $ssh->put($tmp, $remoteNew, ['stream' => false]);
            @unlink($tmp);

            if (!$put->ok()) {
                echo "  · upload failed (exit {$put->exitCode}) — current dispatcher kept\n";

                return;
            }

            $g = escapeshellarg($remoteNew);
            $gFinal = escapeshellarg($rd . '/garnet');
            $cmd = "php {$g} noop >/dev/null 2>&1 && chmod 755 {$g} && mv {$g} {$gFinal} && echo SYNC_OK || { rm -f {$g}; echo SYNC_FAIL; }";
            $res = $ssh->run($cmd, ['stream' => false]);

            if (str_contains($res->stdout, 'SYNC_OK')) {
                echo "  [OK] runtime garnet dispatcher updated (routes current)\n";
            } else {
                echo "  · new dispatcher failed its boot check — kept the existing one\n";
            }
        }

        /**
         * Best-effort opcache invalidation over SSH. Runs `php -r 'opcache_reset();'`
         * on the host — only effective if the FPM pool shares opcache with CLI
         * (uncommon on shared hosting, where opcache.enable_cli=0 is the default).
         * Prints the host's stdout so the operator sees what actually happened.
         */
        private static function resetRemoteOpcache(SshClient $ssh, array $layout): void {
            $remoteRoot = rtrim($layout['remote_path'], '/');
            $runtime = $layout['runtime_dir'];
            // cd into runtime so the host has access to `.env` if any helper
            // tools want it later; then run a one-liner that returns "ok",
            // "noop" or a PHP error string we can read back.
            $php = "if (function_exists('opcache_reset')) { var_export(opcache_reset()); } else { echo 'noop'; }";
            $cmd = 'cd ' . escapeshellarg($remoteRoot . '/' . $runtime) . ' && php -r ' . escapeshellarg($php);

            echo "\nopcache reset (best-effort)\n";
            $res = $ssh->run($cmd);
            $out = trim($res->stdout . $res->stderr);

            if ($out === '') {
                echo "  (no output)\n";
            } else {
                echo "  host: {$out}\n";
            }

            if ($out === 'noop') {
                echo "  hint: CLI opcache disabled on host — restart php-fpm or hit any web URL\n";
                echo "        to invalidate by mtime (opcache.validate_timestamps=1 is the default).\n";
            }
        }

        private static function sshDisplay(): string {
            $ssh = IniConfig::ssh();

            return $ssh->paramString('user', '?') . '@' . $ssh->paramString('host', '?') . ':' . $ssh->paramInt('port', 22);
        }

        // -------------------------------------------------------------------------
        // 9. Confirm
        // -------------------------------------------------------------------------

        private static function confirm(bool $skipToken): bool {
            if ($skipToken) {
                echo "Proceeding (--yes, no prompt).\n";

                return true;
            }
            $token = CliTokens::randToken(4);
            echo "Type {$token} to confirm: ";
            $entered = trim((string)fgets(STDIN));

            return $entered === $token;
        }

        // -------------------------------------------------------------------------
        // 10. Apply batches
        // -------------------------------------------------------------------------

        private static function applyBatches(SshClient $ssh, array $plan, bool $verbose, bool $noDelete): array {
            $start = microtime(true);
            $uploaded = 0;
            $deleted = 0;
            $errors = [];
            $chmodFix = [];   // remote paths needing +x after upload

            // 1. mkdir-p (chunk)
            if (!empty($plan['mkdirs'])) {
                foreach (array_chunk($plan['mkdirs'], self::MKDIR_CHUNK) as $i => $chunk) {
                    $args = implode(' ', array_map(fn ($d) => "'" . str_replace("'", "'\\''", $d) . "'", $chunk));
                    echo '  [mkdir-p ' . ($i + 1) . '] ' . count($chunk) . ' dirs ... ';

                    if ($verbose) {
                        echo "\n    ssh mkdir -p {$args}\n  ";
                    }
                    $r = $ssh->run("mkdir -p {$args}", ['stream' => false]);

                    if (!$r->ok()) {
                        echo "FAIL (exit {$r->exitCode})\n";
                        $errors[] = 'mkdir-p chunk #' . ($i + 1) . ': ' . trim($r->stderr);
                    } else {
                        echo "OK\n";
                    }
                }
            }

            // 2. Uploads, grouped by remote_dir, chunked
            $byDir = [];

            foreach ($plan['uploads'] as $up) {
                $byDir[$up['remote_dir']][] = $up;
            }
            $groupIdx = 0;
            $groupTotal = count($byDir);

            foreach ($byDir as $rDir => $items) {
                $groupIdx++;

                foreach (array_chunk($items, self::SCP_CHUNK) as $chunk) {
                    $localFiles = array_map(fn ($u) => $u['local'], $chunk);
                    echo "  [scp {$groupIdx}/{$groupTotal}] " . count($chunk) . " files → {$rDir}/ ... ";

                    if ($verbose) {
                        echo "\n    " . implode(' ', $localFiles) . "\n  ";
                    }

                    $ok = self::scpMulti($ssh, $localFiles, $rDir, $verbose);

                    if (!$ok['ok']) {
                        echo "FAIL\n";

                        foreach ($ok['errors'] as $e) {
                            $errors[] = $e;
                        }
                    } else {
                        echo "OK\n";
                        $uploaded += count($chunk);
                        // Record landed files AS THEY LAND, not in the final
                        // summary: a run killed mid-flight never reaches the
                        // summary, and "what is already on the host" is exactly
                        // what has to be answerable afterwards. A half-shipped
                        // deploy is the dangerous state — it is how a page ends up
                        // referencing a bundle that was never uploaded.
                        self::journal()->note('landed in ' . $rDir . '/: ' . implode(', ', array_map(
                            static fn (array $u): string => basename((string)$u['remote']),
                            $chunk,
                        )));

                        // Collect chmod +x targets
                        foreach ($chunk as $up) {
                            if (!empty($up['chmod_x'])) {
                                $chmodFix[] = $up['remote'];
                            }
                        }
                    }
                }
            }

            // 3. Deletes (chunked)
            if (!$noDelete && !empty($plan['deletes'])) {
                foreach (array_chunk($plan['deletes'], self::RM_CHUNK) as $i => $chunk) {
                    $args = implode(' ', array_map(fn ($d) => "'" . str_replace("'", "'\\''", $d['remote']) . "'", $chunk));
                    echo '  [rm ' . ($i + 1) . '] ' . count($chunk) . ' files ... ';

                    if ($verbose) {
                        echo "\n    ssh rm -f {$args}\n  ";
                    }
                    $r = $ssh->run("rm -f {$args}", ['stream' => false]);

                    if (!$r->ok()) {
                        echo "FAIL (exit {$r->exitCode})\n";
                        $errors[] = 'rm chunk #' . ($i + 1) . ': ' . trim($r->stderr);
                    } else {
                        echo "OK\n";
                        $deleted += count($chunk);
                    }
                }
            }

            // 4. chmod +x for garnet
            if (!empty($chmodFix)) {
                $args = implode(' ', array_map(fn ($p) => "'" . str_replace("'", "'\\''", $p) . "'", $chmodFix));
                echo '  [chmod +x] ' . count($chmodFix) . ' file(s) ... ';
                $r = $ssh->run("chmod +x {$args}", ['stream' => false]);

                if (!$r->ok()) {
                    echo "FAIL (exit {$r->exitCode})\n";
                    $errors[] = 'chmod +x: ' . trim($r->stderr);
                } else {
                    echo "OK\n";
                }
            }

            $duration = round(microtime(true) - $start, 1);

            return compact('uploaded', 'deleted', 'errors', 'duration');
        }

        /**
         * Run scp with multiple local sources into one remote dir.
         * Returns ['ok' => bool, 'errors' => string[]].
         */
        private static function scpMulti(SshClient $ssh, array $locals, string $remoteDir, bool $verbose): array {
            // Build base scp argv via SshClient with one of the locals, then patch
            // We can't easily get the multi-arg scp via SshClient::put — it's
            // single-file. So construct argv inline by replicating buildPutArgv's
            // base-flags portion via single-file put for the first local, then
            // splice extra locals.
            $argv = $ssh->buildPutArgv($locals[0], $remoteDir . '/');
            // argv ends with: ..., $locals[0], "{$user}@{$host}:{$remoteDir}/"
            // Insert extra locals before the destination
            $dest = array_pop($argv);

            foreach (array_slice($locals, 1) as $extra) {
                $argv[] = $extra;
            }
            $argv[] = $dest;

            // Execute via proc_open (capture mode for clean output)
            $sshCfg = SshClient::fromIniConfig();  // for tempkey handling — but we already have $ssh
            // Use the existing $ssh.execute path indirectly: call ::run on the SshClient with a dummy
            // and re-use its tempkey logic? Simpler: use proc_open here directly.

            $tempfile = '';
            // Resolve identity_key tempfile
            $ssh_cfg = IniConfig::ssh();
            $ikey = $ssh_cfg->paramString('identity_key', '');

            if ($ikey !== '') {
                $tempfile = tempnam(sys_get_temp_dir(), 'garnet_ssh_');
                file_put_contents($tempfile, $ikey);
                chmod($tempfile, 0o600);
                $argv = array_map(
                    fn ($a) => $a === '<REDACTED-tempfile-path-to-be-generated>' ? $tempfile : $a,
                    $argv
                );
            }

            try {
                $desc = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ];
                $proc = proc_open($argv, $desc, $pipes, null, null, ['bypass_shell' => true]);

                if ($proc === false) {
                    return ['ok' => false, 'errors' => ['scp: proc_open failed']];
                }
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $exit = proc_close($proc);

                if ($exit !== 0) {
                    $msg = trim($stderr) !== '' ? trim($stderr) : "scp exit {$exit}";

                    return ['ok' => false, 'errors' => ["scp → {$remoteDir}/: {$msg}"]];
                }

                return ['ok' => true, 'errors' => []];
            } finally {
                if ($tempfile !== '' && file_exists($tempfile)) {
                    unlink($tempfile);
                }
            }
        }

        private static function fail(string $msg): void {
            throw new RuntimeException($msg);
        }
    }
}
