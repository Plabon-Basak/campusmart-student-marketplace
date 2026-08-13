<?php
/**
 * Admin authentication guard. Include at the top of every admin page.
 * Loads the application bootstrap and blocks non-admin visitors.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

require_admin();
