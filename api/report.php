<?php
// api/report.php (admin)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../app/session.php';
require_once __DIR__ . '/../app/db.php';

if (($_SESSION['role'] ?? 'user') !== 'admin') { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Interdit']); exit; }

$ym = $_GET['month'] ?? date('Y-m');
[$y,$m] = array_map('intval', explode('-', $ym));
$start = (new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00',$y,$m)));
$end = $start->modify('first day of next month');

$sql = "SELECT u.id as user_id, u.full_name, u.email, t.started_at, t.ended_at
        FROM users u
        LEFT JOIN time_entries t ON t.user_id=u.id AND t.started_at >= ? AND t.started_at < ?
        ORDER BY u.full_name ASC, t.started_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);

$rows = $stmt->fetchAll();
$report = []; // user_id => {name,email,total,entries[]}

foreach ($rows as $r) {
  $uid = (int)$r['user_id'];
  if (!isset($report[$uid])) {
    $report[$uid] = ['user_id'=>$uid, 'name'=>$r['full_name'], 'email'=>$r['email'], 'total_seconds'=>0, 'entries'=>[]];
  }
  if (!empty($r['started_at'])) {
    $dur = 0;
    if (!empty($r['ended_at'])) {
      $dur = max(0, strtotime($r['ended_at']) - strtotime($r['started_at']));
      $report[$uid]['total_seconds'] += $dur;
    }
    $report[$uid]['entries'][] = ['started_at'=>$r['started_at'], 'ended_at'=>$r['ended_at'], 'seconds'=>$dur];
  }
}
echo json_encode(['ok'=>true,'month'=>$ym,'data'=>array_values($report)]);