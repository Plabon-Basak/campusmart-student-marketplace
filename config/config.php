<?php
/**
 * Application constants.
 * Do NOT put database credentials here - see config/database.php
 */

declare(strict_types=1);

// Detect the app's URL base path so the project works from any htdocs location.
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? 'C:/xampp/htdocs') ?: 'C:/xampp/htdocs');
$projectPath = str_replace('\\', '/', dirname(__DIR__));

if ($docRoot !== '' && strpos($projectPath, $docRoot) === 0) {
    $appUrl = rtrim(substr($projectPath, strlen($docRoot)), '/');
} else {
    $appUrl = '/campusmart';
}

define('APP_NAME', 'CampusMart');
define('APP_TAGLINE', 'Buy. Sell. Connect. Within Your Campus.');
define('APP_BASE_URL', $appUrl);
define('BASE_PATH', dirname(__DIR__));

define('UPLOAD_DIR', BASE_PATH . '/assets/images/uploads');
define('UPLOAD_URL', APP_BASE_URL . '/assets/images/uploads');

// Maximum single uploaded image size (bytes) - 5 MB
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024);
// Maximum width/height an uploaded image is resized down to
define('MAX_IMAGE_DIMENSION', 1400);

// When true, verbose error details are shown (never enable in production).
define('APP_DEBUG', false);
