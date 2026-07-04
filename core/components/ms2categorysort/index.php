<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use Ms2CategorySort\Domain\XpdoMapExtension;

return [
    'map' => XpdoMapExtension::forMsCategoryMember(),
];
