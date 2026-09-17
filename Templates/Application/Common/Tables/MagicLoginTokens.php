<?php declare(strict_types=1);

namespace PHPCraftdream\Application\Common\Tables {
    use PHPCraftdream\Garnet\Bundle\Modules\Accounts\Auth\Tables\FwMagicLoginTokens;

    class MagicLoginTokens extends FwMagicLoginTokens {
        protected string $tableName = 'magic_login_tokens';
    }
}
