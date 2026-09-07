<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Query\Builders {
    use Closure;

    /**
     * Makes positional `?` placeholders survive a chain of where() calls.
     *
     * Aura keys the bind values of each where()/having() call by their
     * position within THAT call, starting at 0. Two calls in a row therefore
     * both write index 0, and the second overwrites the first:
     *
     *     ->where('a = ?', [$a])->where('b = ?', [$b])
     *     // SQL:   ... WHERE a = ? AND b = ?
     *     // binds: [0 => $b]        <- $a is gone
     *
     * Nothing warns about this. The surviving value lands on the FIRST
     * placeholder and the rest have no value at all, so the condition
     * silently compares against the wrong column and against NULL. A query
     * shaped like a guard ("is this hour already taken?", "has this person
     * already booked?") then matches nothing and reports that there is
     * nothing to guard against.
     *
     * This trait gives every positional placeholder its own generated name
     * before handing the condition to Aura, so each call's values keep their
     * own keys no matter how many calls precede it. Conditions that already
     * use named placeholders are passed through untouched, and array binds
     * keep their `IN (...)` expansion — a named key holding an array is
     * expanded by Aura exactly like a positional one.
     */
    trait PositionalBindTrait {
        /** Per-query counter, so names stay unique across every call on this object. */
        private int $garnetBindSeq = 0;

        /**
         * @param string|Closure $cond
         * @param array<array-key, mixed> $bind
         * @return $this
         */
        public function where($cond, array $bind = []) {
            [$cond, $bind] = $this->garnetNameBinds($cond, $bind);

            return parent::where($cond, $bind);
        }

        /**
         * @param string|Closure $cond
         * @param array<array-key, mixed> $bind
         * @return $this
         */
        public function orWhere($cond, array $bind = []) {
            [$cond, $bind] = $this->garnetNameBinds($cond, $bind);

            return parent::orWhere($cond, $bind);
        }

        /**
         * Rewrites each `?` in $cond into a uniquely named placeholder and
         * re-keys the matching bind value to that name.
         *
         * A placeholder with no value in $bind is left as a literal `?` on
         * purpose: QueryTools::patchArgsIndexed() reports it as an unbound
         * placeholder, which is the honest outcome — renaming it here would
         * only move the same missing value further down the pipeline.
         *
         * @param string|Closure $cond
         * @param array<array-key, mixed> $bind
         * @return array{0: string|Closure, 1: array<array-key, mixed>}
         */
        private function garnetNameBinds($cond, array $bind): array {
            if (!is_string($cond) || $bind === [] || !str_contains($cond, '?')) {
                return [$cond, $bind];
            }

            $named = [];
            $index = 0;

            $cond = preg_replace_callback('/\?/', function () use (&$index, &$named, $bind): string {
                $position = $index;
                $index += 1;

                if (!array_key_exists($position, $bind)) {
                    return '?';
                }

                $key = '_gpb' . $this->garnetBindSeq;
                $this->garnetBindSeq += 1;
                $named[$key] = $bind[$position];

                return ':' . $key;
            }, $cond);

            // Values passed under explicit names belong to placeholders this
            // rewrite never touched — carry them through unchanged.
            foreach ($bind as $key => $value) {
                if (!is_int($key)) {
                    $named[$key] = $value;
                }
            }

            return [$cond, $named];
        }
    }
}
