<?php
// app/csrf.php
require_once __DIR__ . '/session.php';
function assert_csrf_or_die(): void {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $t = $_POST['csrf'] ?? '';
    if (!$t || !hash_equals($_SESSION['csrf'], $t)) {
      http_response_code(400);
      exit('Requête invalide (CSRF)');
    }
  }
}