<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Support\Controllers {
    use Aura\SqlQuery\Common\SelectInterface;
    use PHPCraftdream\Garnet\Bundle\Utils\HtmlLayout;
    use PHPCraftdream\Garnet\Bundle\Utils\PaginationHelper;
    use PHPCraftdream\Garnet\Bundle\Utils\RenderIsland;
    use PHPCraftdream\Garnet\Kernel\Core\FrameworkController;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Session;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\FileUploadManager;
    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\SecureFileServing;
    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\UploadRules;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\TwigParams;

    abstract class FwSupportController extends FrameworkController {
        private const UPLOAD_SUBDIR = 'support';

        /**
         * Return the upload directory path (e.g. MyApp::getInstance()->uploadDir).
         */
        abstract protected static function getUploadDir(): string;

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

        public static function renderContent(string $content, string $url): string {
            return HtmlLayout::render(
                TwigParams::init()->get(TwigParams::DEF_LAYOUT_PARAMS, [
                    'content' => $content,
                    'top_menu_items' => static::getMainMenu($url),
                    'side_menu_items' => static::getSideMenu($url),
                ])
            );
        }

        private static function getUploadManager(): FileUploadManager {
            return new FileUploadManager(static::getUploadDir(), self::UPLOAD_SUBDIR);
        }

        /**
         * Store attachments for a message. Returns array of stored attachment records.
         */
        protected static function handleAttachments(IGlobalReqParams $globals, int $messageId): array {
            $filesData = $globals->readFilesValue('attachments', null);

            if (empty($filesData) || empty($filesData['name'])) {
                return [];
            }

            $manager = static::getUploadManager();
            $result = $manager->storeAll($filesData, UploadRules::documentsAndImages());

            $attachments = [];
            $now = time();
            $table = static::attachmentsTable();

            foreach ($result->files as $info) {
                $id = $table->insert([
                    'message_id' => $messageId,
                    'original_name' => $info->originalName,
                    'stored_name' => $info->storedName,
                    'mime_type' => $info->mimeType,
                    'size' => $info->size,
                    'created_at' => $now,
                ]);
                $attachments[] = $table->selectOneByField('id', $id);
            }

            return $attachments;
        }

        /**
         * Enrich messages array with attachments and download URLs.
         */
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
                $mid = (int)$msg['id'];
                $atts = $grouped[$mid] ?? [];

                foreach ($atts as &$a) {
                    $a['download_url'] = static::URL . '~download?id=' . $a['id'];
                }
                unset($a);
                $msg['attachments'] = $atts;
            }
            unset($msg);
        }

        // ── Pages ────────────────────────────────────────────────────

        public static function get__main(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $url = $globals->getUri();
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::redirect('/register');
            }

            $accountId = (int)$account->id();

            $queryCallback = function (SelectInterface $q) use ($accountId): void {
                $q->where('account_id = ?', [$accountId]);
                $q->orderBy(['updated_at DESC']);
            };

            $pageData = PaginationHelper::fetchPage(static::ticketsTable(), 1, PaginationHelper::DEFAULT_PER_PAGE, $queryCallback);

            $content = RenderIsland::render('support-page', [
                'ticketsPagination' => PaginationHelper::toPageResponse($pageData),
                'ticketPageUrl' => static::URL . '~ticketPage',
                'messagesUrl' => static::URL . '~messages',
                'createUrl' => static::URL . '~createTicket',
                'replyUrl' => static::URL . '~reply',
                'downloadUrl' => static::URL . '~download',
                'csrf' => Session::touchCSRF_(),
            ]);

            return ControllerTools::ok(static::renderContent($content, $url));
        }

        // ── API: tickets, messages ───────────────────────────────────

        public static function post__tickets(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $accountId = (int)$account->id();
            $tickets = static::ticketsTable()->selectByField('account_id', $accountId, function (SelectInterface $q): void {
                $q->orderBy(['updated_at DESC']);
            });

            return ControllerTools::JSON(['tickets' => array_values($tickets)]);
        }

        public static function post__ticketPage(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $accountId = (int)$account->id();
            ['page' => $page, 'perPage' => $perPage] = PaginationHelper::readPageParams($globals);

            $queryCallback = function (SelectInterface $q) use ($accountId): void {
                $q->where('account_id = ?', [$accountId]);
                $q->orderBy(['updated_at DESC']);
            };

            $pageData = PaginationHelper::fetchPage(static::ticketsTable(), $page, $perPage, $queryCallback);

            return ControllerTools::JSON(PaginationHelper::toPageResponse($pageData));
        }

        public static function post__messages(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $accountId = (int)$account->id();
            $ticketId = (int)$globals->readPostValue('ticket_id', '0');

            if (!$ticketId) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }

            $ticketsTable = static::ticketsTable();
            $ticket = $ticketsTable->selectOneByField('id', $ticketId);

            if (!$ticket || (int)$ticket['account_id'] !== $accountId) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            // Fetch messages — NEVER leak internal comments
            $messages = static::messagesTable()->selectAll(function (SelectInterface $q) use ($ticketId): void {
                $q->where('ticket_id = ?', [$ticketId]);
                $q->where('is_internal = 0');
                $q->orderBy(['created_at ASC']);
            });

            // Enrich with author names
            $authorIds = array_unique(array_filter(array_column($messages, 'author_id')));
            $authors = [];

            if (!empty($authorIds)) {
                $accs = Account::getAccounts(
                    selectCallback: static function (SelectInterface $select) use ($authorIds): void {
                        $select->resetCols();
                        $select->cols(['id', 'login', 'name']);
                        $select->where('id IN (?)', [array_map('intval', $authorIds)]);
                    },
                );

                foreach ($accs as $a) {
                    $authors[(int)$a['id']] = $a;
                }
            }

            foreach ($messages as &$msg) {
                $aid = (int)$msg['author_id'];
                $msg['author_name'] = $authors[$aid]['name'] ?? '';
                $msg['author_login'] = '';
            }
            unset($msg);

            // Enrich with attachments
            static::enrichWithAttachments($messages);

            // Mark ticket as read
            $ticketsTable->updateByField(['unread_user' => 0], 'id', $ticketId);

            return ControllerTools::JSON([
                'messages' => array_values($messages),
                'ticket' => $ticket,
            ]);
        }

        // ── API: create, reply ───────────────────────────────────────

        public static function post__createTicket(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $postCsrf = $globals->readPostValue(Session::CSRF_TOKEN, '');

            if (!hash_equals(Session::touchCSRF_(), (string)$postCsrf)) {
                return ControllerTools::JSON(['error' => 'CSRF check failed'], status: 403);
            }

            $accountId = (int)$account->id();
            $subject = trim((string)$globals->readPostValue('subject', ''));
            $message = trim((string)$globals->readPostValue('message', ''));
            $context = trim((string)$globals->readPostValue('context', ''));

            if ($subject === '' || $message === '') {
                return ControllerTools::JSON(['error' => 'Subject and message are required'], status: 400);
            }

            $now = time();

            $ticketsTable = static::ticketsTable();
            $messagesTable = static::messagesTable();

            // Insert ticket with auto-context
            $ticketData = [
                'account_id' => $accountId,
                'subject' => $subject,
                'status' => 'open',
                'assignee_id' => null,
                'unread_user' => 0,
                'unread_staff' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($context !== '') {
                $ticketData['context'] = $context;
            }
            $ticketId = $ticketsTable->insert($ticketData);

            // Insert first message
            $messageId = $messagesTable->insert([
                'ticket_id' => $ticketId,
                'author_id' => $accountId,
                'body' => $message,
                'is_internal' => 0,
                'msg_type' => 'user',
                'created_at' => $now,
            ]);

            // Handle file attachments
            static::handleAttachments($globals, (int)$messageId);

            $ticket = $ticketsTable->selectOneByField('id', $ticketId);

            return ControllerTools::JSON([
                'success' => true,
                'ticket' => $ticket,
            ]);
        }

        public static function post__reply(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $postCsrf = $globals->readPostValue(Session::CSRF_TOKEN, '');

            if (!hash_equals(Session::touchCSRF_(), (string)$postCsrf)) {
                return ControllerTools::JSON(['error' => 'CSRF check failed'], status: 403);
            }

            $accountId = (int)$account->id();
            $ticketId = (int)$globals->readPostValue('ticket_id', '0');
            $message = trim((string)$globals->readPostValue('message', ''));

            if (!$ticketId || $message === '') {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }

            $ticketsTable = static::ticketsTable();
            $ticket = $ticketsTable->selectOneByField('id', $ticketId);

            if (!$ticket || (int)$ticket['account_id'] !== $accountId) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            $now = time();

            $messageId = static::messagesTable()->insert([
                'ticket_id' => $ticketId,
                'author_id' => $accountId,
                'body' => $message,
                'is_internal' => 0,
                'msg_type' => 'user',
                'created_at' => $now,
            ]);

            // Handle file attachments
            static::handleAttachments($globals, (int)$messageId);

            $ticketsTable->updateByField([
                'status' => 'waiting_support',
                'unread_staff' => (int)$ticket['unread_staff'] + 1,
                'updated_at' => $now,
            ], 'id', $ticketId);

            return ControllerTools::JSON(['success' => true]);
        }

        // ── API: file download ───────────────────────────────────────

        public static function get__download(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $attachmentId = (int)$globals->readGetValue('id', '0');

            if (!$attachmentId) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }

            $attachment = static::attachmentsTable()->selectOneByField('id', $attachmentId);

            if (!$attachment) {
                return ControllerTools::JSON(['error' => 'File not found'], status: 404);
            }

            // Find message -> ticket chain for access control
            $message = static::messagesTable()->selectOneByField('id', (int)$attachment['message_id']);

            if (!$message) {
                return ControllerTools::JSON(['error' => 'File not found'], status: 404);
            }

            $ticket = static::ticketsTable()->selectOneByField('id', (int)$message['ticket_id']);

            if (!$ticket) {
                return ControllerTools::JSON(['error' => 'File not found'], status: 404);
            }

            $accountId = (int)$account->id();

            // Internal comments — user MUST NOT see
            if ((int)$message['is_internal'] === 1) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            return SecureFileServing::serve(
                uploadDir: static::getUploadDir(),
                subDir: self::UPLOAD_SUBDIR,
                storedName: $attachment['stored_name'],
                displayName: $attachment['original_name'],
                accessCheck: fn () => (int)$ticket['account_id'] === $accountId,
            );
        }

        // ── API: unread count ────────────────────────────────────────

        public static function post__unreadCount(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $ticketsTable = static::ticketsTable();
            $rows = $ticketsTable->selectAll(function ($q) use ($account): void {
                $q->resetCols();
                $q->cols(['COALESCE(SUM(unread_user), 0) as total']);
                $q->where('account_id = ?', [(int)$account->id()]);
            });
            $count = (int)($rows[0]['total'] ?? 0);

            return ControllerTools::JSON(['unreadCount' => $count]);
        }
    }
}
