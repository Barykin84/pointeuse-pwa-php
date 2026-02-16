<?php
// app/session.php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}