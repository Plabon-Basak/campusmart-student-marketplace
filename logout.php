<?php
/**
 * Secure logout: destroys the session and cookie.
 */
require_once __DIR__ . '/includes/init.php';

if (is_logged_in()) {
    audit_log('logout', 'user', current_user_id(), 'User logged out.');
}
logout_user();
set_flash('info', 'You have been logged out successfully.');
redirect('login.php');
