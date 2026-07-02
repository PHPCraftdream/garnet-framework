<?php declare(strict_types=1);

use PHPCraftdream\Garnet\Kernel\Db\Query\QueryTools;

describe('QueryTools', function (): void {
    it('QueryTools::escapeSqlParam', function (): void {
        $testData = [
            ["Hello, world! '; DROP TABLE users; -- ", "Hello, world! \'; DROP TABLE users; -- "],
            ["Hello', DROP TABLE users; --", "Hello\\', DROP TABLE users; --"],
            ['Hello, " World!', 'Hello, \\" World!'],
            ["Hello, \x00World!", 'Hello, World!'],
            ["Hello, 🚀'; DROP TABLE users; --", "Hello, 🚀\\'; DROP TABLE users; --"],
            ["Hello,\x20\x20World!", 'Hello, World!'],
            ['Hello"; DROP TABLE users; --', 'Hello\\"; DROP TABLE users; --'],
            ["Hello\n\r\tWorld", 'Hello\\n\\r\\tWorld'],
            ["Hello\x00\x08\x1F", 'Hello '],
            ["Hello\x80World", ''],
            ["Hello🚀'; DROP TABLE users; --", "Hello🚀\\'; DROP TABLE users; --"],
            ['Hello🚀, World!🌍', 'Hello🚀, World!🌍'],
            ['Hello, world!', 'Hello, world!'],
            ['Hello\'";DROP TABLE users; --', 'Hello\\\'\\";DROP TABLE users; --'],

            ["' OR 'a'='a'; --", "\\' OR \\'a\\'=\'a\\'; --"],
            ["' OR 1=1; --", "\\' OR 1=1; --"],
            ["' UNION SELECT * FROM users; --", "\\' UNION SELECT * FROM users; --"],
            ["'; EXEC malicious_command; --", "\\'; EXEC malicious_command; --"],
            ["\x00\x08\x1F", ' '],
            ['\\x00\\x08\\x1F', '\\\\x00\\\\x08\\\\x1F'],
            ['" OR 1=1; --', '\" OR 1=1; --'],
            ['\'; DROP TABLE users; --', '\\\'; DROP TABLE users; --'],
            ["\\'1 OR 1=1; --", "\\\\\'1 OR 1=1; --"],
            ['\\"1 OR 1=1; --', '\\\\\\"1 OR 1=1; --'],

            ["\\\'1 OR 1=1; --", "\\\\\\\\\'1 OR 1=1; --"],

            ['1; DROP TABLE users; --', '1; DROP TABLE users; --'],
            ["' OR 'a' LIKE 'a'; --", "\\' OR \\'a\\' LIKE \\'a\\'; --"],
            ["' UNION SELECT null, username, password FROM users; --", "\\' UNION SELECT null, username, password FROM users; --"],
            ["' OR 1=1; DROP TABLE users; --", "\\' OR 1=1; DROP TABLE users; --"],
            ["' OR 'text'='text' --", "\\' OR \\'text\\'=\'text\\' --"],
            ["' OR 'text'='text' #", "\\' OR \\'text\\'=\'text\\' #"],
            ["' OR 'text'='text' /*", "\\' OR \\'text\\'=\'text\\' /*"],

            ["'; SELECT * FROM information_schema.tables; --", "\\'; SELECT * FROM information_schema.tables; --"],
            ["'; DELETE FROM users; --", "\\'; DELETE FROM users; --"],
            ["' OR '1'='1'; --", "\\' OR \\'1\\'=\'1\\'; --"],
            ["' OR '1'='1' --", "\\' OR \\'1\\'=\'1\\' --"],
            ["'; UPDATE users SET password = 'hacked'; --", "\\'; UPDATE users SET password = \\'hacked\\'; --"],
            ["' UNION SELECT * FROM sensitive_data; --", "\\' UNION SELECT * FROM sensitive_data; --"],
            ["'; EXEC xp_cmdshell('dir'); --", "\\'; EXEC xp_cmdshell(\\'dir\\'); --"],
        ];

        $errors = null;

        foreach ($testData as $ind => $data) {
            $input = $data[0];
            $expectedResult = $data[1];
            $result = QueryTools::escapeSqlParam($input);

            if ($expectedResult !== $result) {
                if (empty($errors)) {
                    $errors = [];
                }

                $errors[$ind] = [
                    'input' => $input,
                    'result' => $result,
                    'expectedResult' => $expectedResult,
                ];
            }
        }

        expect($errors)->toBe(null);
    });

    it('QueryTools::buildSql', function (): void {
        $sql = 'SELECT * FROM table WHERE column1 = ? AND (column2 = :name OR column2 = :title)';
        $args = ['value1', 'name' => 'value2', ':title' => 'title'];

        $res = QueryTools::buildSql($sql, $args);

        expect($res)->toBe('SELECT * FROM table WHERE column1 = "value1" AND (column2 = "value2" OR column2 = "title")');
    });
});
