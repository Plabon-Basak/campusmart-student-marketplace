<?php
/**
 * Session management and authentication workflows.
 */

declare(strict_types=1);

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('CAMPUSMART_SESSION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

/**
 * Attempts to authenticate a user. Returns the user row or null.
 */
function attempt_login(string $email, string $password): ?array
{
    $st = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $user = $st->fetch();

    if ($user === false || !password_verify($password, $user['password_hash'])) {
        return null;
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['login_time'] = time();
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Returns the safe redirect target from a ?redirect= parameter.
 */
function safe_redirect_target(string $fallback = 'index.php'): string
{
    $target = $_GET['redirect'] ?? ($_SESSION['intended'] ?? '');
    unset($_SESSION['intended']);
    if ($target === '') {
        return $fallback;
    }
    // Only allow same-site relative redirects.
    if (str_starts_with($target, '/') && !str_starts_with($target, '//')) {
        return $target;
    }
    if (!preg_match('#^[a-zA-Z0-9_./-]+$#', $target)) {
        return $fallback;
    }
    return $target;
}
