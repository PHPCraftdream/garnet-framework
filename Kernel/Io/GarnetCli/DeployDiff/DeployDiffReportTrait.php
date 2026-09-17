<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\DeployDiff {
    use PHPCraftdream\Garnet\Kernel\Io\Ssh\SshClient;

    /**
     * Что человек увидит до того, как что-то поедет.
     *
     * Предпросмотр и справка. Справка длинная намеренно: это единственное место,
     * где описаны режимы, отметки и то, как читать журнал прогона.
     */
    trait DeployDiffReportTrait {
        // -------------------------------------------------------------------------
        // 8. Preview
        // -------------------------------------------------------------------------

        private static function printPreview(array $shas, array $cat, array $plan, array $layout, SshClient $ssh, array $warns, string $appName = ''): void {
            $sshCfg = self::sshDisplay();

            echo "=== deploy:diff preview ===\n";
            echo "  host        : {$sshCfg}\n";
            echo "  remote_path : {$layout['remote_path']}\n";
            echo "  framework   : {$layout['framework_dir']}\n";
            echo "  app         : {$layout['app_dir']}\n";
            echo "  runtime     : {$layout['runtime_dir']}\n\n";

            echo 'Commits (' . count($shas) . "):\n";

            foreach ($shas as $sha) {
                $info = trim(self::gitOut(['log', '-1', '--format=%h  %ci  %s', $sha]));
                echo "  {$info}\n";
            }
            echo "\n";

            $total = count($cat['framework']) + count($cat['app']) + count($cat['runtime']) + count($cat['public'] ?? []);
            $skipCount = count($cat['skip']);
            echo "Changes ({$total} files, {$skipCount} skipped):\n";

            foreach (['framework', 'app', 'runtime', 'public'] as $bucket) {
                if (empty($cat[$bucket] ?? [])) {
                    continue;
                }

                foreach ($cat[$bucket] as $row) {
                    $statusColor = self::statusColor($row['status']);
                    $label = str_pad($bucket, 10);
                    $stat = $row['status'];
                    echo "  {$label} {$statusColor}{$stat}  {$row['path']}\n";
                    $remote = self::remoteFor($row, $layout, $bucket, $appName);
                    $arrow = $stat === 'D' ? "rm {$remote}" : $remote;
                    $extra = !empty($row['chmod_x']) ? '  (+chmod +x)' : '';
                    echo "                  → {$arrow}{$extra}\n";
                }
            }

            if ($skipCount > 0) {
                echo "  --\n";
                $names = array_map(fn ($r) => $r['path'], $cat['skip']);
                $shown = array_slice($names, 0, 5);
                $more = $skipCount > 5 ? ' (+' . ($skipCount - 5) . ' more)' : '';
                echo "  Skipped ({$skipCount}): " . implode(', ', $shown) . $more . "\n";

                // Loud callout for bundle-patched files: silent skip lured an
                // operator into ssh:put-ing the raw garnet CLI once and that
                // broke autoload on the host. Make it impossible to miss.
                $patched = array_filter(
                    $cat['skip'],
                    static fn ($r) => isset($r['reason']) && str_starts_with($r['reason'], 'patched-by-bundle'),
                );

                if (!empty($patched)) {
                    echo "\n";

                    foreach ($patched as $row) {
                        echo "  ! patched-by-bundle: {$row['path']} — run `php garnet bundle` and `ssh:put` the result.\n";
                    }
                }
            }
            echo "\n";

            echo 'Batches: ' . count($plan['mkdirs']) . ' mkdir-p dirs, ' . count($plan['uploads']) . ' uploads, ' . count($plan['deletes']) . " deletes\n";

            if (!empty($warns)) {
                echo "\n";

                foreach ($warns as $w) {
                    echo "WARN: {$w}\n";
                }
            }
        }

        private static function statusColor(string $s): string {
            return match ($s) {
                'A' => '',  // green
                'D' => '',  // red
                'M' => '',  // yellow
                default => '',
            };
        }

        private static function help(): void {
            echo <<<HELP

  php garnet deploy:diff [selector(s)] [flags]

  WHAT IT DOES
  ────────────────────────────────────────────────────────────────────────
  Takes a set of git commits, computes the union of files they touched,
  maps each path to its place on the remote host, and uploads only the
  delta via SSH/SCP. No tarballs, no `git pull` on the server, no full
  redeploy — point-fix changes in seconds.

  Default mode is DRY-RUN: you see a preview (file list,
  remote targets, batch counts) and nothing is touched on the host. Pass
  --apply to actually push.

  CONNECTION & LAYOUT
  ────────────────────────────────────────────────────────────────────────
  Host, user, identity key, strict_host_key_checking →
      Apps/<App>/WorkDir/Config*/ssh.ini
  Remote layout (where things land on the host) →
      Apps/<App>/WorkDir/Config*/deploy.ini
      remote_path   = "/var/www/u…/data/www"
      public_dir    = "example.com"                   ← docroot
      public_name   = "myapp"                          ← rebrand segment
      framework_dir = "garnet-framework"
      app_dir       = "garnet-app-myapp"
      runtime_dir   = "garnet-runtime-myapp"
  Any field is overridable per-invocation via the matching CLI flag.

  PATH MAPPING  (what goes where on the host)
  ────────────────────────────────────────────────────────────────────────
    Framework/<rest>            → <framework_dir>/<rest>
    Apps/<App>/WorkDir/<rest>   → <runtime_dir>/WorkDir/<rest>
    Apps/<App>/<rest>           → <app_dir>/<rest>
    Apps/<App>/Public/<rest>         → <public_dir>/<rest>   (rebrand applied)
    garnet  /  _shared_index.php → SKIPPED — see "Bundle-patched" below
    everything else (tests/, docs/, tooling/, FrontBuilder/, …) → skipped

  Rebrand: `assets/<appLower>/…` and `upload/<appLower>/…` segments
  are rewritten to `assets/<public_name>/…` etc., matching what `bundle`
  would have done in the dist tree.

  FRONTEND AUTO-REBUILD
  ────────────────────────────────────────────────────────────────────────
  When any of these change, `Apps/<App>/Public/assets/…` is stale:
      FrontBuilder/**             (TS/TSX/SCSS sources, rspack config)
      Framework/Bundle/Front/**   (shared islands, common JS/CSS)
      Apps/<App>/Front*/**        (per-app islands, assets)
      Apps/<App>/**/I18nDataRu.php, I18nDataEn.php  (regenerate TS i18n)

  Auto-detect is ON by default:
    dry-run: prints "frontend source changes detected — rebuild
              will run on --apply" and skips rspack.
    --apply: snapshots `Apps/<App>/Public/`, runs `php garnet build`,
              snapshots again, ships ONLY the delta (added/modified/
              deleted files). Hashed asset names mean unchanged sources
              keep the same filename and stay off the wire.

  Toggles:
    --frontend       force the rebuild even with no detected source changes
    --no-frontend    skip the rebuild even if sources changed
                     (use when you've already built locally and just
                     want to push the PHP delta)

  --full-public    re-ship every file under `Apps/<App>/Public/` plus the 4
                     *Gen.php — no git diff, no marker check, forced rspack
                     rebuild. The pre-snapshot is forced empty so every file
                     appears as an add. Use for initial deploy, disaster
                     recovery (e.g. remote /assets/ was wiped), or to guarantee
                     the host's public tree matches local exactly. Dry-run
                     prints a count of files that would ship without running
                     rspack. Combine with --apply to execute.

  BUNDLE-PATCHED FILES  (NEVER auto-shipped raw)
  ────────────────────────────────────────────────────────────────────────
  `garnet` (the root CLI) and `_shared_index.php` are rewritten by
  `php garnet bundle` before they're safe on the host (GARNET_ROOT,
  framework-dir name, autoload path). deploy:diff refuses to ship them
  raw — instead it prints a yellow callout pointing you at:
      php garnet bundle   then   php garnet ssh:put dist/<App>/<runtime>/garnet …

  RUN JOURNAL  (what happened, kept on disk)
  ────────────────────────────────────────────────────────────────────────
  Every run appends to <app>/WorkDir/LogJournal/Deploy/<date>.log:
  selectors and target, each phase with its duration (frontend rebuild,
  remote asset probe, upload), the files that landed as they land, and a
  final line with the exit code. Lines are flushed immediately, so a run
  that is killed still leaves a readable tail — and a run with NO final
  line is, by that absence, an interrupted one.

  The next run says so: it prints a warning naming the interrupted run and
  how many files it had already shipped, because a deploy that stopped
  between shipping assets and shipping the code that references them
  leaves the host in a state that looks fine from outside.

  Read it with php garnet deploy:log (--n=N, --run=ID). Secrets never
  reach the file: key material, the -i identity path and token-ish
  values are redacted. --no-log opts out.

  DEPLOY-SHA MARKER  (auto-resume from previous deploy)
  ────────────────────────────────────────────────────────────────────────
  After every successful `--apply`, the newest sha shipped is written to
  <runtime>/WorkDir/.deploy-sha on the host. The next time you run
  `deploy:diff` WITHOUT any commit selector, the file is read and used
  as `--after=SHA` automatically — you just type `php garnet deploy:diff`
  and ship everything new since the last deploy. If the remote sha is
  missing from your local history (e.g. after a rebase/force-push), the
  command stops and asks for an explicit selector.

  FILES MODE  (--file=PATH / --files=A,B,C)
  ────────────────────────────────────────────────────────────────────────
  Ship specific working-tree files without going through git. Useful for
  hot-fixes when committing first would waste time, or for pushing freshly
  built assets from `Apps/<App>/Public/` without a full commit→deploy cycle.

  Pass one or more repo-relative paths:
    --file=PATH       repeatable: --file=A --file=B --file=C
    --files=A,B,C     comma-separated alias (splits on commas)

  Behaviour:
    - Git selectors (--commit, --range, --after, --since, --from,
      --branch) are ignored. A one-line note is printed if any are present.
    - No rspack rebuild. Trusts that you already built locally (or are
      hot-fixing PHP only).
    - No remote deploy-sha marker advance. Point-deploys are surgical;
      they don't represent "the new last-known-deployed state".
    - Every listed file is treated as status 'M' (modified). Deletes are
      not supported — fall back to git-diff mode for that.
    - Auto-include: when any file is under `Apps/<App>/Public/`, the 4
      *Gen.php files are appended automatically (rebranded via shadow).
    - Rebrand is applied when public_name ≠ appName, same as the normal
      commit-based pipeline. Uses PublicPathRebrander — no duplication.
    - Paths outside known buckets (Framework/, Apps/<App>/, WorkDir/,
      Apps/<App>/Public/) produce a clear error.

  COMMIT SELECTORS  (combine freely; union of all sha is taken)
  ────────────────────────────────────────────────────────────────────────
    (none)             auto-resume from remote deploy-sha marker
                       (see above). Use this for the common case.
    --since=DATE       e.g. "2 days ago", "yesterday", "2026-05-18"
                       — passes straight to `git log --since=…`
    --from=SHA         SHA itself and every commit AFTER it (≈ SHA^..HEAD).
                       Include the SHA you name.
    --after=SHA        every commit STRICTLY AFTER the SHA (SHA..HEAD).
                       Use when you've already deployed up to and
                       including SHA, and want only what came later.
    --range=A..B       literal git range — A excluded, B included
                       (use A..B for a clean range, A...B for symmetric).
    --commit=SHA       a single commit (any rev: SHA, HEAD, HEAD~3, tag).
                       Repeatable: --commit=abc --commit=def
    --branch=NAME      $(merge-base master NAME)..NAME — everything
                       on a feature branch since it diverged from master.

  FLAGS
  ────────────────────────────────────────────────────────────────────────
    --apply            actually push (default is dry-run preview)
    --dry-run          explicit dry-run (default if --apply absent)
    --yes, -y          skip the typed-token confirmation prompt
    --no-delete        keep removed files on the host (skip D ops)
    --exclude=GLOB     fnmatch on the local path; repeatable
                       e.g. --exclude='Apps/MyApp/Migrations/*'
    --limit=N          raise the 200-file safety cap
    --verbose, -v      print every ssh/scp argv (debug)
    --strict           fail if any file ends up in 'skipped'
    --frontend / --no-frontend
                       force / skip frontend rebuild (see above)
    --reset-opcache    after a successful apply, ssh into the host and run
                       `php -r 'opcache_reset();'`. Only effective when the
                       FPM pool shares opcache with CLI (atypical on shared
                       hosting where opcache.enable_cli=0 is the default).
                       With opcache.validate_timestamps=1 (default on most
                       hosts) the new files are picked up automatically on
                       the next request anyway — this flag is for the rare
                       prod profile that disables timestamp checks.
    --no-boot-check    skip the post-apply boot smoke. By default, after a
                       successful upload the command runs `php garnet noop` on
                       the host and FAILS (exit 1) if the app no longer boots —
                       catching a half-coherent file set that leaves the site
                       returning 500 (e.g. a cherry-picked --commit that skipped
                       a dependency). Disable only when the host can't run the
                       CLI for unrelated reasons.
    --no-log           don't write a run journal (see RUN JOURNAL below)
    --public-dir=NAME / --public-name=NAME
    --framework-dir=NAME / --app-dir=NAME
    --runtime-dir=NAME
                       override deploy.ini values per invocation
    --file=PATH         repo-relative path; repeatable.
                       Activates files mode (see FILES MODE above).
    --files=A,B,C      comma-separated alias for --file=
    --full-public    re-ship every Apps/<App>/Public/ file + *Gen.php (see above)

  EXAMPLES — COPY/PASTE FRIENDLY
  ────────────────────────────────────────────────────────────────────────
  # 1. Preview the latest commit (safe, no side effects).
  php garnet deploy:diff --commit=HEAD

  # 2. Apply the latest commit. PHP + auto-rebuilt frontend delta.
  php garnet deploy:diff --commit=HEAD --apply

  # 3. Catch the server up to HEAD when last applied was abc1234.
  php garnet deploy:diff --after=abc1234 --apply

  # 4. Everything from a feature branch since it forked off master.
  php garnet deploy:diff --branch=feature/foo --apply

  # 5. PHP hot-patch — skip the rebuild entirely (you built locally).
  php garnet deploy:diff --commit=HEAD --no-frontend --apply

  # 6. Force frontend rebuild even when only PHP changed
  #    (e.g. someone touched a *Gen.php without bumping a TS file).
  php garnet deploy:diff --commit=HEAD --frontend --apply

  # 7. Deploy last 24h, skip migrations from the diff.
  php garnet deploy:diff --since="1 day ago" \\
       --exclude='Apps/MyApp/Migrations/*' --apply

  # 8. Cherry-pick two specific commits, no confirmation.
  php garnet deploy:diff --commit=abc1234 --commit=def5678 --apply --yes

  # 9. Big release; raise safety cap.
  php garnet deploy:diff --range=v1.2..HEAD --limit=500 --apply

  # 10. Investigate: what would change without applying?
  php garnet deploy:diff --after=v1.2 -v        # verbose dry-run

  # 11. Hot PHP patch + opcache reset (server with shared CLI/FPM opcache).
  php garnet deploy:diff --commit=HEAD --no-frontend --reset-opcache --apply

  # 12. Hot-fix two PHP files (rebrand auto-applied if needed)
  php garnet deploy:diff \\
       --file=Apps/MyApp/Foreground/Controllers/Foo.php \\
       --file=Apps/MyApp/Foreground/Controllers/Bar.php --apply

  # 13. Push freshly-built assets without committing — Gen.php auto-included
  php garnet deploy:diff \\
       --file=Apps/MyApp/Public/assets/myapp/gen/js/foreground.foreground.XXX.gen.js \\
       --apply

  # 14. Recovery: re-ship every Apps/<App>/Public/ file with fresh rebrand.
  # Use after wiping remote /assets/ or to guarantee parity with local.
  php garnet deploy:diff --full-public --apply --reset-opcache

  WHAT IT DOES NOT DO
  ────────────────────────────────────────────────────────────────────────
  - Run DB migrations.        Use `php garnet deploy` (full release) or
                              call `migration` over ssh.
  - Toggle maintenance mode.  Same — `php garnet deploy` handles that.
  - Reset opcache for FPM.    Only `--reset-opcache` (CLI opcache) is
                              wired up. On shared hosts where CLI and
                              FPM opcaches are separate (the common case),
                              the FPM pool needs `validate_timestamps=1`
                              (default) to pick up the new files, or a
                              manual restart of the pool.
  - Ship `garnet` / `_shared_index.php` / `.env`.  Use `bundle` + `ssh:put`.
  - Ship `WorkDir/Config/*.ini`.  Server-owned state; bundle has the
                                  same default.

  SEE ALSO
  ────────────────────────────────────────────────────────────────────────
  php garnet deploy             full release (maintenance / migrate /
                                cache / off)
  php garnet bundle             build dist tree for a fresh host bootstrap
  php garnet ssh:put <l> [r]    one-off file copy (for bundle-patched files)
  php garnet sql "<query>"      run a query against the active app's DB


HELP;
        }
    }
}
