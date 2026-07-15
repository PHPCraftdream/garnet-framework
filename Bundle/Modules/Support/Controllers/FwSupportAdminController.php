<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Support\Controllers {
    use Aura\SqlQuery\Common\SelectInterface;
    use PHPCraftdream\Garnet\Kernel\Core\FrameworkController;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\FileUploadManager;
    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\SecureFileServing;
    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\UploadRules;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;

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

        protected static function handleAttachments(IGlobalReqParams $globals, int $messageId): void {
            $filesData = $globals->readFilesValue('attachments', null);

            if (empty($filesData) || empty($filesData['name'])) {
                return;
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

        protected static function fetchTickets(): array {
            $tickets = static::ticketsTable()->selectAll(function (SelectInterface $q): void {
                $q->orderBy(['updated_at DESC']);
                $q->limit(200);
            });

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

            return $tickets;
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

            // Insert staff reply (visible to user)
            $messageId = static::messagesTable()->insert([
                'ticket_id' => $ticketId,
                'author_id' => (int)$account->id(),
                'body' => $message,
                'is_internal' => 0,
                'msg_type' => 'staff',
                'created_at' => $now,
            ]);

            static::handleAttachments($globals, (int)$messageId);

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

            return ControllerTools::JSON(['success' => true]);
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

            static::handleAttachments($globals, (int)$messageId);

            // Update timestamp only — no status change, no unread change
            $ticketsTable->updateByField(['updated_at' => $now], 'id', $ticketId);

            return ControllerTools::JSON(['success' => true]);
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

            // Insert system message about status change with translated labels
            $statusLabels = static::getStatusLabels();
            $oldLabel = $statusLabels[$oldStatus] ?? $oldStatus;
            $newLabel = $statusLabels[$newStatus] ?? $newStatus;
            static::messagesTable()->insert([
                'ticket_id' => $ticketId,
                'author_id' => (int)$account->id(),
                'body' => static::getStatusChangedLabel() . ": {$oldLabel} \u{2192} {$newLabel}",
                'is_internal' => 0,
                'msg_type' => 'system',
                'created_at' => $now,
            ]);

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
                'is_internal' => 0,
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
