<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Commands\Deploy\Journal\DeployJournal;

describe('DeployJournal', function (): void {
    describe('::redactLine', function (): void {
        it('drops a PEM private key block entirely — a journal gets pasted into chats and issues, '
            . 'so the bar is "cannot leak", not "usually does not"', function (): void {
                $line = "ssh failed with key -----BEGIN OPENSSH PRIVATE KEY-----\nabc\ndef\n-----END OPENSSH PRIVATE KEY----- trailing";
                $out = DeployJournal::redactLine($line);
                expect($out)->toContain('[redacted-key]');
                expect($out)->not->toContain('abc');
                expect($out)->toContain('trailing');
            });

        it('redacts the -i argument — that path points at the identity tempfile', function (): void {
            $out = DeployJournal::redactLine('ssh -o X=1 -i /tmp/garnet_ssh_abc123 -T user@host');
            expect($out)->toContain('-i [redacted]');
            expect($out)->not->toContain('garnet_ssh_abc123');
        });

        it('redacts secret-ish key=value pairs whatever the case or separator', function (): void {
            expect(DeployJournal::redactLine('opcache_token=s3cret'))->toBe('opcache_token=[redacted]');
            expect(DeployJournal::redactLine('Password: hunter2'))->toBe('Password=[redacted]');
        });

        it('leaves ordinary deploy chatter alone', function (): void {
            $line = '62 file(s) from this rebuild, 4 file(s) missing on remote';
            expect(DeployJournal::redactLine($line))->toBe($line);
        });
    });

    describe('::redactPairs', function (): void {
        it('blanks a secret value by key even when the value itself looks innocent', function (): void {
            $out = DeployJournal::redactPairs(['host' => 'user@example.com', 'identity_key' => 'plain-looking']);
            expect($out['host'])->toBe('user@example.com');
            expect($out['identity_key'])->toBe('[redacted]');
        });
    });

    describe('line formats', function (): void {
        it('writes a header naming the run, the command and the wall-clock start', function (): void {
            expect(DeployJournal::formatHeader('120000-abcdef', 'deploy:diff', '2026-09-16 12:00:00'))
                ->toBe('=== run 120000-abcdef | deploy:diff | started 2026-09-16 12:00:00');
        });

        it('reports a phase duration — the whole point is turning "it was slow" into "slow HERE"', function (): void {
            $line = DeployJournal::formatPhaseEnd('remote asset probe', 12.345, '62 asked, 58 present');
            expect($line)->toContain('remote asset probe done in 12.35s');
            expect($line)->toContain('62 asked, 58 present');
        });

        it('marks the end of a run with its exit code, which is what makes a MISSING end line '
            . 'mean "interrupted"', function (): void {
                expect(DeployJournal::formatFooter('120000-abcdef', 0, 3.2, '8 uploaded'))
                    ->toBe('=== end 120000-abcdef | ok | 3.20s | 8 uploaded');
                expect(DeployJournal::formatFooter('120000-abcdef', 1, 0.5))
                    ->toBe('=== end 120000-abcdef | exit 1 | 0.50s');
            });

        it('redacts inside a footer summary too', function (): void {
            expect(DeployJournal::formatFooter('r1', 1, 1.0, 'token=abc'))->toContain('token=[redacted]');
        });
    });

    describe('::mintRunId', function (): void {
        it('is time-prefixed so the journal reads chronologically, and unique per run', function (): void {
            $a = DeployJournal::mintRunId();
            $b = DeployJournal::mintRunId();
            expect($a)->toMatch('/^\d{6}-[0-9a-f]{6}$/');
            expect($a)->not->toBe($b);
        });
    });

    describe('writing', function (): void {
        it('appends header, phases and footer to a dated file', function (): void {
            $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_journal_' . bin2hex(random_bytes(4));
            $j = new DeployJournal($dir, 'deploy:diff');
            $j->context(['host' => 'user@example.com', 'identity_key' => 'nope']);
            $j->phaseStart('upload');
            $j->phaseEnd('upload', '3 uploaded');
            $j->finish(0, 'done');

            $file = $dir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
            $body = (string)file_get_contents($file);

            expect($body)->toContain('=== run ' . $j->runId());
            expect($body)->toContain('host: user@example.com');
            expect($body)->toContain('identity_key: [redacted]');
            expect($body)->toContain('upload done in');
            expect($body)->toContain('=== end ' . $j->runId() . ' | ok');

            @unlink($file);
            @rmdir($dir);
        });

        it('writes no end line when the run never finishes — that absence is the interrupted-run signal', function (): void {
            $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_journal_' . bin2hex(random_bytes(4));
            $j = new DeployJournal($dir, 'deploy:diff');
            $j->phaseStart('upload');

            $file = $dir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
            $body = (string)file_get_contents($file);

            expect($body)->toContain('upload …');
            expect($body)->not->toContain('=== end');

            @unlink($file);
            @rmdir($dir);
        });

        it('finishes only once, so a second call cannot fake a clean end over a real one', function (): void {
            $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_journal_' . bin2hex(random_bytes(4));
            $j = new DeployJournal($dir, 'deploy:diff');
            $j->finish(1, 'first');
            $j->finish(0, 'second');

            $file = $dir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
            $body = (string)file_get_contents($file);
            expect(substr_count($body, '=== end'))->toBe(1);
            expect($body)->toContain('exit 1');

            @unlink($file);
            @rmdir($dir);
        });

        it('reads runs back, marking one without an end line as INTERRUPTED — the state nobody '
            . 'was told about when a killed deploy left assets on the host but not the code', function (): void {
                $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_journal_' . bin2hex(random_bytes(4));
                $done = new DeployJournal($dir, 'deploy:diff');
                $done->finish(0, 'clean');
                $killed = new DeployJournal($dir, 'deploy:diff');
                $killed->note('landed in /srv/assets/: a.js, b.js, c.js');

                $runs = DeployJournal::readRuns($dir);
                expect($runs)->toHaveLength(2);
                expect($runs[0]['ended'])->toBe(true);
                expect($runs[0]['verdict'])->toBe('ok');
                expect($runs[1]['ended'])->toBe(false);
                expect($runs[1]['verdict'])->toBe('INTERRUPTED');

                $unfinished = DeployJournal::findUnfinished($dir);
                expect($unfinished)->toHaveLength(1);
                expect($unfinished[0]['id'])->toBe($killed->runId());
                expect(DeployJournal::landedCount($unfinished[0]))->toBe(3);

                @unlink($dir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log');
                @rmdir($dir);
            });

        it('excludes the current run from the unfinished list — the run doing the asking has not '
            . 'ended yet either, and must not warn about itself', function (): void {
                $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_journal_' . bin2hex(random_bytes(4));
                $current = new DeployJournal($dir, 'deploy:diff');

                expect(DeployJournal::findUnfinished($dir))->toHaveLength(1);
                expect(DeployJournal::findUnfinished($dir, $current->runId()))->toBe([]);

                @unlink($dir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log');
                @rmdir($dir);
            });

        it('не считает прерванным прогон, который честно завершился с ошибкой — иначе '
            . 'предупреждение о партиальном деплое печатается после каждого отказа '
            . 'на первом шаге и перестаёт читаться', function (): void {
                $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_journal_' . bin2hex(random_bytes(4));
                $run = new DeployJournal($dir, 'deploy:diff');
                $run->finish(1, 'error: RuntimeException: no commits selected');

                expect(DeployJournal::findUnfinished($dir))->toBe([]);

                $runs = DeployJournal::readRuns($dir);
                expect($runs[0]['ended'])->toBe(true);

                @unlink($dir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log');
                @rmdir($dir);
            });

        it('closes the matching run id when two runs interleave, not merely the latest header', function (): void {
            $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_journal_' . bin2hex(random_bytes(4));
            $a = new DeployJournal($dir, 'deploy:diff');
            $b = new DeployJournal($dir, 'deploy:diff');
            $a->finish(0, 'a done');   // older run ends AFTER the newer one started

            $runs = DeployJournal::readRuns($dir);
            $byId = [];

            foreach ($runs as $run) {
                $byId[$run['id']] = $run;
            }
            expect($byId[$a->runId()]['ended'])->toBe(true);
            expect($byId[$b->runId()]['ended'])->toBe(false);

            @unlink($dir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log');
            @rmdir($dir);
        });

        it('returns no runs for a directory that does not exist', function (): void {
            expect(DeployJournal::readRuns(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_nope_' . bin2hex(random_bytes(4))))
                ->toBe([]);
        });

        it('writes nothing at all when disabled (--no-log)', function (): void {
            $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_journal_' . bin2hex(random_bytes(4));
            $j = new DeployJournal($dir, 'deploy:diff', false);
            $j->phaseStart('upload');
            $j->finish(0);

            expect($j->isEnabled())->toBe(false);
            expect(is_dir($dir))->toBe(false);
        });
    });
});
