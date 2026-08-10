<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Core\Env\Env;

describe('Env::isDevDir()', function (): void {
    beforeEach(function (): void {
        $this->prevMode = getenv(Env::ENV_MODE);
    });

    afterEach(function (): void {
        if ($this->prevMode === false) {
            putenv(Env::ENV_MODE);
        } else {
            putenv(Env::ENV_MODE . '=' . $this->prevMode);
        }
    });

    describe('GARNET_ENV override', function (): void {
        it('returns true when GARNET_ENV=dev, regardless of the filesystem', function (): void {
            putenv(Env::ENV_MODE . '=dev');
            expect(Env::isDevDir())->toBe(true);
        });

        it('returns false when GARNET_ENV=prod, regardless of the filesystem', function (): void {
            putenv(Env::ENV_MODE . '=prod');
            expect(Env::isDevDir())->toBe(false);
        });

        it('ignores an unrecognized GARNET_ENV value and falls back to the heuristic', function (): void {
            // Whatever the ancestor-walk heuristic resolves to on THIS
            // machine (it depends on IDE-marker directories that may
            // legitimately exist above the repo checkout), a garbage
            // override value must fall through to that same result rather
            // than short-circuiting to true the way "dev" does.
            putenv(Env::ENV_MODE);
            $heuristicResult = Env::isDevDir();

            putenv(Env::ENV_MODE . '=bogus');
            expect(Env::isDevDir())->toBe($heuristicResult);
        });
    });

    // These specs drive Env::hasDevMarkerAbove() directly against a
    // throwaway directory tree under the system temp dir instead of the
    // real repo checkout: this machine (like many dev machines) has a real
    // .idea directory sitting above the repo root, so asserting "no marker
    // found" against the actual filesystem is inherently flaky/environment
    // -dependent — exactly the kind of instability this fix removes from
    // production behavior. An isolated tree keeps these specs deterministic
    // on every machine and in CI.
    describe('ancestor-walk (hasDevMarkerAbove)', function (): void {
        beforeEach(function (): void {
            $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'garnet_isdevdir_spec_' . uniqid();
            $this->level1 = $this->root . DIRECTORY_SEPARATOR . 'l1';
            $this->level2 = $this->level1 . DIRECTORY_SEPARATOR . 'l2';
            $this->level3 = $this->level2 . DIRECTORY_SEPARATOR . 'l3';
            mkdir($this->level3, 0o777, true);
        });

        afterEach(function (): void {
            $marker = $this->root . DIRECTORY_SEPARATOR . '.idea';

            if (is_dir($marker)) {
                rmdir($marker);
            }
            rmdir($this->level3);
            rmdir($this->level2);
            rmdir($this->level1);
            rmdir($this->root);
        });

        it('finds a marker several marker-less ancestors above the anchor instead of stopping early', function (): void {
            // l3 (the anchor), l2 and l1 all have zero dot-entries; only
            // $root (3 levels up) gets a marker. The old implementation
            // returned false the instant it hit ANY ancestor with no
            // dot-files at all — this proves the walk now keeps going
            // past those marker-less levels instead of aborting on l2.
            mkdir($this->root . DIRECTORY_SEPARATOR . '.idea');

            expect(Env::hasDevMarkerAbove($this->level3))->toBe(true);
        });

        it('returns false when none of the ancestors (within the 6-level window) has a marker', function (): void {
            expect(Env::hasDevMarkerAbove($this->level3))->toBe(false);
        });

        it('recognizes every supported marker name, not just .idea', function (): void {
            foreach (['.idea', '.vs', '.xcodeproj', '.vscode', '.atom'] as $markerName) {
                $marker = $this->level1 . DIRECTORY_SEPARATOR . $markerName;
                mkdir($marker);

                expect(Env::hasDevMarkerAbove($this->level3))->toBe(true);

                rmdir($marker);
            }
        });

        it('does not treat an unrelated dotfile as a marker', function (): void {
            $unrelated = $this->level1 . DIRECTORY_SEPARATOR . '.gitignore';
            file_put_contents($unrelated, '');

            expect(Env::hasDevMarkerAbove($this->level3))->toBe(false);

            unlink($unrelated);
        });
    });
});
