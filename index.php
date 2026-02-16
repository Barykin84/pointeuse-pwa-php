<?php
require_once __DIR__ . '/app/session.php';
if (!empty($_SESSION['user_id'])) {
  header('Location: /pointeuse.php'); exit;
} else {
  header('Location: /login.php'); exit;
}