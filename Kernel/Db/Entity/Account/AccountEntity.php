<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Db\Entity\Account {
    use PHPCraftdream\Garnet\Bundle\I18n\FwI18n;
    use PHPCraftdream\Garnet\Kernel\Db\Entity\BaseEntity\BaseEntity;

    class AccountEntity extends BaseEntity {
        public function selectFields(): array {
            return [
                'id', 'login', 'login_type', 'name', 'type', 'time_zone', 'about',
                'reg_time', 'last_auth_time', 'last_online_time', 'token16',
            ];
        }

        public function manageGridFields(): array {
            return [
                'id',
                'login',
                'name',
                'last_online_time',
                'IS_ADMIN',
                'IS_MODERATOR',
                'IS_APPROVED',
                'IS_DISABLED',
            ];
        }

        public function manageFormFields(): array {
            return [
                'id',
                'login',
                'reg_time',
                'last_auth_time',
                'last_online_time',
                'name',
                'type',
                'time_zone',
                'about',
                'photo',
                'IS_ADMIN',
                'IS_MODERATOR',
                'IS_APPROVED',
                'IS_DISABLED',
            ];
        }

        public function viewFields(): array {
            return [
                'id', 'name', 'about', 'type',
            ];
        }

        public function editFields(): array {
            return [
                'id', 'login', 'name', 'type', 'time_zone', 'about',
            ];
        }

        public function dataFields(): array {
            return [
                Account::IS_ADMIN,
                Account::IS_MODERATOR,
                Account::IS_APPROVED,
                Account::IS_DISABLED,
            ];
        }

        public function patchItem(array &$item): array {
            return $item;
        }

        public function getFieldsInfo(array $fields = null): array {
            $account = Account::fromSession();
            $tf = FwI18n::getInstance();

            $result = [
                'id' => [
                    'name' => 'id',
                    'readOnly' => true,
                    'hidden' => !$account->isAdmin() && !$account->isModerator(),
                ],
                'login' => [
                    'type' => 'input',
                    'name' => $tf->User_login(),
                    'readOnly' => !$account->isAdmin(),
                ],
                'name' => [
                    'type' => 'input',
                    'name' => $tf->User_name(),
                    'validation' => ['len[3,32]', 'nameSymbols'],
                ],
                'reg_time' => [
                    'type' => 'unix_time',
                    'name' => $tf->User_reg_time(),
                    'readOnly' => true,
                ],
                'last_auth_time' => [
                    'type' => 'unix_time',
                    'name' => $tf->User_last_auth_time(),
                    'readOnly' => true,
                ],
                'last_online_time' => [
                    'type' => 'unix_time',
                    'name' => $tf->User_last_online_time(),
                    'readOnly' => true,
                ],
                'time_zone' => [
                    'name' => $tf->User_timezone(),
                    'type' => ['time_zone' => timezone_identifiers_list()],
                    'validation' => ['tzExists'],
                ],
                'about' => [
                    'name' => $tf->User_about(),
                    'type' => 'textarea',
                    'validation' => ['maxLen[1024]', 'simpleText'],
                ],
                Account::IS_ADMIN => [
                    'name' => $tf->UserFlag_ADMIN(),
                    'type' => ['bool' => $tf->UserFlag_ADMIN()],
                    'readOnly' => !$account->isAdmin(),
                    'dataParam' => true,
                ],
                Account::IS_MODERATOR => [
                    'name' => $tf->UserFlag_MODERATOR(),
                    'type' => ['bool' => $tf->UserFlag_MODERATOR()],
                    'readOnly' => !$account->isAdmin(),
                    'dataParam' => true,
                ],
                Account::IS_APPROVED => [
                    'name' => $tf->UserFlag_APPROVED(),
                    'type' => ['bool' => $tf->UserFlag_APPROVED()],
                    'readOnly' => !$account->isAdmin() && !$account->isModerator(),
                    'dataParam' => true,
                ],
                Account::IS_DISABLED => [
                    'name' => $tf->UserFlag_DISABLED(),
                    'type' => ['bool' => $tf->UserFlag_DISABLED()],
                    'readOnly' => !$account->isAdmin() && !$account->isModerator(),
                    'dataParam' => true,
                ],
            ];

            return $this->filterKeys($result, $fields);
        }
    }
}
