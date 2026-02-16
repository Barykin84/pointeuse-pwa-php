<?php
// api/time.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../app/db.php';

if (empty($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Non connecté']); exit; }
$userId = (int)$_SESSION['user_id'];

$method = $_SERVER['REQUEST_METHOD'];

// Read entries for a month
if ($method === 'GET') {
  $ym = $_GET['month'] ?? date('Y-m');
  [$y,$m] = array_map('intval', explode('-', $ym));
  $start = (new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00',$y,$m)));
  $end = $start->modify('first day of next month');

  $sql = 'SELECT id, started_at, ended_at, created_at
          FROM time_entries
          WHERE user_id=? AND started_at >= ? AND started_at < ?
          ORDER BY started_at DESC';
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$userId, $start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
  $rows = $stmt->fetchAll();

  // compute total + flag editable
  $total = 0;
  $nowTs = time();
  $role = $_SESSION['role'] ?? 'user';
  $entries = [];

  foreach ($rows as $r) {
    if (!empty($r['ended_at'])) {
      $total += max(0, strtotime($r['ended_at']) - strtotime($r['started_at']));
    }

    $canEdit = false;
    if ($role === 'admin') {
      $canEdit = true;
    } else {
      // Fenêtre d'édition 48h pour l'utilisateur
      $age = $nowTs - strtotime($r['created_at']);
      if ($age <= 48*3600) {
        $canEdit = true;
      }
    }

    $entries[] = [
      'id'         => (int)$r['id'],
      'started_at' => $r['started_at'],
      'ended_at'   => $r['ended_at'],
      'editable'   => $canEdit,
    ];
  }

  echo json_encode(['ok'=>true,'entries'=>$entries,'total_seconds'=>$total]);
  exit;
}


// Start / Stop
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $input['action'] ?? '';

// Arrivée
if ($action === 'arrive') {
  $stmt = $pdo->prepare('SELECT id FROM time_entries WHERE user_id=? AND ended_at IS NULL ORDER BY started_at DESC LIMIT 1');
  $stmt->execute([$userId]);
  if ($stmt->fetchColumn()) { echo json_encode(['ok'=>false,'error'=>'Session déjà ouverte']); exit; }

  $now = (new DateTime('now'))->format('Y-m-d H:i:s');
  $pdo->prepare('INSERT INTO time_entries (user_id, started_at) VALUES (?, ?)')->execute([$userId, $now]);
  echo json_encode(['ok'=>true]); exit;
}

// Départ
if ($action === 'depart') {
  $stmt = $pdo->prepare('SELECT id FROM time_entries WHERE user_id=? AND ended_at IS NULL ORDER BY started_at DESC LIMIT 1');
  $stmt->execute([$userId]);
  $openId = $stmt->fetchColumn();
  if (!$openId) { echo json_encode(['ok'=>false,'error'=>'Aucune session ouverte']); exit; }

  $now = (new DateTime('now'))->format('Y-m-d H:i:s');
  $pdo->prepare('UPDATE time_entries SET ended_at = ? WHERE id=?')->execute([$now, $openId]);
  echo json_encode(['ok'=>true]); exit;
}
if ($action === 'update') {
  $entryId   = (int)($input['entry_id'] ?? 0);
  $startStr  = trim((string)($input['started_at'] ?? ''));
  $endStr    = trim((string)($input['ended_at'] ?? ''));
  $reason    = trim((string)($input['reason'] ?? ''));

  if (!$entryId || !$startStr || !$endStr || !$reason) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Données incomplètes']); exit;
  }

  // Parser les dates "Y-m-d H:i"
  try {
    $start = new DateTime($startStr);
    $end   = new DateTime($endStr);
  } catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Format de date invalide']); exit;
  }

  if ($end <= $start) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'La fin doit être après le début']); exit;
  }

  // Charger l'entrée
  $stmt = $pdo->prepare('SELECT id, user_id, started_at, ended_at, created_at FROM time_entries WHERE id=? LIMIT 1');
  $stmt->execute([$entryId]);
  $entry = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$entry) {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'Entrée introuvable']); exit;
  }

  $role = $_SESSION['role'] ?? 'user';

  // Permissions : user seulement sur lui-même, admin sur tout
  if ($role !== 'admin' && (int)$entry['user_id'] !== $userId) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Non autorisé']); exit;
  }

  // Fenêtre 48h pour user (sur created_at)
  if ($role === 'user') {
    $age = time() - strtotime($entry['created_at']);
    if ($age > 48*3600) {
      http_response_code(403);
      echo json_encode(['ok'=>false,'error'=>'Délai d\'édition dépassé']); exit;
    }
  }

  // Vérifier les chevauchements avec d'autres sessions du même user
  $startDb = $start->format('Y-m-d H:i:s');
  $endDb   = $end->format('Y-m-d H:i:s');

  $sqlOverlap = "SELECT COUNT(*) FROM time_entries
                 WHERE user_id = ?
                   AND id <> ?
                   AND ended_at IS NOT NULL
                   AND started_at < ?
                   AND ended_at   > ?";
  $stmt = $pdo->prepare($sqlOverlap);
  $stmt->execute([(int)$entry['user_id'], (int)$entry['id'], $endDb, $startDb]);
  if ($stmt->fetchColumn() > 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Chevauchement avec une autre session']); exit;
  }

  // Journal d'audit
  $stmt = $pdo->prepare("INSERT INTO time_entry_audit
    (entry_id, action, old_started_at, old_ended_at, new_started_at, new_ended_at, reason, actor_user_id, actor_role)
    VALUES (?, 'update', ?, ?, ?, ?, ?, ?, ?)");
  $stmt->execute([
    (int)$entry['id'],
    $entry['started_at'],
    $entry['ended_at'],
    $startDb,
    $endDb,
    $reason,
    $userId,
    $role,
  ]);

  // Mise à jour de l'entrée
  $stmt = $pdo->prepare('UPDATE time_entries SET started_at = ?, ended_at = ? WHERE id = ?');
  $stmt->execute([$startDb, $endDb, (int)$entry['id']]);

  echo json_encode(['ok'=>true]);
  exit;
}



http_response_code(400);
echo json_encode(['ok'=>false,'error'=>'Action invalide']);