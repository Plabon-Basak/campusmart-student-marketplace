<?php
/**
 * Admin login entry point.
 * Admins authenticate through the main login page, which routes them
 * straight to the admin dashboard on success.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/init.php';

if (is_admin()) {
    redirect('dashboard.php');
}

set_flash('info', 'Admins log in with their CampusMart account through the same login page.');
redirect('../login.php?redirect=' . urlencode('admin/dashboard.php'));
