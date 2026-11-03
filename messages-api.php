<?php
// JSON API for the Messages pages (student + landlord). Ownership is enforced on every call.
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/messages_lib.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$role = $_SESSION['role'] ?? '';
if (empty($_SESSION['user_id']) || !in_array($role, ['student', 'landlord'], true)) { http_response_code(401); echo '{"error":"login"}'; exit; }
require_role($role);                      // also re-checks the account is still active
$uid = (int)$_SESSION['user_id'];
function out($d, int $code = 200) { http_response_code($code); echo json_encode($d); exit; }
function iso(string $ts): string { return date('c', strtotime($ts)); }

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'list') {
    $q = trim($_GET['q'] ?? '');
    $otherTable = $role === 'student' ? 'landlords' : 'students';
    $meCol = $role === 'student' ? 'c.student_id' : 'c.landlord_id';
    $otherCol = $role === 'student' ? 'c.landlord_id' : 'c.student_id';
    $sql = "SELECT c.id, c.property_id, c.last_message_at, p.name AS pname, o.fullname AS other_name,
              (SELECT m.body FROM messages m WHERE m.conversation_id = c.id ORDER BY m.id DESC LIMIT 1) AS last_body,
              (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_role <> ? AND m.is_read = 0) AS unread
            FROM conversations c JOIN properties p ON p.id = c.property_id JOIN $otherTable o ON o.id = $otherCol
            WHERE $meCol = ?";
    $params = [$role, $uid]; $bind = 'si';
    if ($role === 'landlord') $sql .= " AND EXISTS (SELECT 1 FROM messages m3 WHERE m3.conversation_id = c.id)";
    if ($q !== '') {
        $like = '%' . addcslashes(mb_substr($q, 0, 100), '%_\\') . '%';
        $sql .= " AND (o.fullname LIKE ? OR p.name LIKE ? OR EXISTS (SELECT 1 FROM messages m2 WHERE m2.conversation_id = c.id AND m2.body LIKE ?))";
        array_push($params, $like, $like, $like); $bind .= 'sss';
    }
    $sql .= " ORDER BY c.last_message_at DESC, c.id DESC LIMIT 100";
    $st = $conn->prepare($sql); $st->bind_param($bind, ...$params); $st->execute();
    $items = [];
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
        $items[] = ['id' => (int)$r['id'], 'property_id' => (int)$r['property_id'], 'name' => $r['other_name'], 'property' => $r['pname'],
                    'preview' => mb_strimwidth((string)$r['last_body'], 0, 80, '...'), 'at' => iso($r['last_message_at']), 'unread' => (int)$r['unread']];
    }
    out(['conversations' => $items, 'unread_total' => msg_unread_total($conn, $role, $uid)]);
}

if ($action === 'thread') {
    $cid = (int)($_GET['id'] ?? 0); $after = (int)($_GET['after'] ?? 0);
    $c = conversation_for($conn, $role, $uid, $cid);
    if (!$c) out(['error' => 'not_found'], 404);
    $st = $conn->prepare("SELECT id, sender_role, body, created_at FROM messages WHERE conversation_id = ? AND id > ? ORDER BY created_at, id LIMIT 500");
    $st->bind_param('ii', $cid, $after); $st->execute();
    $msgs = [];
    foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $m) {
        $msgs[] = ['id' => (int)$m['id'], 'mine' => $m['sender_role'] === $role, 'body' => $m['body'], 'at' => iso($m['created_at'])];
    }
    // Opening the thread marks the other person's messages as read
    $u = $conn->prepare("UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_role <> ? AND is_read = 0");
    $u->bind_param('is', $cid, $role); $u->execute();
    out(['meta' => ['name' => $c['other_name'], 'property' => $c['pname'], 'property_id' => (int)$c['property_id']],
         'messages' => $msgs, 'unread_total' => msg_unread_total($conn, $role, $uid)]);
}

if ($action === 'send' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf'], $_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $_POST['csrf'])) out(['error' => 'csrf'], 403);
    $cid = (int)($_POST['id'] ?? 0);
    $body = trim($_POST['body'] ?? '');
    if (!conversation_for($conn, $role, $uid, $cid)) out(['error' => 'not_found'], 404);
    if ($body === '' || mb_strlen($body) > 1000) out(['error' => 'Write a message of 1 to 1000 characters.'], 422);
    $id = add_message($conn, $cid, $role, $uid, $body);
    out(['ok' => true, 'id' => $id]);
}

out(['error' => 'bad_request'], 400);
