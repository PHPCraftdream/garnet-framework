<?php declare(strict_types=1);

// Regenerate framework i18n TypeScript files from PHP source.
//
// This is a framework-standalone version of what BaseAppInit::dumpFrontLang()
// does for scaffolded apps. Use this after editing Bundle/I18n/I18nData{En,Ru}.php
// to update Bundle/Front/I18nGen/* without needing an app context or .env.
//
// Usage: php tooling/scripts/regen-i18n.php

$root = dirname(__DIR__, 2);
require $root . '/Kernel/Io/I18n/I18nFrontendDumper.php';
require $root . '/Bundle/I18n/I18nDataEn.php';
require $root . '/Bundle/I18n/I18nDataRu.php';

use PHPCraftdream\Garnet\Bundle\I18n\I18nDataEn;
use PHPCraftdream\Garnet\Bundle\I18n\I18nDataRu;
use PHPCraftdream\Garnet\Kernel\Io\I18n\I18nFrontendDumper;

$out = $root . DIRECTORY_SEPARATOR . 'Bundle' . DIRECTORY_SEPARATOR . 'Front' . DIRECTORY_SEPARATOR . 'I18nGen';

$langData = [
    I18nDataEn::LANG => I18nDataEn::$data,
    I18nDataRu::LANG => I18nDataRu::$data,
];

I18nFrontendDumper::dump($langData, $out, 'Framework', true);

echo "Regenerated i18n files to: {$out}\n";
