<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Io\Cookies\StringUtilTrait;

class StringUtilClass {
    use StringUtilTrait {
        splitOnAttributeDelimiter as public;
        splitCookiePair as public;
    }
}

describe('StringUtilTrait', function (): void {
    beforeEach(function (): void {
        $this->util = new StringUtilClass();
    });

    describe('splitOnAttributeDelimiter()', function (): void {
        it('splits simple string with semicolons', function (): void {
            $result = $this->util->splitOnAttributeDelimiter('a;b;c');
            expect($result)->toBe(['a', 'b', 'c']);
        });

        it('handles whitespace around delimiters', function (): void {
            $result = $this->util->splitOnAttributeDelimiter('a ; b ; c');
            expect($result)->toBe(['a', 'b', 'c']);
        });

        it('handles multiple spaces around delimiters', function (): void {
            $result = $this->util->splitOnAttributeDelimiter('a  ;  b  ;  c');
            expect($result)->toBe(['a', 'b', 'c']);
        });

        it('returns empty array for empty string', function (): void {
            $result = $this->util->splitOnAttributeDelimiter('');
            expect($result)->toBe([]);
        });

        it('returns empty array for whitespace only', function (): void {
            $result = $this->util->splitOnAttributeDelimiter('   ;   ;   ');
            expect($result)->toBe([]);
        });

        it('handles single element', function (): void {
            $result = $this->util->splitOnAttributeDelimiter('single');
            expect($result)->toBe(['single']);
        });

        it('handles single element with trailing semicolon', function (): void {
            $result = $this->util->splitOnAttributeDelimiter('single;');
            expect($result)->toBe(['single']);
        });

        it('filters out empty elements', function (): void {
            $result = $this->util->splitOnAttributeDelimiter('a;;b;;;c');
            expect($result)->toBe(['a', 'b', 'c']);
        });

        it('handles cookie-like strings', function (): void {
            $result = $this->util->splitOnAttributeDelimiter('session=abc123; Path=/; Secure; HttpOnly');
            expect($result)->toBe(['session=abc123', 'Path=/', 'Secure', 'HttpOnly']);
        });
    });

    describe('splitCookiePair()', function (): void {
        it('splits simple key-value pair', function (): void {
            $result = $this->util->splitCookiePair('name=value');
            expect($result)->toBe(['name', 'value']);
        });

        it('URL decodes both parts', function (): void {
            $result = $this->util->splitCookiePair('name%20with%20spaces=value%20with%20spaces');
            expect($result)->toBe(['name with spaces', 'value with spaces']);
        });

        it('handles empty value', function (): void {
            $result = $this->util->splitCookiePair('name=');
            expect($result)->toBe(['name', '']);
        });

        it('handles key without equals sign', function (): void {
            $result = $this->util->splitCookiePair('name');
            expect($result)->toBe(['name', '']);
        });

        it('handles value with equals sign', function (): void {
            $result = $this->util->splitCookiePair('name=value=test=123');
            expect($result)->toBe(['name', 'value=test=123']);
        });

        it('URL decodes special characters', function (): void {
            $result = $this->util->splitCookiePair('na%20me=val%20ue');
            expect($result)->toBe(['na me', 'val ue']);
        });

        it('handles plus signs - converts to spaces', function (): void {
            $result = $this->util->splitCookiePair('name+with+plus=value+with+plus');
            expect($result)->toBe(['name with plus', 'value with plus']);
        });

        it('handles percent-encoded equals', function (): void {
            $result = $this->util->splitCookiePair('name%3Dvalue');
            expect($result)->toBe(['name=value', '']);
        });
    });
});
