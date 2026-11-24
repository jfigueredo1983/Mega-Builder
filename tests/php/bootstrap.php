<?php

declare(strict_types=1);

// Unit tests run outside PrestaShop: core classes (Db, Configuration, Context...) are absent,
// and the module code falls back to its standalone behaviour.
require_once dirname(__DIR__, 2) . '/src/Autoloader.php';
\MegaBuilder\Autoloader::register();

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
