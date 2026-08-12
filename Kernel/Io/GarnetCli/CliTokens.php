<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli;

use InvalidArgumentException;

/**
 * Token generation utilities for CLI confirmation prompts.
 *
 * All tokens use a safe alphabet without visually similar characters
 * (I, O, l, 0, 1) to prevent copy-paste and manual entry errors.
 *
 * The alphabet provides 56 distinct characters, giving ~5.8 bits per
 * character of entropy. A 4-char token has ~23 bits, a 14-char token has ~81 bits.
 */
final class CliTokens {
    /**
     * Safe alphabet: 56 chars, no I/O/l/0/1 (visual ambiguity).
     */
    private const SAFE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

    /**
     * Generate a random confirmation token using the safe alphabet.
     *
     * @param int $len Desired token length (typically 4-16 chars for human prompts).
     * @return string Random token of requested length.
     */
    public static function randToken(int $len): string {
        if ($len < 1) {
            throw new InvalidArgumentException('Token length must be at least 1');
        }

        $out = '';
        $max = strlen(self::SAFE_ALPHABET) - 1;

        for ($i = 0; $i < $len; $i++) {
            $out .= self::SAFE_ALPHABET[random_int(0, $max)];
        }

        return $out;
    }

    /**
     * Get the safe alphabet constant (for testing or documentation).
     *
     * @return string The alphabet used for token generation.
     */
    public static function alphabet(): string {
        return self::SAFE_ALPHABET;
    }

    /**
     * Check if a character is safe for confirmation tokens.
     *
     * @param string $char Single character to check.
     * @return bool True if the character is in the safe alphabet.
     */
    public static function isSafeChar(string $char): bool {
        return str_contains(self::SAFE_ALPHABET, $char);
    }
}
