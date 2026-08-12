<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\GarnetCli\Spec;

use InvalidArgumentException;
use PHPCraftdream\Garnet\Kernel\Io\GarnetCli\CliTokens;

describe(CliTokens::class, function (): void {
    it('generates tokens of correct length', function (): void {
        expect(CliTokens::randToken(1))->toHaveLength(1);
        expect(CliTokens::randToken(4))->toHaveLength(4);
        expect(CliTokens::randToken(10))->toHaveLength(10);
        expect(CliTokens::randToken(14))->toHaveLength(14);
    });

    it('generates tokens with only safe alphabet characters', function (): void {
        $safeAlphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $forbiddenChars = ['I', 'O', 'l', '0', '1'];

        // Generate multiple tokens and check all characters
        for ($i = 0; $i < 100; $i++) {
            $token = CliTokens::randToken(10);

            foreach (str_split($token) as $char) {
                // Must be in safe alphabet
                expect(str_contains($safeAlphabet, $char))->toBe(true);

                // Must not contain any forbidden character
                expect(in_array($char, $forbiddenChars, true))->toBe(false);
            }
        }
    });

    it('produces different tokens on consecutive calls', function (): void {
        $tokens = [];
        $seen = [];

        // Generate 100 tokens, all should be different
        for ($i = 0; $i < 100; $i++) {
            $token = CliTokens::randToken(8);

            expect(isset($seen[$token]))->toBe(false, "Token {$token} was duplicated on iteration {$i}");

            $seen[$token] = true;
            $tokens[] = $token;
        }

        expect(count(array_unique($tokens)))->toBe(100);
    });

    it('throws exception for invalid length (zero)', function (): void {
        expect(function (): void {
            CliTokens::randToken(0);
        })->toThrow(new InvalidArgumentException('Token length must be at least 1'));
    });

    it('throws exception for invalid length (negative)', function (): void {
        expect(function (): void {
            CliTokens::randToken(-1);
        })->toThrow(new InvalidArgumentException('Token length must be at least 1'));
    });

    it('provides access to the safe alphabet', function (): void {
        expect(CliTokens::alphabet())->toBe('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789');
    });

    it('correctly identifies safe characters', function (): void {
        $safeAlphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

        foreach (str_split($safeAlphabet) as $char) {
            expect(CliTokens::isSafeChar($char))->toBe(true);
        }

        // Forbidden characters
        expect(CliTokens::isSafeChar('I'))->toBe(false);
        expect(CliTokens::isSafeChar('O'))->toBe(false);
        expect(CliTokens::isSafeChar('l'))->toBe(false);
        expect(CliTokens::isSafeChar('0'))->toBe(false);
        expect(CliTokens::isSafeChar('1'))->toBe(false);
    });

    it('has sufficient entropy (no obvious patterns)', function (): void {
        // Generate many tokens and check character distribution
        $charCounts = array_fill(0, 56, 0); // 56 chars in safe alphabet
        $iterations = 1000;
        $tokenLen = 10;

        for ($i = 0; $i < $iterations; $i++) {
            $token = CliTokens::randToken($tokenLen);
            $alphabet = CliTokens::alphabet();

            foreach (str_split($token) as $char) {
                $pos = strpos($alphabet, $char);

                if ($pos !== false) {
                    $charCounts[$pos]++;
                }
            }
        }

        // With 10000 total character draws (1000 * 10), each position
        // should see roughly 178-179 draws. Check for reasonable spread
        // (no character should appear significantly more than expected)
        $expectedPerChar = ($iterations * $tokenLen) / 56; // ~178.6
        $totalChars = array_sum($charCounts);

        expect($totalChars)->toBe($iterations * $tokenLen);

        // Check that each character appeared at least once (very likely with 1000 draws)
        foreach ($charCounts as $count) {
            expect($count)->toBeGreaterThan(0);
        }

        // Check that distribution is reasonably even (no character appears > 3x expected)
        foreach ($charCounts as $count) {
            expect($count)->toBeLessThan($expectedPerChar * 3);
        }
    });
});
