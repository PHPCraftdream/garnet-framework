<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\Router {
    describe('ErrorTools', function (): void {
        describe('::makeErrorJsonArr()', function (): void {
            it('creates error array without details when not local', function (): void {
                $result = ErrorTools::makeErrorJsonArr(false, 'Some error', 500);

                expect($result)->toBeAn('array');
                expect(array_key_exists('code', $result))->toBe(true);
                expect(array_key_exists('message', $result))->toBe(true);
                expect(array_key_exists('details', $result))->toBe(false);
                expect($result['code'])->toBe(500);
            });

            it('includes details when isLocal is true', function (): void {
                $error = "Line 1\nLine 2\nLine 3";
                $result = ErrorTools::makeErrorJsonArr(true, $error, 404);

                expect(array_key_exists('details', $result))->toBe(true);
                expect($result['details'])->toBeAn('array');
                expect(count($result['details']))->toBe(3);
            });

            it('trims error lines and removes empty lines', function (): void {
                $error = "  Line 1  \n\n  Line 2  \n\n";
                $result = ErrorTools::makeErrorJsonArr(true, $error, 500);

                $details = array_values($result['details']);
                expect(count($details))->toBe(2);
                expect($details[0])->toBe('Line 1');
                expect($details[1])->toBe('Line 2');
            });

            it('handles single line error', function (): void {
                $error = 'Single line error';
                $result = ErrorTools::makeErrorJsonArr(true, $error, 500);

                expect(count($result['details']))->toBe(1);
                expect($result['details'][0])->toBe('Single line error');
            });

            it('handles empty error string', function (): void {
                $error = '';
                $result = ErrorTools::makeErrorJsonArr(true, $error, 500);

                expect(array_key_exists('details', $result))->toBe(true);
                expect(count($result['details']))->toBe(0);
            });
        });

        describe('::makeErrorJson()', function (): void {
            it('creates simple error array without details', function (): void {
                $result = ErrorTools::makeErrorJson(500);

                expect($result)->toBeAn('array');
                expect(array_key_exists('code', $result))->toBe(true);
                expect(array_key_exists('message', $result))->toBe(true);
                expect(array_key_exists('details', $result))->toBe(false);
            });

            it('uses provided error code', function (): void {
                $result = ErrorTools::makeErrorJson(404);

                expect($result['code'])->toBe(404);
            });
        });

        describe('::formatErrorStrToHtml()', function (): void {
            it('replaces double spaces with nbsp', function (): void {
                $error = 'Error  message  with  spaces';
                $result = ErrorTools::formatErrorStrToHtml($error);

                expect($result)->toContain('&nbsp;&nbsp;&nbsp;&nbsp;');
            });

            it('wraps each line in div with class line', function (): void {
                $error = "Line 1\nLine 2\nLine 3";
                $result = ErrorTools::formatErrorStrToHtml($error);

                expect($result)->toContain("<div class='line'>Line 1</div>");
                expect($result)->toContain("<div class='line'>Line 2</div>");
                expect($result)->toContain("<div class='line'>Line 3</div>");
            });

            it('trims each line and removes empty lines', function (): void {
                $error = "  Line 1  \n\n  Line 2  \n\n";
                $result = ErrorTools::formatErrorStrToHtml($error);

                expect($result)->not->toContain('  Line 1  ');
                expect($result)->not->toContain('  Line 2  ');
                expect(substr_count($result, "<div class='line'>"))->toBe(2);
            });

            it('wraps text before colon in bold tags', function (): void {
                $error = "Error: Some message\nWarning: Another message";
                $result = ErrorTools::formatErrorStrToHtml($error);

                expect($result)->toContain('<b>Error</b>:');
                expect($result)->toContain('<b>Warning</b>:');
            });

            it('handles lines without colons', function (): void {
                $error = "Simple message without colon\nAnother line";
                $result = ErrorTools::formatErrorStrToHtml($error);

                expect($result)->toContain("<div class='line'>Simple message without colon</div>");
                expect($result)->toContain("<div class='line'>Another line</div>");
                expect($result)->not->toContain('<b>');
            });

            it('handles multiple colons in line', function (): void {
                $error = 'Error: Message: Details';
                $result = ErrorTools::formatErrorStrToHtml($error);

                expect($result)->toContain('<b>Error</b>: Message: Details');
            });

            it('handles single line error', function (): void {
                $error = 'Single error: message';
                $result = ErrorTools::formatErrorStrToHtml($error);

                expect($result)->toContain("<div class='line'><b>Single error</b>: message</div>");
            });
        });

        describe('::makeErrorPageHtml()', function (): void {
            it('includes error details when isLocal is true', function (): void {
                $error = "Error: Test\nLine 2";
                $result = ErrorTools::makeErrorPageHtml('Error Title', $error, true);

                expect($result)->toContain('<b>Error</b>: Test');
                expect($result)->toContain('Line 2');
                expect($result)->not->toContain('Internal server error');
            });

            it('hides error details when isLocal is false', function (): void {
                $error = 'Secret error details';
                $result = ErrorTools::makeErrorPageHtml('Error Title', $error, false);

                expect($result)->not->toContain('Secret error details');
                expect($result)->toContain('Internal server error');
            });

            it('wraps error in div with class error', function (): void {
                $result = ErrorTools::makeErrorPageHtml('Title', 'Error', true);

                expect($result)->toContain("<div class='error'>");
                expect($result)->toContain('</div>');
            });

            it('includes CSS styles for layout', function (): void {
                $result = ErrorTools::makeErrorPageHtml('Title', 'Error', true);

                expect($result)->toContain('.content {display: flex;');
                expect($result)->toContain('.error {max-width: 900px;');
                expect($result)->toContain('.line {background-color: #d4ecf3;');
            });

            it('includes page title', function (): void {
                $result = ErrorTools::makeErrorPageHtml('Test Title', 'Error', true);

                expect($result)->toContain('<title>Test Title</title>');
            });

            it('generates valid HTML structure', function (): void {
                $result = ErrorTools::makeErrorPageHtml('Title', 'Error', true);

                expect($result)->toContain('<html');
                expect($result)->toContain('<head>');
                expect($result)->toContain('<body>');
                expect($result)->toContain('</html>');
            });
        });
    });
}
