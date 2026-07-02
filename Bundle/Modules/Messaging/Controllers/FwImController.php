<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Messaging\Controllers {
    use Aura\SqlQuery\Common\SelectInterface;
    use PHPCraftdream\Garnet\Bundle\Modules\Messaging\Tables\FwImAttachments;
    use PHPCraftdream\Garnet\Bundle\Modules\Messaging\Tables\FwImConversations;
    use PHPCraftdream\Garnet\Bundle\Modules\Messaging\Tables\FwImMessages;
    use PHPCraftdream\Garnet\Bundle\Modules\Messaging\Tables\FwImReadStatus;
    use PHPCraftdream\Garnet\Bundle\Utils\HtmlLayout;
    use PHPCraftdream\Garnet\Bundle\Utils\RenderIsland;
    use PHPCraftdream\Garnet\Kernel\Core\FrameworkController;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Account\Account;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Session;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IGlobalReqParams;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Router\IRouterUriParams;
    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\FileUploadManager;
    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\SecureFileServing;
    use PHPCraftdream\Garnet\Kernel\Io\FileUpload\UploadRules;
    use PHPCraftdream\Garnet\Kernel\Io\Router\ControllerTools;
    use PHPCraftdream\Garnet\Kernel\Io\Twig\TwigParams;

    abstract class FwImController extends FrameworkController {
        public const URL = '/im/';

        private const UPLOAD_SUBDIR = 'im';

        // -- Abstract methods: business-specific, must be implemented by app --------

        /**
         * Search for messaging recipients. This is THE business boundary.
         * @return array<array{id: int, name: string, role: string}>
         */
        abstract protected static function searchRecipients(int $accountId, string $query): array;

        /**
         * Enrich a conversation array with app-specific fields (e.g. partner_has_expert_profile).
         */
        abstract protected static function enrichConversation(array &$conv, int $accountId): void;

        /**
         * Return the upload directory path for IM attachments.
         */
        abstract protected static function getUploadDir(): string;

        /**
         * Return the side menu items for the IM page.
         */
        abstract protected static function getSideMenu(string $url): array;

        /**
         * Return the main (top) menu items for the IM page.
         */
        abstract protected static function getMainMenu(string $url): array;

        /**
         * Check if the current user is a moderator (for UI flags).
         */
        abstract protected static function isModeratorCheck(): bool;

        // -- Table factories: override to use app-specific table subclasses ---------

        /**
         * @return class-string<FwImConversations>
         */
        protected static function conversationsTable(): string {
            return FwImConversations::class;
        }

        /**
         * @return class-string<FwImMessages>
         */
        protected static function messagesTable(): string {
            return FwImMessages::class;
        }

        /**
         * @return class-string<FwImAttachments>
         */
        protected static function attachmentsTable(): string {
            return FwImAttachments::class;
        }

        /**
         * @return class-string<FwImReadStatus>
         */
        protected static function readStatusTable(): string {
            return FwImReadStatus::class;
        }

        // -- Internals --------------------------------------------------------------

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
        private static function handleAttachments(IGlobalReqParams $globals, int $messageId): array {
            $filesData = $globals->readFilesValue('attachments', null);

            if (empty($filesData) || empty($filesData['name'])) {
                return [];
            }

            $manager = self::getUploadManager();
            $result = $manager->storeAll($filesData, UploadRules::documentsAndImages());

            $attClass = static::attachmentsTable();
            $attachments = [];
            $now = time();

            foreach ($result->files as $info) {
                $id = $attClass::get()->insert([
                    'message_id' => $messageId,
                    'original_name' => $info->originalName,
                    'stored_name' => $info->storedName,
                    'mime_type' => $info->mimeType,
                    'size' => $info->size,
                    'created_at' => $now,
                ]);
                $attachments[] = $attClass::get()->selectOneByField('id', $id);
            }

            return $attachments;
        }

        /**
         * Enrich messages array with attachments and download URLs.
         */
        private static function enrichWithAttachments(array &$messages): void {
            $messageIds = array_map(fn ($m) => (int)$m['id'], $messages);
            $attClass = static::attachmentsTable();
            $grouped = $attClass::getByMessageIds($messageIds);

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

        // -- Pages ---------------------------------------------------------------

        public static function get__main(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $url = $globals->getUri();
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::redirect('/register');
            }

            $accountId = (int)$account->id();

            // Load conversations for this user
            $conversations = self::buildConversationList($accountId);

            $content = RenderIsland::render('im-page', [
                'conversations' => $conversations,
                'messagesUrl' => static::URL . '~messages',
                'sendUrl' => static::URL . '~send',
                'conversationsUrl' => static::URL . '~conversations',
                'downloadUrl' => static::URL . '~download',
                'searchRecipientsUrl' => static::URL . '~searchRecipients',
                'csrf' => Session::touchCSRF_(),
                'currentAccountId' => $accountId,
                'isModerator' => static::isModeratorCheck(),
            ]);

            return ControllerTools::ok(static::renderContent($content, $url));
        }

        // -- API: conversations ---------------------------------------------------

        public static function post__conversations(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $accountId = (int)$account->id();
            $conversations = self::buildConversationList($accountId);

            return ControllerTools::JSON(['conversations' => $conversations]);
        }

        // -- API: messages --------------------------------------------------------

        public static function post__messages(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $accountId = (int)$account->id();
            $conversationId = (int)$globals->readPostValue('conversation_id', '0');

            if (!$conversationId) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }

            $convsClass = static::conversationsTable();
            $msgsClass = static::messagesTable();
            $readClass = static::readStatusTable();

            // Access check
            if (!$convsClass::isParticipant($conversationId, $accountId)) {
                return ControllerTools::JSON(['error' => 'Access denied'], status: 403);
            }

            // Fetch messages
            $messages = $msgsClass::get()->selectAll(function (SelectInterface $q) use ($conversationId): void {
                $q->where('conversation_id = ?', [$conversationId]);
                $q->orderBy(['created_at ASC']);
            });

            // Enrich with sender names
            $senderIds = array_unique(array_filter(array_column($messages, 'sender_id')));
            $senders = [];

            if (!empty($senderIds)) {
                $accs = Account::getAccounts(
                    selectCallback: static function (SelectInterface $select) use ($senderIds): void {
                        $select->resetCols();
                        $select->cols(['id', 'login', 'name']);
                        $select->where('id IN (?)', [array_map('intval', $senderIds)]);
                    },
                );

                foreach ($accs as $a) {
                    $senders[(int)$a['id']] = $a;
                }
            }

            foreach ($messages as &$msg) {
                $sid = (int)$msg['sender_id'];
                $msg['sender_name'] = $senders[$sid]['name'] ?? '';
                $msg['sender_login'] = '';
            }
            unset($msg);

            // Enrich with attachments
            self::enrichWithAttachments($messages);

            // Mark as read
            if (!empty($messages)) {
                $lastMessageId = (int)$messages[array_key_last($messages)]['id'];
                $readClass::markRead($conversationId, $accountId, $lastMessageId);
            }

            return ControllerTools::JSON([
                'messages' => array_values($messages),
            ]);
        }

        // -- API: send ------------------------------------------------------------

        public static function post__send(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $postCsrf = $globals->readPostValue(Session::CSRF_TOKEN, '');

            if (!hash_equals(Session::touchCSRF_(), (string)$postCsrf)) {
                return ControllerTools::JSON(['error' => 'CSRF check failed'], status: 403);
            }

            $accountId = (int)$account->id();
            $recipientId = (int)$globals->readPostValue('recipient_id', '0');
            $message = trim((string)$globals->readPostValue('message', ''));

            if (!$recipientId || $message === '') {
                return ControllerTools::JSON(['error' => 'Recipient and message are required'], status: 400);
            }

            if ($recipientId === $accountId) {
                return ControllerTools::JSON(['error' => 'Cannot send message to yourself'], status: 400);
            }

            // Verify recipient exists
            $recipient = Account::getAccounts(
                selectCallback: static function (SelectInterface $select) use ($recipientId): void {
                    $select->resetCols();
                    $select->cols(['id']);
                    $select->where('id = ?', [$recipientId]);
                },
            );

            if (empty($recipient)) {
                return ControllerTools::JSON(['error' => 'Recipient not found'], status: 404);
            }

            $convsClass = static::conversationsTable();
            $msgsClass = static::messagesTable();
            $readClass = static::readStatusTable();

            $now = time();

            // Find or create conversation
            $conversationId = $convsClass::findOrCreate($accountId, $recipientId);

            // Insert message
            $messageId = $msgsClass::get()->insert([
                'conversation_id' => $conversationId,
                'sender_id' => $accountId,
                'body' => $message,
                'created_at' => $now,
            ]);

            // Handle file attachments
            self::handleAttachments($globals, (int)$messageId);

            // Update conversation last_message_at
            $convsClass::get()->updateByField([
                'last_message_at' => $now,
            ], 'id', $conversationId);

            // Mark as read for sender
            $readClass::markRead($conversationId, $accountId, (int)$messageId);

            return ControllerTools::JSON([
                'success' => true,
                'conversation_id' => $conversationId,
            ]);
        }

        // -- API: file download ---------------------------------------------------

        public static function get__download(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $attClass = static::attachmentsTable();
            $msgsClass = static::messagesTable();
            $convsClass = static::conversationsTable();

            $attachmentId = (int)$globals->readGetValue('id', '0');

            if (!$attachmentId) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }

            $attachment = $attClass::get()->selectOneByField('id', $attachmentId);

            if (!$attachment) {
                return ControllerTools::JSON(['error' => 'File not found'], status: 404);
            }

            // Find message -> conversation chain for access control
            $message = $msgsClass::get()->selectOneByField('id', (int)$attachment['message_id']);

            if (!$message) {
                return ControllerTools::JSON(['error' => 'File not found'], status: 404);
            }

            $accountId = (int)$account->id();
            $conversationId = (int)$message['conversation_id'];

            return SecureFileServing::serve(
                uploadDir: static::getUploadDir(),
                subDir: self::UPLOAD_SUBDIR,
                storedName: $attachment['stored_name'],
                displayName: $attachment['original_name'],
                accessCheck: fn () => $convsClass::isParticipant($conversationId, $accountId),
            );
        }

        // -- API: unread count ----------------------------------------------------

        public static function post__unreadCount(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $readClass = static::readStatusTable();
            $count = $readClass::getUnreadCountForUser((int)$account->id());

            return ControllerTools::JSON(['unreadCount' => $count]);
        }

        // -- API: search recipients -----------------------------------------------

        public static function post__searchRecipients(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $query = trim((string)$globals->readPostValue('query', ''));
            $accountId = (int)$account->id();

            $results = static::searchRecipients($accountId, $query);

            return ControllerTools::JSON(['recipients' => $results]);
        }

        // -- API: quick chat (for embedded widgets) --------------------------------

        /**
         * Load last N messages with a partner by partner_id.
         * Finds existing conversation or returns empty messages array.
         */
        public static function post__quickChat(IGlobalReqParams $globals, IRouterUriParams $params): mixed {
            $account = Account::fromSession();

            if (!$account) {
                return ControllerTools::JSON(['error' => 'Not authenticated'], status: 401);
            }

            $convsClass = static::conversationsTable();
            $msgsClass = static::messagesTable();

            $accountId = (int)$account->id();
            $partnerId = (int)$globals->readPostValue('partner_id', '0');
            $limit = min(20, max(1, (int)$globals->readPostValue('limit', '10')));

            if (!$partnerId || $partnerId === $accountId) {
                return ControllerTools::JSON(['error' => 'Invalid params'], status: 400);
            }

            // Find existing conversation (do NOT create one)
            $a = min($accountId, $partnerId);
            $b = max($accountId, $partnerId);
            $existing = $convsClass::get()->selectAll(function (SelectInterface $q) use ($a, $b): void {
                $q->where('participant_a = ? AND participant_b = ?', [$a, $b]);
            });

            if (empty($existing)) {
                return ControllerTools::JSON([
                    'messages' => [],
                    'conversation_id' => null,
                ]);
            }

            $conversationId = (int)$existing[0]['id'];

            // Fetch last N messages
            $messages = $msgsClass::get()->selectAll(function (SelectInterface $q) use ($conversationId, $limit): void {
                $q->where('conversation_id = ?', [$conversationId]);
                $q->orderBy(['created_at DESC']);
                $q->limit($limit);
            });

            // Reverse to chronological order
            $messages = array_reverse($messages);

            // Enrich with sender names
            $senderIds = array_unique(array_filter(array_column($messages, 'sender_id')));
            $senders = [];

            if (!empty($senderIds)) {
                $accs = Account::getAccounts(
                    selectCallback: static function (SelectInterface $select) use ($senderIds): void {
                        $select->resetCols();
                        $select->cols(['id', 'login', 'name']);
                        $select->where('id IN (?)', [array_map('intval', $senderIds)]);
                    },
                );

                foreach ($accs as $acc) {
                    $senders[(int)$acc['id']] = $acc;
                }
            }

            foreach ($messages as &$msg) {
                $sid = (int)$msg['sender_id'];
                $msg['sender_name'] = $senders[$sid]['name'] ?? '';
                $msg['sender_login'] = '';
            }
            unset($msg);

            // Enrich with attachments
            self::enrichWithAttachments($messages);

            return ControllerTools::JSON([
                'messages' => array_values($messages),
                'conversation_id' => $conversationId,
            ]);
        }

        // -- Helpers --------------------------------------------------------------

        /**
         * Build conversation list with partner info, last message snippet, unread count.
         */
        private static function buildConversationList(int $accountId): array {
            $convsClass = static::conversationsTable();
            $msgsClass = static::messagesTable();
            $readClass = static::readStatusTable();

            $convs = $convsClass::get()->selectAll(function (SelectInterface $q) use ($accountId): void {
                $q->where('participant_a = ? OR participant_b = ?', [$accountId, $accountId]);
                $q->orderBy(['last_message_at DESC']);
            });

            if (empty($convs)) {
                return [];
            }

            // Collect partner IDs
            $partnerIds = [];

            foreach ($convs as $conv) {
                $partnerIds[] = $convsClass::getPartnerId($conv, $accountId);
            }
            $partnerIds = array_unique($partnerIds);

            // Fetch partner accounts
            $partners = [];
            $accs = Account::getAccounts(
                selectCallback: static function (SelectInterface $select) use ($partnerIds): void {
                    $select->resetCols();
                    $select->cols(['id', 'login', 'name']);
                    $select->where('id IN (?)', [array_map('intval', $partnerIds)]);
                },
            );

            foreach ($accs as $a) {
                $partners[(int)$a['id']] = $a;
            }

            // Fetch read statuses for current user
            $readStatuses = [];
            $statuses = $readClass::get()->selectAll(function (SelectInterface $q) use ($accountId): void {
                $q->where('account_id = ?', [$accountId]);
            });

            foreach ($statuses as $rs) {
                $readStatuses[(int)$rs['conversation_id']] = (int)$rs['last_read_message_id'];
            }

            // Build result
            $result = [];

            foreach ($convs as $conv) {
                $convId = (int)$conv['id'];
                $partnerId = $convsClass::getPartnerId($conv, $accountId);
                $partner = $partners[$partnerId] ?? null;

                // Last message snippet
                $lastMessages = $msgsClass::get()->selectAll(function (SelectInterface $q) use ($convId): void {
                    $q->where('conversation_id = ?', [$convId]);
                    $q->orderBy(['created_at DESC']);
                    $q->limit(1);
                });
                $snippet = '';
                $lastMessageAt = (int)$conv['last_message_at'];

                if (!empty($lastMessages)) {
                    $body = $lastMessages[0]['body'] ?? '';
                    $snippet = mb_strlen($body) > 50 ? mb_substr($body, 0, 50) . '...' : $body;
                    $lastMessageAt = (int)$lastMessages[0]['created_at'];
                }

                // Unread count
                $lastRead = $readStatuses[$convId] ?? 0;
                $unreadRows = $msgsClass::get()->selectAll(function (SelectInterface $q) use ($convId, $accountId, $lastRead): void {
                    $q->resetCols();
                    $q->cols(['COUNT(*) as cnt']);
                    $q->where('conversation_id = ? AND sender_id != ? AND id > ?', [$convId, $accountId, $lastRead]);
                });
                $unreadCount = (int)($unreadRows[0]['cnt'] ?? 0);

                $entry = [
                    'id' => $convId,
                    'partner_id' => $partnerId,
                    'partner_name' => $partner['name'] ?? '',
                    'partner_login' => '',
                    'last_message_snippet' => $snippet,
                    'last_message_at' => $lastMessageAt,
                    'unread_count' => $unreadCount,
                ];

                // Let the app add custom fields (e.g. partner_has_expert_profile)
                static::enrichConversation($entry, $accountId);

                $result[] = $entry;
            }

            return $result;
        }
    }
}
