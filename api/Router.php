<?php
declare(strict_types=1);

// Compatibility loader. Implementasi router berada di Core agar route lama
// yang masih meng-include api/Router.php tetap tidak rusak.
require_once __DIR__ . '/Core/Routing/ApiRouter.php';
