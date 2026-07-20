<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Email {
    use Aura\SqlQuery\Common\SelectInterface;
    use LogicException;
    use PHPCraftdream\Garnet\Bundle\Modules\Email\Tables\FwEmailAttempts;
    use PHPCraftdream\Garnet\Bundle\Modules\Email\Tables\FwEmailQueue;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\DbAccount;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use PHPCraftdream\Garnet\Kernel\Io\Mailer\Mailer;
    use Throwable;

    /**
     * Outbound email queue driver: enqueue, drain with retries, log
     * attempts. Concrete table classes (per-app, with the right
     * $tableName) are injected via `setTableClasses(...)` at init.
     *
     * In dev mode (`env=dev` in app.ini), recipients ending in `.test`
     * are short-circuited as `sent` without actually invoking Mailer —
     * keeps the test suite free of accidental SMTP traffic.
     */
    class FwEmailQueueService {
        /**
         * Exponential backoff tiers (in seconds) applied to failed send
         * attempts, indexed by attempt number - 1 (tier 0 = 1st failure,
         * tier 1 = 2nd failure, ...). Once the attempt number exceeds the
         * number of tiers, the LAST tier is held for all further retries.
         *
         * Default ladder: 1 minute -> 10 minutes -> 1 hour -> 6 hours.
         *
         * Real SMTP outages (mail service restarts, transient provider
         * overload) commonly last well over a minute; the previous linear
         * 5/10/15-second backoff exhausted a typical max_attempts=3 budget
         * in 15-30 seconds total, sending the message to a terminal
         * dead-letter state long before the other side had a chance to
         * recover. This tier list gives real-world outages a realistic
         * window to clear before giving up.
         *
         * App-level subclasses may override this constant, or override
         * backoffSeconds() entirely, to plug in their own retry strategy.
         */
        protected const BACKOFF_TIERS_SECONDS = [60, 600, 3600, 21600];

        /** @var class-string<FwEmailQueue>|null */
        private static ?string $queueTable = null;

        /** @var class-string<FwEmailAttempts>|null */
        private static ?string $attemptsTable = null;

        /**
         * @param class-string<FwEmailQueue>    $queue
         * @param class-string<FwEmailAttempts> $attempts
         */
        public static function setTableClasses(string $queue, string $attempts): void {
            static::$queueTable = $queue;
            static::$attemptsTable = $attempts;
        }

        private static function queue(): FwEmailQueue {
            if (static::$queueTable === null) {
                throw new LogicException('FwEmailQueueService::setTableClasses() must be called before use.');
            }

            return static::$queueTable::get();
        }

        private static function attempts(): FwEmailAttempts {
            if (static::$attemptsTable === null) {
                throw new LogicException('FwEmailQueueService::setTableClasses() must be called before use.');
            }

            return static::$attemptsTable::get();
        }

        public static function enqueue(
            string $recipientEmail,
            string $subject,
            string $bodyHtml,
            int $maxAttempts = 3
        ): int {
            $accountId = null;

            try {
                $row = DbAccount::get()->selectOneByField('login', strtolower($recipientEmail));
                $accountId = $row ? (int)$row['id'] : null;
            } catch (Throwable) {
            }

            $insertedId = static::queue()->insert([
                'account_id' => $accountId,
                'recipient_email' => $recipientEmail,
                'subject' => $subject,
                'body_html' => $bodyHtml,
                'status' => 'queued',
                'attempts' => 0,
                'max_attempts' => $maxAttempts,
                'next_attempt_at' => time(),
                'sent_at' => null,
                'created_at' => time(),
            ]);

            return (int)$insertedId;
        }

        public static function enqueueToMany(
            array $recipientEmails,
            string $subject,
            string $bodyHtml,
            int $maxAttempts = 3
        ): void {
            foreach ($recipientEmails as $email) {
                static::enqueue($email, $subject, $bodyHtml, $maxAttempts);
            }
        }

        public static function processQueue(int $limit = 50): int {
            $isDev = IniConfig::app()->paramString('env', 'prod') === 'dev';
            $queue = static::queue();

            $items = $queue->selectAll(function (SelectInterface $query) use ($limit): void {
                $query->where('status IN (?)', [['queued', 'error']]);
                $query->where('attempts < max_attempts');
                $query->where('(next_attempt_at IS NULL OR next_attempt_at <= ?)', [time()]);
                $query->orderBy(['id ASC']);
                $query->limit($limit);
            });

            $processed = 0;

            foreach ($items as $item) {
                if ($isDev && str_ends_with(strtolower($item['recipient_email']), '.test')) {
                    $queue->updateById([
                        'status' => 'sent',
                        'attempts' => $item['attempts'] + 1,
                        'sent_at' => time(),
                    ], $item['id']);
                    static::logAttempt((int)$item['id'], (int)$item['attempts'] + 1, 'success', null);
                    $processed++;

                    continue;
                }

                $queue->updateById([
                    'status' => 'sending',
                ], $item['id']);

                try {
                    Mailer::get()->sendHtmlMail(
                        $item['recipient_email'],
                        $item['subject'],
                        $item['body_html']
                    );

                    $queue->updateById([
                        'status' => 'sent',
                        'attempts' => $item['attempts'] + 1,
                        'sent_at' => time(),
                        'next_attempt_at' => null,
                    ], $item['id']);

                    static::logAttempt((int)$item['id'], (int)$item['attempts'] + 1, 'success', null);
                    $processed++;
                } catch (Throwable $e) {
                    $newAttempts = $item['attempts'] + 1;
                    $isFinal = $newAttempts >= (int)$item['max_attempts'];

                    $queue->updateById([
                        'status' => 'error',
                        'attempts' => $newAttempts,
                        'next_attempt_at' => $isFinal ? null : time() + static::backoffSeconds($newAttempts),
                    ], $item['id']);

                    static::logAttempt((int)$item['id'], $newAttempts, 'error', $e->getMessage());
                    $processed++;
                }
            }

            return $processed;
        }

        /**
         * Computes the delay (in seconds) before the next send attempt
         * following a failure, given the attempt number that just failed
         * (1-based: 1 = first failure, 2 = second failure, ...).
         *
         * Default implementation walks BACKOFF_TIERS_SECONDS by tier index
         * ($attemptNumber - 1); once $attemptNumber exceeds the number of
         * configured tiers, the last (largest) tier is held indefinitely
         * for all subsequent attempts, so retries never grow unbounded and
         * never shrink back down.
         *
         * Protected + static (not private) so app-level subclasses can
         * override this method - or just the BACKOFF_TIERS_SECONDS
         * constant - to implement their own backoff strategy while reusing
         * the rest of processQueue()'s retry bookkeeping, via late static
         * binding (`static::backoffSeconds(...)`).
         */
        protected static function backoffSeconds(int $attemptNumber): int {
            $tiers = static::BACKOFF_TIERS_SECONDS;
            $index = min($attemptNumber, count($tiers)) - 1;
            $index = max($index, 0);

            return $tiers[$index];
        }

        private static function logAttempt(int $queueId, int $attemptNumber, string $status, ?string $errorMessage): void {
            try {
                static::attempts()->insert([
                    'queue_id' => $queueId,
                    'attempt_number' => $attemptNumber,
                    'status' => $status,
                    'error_message' => $errorMessage,
                    'created_at' => time(),
                ]);
            } catch (Throwable) {
            }
        }

        /**
         * Re-queues a queue row for another send attempt, granting it a
         * FULL new attempts budget rather than continuing the old counter:
         * `attempts` is reset to 0 alongside `status => 'queued'` and
         * `next_attempt_at => time()`. This is a deliberate choice - without
         * it, a terminally-failed row (attempts == max_attempts) would be
         * moved back to 'queued' but immediately ignored again by
         * processQueue()'s `WHERE attempts < max_attempts` filter, leaving
         * it stuck in 'queued' forever.
         *
         * Returns false if the row does not exist or has already been sent
         * (status === 'sent'); does not touch `attempts` in either case.
         */
        public static function retry(int $queueId): bool {
            $queue = static::queue();
            $item = $queue->selectById($queueId);

            if (empty($item)) {
                return false;
            }

            if ($item['status'] === 'sent') {
                return false;
            }

            $queue->updateById([
                'status' => 'queued',
                'attempts' => 0,
                'next_attempt_at' => time(),
            ], $queueId);

            return true;
        }
    }
}
