<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Comms\Support\Controllers {
    use Aura\SqlQuery\Common\SelectInterface;
    use PHPCraftdream\Garnet\Bundle\Support\Utils\PaginationHelper;
    use PHPCraftdream\Garnet\Kernel\Core\Runtime\FrameworkController;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Core\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Web\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\Http\FileUpload\FileUploadManager;
    use PHPCraftdream\Garnet\Kernel\Io\Http\FileUpload\SecureFileServing;
    use PHPCraftdream\Garnet\Kernel\Io\Http\FileUpload\Types\UploadRules;
    use PHPCraftdream\Garnet\Kernel\Io\Http\Router\Controller\ControllerTools;

    abstract class FwSupportAdminController extends FrameworkController {
        private const UPLOAD_SUBDIR = 'support';

        protected const VALID_STATUSES = [
            'open', 'investigation', 'in_progress', 'waiting_user',
            'waiting_support', 'escalated', 'on_hold', 'resolved', 'rejected',
        ];

        /**
         * Return the upload directory path.
         */
        abstract protected static function getUploadDir(): string;

        /**
         * Check whether the current user has moderator-level access.
         */
        abstract protected static function isModerator(): bool;

        /**
         * Return the side menu array for the given URL.
         */
        abstract protected static function getSideMenu(string $url): array;

        /**
         * Return the main/top menu array for the given URL.
         */
        abstract protected static function getMainMenu(string $url): array;

        /**
         * Return the SupportTickets table instance.
         */
        abstract protected static function ticketsTable(): DbTable;

        /**
         * Return the SupportMessages table instance.
         */
        abstract protected static function messagesTable(): DbTable;

        /**
         * Return the SupportAttachments table instance.
         */
        abstract protected static function attachmentsTable(): DbTable;

        /**
         * Return the SupportAssignmentLog table instance.
         */
        abstract protected static function assignmentLogTable(): DbTable;

        /**
         * Resolve the user role string for the given account ID.
         * E.g. 'admin', 'owner', 'moderator', 'expert', 'user'.
         * Returns ['role' => string, 'has_expert_profile' => bool].
         */
        abstract protected static function resolveUserRole(int $accountId): array;

        /**
         * Public web URL of the account's avatar, or null. App-specific (depends
         * on the app's upload paths / account photo columns). Default: none.
         */
        protected static function accountAvatarUrl(int $accountId): ?string {
            return null;
        }

        /**
         * Return a map of status key => translated label for system messages.
         * E.g. ['open' => 'Open', 'resolved' => 'Resolved', ...].
         */
        abstract protected static function getStatusLabels(): array;

        /**
         * Fetch moderator/admin accounts for the assignee dropdown.
         * Returns array of ['id', 'login', 'name'].
         */
        abstract protected static function fetchModerators(): array;

        /**
         * Return the translated string for "Status changed".
         */
        abstract protected static function getStatusChangedLabel(): string;

        /**
         * Текст системной строки о смене статуса — той, что видит и сотрудник,
         * и клиент.
         *
         * По умолчанию сохраняется прежнее поведение: «Статус изменён: A → B»
         * названиями из getStatusLabels(). Это честно для очереди сотрудников и
         * бесполезно для клиента: названия статусов — внутренняя кухня, и
         * человек снаружи читает «Ожидание ответа → В работе» как утечку
         * служебных терминов, а не как новость о своём обращении.
         *
         * Приложение может переопределить метод и вернуть свою формулировку, а
         * вернув null — вовсе не писать сообщение: у части переходов нет
         * никакого смысла для того, кто обращение написал.
         */
        protected static function buildStatusChangeBody(string $oldStatus, string $newStatus): ?string {
            $statusLabels = static::getStatusLabels();
            $oldLabel = $statusLabels[$oldStatus] ?? $oldStatus;
            $newLabel = $statusLabels[$newStatus] ?? $newStatus;

            return static::getStatusChangedLabel() . ": {$oldLabel} \u{2192} {$newLabel}";
        }

        /**
         * Return the translated string for "Assigned to".
         */
        abstract protected static function getAssignedToLabel(): string;

        /**
         * Return the translated string for "Unassigned" action.
         */
        abstract protected static function getUnassignedLabel(): string;

        private static function getUploadManager(): FileUploadManager {
            return new FileUploadManager(static::getUploadDir(), self::UPLOAD_SUBDIR);
        }

        /**
         * Rejected files come back as reasons rather than vanishing: an answer
         * that arrives without the file its author attached, and without a word
         * about why, leaves the author believing it went with it.
         *
         * @return list<string>
         */
        protected static function handleAttachments(IGlobalReqParams $globals, int $messageId): array {
            $filesData = $globals->readFilesValue('attachments', null);

            if (empty($filesData) || empty($filesData['name'])) {
                return [];
            }

            $manager = static::getUploadManager();
            $result = $manager->storeAll($filesData, UploadRules::documentsAndImages());

            $table = static::attachmentsTable();
            $now = time();

            foreach ($result->files as $info) {
                $table->insert([
                    'message_id' => $messageId,
                    'original_name' => $info->originalName,
                    'stored_name' => $info->storedName,
                    'mime_type' => $info->mimeType,
                    'size' => $info->size,
                    'created_at' => $now,
                ]);
            }

            return $result->errors;
        }

        protected static function enrichWithAttachments(array &$messages): void {
            $messageIds = array_map(fn ($m) => (int)$m['id'], $messages);

            if (empty($messageIds)) {
                return;
            }

            $table = static::attachmentsTable();
            $rows = $table->selectAll(function ($q) use ($messageIds): void {
                $q->where('message_id IN (?)', [array_map('intval', $messageIds)]);
                $q->orderBy(['id ASC']);
            });

            $grouped = [];

            foreach ($rows as $row) {
                $grouped[(int)$row['message_id']][] = $row;
            }

            foreach ($messages as &$msg) {
                $atts = $grouped[(int)$msg['id']] ?? [];

                foreach ($atts as &$a) {
                    $a['download_url'] = static::URL . '~download?id=' . $a['id'];
                }
                unset($a);
                $msg['attachments'] = $atts;
            }
            unset($msg);
        }

        protected const TICKET_SEARCH_FIELDS = ['subject'];

        protected const TICKET_SORT_FIELDS = ['id', 'status', 'created_at', 'updated_at'];

        protected const ASSIGNEE_UNASSIGNED = '__none__';

        /**
         * @param array<string, mixed> $filters status?, accountId?, assigneeId?
         *   (int, or self::ASSIGNEE_UNASSIGNED), dateField? (created_at|updated_at),
         *   dateFrom?, dateTo? (unix ts)
         * @return array<string, mixed> PageResponse shape
         */
        protected static function fetchTicketsPage(
            int $page,
            int $perPage,
            string $query = '',
            ?string $sortField = null,
            string $sortDir = 'asc',
            array $filters = [],
        ): array {
            $pageData = PaginationHelper::fetchPage(
                static::ticketsTable(),
                $page,
                $perPage,
                static function (SelectInterface $q) use ($filters, $query, $sortField, $sortDir): void {
                    if (!empty($filters['status'])) {
                        $q->where('status = ?', [$filters['status']]);
                    }

                    if (!empty($filters['accountId'])) {
                        $q->where('account_id = ?', [(int)$filters['accountId']]);
                    }

                    if (isset($filters['assigneeId']) && $filters['assigneeId'] === self::ASSIGNEE_UNASSIGNED) {
                        $q->where('assignee_id IS NULL');
                    } elseif (!empty($filters['assigneeId'])) {
                        $q->where('assignee_id = ?', [(int)$filters['assigneeId']]);
                    }
                    $dateField = ($filters['dateField'] ?? '') === 'created_at' ? 'created_at' : 'updated_at';

                    if (!empty($filters['dateFrom'])) {
                        $q->where("{$dateField} >= ?", [(int)$filters['dateFrom']]);
                    }

                    if (!empty($filters['dateTo'])) {
                        $q->where("{$dateField} <= ?", [(int)$filters['dateTo']]);
                    }
                    PaginationHelper::applySearchAndSort(
                        $q, $query, self::TICKET_SEARCH_FIELDS, $sortField, $sortDir, self::TICKET_SORT_FIELDS, 'updated_at DESC',
                    );
                },
            );

            $pageData->pageItems = static::hydrateTickets($pageData->pageItems);

            return PaginationHelper::toPageResponse($pageData);
        }

        /**
         * @param array<int, array<string, mixed>> $tickets
         * @return array<int, array<string, mixed>>
         */
        protected static function hydrateTickets(array $tickets): array {
            if ($tickets === []) {
                return $tickets;
            }

            // Collect account IDs (ticket owners) and assignee IDs
            $accountIds = array_unique(array_filter(array_column($tickets, 'account_id')));
            $assigneeIds = array_unique(array_filter(array_column($tickets, 'assignee_id')));
            $allIds = array_unique(array_merge($accountIds, $assigneeIds));

            $accounts = [];

            if (!empty($allIds)) {
                $accs = Account::getAccounts(
                    selectCallback: static function (SelectInterface $select) use ($allIds): void {
                        $select->resetCols();
                        $select->cols(['id', 'login', 'name']);
                        $select->where('id IN (?)', [array_map('intval', $allIds)]);
                    },
                );

                foreach ($accs as $a) {
                    $accounts[(int)$a['id']] = $a;
                }
            }

            // Batch resolve user roles
            $roleCache = [];

            foreach ($tickets as &$ticket) {
                $aid = (int)$ticket['account_id'];
                $ticket['user_login'] = $accounts[$aid]['login'] ?? '';
                $ticket['user_name'] = $accounts[$aid]['name'] ?? '';

                if (!isset($roleCache[$aid]) && $aid > 0) {
                    $roleCache[$aid] = static::resolveUserRole($aid);
                }
                $ticket['user_role'] = $roleCache[$aid]['role'] ?? 'student';

                $assigneeId = $ticket['assignee_id'] ? (int)$ticket['assignee_id'] : null;
                $ticket['assignee_name'] = $assigneeId ? ($accounts[$assigneeId]['name'] ?? '') : '';
                $ticket['assignee_login'] = $assigneeId ? ($accounts[$assigneeId]['login'] ?? '') : '';
            }
            unset($ticket);

            static::enrichWithAttachmentCounts($tickets);

            return $tickets;
        }

        /**
         * Status pill counts — true global counts (GROUP BY), not just
         * among whatever page happens to be loaded.
         *
         * @return array<string, int>
         */
        protected static function fetchTicketStatusCounts(): array {
            $rows = static::ticketsTable()->selectAll(static function (SelectInterface $q): void {
                $q->resetCols();
                $q->cols(['status', 'COUNT(*) AS cnt']);
                $q->groupBy(['status']);
            });

            $counts = [];

            foreach ($rows as $row) {
                $counts[(string)$row['status']] = (int)$row['cnt'];
            }

            return $counts;
        }

        /**
         * Combobox options for the ticket-owner/assignee filters — distinct
         * across the whole table, not just the currently loaded page.
         *
         * @return array{users: array<int, array{value: string, label: string}>, assignees: array<int, array{value: string, label: string}>, hasUnassigned: bool}
         */
        protected static function fetchTicketsFilterOptions(): array {
            $accountIds = array_column(static::ticketsTable()->selectAll(static function (SelectInterface $q): void {
                $q->resetCols();
                $q->cols(['account_id']);
                $q->groupBy(['account_id']);
            }), 'account_id');

            $assigneeRows = static::ticketsTable()->selectAll(static function (SelectInterface $q): void {
                $q->resetCols();
                $q->cols(['assignee_id']);
                $q->groupBy(['assignee_id']);
            });
            $hasUnassigned = false;
            $assigneeIds = [];

            foreach ($assigneeRows as $row) {
                if (empty($row['assignee_id'])) {
                    $hasUnassigned = true;
                } else {
                    $assigneeIds[] = (int)$row['assignee_id'];
                }
            }

            $allIds = array_unique(array_map('intval', array_merge($accountIds, $assigneeIds)));
            $accounts = [];

            if (!empty($allIds)) {
                $accs = Account::getAccounts(
                    selectCallback: static function (SelectInterface $select) use ($allIds): void {
                        $select->resetCols();
                        $select->cols(['id', 'login', 'name']);
                        $select->where('id IN (?)', [$allIds]);
                    },
                );

                foreach ($accs as $a) {
                    $accounts[(int)$a['id']] = $a;
                }
            }

            $label = static fn (int $id): string => $accounts[$id]['name'] ?? $accounts[$id]['login'] ?? "#{$id}";

            $users = [];

            foreach (array_unique(array_map('intval', $accountIds)) as $id) {
                if ($id > 0) {
                    $users[] = ['value' => (string)$id, 'label' => $label($id)];
                }
            }
            $assignees = [];

            foreach (array_unique($assigneeIds) as $id) {
                $assignees[] = ['value' => (string)$id, 'label' => $label($id)];
            }

            return ['users' => $users, 'assignees' => $assignees, 'hasUnassigned' => $hasUnassigned];
        }

        /**
         * Whether a ticket carries files was previously discoverable only by
         * opening it — a queue of two hundred rows gave no way to see which
         * ones had something to look at.
         *
         * Two batched queries rather than one per row: the list is capped at
         * 200, and a per-row count would be 200 round trips for a column.
         *
         * @param array<int, array<string, mixed>> $tickets
         */
        protected static function enrichWithAttachmentCounts(array &$tickets): void {
            $ticketIds = array_values(array_filter(array_map(
                static fn (array $t): int => (int)($t['id'] ?? 0),
                $tickets,
            )));

            if ($ticketIds === []) {
                return;
            }

            $messages = static::messagesTable()->selectAll(function (SelectInterface $q) use ($ticketIds): void {
                $q->resetCols();
                $q->cols(['id', 'ticket_id']);
                $q->where('ticket_id IN (?)', [$ticketIds]);
            });

            $ticketOfMessage = [];

            foreach ($messages as $message) {
                $ticketOfMessage[(int)$message['id']] = (int)$message['ticket_id'];
            }

            $counts = [];

            if ($ticketOfMessage !== []) {
                $attachments = static::attachmentsTable()->selectAll(
                    function (SelectInterface $q) use ($ticketOfMessage): void {
                        $q->resetCols();
                        $q->cols(['message_id']);
                        $q->where('message_id IN (?)', [array_keys($ticketOfMessage)]);
                    },
                );

                foreach ($attachments as $attachment) {
                    $ticketId = $ticketOfMessage[(int)$attachment['message_id']] ?? 0;

                    if ($ticketId > 0) {
                        $counts[$ticketId] = ($counts[$ticketId] ?? 0) + 1;
                    }
                }
            }

            foreach ($tickets as &$ticket) {
                $ticket['attachments_count'] = $counts[(int)($ticket['id'] ?? 0)] ?? 0;
            }
            unset($ticket);
        }

        public static function post__ticketDetail(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isModerator()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            $ticketId = (int)$globals->readPostValue('ticket_id', '0');

            if (!$ticketId) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }

            $ticketsTable = static::ticketsTable();
            $ticket = $ticketsTable->selectOneByField('id', $ticketId);

            if (!$ticket) {
                return ControllerTools::JSON(['error' => 'Ticket not found'], status: 404);
            }

            // Fetch ALL messages (including internal — admin sees everything)
            $messages = static::messagesTable()->selectAll(function (SelectInterface $q) use ($ticketId): void {
                $q->where('ticket_id = ?', [$ticketId]);
                $q->orderBy(['created_at ASC']);
            });

            // Fetch assignment log
            $assignments = static::assignmentLogTable()->selectAll(function (SelectInterface $q) use ($ticketId): void {
                $q->where('ticket_id = ?', [$ticketId]);
                $q->orderBy(['created_at ASC']);
            });

            // Collect all referenced account IDs for name enrichment
            $allIds = array_unique(array_filter(array_merge(
                [(int)$ticket['account_id'], $ticket['assignee_id'] ? (int)$ticket['assignee_id'] : 0],
                array_column($messages, 'author_id'),
                array_column($assignments, 'actor_id'),
                array_filter(array_column($assignments, 'from_id')),
                array_filter(array_column($assignments, 'to_id')),
            )));

            $accounts = [];

            if (!empty($allIds)) {
                $accs = Account::getAccounts(
                    selectCallback: static function (SelectInterface $select) use ($allIds): void {
                        $select->resetCols();
                        $select->cols(['id', 'login', 'name']);
                        $select->where('id IN (?)', [array_map('intval', $allIds)]);
                    },
                );

                foreach ($accs as $a) {
                    $accounts[(int)$a['id']] = $a;
                }
            }

            // Enrich messages with author names
            foreach ($messages as &$msg) {
                $aid = (int)$msg['author_id'];
                $msg['author_name'] = $accounts[$aid]['name'] ?? '';
                $msg['author_login'] = $accounts[$aid]['login'] ?? '';
            }
            unset($msg);

            // Enrich assignments with names
            foreach ($assignments as &$asg) {
                $asg['actor_name'] = $accounts[(int)$asg['actor_id']]['name'] ?? '';
                $asg['from_name'] = $asg['from_id'] ? ($accounts[(int)$asg['from_id']]['name'] ?? '') : '';
                $asg['to_name'] = $asg['to_id'] ? ($accounts[(int)$asg['to_id']]['name'] ?? '') : '';
            }
            unset($asg);

            // Enrich ticket with user info
            $aid = (int)$ticket['account_id'];
            $ticket['user_login'] = $accounts[$aid]['login'] ?? '';
            $ticket['user_name'] = $accounts[$aid]['name'] ?? '';
            $ticket['user_avatar'] = static::accountAvatarUrl($aid);

            // Determine user role (app-specific)
            $roleInfo = static::resolveUserRole($aid);
            $ticket['user_role'] = $roleInfo['role'];
            $ticket['has_expert_profile'] = $roleInfo['has_expert_profile'];

            // Enrich messages with attachments
            $messagesArray = array_values($messages);
            static::enrichWithAttachments($messagesArray);

            // Parse context JSON for admin display
            $context = null;

            if (!empty($ticket['context'])) {
                $context = json_decode($ticket['context'], true);
            }

            // Mark ticket as read by staff
            $ticketsTable->updateByField(['unread_staff' => 0], 'id', $ticketId);

            return ControllerTools::JSON([
                'ticket' => $ticket,
                'messages' => $messagesArray,
                'assignmentLog' => array_values($assignments),
                'context' => $context,
            ]);
        }

        public static function post__reply(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isModerator()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            $ticketId = (int)$globals->readPostValue('ticket_id', '0');
            $message = trim((string)$globals->readPostValue('message', ''));
            // D-188: the client sends how many messages it saw when the
            // moderator started composing. The 15s poll only catches a
            // colleague's reply that lands DURING typing; two people
            // submitting within the same short window both compose against
            // the same count and neither poll fires in time. Comparing
            // against the actual count here, in the same request that inserts
            // the reply, catches the collision no matter how fast it happens.
            $knownMessageCountRaw = $globals->readPostValue('known_message_count', '');
            $knownMessageCount = $knownMessageCountRaw === '' ? null : (int)$knownMessageCountRaw;

            if (!$ticketId || $message === '') {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }

            $ticketsTable = static::ticketsTable();
            $ticket = $ticketsTable->selectOneByField('id', $ticketId);

            if (!$ticket) {
                return ControllerTools::JSON(['error' => 'Ticket not found'], status: 404);
            }

            $account = Account::fromSession();
            $now = time();

            // Не блокируем ответ — коллега уже потратил время на его
            // составление, и правильный ответ дважды лучше, чем потерянный
            // ответ ни разу. Клиент вместо этого сразу покажет
            // предупреждение, а не через 15 секунд опроса.
            $staleReply = false;

            if ($knownMessageCount !== null) {
                $actualMessageCount = static::messagesTable()->getCount(function (SelectInterface $q) use ($ticketId): void {
                    $q->where('ticket_id = ?', [$ticketId]);
                });
                $staleReply = $actualMessageCount > $knownMessageCount;
            }

            // Insert staff reply (visible to user)
            $messageId = static::messagesTable()->insert([
                'ticket_id' => $ticketId,
                'author_id' => (int)$account->id(),
                'body' => $message,
                'is_internal' => 0,
                'msg_type' => 'staff',
                'created_at' => $now,
            ]);

            $attachmentErrors = static::handleAttachments($globals, (int)$messageId);

            // Update ticket
            $updates = [
                'unread_user' => (int)$ticket['unread_user'] + 1,
                'updated_at' => $now,
            ];

            // Change status if it was waiting_support or open
            if (in_array($ticket['status'], ['waiting_support', 'open'], true)) {
                $updates['status'] = 'waiting_user';
            }

            // Auto-assign if no assignee
            if (empty($ticket['assignee_id'])) {
                $updates['assignee_id'] = (int)$account->id();
            }

            $ticketsTable->updateByField($updates, 'id', $ticketId);

            return ControllerTools::JSON(['success' => true, 'attachmentErrors' => $attachmentErrors, 'staleReply' => $staleReply]);
        }

        public static function post__internalComment(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isModerator()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            $ticketId = (int)$globals->readPostValue('ticket_id', '0');
            $message = trim((string)$globals->readPostValue('message', ''));

            if (!$ticketId || $message === '') {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }

            $ticketsTable = static::ticketsTable();
            $ticket = $ticketsTable->selectOneByField('id', $ticketId);

            if (!$ticket) {
                return ControllerTools::JSON(['error' => 'Ticket not found'], status: 404);
            }

            $account = Account::fromSession();
            $now = time();

            // Insert internal comment (NOT visible to user)
            $messageId = static::messagesTable()->insert([
                'ticket_id' => $ticketId,
                'author_id' => (int)$account->id(),
                'body' => $message,
                'is_internal' => 1,
                'msg_type' => 'staff',
                'created_at' => $now,
            ]);

            $attachmentErrors = static::handleAttachments($globals, (int)$messageId);

            // Update timestamp only — no status change, no unread change
            $ticketsTable->updateByField(['updated_at' => $now], 'id', $ticketId);

            return ControllerTools::JSON(['success' => true, 'attachmentErrors' => $attachmentErrors]);
        }

        public static function post__changeStatus(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isModerator()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            $ticketId = (int)$globals->readPostValue('ticket_id', '0');
            $newStatus = trim((string)$globals->readPostValue('status', ''));

            if (!$ticketId || !in_array($newStatus, static::VALID_STATUSES, true)) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }

            $ticketsTable = static::ticketsTable();
            $ticket = $ticketsTable->selectOneByField('id', $ticketId);

            if (!$ticket) {
                return ControllerTools::JSON(['error' => 'Ticket not found'], status: 404);
            }

            $oldStatus = $ticket['status'];
            $account = Account::fromSession();
            $now = time();

            // Update ticket status
            $ticketsTable->updateByField([
                'status' => $newStatus,
                'updated_at' => $now,
            ], 'id', $ticketId);

            // Системная строка о смене статуса. Её видит не только сотрудник,
            // но и человек по ту сторону: она пишется с is_internal = 0.
            // Поэтому текст отдан приложению — только оно знает, какие из его
            // статусов что-то значат для клиента, а какие являются внутренней
            // кухней очереди. Вернуть null — не писать сообщение вовсе.
            $body = static::buildStatusChangeBody($oldStatus, $newStatus);

            if ($body !== null && $body !== '') {
                static::messagesTable()->insert([
                    'ticket_id' => $ticketId,
                    'author_id' => (int)$account->id(),
                    'body' => $body,
                    'is_internal' => 0,
                    'msg_type' => 'system',
                    'created_at' => $now,
                ]);
            }

            return ControllerTools::JSON(['success' => true]);
        }

        public static function post__assign(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isModerator()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            $ticketId = (int)$globals->readPostValue('ticket_id', '0');
            $assigneeId = (int)$globals->readPostValue('assignee_id', '0');

            if (!$ticketId) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }

            // Security audit M-02: assignee_id used to be trusted verbatim from
            // POST with no check that it names a moderator/owner/admin (or an
            // existing account at all). 0 stays a valid "unassign" sentinel;
            // any positive id must resolve to one of fetchModerators().
            if ($assigneeId !== 0) {
                $isValidAssignee = false;

                foreach (static::fetchModerators() as $moderator) {
                    if ((int)($moderator['id'] ?? 0) === $assigneeId) {
                        $isValidAssignee = true;

                        break;
                    }
                }

                if (!$isValidAssignee) {
                    return ControllerTools::JSON(['error' => 'Invalid assignee'], status: 400);
                }
            }

            $ticketsTable = static::ticketsTable();
            $ticket = $ticketsTable->selectOneByField('id', $ticketId);

            if (!$ticket) {
                return ControllerTools::JSON(['error' => 'Ticket not found'], status: 404);
            }

            $account = Account::fromSession();
            $actorId = (int)$account->id();
            $currentAssId = $ticket['assignee_id'] ? (int)$ticket['assignee_id'] : null;
            $now = time();

            // Update ticket assignee
            $ticketsTable->updateByField([
                'assignee_id' => $assigneeId ?: null,
                'updated_at' => $now,
            ], 'id', $ticketId);

            // Insert assignment log entry
            static::assignmentLogTable()->insert([
                'ticket_id' => $ticketId,
                'actor_id' => $actorId,
                'from_id' => $currentAssId,
                'to_id' => $assigneeId ?: null,
                'created_at' => $now,
            ]);

            // Resolve assignee name for system message
            $assigneeName = '';

            if ($assigneeId) {
                $accs = Account::getAccounts(
                    selectCallback: static function (SelectInterface $select) use ($assigneeId): void {
                        $select->resetCols();
                        $select->cols(['id', 'name']);
                        $select->where('id = ?', [$assigneeId]);
                    },
                );
                $assigneeName = $accs[0]['name'] ?? '';
            }

            // Insert system message
            $body = $assigneeId
                ? static::getAssignedToLabel() . ": {$assigneeName}"
                : static::getUnassignedLabel();
            static::messagesTable()->insert([
                'ticket_id' => $ticketId,
                'author_id' => $actorId,
                'body' => $body,
                'is_internal' => 1,
                'msg_type' => 'system',
                'created_at' => $now,
            ]);

            return ControllerTools::JSON(['success' => true]);
        }

        public static function get__download(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isModerator()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            $attachmentId = (int)$globals->readGetValue('id', '0');

            if (!$attachmentId) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }

            $attachment = static::attachmentsTable()->selectOneByField('id', $attachmentId);

            if (!$attachment) {
                return ControllerTools::JSON(['error' => 'File not found'], status: 404);
            }

            return SecureFileServing::serve(
                uploadDir: static::getUploadDir(),
                subDir: self::UPLOAD_SUBDIR,
                storedName: $attachment['stored_name'],
                displayName: $attachment['original_name'],
                mimeType: $attachment['mime_type'],
                accessCheck: fn () => true,  // moderator+ already checked above
            );
        }

        public static function post__userTickets(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            if (!static::isModerator()) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            $accountId = (int)$globals->readPostValue('account_id', '0');

            if (!$accountId) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }

            $tickets = static::ticketsTable()->selectByField('account_id', $accountId, function (SelectInterface $q): void {
                $q->orderBy(['updated_at DESC']);
            });

            return ControllerTools::JSON(['tickets' => array_values($tickets)]);
        }
    }
}
