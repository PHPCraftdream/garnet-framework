<?php declare(strict_types=1);

// PHPStan-only stub for the codegen'd asset-bridge classes (see
// Templates/CodeFiles/Class.template + FrontBuilder/build/PhpClassGeneratorPlugin.ts).
// `php garnet build`/`prepare` writes the real Bundle/FrameworkJsGen.php and
// Bundle/FrameworkCssGen.php at build time — one static method per emitted
// JS/CSS chunk, so the exact method set is only known after a real rspack
// build and the files are gitignored (*Gen.php). PHPStan's own CI job
// deliberately never runs a frontend build (it's meant to stay fast/Node-free),
// so it can't see the real generated classes — hence this stub, scoped to
// only the methods the framework's OWN Kernel/Bundle code actually calls.
// Never `require`d at runtime; phpstan.neon loads it via `scanFiles` only.

namespace PHPCraftdream\Garnet\Bundle {
    class FrameworkJsGen {
        public static function framework(): string {
            return '';
        }

        public static function auth(): string {
            return '';
        }

        public static function gridtable(): string {
            return '';
        }

        public static function vendor_react(): string {
            return '';
        }

        public static function vendor_other(): string {
            return '';
        }
    }

    class FrameworkCssGen {
        public static function framework(): string {
            return '';
        }
    }
}
