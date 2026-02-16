<?php
// app/db.php
$configPath = __DIR__ . '/config.php';
if (!is_file($configPath)) {
  http_response_code(500);
  exit('Configuration manquante.');
}
$cfg = require $configPath;
date_default_timezone_set($cfg['timezone'] ?? 'Europe/Paris');

try {
  $pdo = new PDO(
    $cfg['db']['dsn'],
    $cfg['db']['user'],
    $cfg['db']['pass'],
    $cfg['db']['options']
  );
} catch (Throwable $e) {
  http_response_code(500);
  exit('Erreur de connexion DB: ' . htmlspecialchars($e->getMessage()));
}