<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Account {
    use Aura\Sql\Exception;
    use Aura\SqlQuery\Common\SelectInterface;
    use PHPCraftdream\Garnet\Kernel\Core\Benchmark\BenchmarkLog;
    use PHPCraftdream\Garnet\Kernel\Core\Event\Event;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\Session\Session;
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Exceptions\DbException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\IniConfigException;
    use PHPCraftdream\Garnet\Kernel\Exceptions\ValidationException;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IAccount;
    use PHPCraftdream\Garnet\Kernel\Interfaces\IEventObj;

    class Account implements IAccount {
        public const SESSION_AUTH_LOGIN = 'auth_login';

        public const SESSION_AUTH_LOGIN_TYPE = 'auth_login_type';

        public const PARAM_CONSENT_PD_AT = 'consent_pd_at';

        public const PARAM_CONSENT_MARKETING_AT = 'consent_marketing_at';

        public const PARAM_CONSENT_MARKETING_WITHDRAWN_AT = 'consent_marketing_withdrawn_at';

        protected static array $items = [];

        protected int $id = 0;

        protected string $login = '';

        protected array $params = [];

        protected array $data = [];

        protected array $setParams = [];

        protected array $setData = [];

        protected array $unsetData = [];

        protected function __construct(string $loginOrId) {
            $id = intval($loginOrId);

            if ($id . '' === $loginOrId) {
                $this->login = '';
                $this->id = $id;
            } else {
                $this->login = $loginOrId;
                $this->id = 0;
            }
        }

        protected static IAccount|null $sessionAccount = null;

        /**
         * @return Account|null
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         * @throws ValidationException
         */
        public static function fromSession(): ?Account {
            // Cache hit only when the row actually exists. We must never cache
            // an id=0 "ghost" — that would freeze the early-flow stub even
            // after touchAccount() inserts the real row later in the request.
            if (!empty(static::$sessionAccount) && static::$sessionAccount->id() > 0) {
                /** @var Account */
                return static::$sessionAccount;
            }

            $session = Session::get();
            $email = $session->getValue(Account::SESSION_AUTH_LOGIN);

            if (empty($email)) {
                return null;
            }

            // Read-only: do NOT insert here. A pre-verify POST (request-code
            // phase) places the email into the session but the account must
            // not exist until verify-success. Insertion happens explicitly via
            // Account::touchAccount() in the verify-success path.
            /** @var Account $account */
            $account = static::get($email);

            if ($account->id() > 0) {
                static::$sessionAccount = $account;
            }

            return $account;
        }

        /**
         * @param string $login
         * @param string $loginType
         * @return Account
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         * @throws ValidationException
         */
        public static function touchAccount(string $login, string $loginType): Account {
            $dbAccount = DbAccount::get();

            if (mb_strlen($login) < 7) {
                ValidationException::fire('max_len', [7]);
            }

            if (!empty(static::$items[$login])) {
                $account = static::$items[$login];
                /* @phpstan-ignore-next-line */
                $account?->readDataAsyncPollFinishAll();

                /* @phpstan-ignore-next-line */
                if ($account?->readParam('login') === $login) {
                    return $account;
                }
            }

            $insert = $dbAccount->newInsert();
            $insert->addRow(['login' => $login, 'login_type' => $loginType]);
            $dbAccount->getQueryEx()->exInsertIgnoreAsync($insert);

            // Wait for INSERT to complete before retrieving
            DbPool::get()->pollFinishAll();

            return static::get($login);
        }

        /**
         * @param string $login
         * @return static
         */
        public static function get(string $login): IAccount {
            if (empty(static::$items[$login])) {
                $item = new static($login);

                Event::get()->subscribe('flush_data', fn (IEventObj $ev) => $item->flush());

                static::$items[$login] = $item;
            }

            static::$items[$login]->readDbAsync();
            static::$items[$login]->readDataAsyncPollFinishAll();

            return static::$items[$login];
        }

        protected array $readDataAsyncLinks = [];

        public function readDataAsyncPollFinishAll(): void {
            if (!empty($this->readDataAsyncLinks)) {
                BenchmarkLog::log('before_account_readDataAsyncPoll');
                DbPool::pollLinks($this->readDataAsyncLinks);
                BenchmarkLog::log('after_account_readDataAsyncPoll');
            }
        }

        public function readDataAsyncPollOnce(): void {
            if (!empty($this->readDataAsyncLinks)) {
                DbPool::pollLinks($this->readDataAsyncLinks, false);
            }
        }

        /**
         * @return void
         * @throws DbException
         * @throws IniConfigException
         */
        public function readDbAsync(): void {
            $dbAccount = DbAccount::get();

            $this->readDataAsyncLinks[] = $dbAccount->simpleSelectOneByFieldAsync(
                $this->id > 0 ? 'id' : 'login',
                $this->id > 0 ? $this->id . '' : $this->login,
                callback: function ($params) use ($dbAccount): void {
                    if (!empty($params)) {
                        $this->params = $params;
                        $this->id = intval($params[$dbAccount->getPrimaryKey()]);
                        $login = $params['login'] ?? null;

                        static::$items[$this->id] = $this;

                        if (!empty($login)) {
                            $this->login = $login;
                            static::$items[$login] = $this;
                        }
                    }

                    if (empty($this->id)) {
                        return;
                    }

                    $dbAccountData = DbAccountData::get();
                    $this->readDataAsyncLinks[] = $dbAccountData->simpleSelectByFieldAsync(
                        'account_id',
                        $this->id,
                        callback: function ($data): void {
                            if (empty($data)) {
                                return;
                            }

                            $newData = [];

                            foreach ($data as $dataItem) {
                                $newData[$dataItem['param']] = $dataItem['value'];
                            }

                            $this->data = $newData;
                        }
                    );
                }
            );
        }

        /**
         * @return array
         */
        public function getParams(): array {
            return $this->params;
        }

        /**
         * @return array
         */
        public function getData(): array {
            return $this->data;
        }

        /**
         * @param string $name
         * @param string|null $default
         * @return string|null
         */
        public function readParam(string $name, ?string $default = null): ?string {
            return array_key_exists($name, $this->params) ? (string)$this->params[$name] : $default;
        }

        /**
         * @param array $names
         * @return array
         */
        public function readParams(array $names): array {
            $result = [];

            foreach ($names as $name) {
                $result[$name] = array_key_exists($name, $this->params) ? $this->params[$name] : null;
            }

            return $result;
        }

        public function id(): int {
            return (int)($this->params['id'] ?? 0);
        }

        /**
         * @param string $name
         * @param string|null $default
         * @return string|null
         */
        public function readData(string $name, ?string $default = null): ?string {
            return array_key_exists($name, $this->data) ? $this->data[$name] : $default;
        }

        /**
         * @param array $names
         * @return array
         */
        public function readDataParams(array $names): array {
            $result = [];

            foreach ($names as $name) {
                $result[$name] = array_key_exists($name, $this->data) ? $this->data[$name] : null;
            }

            return $result;
        }

        /**
         * @param string $name
         * @param string|int|null $value
         * @return void
         */
        public function setParam(string $name, string|int|null $value): void {
            if ($value !== ($this->params[$name] ?? null)) {
                $this->params[$name] = $value;
                $this->setParams[$name] = $value;
            }
        }

        public const IS_ADMIN = 'IS_ADMIN';

        public const IS_OWNER = 'IS_OWNER';

        public const IS_MODERATOR = 'IS_MODERATOR';

        public const IS_APPROVED = 'IS_APPROVED';

        public const IS_DISABLED = 'IS_DISABLED';

        public function hasConsentPd(): bool {
            return $this->consentPdAt() !== null;
        }

        public function hasConsentMarketing(): bool {
            $setAt = $this->consentMarketingAt();

            if ($setAt === null) {
                return false;
            }

            $withdrawnAt = $this->consentTimestamp(static::PARAM_CONSENT_MARKETING_WITHDRAWN_AT);

            if ($withdrawnAt !== null && $withdrawnAt >= $setAt) {
                return false;
            }

            return true;
        }

        public function consentPdAt(): ?int {
            return $this->consentTimestamp(static::PARAM_CONSENT_PD_AT);
        }

        public function consentMarketingAt(): ?int {
            return $this->consentTimestamp(static::PARAM_CONSENT_MARKETING_AT);
        }

        public function withdrawMarketingConsent(): void {
            $this->setParam(static::PARAM_CONSENT_MARKETING_WITHDRAWN_AT, (string)time());
        }

        private function consentTimestamp(string $param): ?int {
            $raw = $this->readParam($param);

            if ($raw === null || $raw === '' || $raw === '0') {
                return null;
            }
            $ts = (int)$raw;

            return $ts > 0 ? $ts : null;
        }

        public function setAdmin(bool $value): void {
            $this->data[static::IS_ADMIN] = $value ? 1 : 0;
            $this->setData[static::IS_ADMIN] = $value ? 1 : 0;
        }

        public function setOwner(bool $value): void {
            $this->data[static::IS_OWNER] = $value ? 1 : 0;
            $this->setData[static::IS_OWNER] = $value ? 1 : 0;
        }

        public function setModerator(bool $value): void {
            $this->data[static::IS_MODERATOR] = $value ? 1 : 0;
            $this->setData[static::IS_MODERATOR] = $value ? 1 : 0;
        }

        public function setApproved(bool $value): void {
            $this->data[static::IS_APPROVED] = $value ? 1 : 0;
            $this->setData[static::IS_APPROVED] = $value ? 1 : 0;
        }

        public function setDisabled(bool $value): void {
            $this->data[static::IS_DISABLED] = $value ? 1 : 0;
            $this->setData[static::IS_DISABLED] = $value ? 1 : 0;
        }

        public function isAdmin(): bool {
            return isset($this->data[static::IS_ADMIN]) && intval($this->data[static::IS_ADMIN]) > 0;
        }

        public function isOwner(): bool {
            return isset($this->data[static::IS_OWNER]) && intval($this->data[static::IS_OWNER]) > 0;
        }

        public function isModerator(): bool {
            return isset($this->data[static::IS_MODERATOR]) && intval($this->data[static::IS_MODERATOR]) > 0;
        }

        public function isApproved(): bool {
            return isset($this->data[static::IS_APPROVED]) && intval($this->data[static::IS_APPROVED]) > 0;
        }

        public function isDisabled(): bool {
            return isset($this->data[static::IS_DISABLED]) && intval($this->data[static::IS_DISABLED]) > 0;
        }

        /**
         * @param array $params
         * @return void
         */
        public function setParams(array $params): void {
            foreach ($params as $name => $value) {
                $this->setParam($name, $value);
            }
        }

        /**
         * @param string $name
         * @param string|int $value
         * @return void
         */
        public function setData(string $name, string|int $value): void {
            if (array_key_exists($name, $this->data)) {
                if ($this->data[$name] !== $value) {
                    $this->data[$name] = $value;
                    $this->setData[$name] = $value;
                }
            } else {
                $this->data[$name] = $value;
                $this->setData[$name] = $value;
            }

            if (array_key_exists($name, $this->unsetData)) {
                unset($this->unsetData[$name]);
            }
        }

        /**
         * @param string $name
         * @return void
         */
        public function unsetData(string $name): void {
            if (array_key_exists($name, $this->data)) {
                unset($this->data[$name]);
            }

            if (array_key_exists($name, $this->setData)) {
                unset($this->setData[$name]);
            }

            $this->unsetData[$name] = true;
        }

        /**
         * @param array $names
         * @return void
         */
        public function setBoolDataArr(array $names): void {
            foreach ($names as $name => $value) {
                $val = intval($value);
                $oldVal = intval($this->data[$name] ?? 0);

                if ($val === $oldVal) {
                    continue;
                }

                if ($val) {
                    $this->setData($name, $val);

                    continue;
                }

                $this->unsetData($name);
            }
        }

        /**
         * @param array $names
         * @return void
         */
        public function setDataArr(array $names): void {
            foreach ($names as $name => $value) {
                $this->setData($name, $value);
            }
        }

        /**
         * @param array $names
         * @return void
         */
        public function unsetDataArr(array $names): void {
            foreach ($names as $name) {
                $this->unsetData($name);
            }
        }

        /**
         * @return void
         * @throws DbException
         * @throws Exception
         * @throws IniConfigException
         */
        public function flush(): void {
            if (!empty($this->setParams)) {
                $dbAccount = DbAccount::get();
                $this->readDataAsyncLinks[] = $dbAccount->updateByIdAsync($this->setParams, $this->id);
                $this->setParams = [];
            }

            if (!empty($this->setData)) {
                $dbAccountData = DbAccountData::get();
                $insertData = [];

                foreach ($this->setData as $k => $v) {
                    $insertData[] = [
                        'account_id' => $this->id,
                        'param' => $k,
                        'value' => $v,
                    ];
                }

                $this->readDataAsyncLinks[] = $dbAccountData->insertBatchAsync($insertData, 'value = VALUES(value)');
                $this->setData = [];
            }

            if (!empty($this->unsetData)) {
                $dbAccountData = DbAccountData::get();
                $delete = $dbAccountData->newDelete();
                $params = array_keys($this->unsetData);
                $delete->where('account_id = :account_id', ['account_id' => $this->id]);
                $delete->where('param in (:params)', ['params' => $params]);

                $this->readDataAsyncLinks[] = $dbAccountData->getQueryEx()->exDeleteAsync($delete);
                $this->unsetData = [];
            }
        }

        public static function getAccounts(?callable $selectCallback = null, ?array $accountDataFields = null): array {
            $closure = is_callable($selectCallback) ? $selectCallback(...) : (function (SelectInterface $select): void {
                $select->orderBy(['id desc']);
            })(...);
            $accounts = DbAccount::get()->selectAll($closure);

            if (empty($accountDataFields)) {
                return $accounts;
            }

            $accountsData = DbAccountData::getAllUsersData($accountDataFields);

            $accounts = array_map(static function (array $account) use ($accountsData): array {
                $accountId = $account['id'] ?? null;
                $accountData = $accountsData[$accountId] ?? null;

                if (!empty($accountData)) {
                    foreach ($accountData as $name => $value) {
                        $account[$name] = $value;
                    }
                }

                return $account;
            }, $accounts);

            return $accounts;
        }
    }
}
