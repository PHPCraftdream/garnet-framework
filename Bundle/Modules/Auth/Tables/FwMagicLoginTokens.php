<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Bundle\Modules\Auth\Tables {
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTable;
    use PHPCraftdream\Garnet\Kernel\Db\Tables\DbTableBuilderFactory;
    use PHPCraftdream\Garnet\Kernel\Interfaces\Db\ITableBuilderDriver;

    /**
     * Magic-login-token registry — issue, validate and consume
     * one-click login codes. Schema is generic; the app that uses
     * this module extends the class and pins `$tableName` to its
     * own prefix (e.g. `Apps/MyApp/Common/Tables/MagicLoginTokens`
     * sets `'ir_magic_login_tokens'`).
     *
     * This is the NEW one-click magic-login mechanism, replacing the
     * legacy hash-based auth (`#token=`) with a plain URL query
     * parameter carrying a one-time 32-char hex code
     * (`bin2hex(random_bytes(16))`). The code is valid for 5 minutes
     * (`expires_at`, computed by the service as `created_at + 300`)
     * and, unlike the old mechanism, works independently of any PHP
     * session: the code alone is the credential.
     *
     * `used_at` is `NULL` until the code is consumed and is set
     * atomically (first write wins) at consumption time — this is
     * the only guarantee of one-time use, so it must never be reset
     * by anything other than the consuming transaction.
     *
     * `return_uri` snapshots where to redirect the user after a
     * successful login (the page that requested the code, e.g.
     * `/first-step/token~INVITE`). `consent_pd_at` and
     * `consent_marketing_at` snapshot the corresponding session
     * consent values at code-generation time, since the session
     * active when the link is opened may differ from the one that
     * requested it.
     */
    abstract class FwMagicLoginTokens extends DbTable {
        protected string $primaryKey = 'id';

        public static function init(): ITableBuilderDriver {
            return DbTableBuilderFactory::newCreateTable(table: static::get())
                ->addIdColumn()
                ->addColumn(column: 'token', type: 'VARCHAR', length: '64', null: false)
                ->addColumn(column: 'email', type: 'VARCHAR', length: '255', null: false)
                ->addColumn(column: 'created_at', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'expires_at', type: 'INT', length: '11', null: false, default: '0')
                ->addColumn(column: 'used_at', type: 'INT', length: '11', null: true)
                ->addColumn(column: 'return_uri', type: 'VARCHAR', length: '500', null: false, default: "''")
                ->addColumn(column: 'consent_pd_at', type: 'VARCHAR', length: '32', null: false, default: "''")
                ->addColumn(column: 'consent_marketing_at', type: 'VARCHAR', length: '32', null: false, default: "''")
                ->addIndex(indexName: 'token', indexes: ['token'], type: 'UNIQUE')
                ->addIndex(indexName: 'email', indexes: ['email'])
            ;
        }
    }
}
