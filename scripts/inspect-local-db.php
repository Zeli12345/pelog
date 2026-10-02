<?php

$path = $argv[1] ?? 'E:\pelog-build\received\local.db';

$db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

echo "== KV ==\n";
foreach ($db->query('SELECT key, substr(value, 1, 30) AS v FROM kv') as $row) {
    echo '  ' . $row['key'] . ' = ' . $row['v'] . "\n";
}

echo "== SESSIONS (>= 6 terakhir) ==\n";
$sessions = $db->query('SELECT session_uuid, user_type, substr(purpose,1,28) AS purpose, state, screenshot_state, last_heartbeat_at, ended_at_client, close_reason FROM sessions ORDER BY created_at DESC LIMIT 8');

foreach ($sessions as $row) {
    echo sprintf(
        "  %s | %s | %s | state=%s | shot=%s | hb=%s | end=%s | %s\n",
        substr($row['session_uuid'], 0, 8),
        $row['user_type'],
        $row['purpose'],
        $row['state'],
        $row['screenshot_state'],
        $row['last_heartbeat_at'] ?? '-',
        $row['ended_at_client'] ?? '-',
        $row['close_reason'],
    );
}

echo "== COUNTS ==\n";
foreach (['students', 'staff', 'subjects'] as $table) {
    $count = $db->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    echo "  {$table} = {$count}\n";
}
