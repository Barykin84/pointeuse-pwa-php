<?php
// api/login.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../app/db.php';

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$email = trim($data['email'] ?? '');
$pass  = (string)($data['password'] ?? '');

if (!$email || !$pass) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Champs requis']); exit; }

$stmt = $pdo->prepare('SELECT id, password_hash, full_name, role FROM users WHERE email=? LIMIT 1');
$stmt->execute([$email]);
$u = $stmt->fetch();
if (!$u || !password_verify($pass, $u['password_hash'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'Identifiants invalides']);
  exit;
}
$_SESSION['user_id'] = (int)$u['id'];
$_SESSION['full_name'] = $u['full_name'];
$_SESSION['email'] = $email;
$_SESSION['role'] = $u['role'];
echo json_encode(['ok'=>true, 'user'=>['id'=>(int)$u['id'],'name'=>$u['full_name'],'role'=>$u['role']]]);