<?php
// api/logout.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/session.php';
session_unset();
session_destroy();
echo json_encode(['ok'=>true]);