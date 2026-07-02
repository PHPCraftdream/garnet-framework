<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Io\Router\CodesTool;

describe('CodesTool', function (): void {
    describe('getReasonByCode()', function (): void {
        it('returns reason for informational codes (1xx)', function (): void {
            expect(CodesTool::getReasonByCode(100))->toBe('Continue');
            expect(CodesTool::getReasonByCode(101))->toBe('Switching Protocols');
        });

        it('returns reason for success codes (2xx)', function (): void {
            expect(CodesTool::getReasonByCode(200))->toBe('OK');
            expect(CodesTool::getReasonByCode(201))->toBe('Created');
            expect(CodesTool::getReasonByCode(202))->toBe('Accepted');
            expect(CodesTool::getReasonByCode(203))->toBe('Non-Authoritative Information');
            expect(CodesTool::getReasonByCode(204))->toBe('No Content');
            expect(CodesTool::getReasonByCode(205))->toBe('Reset Content');
            expect(CodesTool::getReasonByCode(206))->toBe('Partial Content');
        });

        it('returns reason for redirect codes (3xx)', function (): void {
            expect(CodesTool::getReasonByCode(300))->toBe('Multiple Choices');
            expect(CodesTool::getReasonByCode(301))->toBe('Moved Permanently');
            expect(CodesTool::getReasonByCode(302))->toBe('Found');
            expect(CodesTool::getReasonByCode(303))->toBe('See Other');
            expect(CodesTool::getReasonByCode(304))->toBe('Not Modified');
            expect(CodesTool::getReasonByCode(305))->toBe('Use Proxy');
            expect(CodesTool::getReasonByCode(307))->toBe('Temporary Redirect');
        });

        it('returns reason for client error codes (4xx)', function (): void {
            expect(CodesTool::getReasonByCode(400))->toBe('Bad Request');
            expect(CodesTool::getReasonByCode(401))->toBe('Unauthorized');
            expect(CodesTool::getReasonByCode(402))->toBe('Payment Required');
            expect(CodesTool::getReasonByCode(403))->toBe('Forbidden');
            expect(CodesTool::getReasonByCode(404))->toBe('Not Found');
            expect(CodesTool::getReasonByCode(405))->toBe('Method Not Allowed');
            expect(CodesTool::getReasonByCode(406))->toBe('Not Acceptable');
            expect(CodesTool::getReasonByCode(407))->toBe('Proxy Authentication Required');
            expect(CodesTool::getReasonByCode(408))->toBe('Request Timeout');
            expect(CodesTool::getReasonByCode(409))->toBe('Conflict');
            expect(CodesTool::getReasonByCode(410))->toBe('Gone');
            expect(CodesTool::getReasonByCode(411))->toBe('Length Required');
            expect(CodesTool::getReasonByCode(412))->toBe('Precondition Failed');
            expect(CodesTool::getReasonByCode(413))->toBe('Request Entity Too Large');
            expect(CodesTool::getReasonByCode(414))->toBe('Request-URI Too Long');
            expect(CodesTool::getReasonByCode(415))->toBe('Unsupported Media Type');
            expect(CodesTool::getReasonByCode(416))->toBe('Requested Range Not Satisfiable');
            expect(CodesTool::getReasonByCode(417))->toBe('Expectation Failed');
        });

        it('returns reason for server error codes (5xx)', function (): void {
            expect(CodesTool::getReasonByCode(500))->toBe('Internal Server Error');
            expect(CodesTool::getReasonByCode(501))->toBe('Not Implemented');
            expect(CodesTool::getReasonByCode(502))->toBe('Bad Gateway');
            expect(CodesTool::getReasonByCode(503))->toBe('Service Unavailable');
            expect(CodesTool::getReasonByCode(504))->toBe('Gateway Timeout');
            expect(CodesTool::getReasonByCode(505))->toBe('HTTP Version Not Supported');
        });

        it('returns code as string for unknown codes', function (): void {
            expect(CodesTool::getReasonByCode(999))->toBe('999');
            expect(CodesTool::getReasonByCode(1))->toBe('1');
            expect(CodesTool::getReasonByCode(0))->toBe('0');
            expect(CodesTool::getReasonByCode(-1))->toBe('-1');
            expect(CodesTool::getReasonByCode(1000))->toBe('1000');
        });

        it('returns correct reason for 207 Multi-Status type codes (not implemented)', function (): void {
            // 207 is not in the codesInfo, should return "207"
            expect(CodesTool::getReasonByCode(207))->toBe('207');
        });

        it('returns correct reason for intermediate 4xx codes (not implemented)', function (): void {
            // 418, 422 etc. are not in the codesInfo
            expect(CodesTool::getReasonByCode(418))->toBe('418');
            expect(CodesTool::getReasonByCode(422))->toBe('422');
        });

        it('handles string codes by converting to string representation', function (): void {
            // Test that we can handle edge cases with integer conversion
            expect(CodesTool::getReasonByCode(intval('200')))->toBe('OK');
        });
    });
});
