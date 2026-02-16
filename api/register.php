<?php
// api/register.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../app/db.php';

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$name  = trim($data['full_name'] ?? '');
$email = trim($data['email'] ?? '');
$pass  = (string)($data['password'] ?? '');

if (!$name || !$email || !$pass) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Champs requis']); exit; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Email invalide']); exit; }

$hash = password_hash($pass, PASSWORD_DEFAULT);
try {
  $stmt = $pdo->prepare('INSERT INTO users (email, password_hash, full_name) VALUES (?,?,?)');
  $stmt->execute([$email, $hash, $name]);
  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  http_response_code(409);
  echo json_encode(['ok'=>false,'error'=>'Email déjà utilisé']);
}