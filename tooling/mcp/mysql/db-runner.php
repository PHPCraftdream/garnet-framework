<?php declare(strict_types=1);
/**
 * Garnet MySQL Runner — executes SQL through the framework's DB connection.
 *
 * Reads JSON from argv[1]: {"sql": "...", "params": [...]}
 * Returns JSON to stdout: {"rows": [...]} or {"affected": N, "insertId": N} or {"error": "..."}
 */

namespace PHPCraftdream\GarnetMySql {
    // Bootstrap the framework
    $appDir = getenv('GARNET_APP_DIR') ?: (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Apps' . DIRECTORY_SEPARATOR . 'App');
    require_once $appDir . DIRECTORY_SEPARATOR . 'autoload.php';

    use PHPCraftdream\Garnet\Kernel\Core\Env\Env;
    use PHPCraftdream\Garnet\Kernel\Db\Link\DbPool;
    use PHPCraftdream\Garnet\Kernel\Io\IniConfig\IniConfig;
    use Throwable;

    // Minimal init: just load configs so DB connection works
    $configDir = $appDir . DIRECTORY_SEPARATOR . 'WorkDir' . DIRECTORY_SEPARATOR;
    $configDir .= (Env::isDevDir() ? 'ConfigDev' : 'ConfigProd') . DIRECTORY_SEPARATOR;

    IniConfig::defineDbIni($configDir . 'db.ini');

    // Read input
    $input = $argv[1] ?? '';

    if (!$input) {
        echo json_encode(['error' => 'No input provided']);

        exit(1);
    }

    $data = json_decode($input, true);

    if (!is_array($data) || empty($data['sql'])) {
        echo json_encode(['error' => 'Invalid input: expected {"sql": "...", "params": [...]}']);

        exit(1);
    }

    $sql = trim($data['sql']);
    $params = $data['params'] ?? [];

    try {
        $link = DbPool::get()->newLink();

        // Determine query type
        $firstWord = strtoupper(strtok($sql, " \t\n\r"));

        if (in_array($firstWord, ['SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'], true)) {
            // Read query — return rows
            $rows = $link->query($sql, $params);
            echo json_encode(['rows' => is_array($rows) ? $rows : []], JSON_UNESCAPED_UNICODE);
        } else {
            // Write query — return affected rows
            $result = $link->query($sql, $params);
            $insertId = 0;

            if (is_int($result) && $result > 0) {
                $insertId = $result;
            }
            echo json_encode([
                'affected' => is_bool($result) ? ($result ? 1 : 0) : (is_int($result) ? 1 : 0),
                'insertId' => $insertId,
            ], JSON_UNESCAPED_UNICODE);
        }
    } catch (Throwable $e) {
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);

        exit(1);
    }
}
