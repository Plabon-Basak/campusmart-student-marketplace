<?php
/**
 * Dedicated search entry point. Forwards to the marketplace page with the query.
 */
require_once __DIR__ . '/includes/init.php';

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') {
    redirect('products.php');
}
redirect('products.php?q=' . urlencode($q));
