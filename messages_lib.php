<?php
// Messaging helpers. All access checks go through conversation_for().

function msg_unread_total(mysqli $conn, string $role, int $uid): int {
    $col = $role === 'student' ? 'c.student_id' : 'c.landlord_id';
    $st = $conn->prepare("SELECT COUNT(*) FROM messages m JOIN conversations c ON c.id = m.conversation_id
                          WHERE $col = ? AND m.sender_role <> ? AND m.is_read = 0");
    $st->bind_param('is', $uid, $role); $st->execute();
    $n = (int)$st->get_result()->fetch_row()[0]; $st->close();
    return $n;
}

// Returns the conversation ONLY if it belongs to this user, otherwise null.
function conversation_for(mysqli $conn, string $role, int $uid, int $cid): ?array {
    if ($role === 'student') {
        $sql = "SELECT c.*, p.name AS pname, o.fullname AS other_name FROM conversations c
                JOIN properties p ON p.id = c.property_id JOIN landlords o ON o.id = c.landlord_id
                WHERE c.id = ? AND c.student_id = ?";
    } else {
        $sql = "SELECT c.*, p.name AS pname, o.fullname AS other_name FROM conversations c
                JOIN properties p ON p.id = c.property_id JOIN students o ON o.id = c.student_id
                WHERE c.id = ? AND c.landlord_id = ?";
    }
    $st = $conn->prepare($sql); $st->bind_param('ii', $cid, $uid); $st->execute();
    $row = $st->get_result()->fetch_assoc(); $st->close();
    return $row ?: null;
}

function get_or_create_conversation(mysqli $conn, int $studentId, int $landlordId, int $propertyId): int {
    $i = $conn->prepare("INSERT IGNORE INTO conversations (student_id, landlord_id, property_id) VALUES (?, ?, ?)");
    $i->bind_param('iii', $studentId, $landlordId, $propertyId); $i->execute(); $i->close();
    $s = $conn->prepare("SELECT id FROM conversations WHERE student_id = ? AND landlord_id = ? AND property_id = ?");
    $s->bind_param('iii', $studentId, $landlordId, $propertyId); $s->execute();
    $id = (int)$s->get_result()->fetch_row()[0]; $s->close();
    return $id;
}

function add_message(mysqli $conn, int $cid, string $role, int $uid, string $body): int {
    $i = $conn->prepare("INSERT INTO messages (conversation_id, sender_role, sender_id, body) VALUES (?, ?, ?, ?)");
    $i->bind_param('isis', $cid, $role, $uid, $body); $i->execute();
    $id = $conn->insert_id; $i->close();
    $u = $conn->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?");
    $u->bind_param('i', $cid); $u->execute(); $u->close();
    return (int)$id;
}
