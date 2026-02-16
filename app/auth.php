<?php
// app/auth.php
require_once __DIR__ . '/session.php';
function require_login(): void {
  if (empty($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
  }
}
function require_admin(): void {
  if (($_SESSION['role'] ?? 'user') !== 'admin') {
    http_response_code(403);
    exit('Accès interdit');
  }
}