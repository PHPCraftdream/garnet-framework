<?php declare(strict_types=1);

namespace PHPCraftdream\Garnet\Kernel\Io\I18n {
    use PHPCraftdream\Garnet\Kernel\Exceptions\CommonException;

    class I18nFrontendDumper {
        /**
         * @param array $langData
         * @param string $dir
         * @param string $bundleName
         * @param bool $isFrameworkBundle
         * @return void
         * @throws CommonException
         */
        public static function dump(array $langData, string $dir, string $bundleName, bool $isFrameworkBundle): void {
            $dir = realpath($dir);

            if (!$dir || !is_dir($dir)) {
                return;
            }

            $classNames = [];
            $methods = [];

            foreach ($langData as $lang => $data) {
                $className = "I18nData{$lang}";
                $classNames[] = $className;
                $fileName = "{$className}.ts";

                $fields = ["    static lang = '{$lang}';"];

                foreach ($data as $key => $value) {
                    if (mb_strtolower($key) !== 'lang') {
                        $v = str_replace("'", "\\'", $value);
                        $fields[] = "    static {$key} = '{$v}';";
                        $methods[$key] = $key;
                    }
                }

                if (count($fields) < 2) {
                    continue;
                }

                $fields = join("\n", $fields);

                $content = "export class {$className} {\n{$fields}\n}\n";
                file_put_contents($dir . DIRECTORY_SEPARATOR . $fileName, $content);
            }

            $imports = array_map(fn ($cl) => "import {{$cl}} from './{$cl}';", $classNames);
            $imports[] = "import {I18nBase} from '@common/Utils/I18nBase';";
            $imports = join("\n", $imports);
            $className = "I18n{$bundleName}";

            $baseDir = dirname(dirname(dirname(dirname(__FILE__))));
            $templatePath = $baseDir . DIRECTORY_SEPARATOR . 'Templates' . DIRECTORY_SEPARATOR . 'CodeFiles' . DIRECTORY_SEPARATOR . 'TrTs.template';
            $classTemplate = @file_get_contents($templatePath);

            if (!$classTemplate) {
                throw new CommonException('Fail on read template');
            }

            $tr = str_replace('[[imports]]', $imports, $classTemplate);
            $tr = str_replace('[[bundleName]]', $bundleName, $tr);
            $tr = str_replace('[[classArr]]', join(', ', $classNames), $tr);
            $tr = str_replace('[[className]]', $className, $tr);
            $tr = str_replace('[[classNames]]', join(', ', $classNames), $tr);

            if (empty($methods)) {
                return;
            }

            $methods = array_values($methods);
            $methods = array_map(
                fn ($m) => "    {$m}: t = (a = []) => this.t('{$m}', a);",
                $methods
            );

            $tr = str_replace('[[methods]]', join("\n", $methods), $tr);

            file_put_contents($dir . DIRECTORY_SEPARATOR . "{$className}.ts", $tr);
        }
    }
}
