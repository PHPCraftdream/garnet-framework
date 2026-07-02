<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Common\Services {
    use PHPCraftdream\Garnet\Kernel\Io\Cron\FwCronService;

    class AppCronService extends FwCronService {
        public static function registerTasks(): void {
            // Register your app's cron tasks here, e.g.:
            // static::registerTask('email-queue', function (Stdio $stdio): int {
            //     return FwEmailQueueService::processQueue(50);
            // }, 'Process email queue (send pending emails)');
        }
    }
}
