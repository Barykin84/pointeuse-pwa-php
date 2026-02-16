<?php
// /api/export_csv.php
require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../app/db.php';

if (($_SESSION['role'] ?? 'user') !== 'admin') {
  http_response_code(403); exit('Interdit');
}

$ym = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) { $ym = date('Y-m'); }

[$y,$m] = array_map('intval', explode('-', $ym));
$start = (new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00',$y,$m)));
$end   = $start->modify('first day of next month');

$sql = "SELECT u.full_name, u.email, t.started_at, t.ended_at, t.note
        FROM users u
        LEFT JOIN time_entries t ON t.user_id=u.id
          AND t.started_at >= ? AND t.started_at < ?
        ORDER BY u.full_name ASC, t.started_at ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);

// Prépare le CSV
$filename = 'rapport-'.$ym.'.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
echo "\xEF\xBB\xBF"; // BOM UTF-8 (Excel friendly)

$out = fopen('php://output', 'w');

// Petite “mise en forme” en tête : titre + ligne vide
fputcsv($out, ["Rapport d'heures – Mois $ym"]);
fputcsv($out, []);

// En-têtes
fputcsv($out, ['Employé', 'Email', 'Début', 'Fin', 'Durée (hh:mm)', 'Secondes', 'Note']);

function fmtH($seconds){
  $h = floor($seconds/3600);
  $m = floor(($seconds%3600)/60);
  return sprintf('%02d:%02d', $h, $m);
}

$totalSeconds = 0;

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  if (empty($row['started_at'])) {
    // utilisateur sans sessions dans le mois : on le saute
    continue;
  }
  $secs = 0;
  if (!empty($row['ended_at'])) {
    $secs = max(0, strtotime($row['ended_at']) - strtotime($row['started_at']));
    $totalSeconds += $secs; // cumule uniquement les sessions fermées
  }

  fputcsv($out, [
    $row['full_name'],
    $row['email'],
    (new DateTime($row['started_at']))->format('Y-m-d H:i'),
    $row['ended_at'] ? (new DateTime($row['ended_at']))->format('Y-m-d H:i') : 'Ouverte',
    $row['ended_at'] ? fmtH($secs) : '',
    $secs ?: '',
    $row['note'] ?? '',
  ]);
}

// Ligne vide + ligne TOTAL en pied
fputcsv($out, []);
fputcsv($out, [
  '', '', '', 'TOTAL',
  fmtH($totalSeconds),
  $totalSeconds,
  ''
]);

fclose($out);

