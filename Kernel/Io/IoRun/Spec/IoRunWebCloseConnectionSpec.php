<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\L4_Modules\Io\Spec {
    use PHPCraftdream\Garnet\Kernel\Core\GlobalVars\GlobalVars;

    describe('IoRunWeb', function (): void {
        it('IoRunWeb::closeConnection', function (): void {
            $file = __DIR__ . DIRECTORY_SEPARATOR . 'CmdRunFiles' . DIRECTORY_SEPARATOR . 'IoRunWebCloseConnection.php';

            $phpRunCmd = GlobalVars::getString('phpRunCmd', 'php');
            $result = shell_exec("{$phpRunCmd} {$file}");

            expect($result)->toContain('Content: some_response! Hállö! Hí!');
            expect($result)->toContain('HTTP/1.1 200 OK');
            expect($result)->toContain('X-Powered-By: Application');
            expect($result)->toContain('Content-Length: 28');
            expect($result)->toContain('Content-Encoding: none');
            expect($result)->toContain('ob_get_length();');
            expect($result)->toContain('ob_get_clean();');
            expect($result)->toContain('set_time_limit(0);');
            expect($result)->toContain('ignore_user_abort(1);');
            expect($result)->toContain('flush();');
        });
    });
}
