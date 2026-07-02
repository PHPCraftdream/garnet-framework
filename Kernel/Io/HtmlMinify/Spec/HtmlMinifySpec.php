<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Io\HtmlMinify\HtmlMinify;

describe('HtmlMinify', function (): void {
    describe('get() singleton', function (): void {
        it('returns singleton instance', function (): void {
            $minifier1 = HtmlMinify::get();
            $minifier2 = HtmlMinify::get();
            expect($minifier1)->toBe($minifier2);
        });
    });

    describe('minify()', function (): void {
        it('minifies simple HTML by removing extra whitespace', function (): void {
            $minifier = new HtmlMinify(['collapse_whitespace' => true]);
            $html = '<div>   <p>Hello</p>   </div>';
            $result = $minifier->minify($html);
            expect($result)->toContain('<div>');
            expect($result)->toContain('<p>');
            expect($result)->toContain('Hello');
            expect($result)->not->toContain('   '); // No multiple spaces
        });

        it('removes consecutive newlines', function (): void {
            $minifier = new HtmlMinify(['collapse_whitespace' => true]);
            $html = "<html>\n\n<body>\n\n<p>Text</p>\n\n</body>\n\n</html>";
            $result = $minifier->minify($html);
            expect($result)->not->toContain("\n\n");
        });

        it('preserves whitespace in preformatted content tags', function (): void {
            $minifier = new HtmlMinify(['collapse_whitespace' => true]);

            // pre tag preserves whitespace
            $html = '<pre>    indented    text   </pre>';
            $result = $minifier->minify($html);
            expect($result)->toContain('indented    text');

            // code tag preserves formatting
            $html = '<code>  var x = 1;  </code>';
            $result = $minifier->minify($html);
            expect($result)->toContain('var x = 1;');

            // script tag preserves formatting
            $html = '<script>  var x = 1;  </script>';
            $result = $minifier->minify($html);
            expect($result)->toContain('var x = 1;');

            // textarea preserves content
            $html = '<textarea>  some text   </textarea>';
            $result = $minifier->minify($html);
            expect($result)->toContain('some text');
        });

        it('handles various HTML structures', function (): void {
            $minifier = new HtmlMinify(['collapse_whitespace' => true]);

            // Nested tags
            $html = '<div><span><strong>Text</strong></span></div>';
            $result = $minifier->minify($html);
            expect($result)->toContain('Text');

            // Self-closing tags
            $html = '<img src="test.jpg" />';
            $result = $minifier->minify($html);
            expect($result)->toContain('<img');

            // Attributes with extra spaces
            $html = '<a href="http://example.com"   class="link"  >Text</a>';
            $result = $minifier->minify($html);
            expect($result)->toContain('href=');
            expect($result)->toContain('class=');
            expect($result)->toContain('Text');
        });

        it('handles edge cases', function (): void {
            $minifier = new HtmlMinify(['collapse_whitespace' => true]);

            // Empty input
            $result = $minifier->minify('');
            expect($result)->toBe('');

            // Text without tags
            $result = $minifier->minify('plain text');
            expect($result)->toContain('plain text');

            // HTML with DOCTYPE
            $html = '<!DOCTYPE html><html><body></body></html>';
            $result = $minifier->minify($html);
            expect($result)->toContain('!DOCTYPE');

            // HTML with comments
            $html = '<div><!-- comment --><p>Text</p></div>';
            $result = $minifier->minify($html);
            expect($result)->toContain('Text');
        });

        it('handles inline elements', function (): void {
            $minifier = new HtmlMinify(['collapse_whitespace' => true]);
            $html = '<strong>bold</strong> <em>italic</em>';
            $result = $minifier->minify($html);
            expect($result)->toContain('bold');
            expect($result)->toContain('italic');
        });
    });

    describe('constructor options', function (): void {
        it('handles collapse_whitespace option', function (): void {
            $minifierOn = new HtmlMinify(['collapse_whitespace' => true]);
            $minifierOff = new HtmlMinify(['collapse_whitespace' => false]);

            $html = '<div>   <p>Text</p>   </div>';

            $resultOn = $minifierOn->minify($html);
            $resultOff = $minifierOff->minify($html);

            // With collapse_whitespace, extra spaces should be removed
            expect($resultOn)->not->toContain('   ');

            // Without collapse_whitespace, some formatting may differ
            expect($resultOff)->toContain('Text');
        });
    });
});
