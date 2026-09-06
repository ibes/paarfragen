<?php

declare(strict_types=1);

namespace Paarfragen\Tests;

use Tempest\Database\Config\SQLiteConfig;

return new SQLiteConfig(path: __DIR__ . '/testing.sqlite');
