<?php
/**
 * ajax/admin/rooms.php
 * Single AJAX file for ALL room operations.
 *
 * Dispatch via `action`:
 *   getRooms      — list all rooms with occupancy info, optional search/filter
 *   getRoomById   — fetch single room for edit modal
 *   createRoom    — insert new room
 *   updateRoom    — update existing room
 *   deleteRoom    — hard delete (blocked if room has any admission records)
 *   toggleRoom    — flip is_active (maintenance mode)
 *
 * Auth: Admin only.
 */

require_once __DIR__ . '/../../config.php';
require_once BASE_PATH . '/includes/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth_check.php';

bootApp();
requireAdmin();

$action = trim($_POST['action'] ?? $_GET['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'getRooms':    getRooms();    break;
        case 'getRoomById': getRoomById(); break;
        default: jsonResponse(false, 'Unknown GET action.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCsrf(true);
    switch ($action) {
        case 'createRoom': createRoom(); break;
        case 'updateRoom': updateRoom(); break;
        case 'deleteRoom': deleteRoom(); break;
        case 'toggleRoom': toggleRoom(); break;
        default: jsonResponse(false, 'Unknown POST action.');
    }
}

jsonResponse(false, 'Method not allowed.');

// ════════════════════════════════════════════════════════════
// GET HANDLERS
// ════════════════════════════════════════════════════════════

/**
 * getRooms
 * GET params:
 *   search     — searches room_number
 *   type       — 'general' | 'private' | 'icu' | ''
 *   status     — 'active' | 'inactive' | 'occupied' | ''
 */
function getRooms(): void
{
    $search = trim($_GET['search'] ?? '');
    $type   = trim($_GET['type']   ?? '');
    $status = trim($_GET['status'] ?? '');

    $where  = [];
    $params = [];

    if ($search !== '') {
        $where[]  = "r.room_number LIKE ?";
        $params[] = "%{$search}%";
    }

    if (in_array($type, ['general', 'private', 'icu'], true)) {
        $where[]  = "r.room_type = ?";
        $params[] = $type;
    }

    // 'occupied' is a derived state — handled in PHP after fetch
    if ($status === 'active') {
        $where[] = "r.is_active = 1";
    } elseif ($status === 'inactive') {
        $where[] = "r.is_active = 0";
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $rows = Database::fetchAll(
        "SELECT
             r.id, r.room_number, r.room_type, r.daily_fee, r.is_active, r.created_at,
             (SELECT COUNT(*)
              FROM   admissions a
              WHERE  a.room_id = r.id
              AND    a.status  = 'admitted') AS is_occupied,
             (SELECT a.patient_name
              FROM   admissions a
              WHERE  a.room_id = r.id
              AND    a.status  = 'admitted'
              LIMIT  1)                      AS current_patient,
             (SELECT COUNT(*)
              FROM   admissions a
              WHERE  a.room_id = r.id)       AS total_admissions
         FROM rooms r
         {$whereSql}
         ORDER BY r.room_type ASC, r.room_number ASC",
        $params
    );

    // Apply occupied filter post-query
    if ($status === 'occupied') {
        $rows = array_values(array_filter($rows, fn($r) => (int) $r['is_occupied'] > 0));
    } elseif ($status === 'available') {
        $rows = array_values(array_filter($rows, fn($r) => (int) $r['is_occupied'] === 0 && (int) $r['is_active'] === 1));
    }

    foreach ($rows as &$r) {
        $r['daily_fee_fmt']  = formatCurrency((float) $r['daily_fee']);
        $r['created_fmt']    = formatDate($r['created_at']);
        $r['is_occupied']    = (int) $r['is_occupied'];
        $r['total_admissions'] = (int) $r['total_admissions'];
    }
    unset($r);

    // Summary counts for the stat strip
    $summary = [
        'total'    => count($rows),
        'occupied' => count(array_filter($rows, fn($r) => $r['is_occupied'] > 0)),
        'available'=> count(array_filter($rows, fn($r) => $r['is_occupied'] === 0 && $r['is_active'])),
        'inactive' => count(array_filter($rows, fn($r) => !$r['is_active'])),
    ];

    jsonResponse(true, count($rows) . ' room(s) found.', [
        'rooms'   => $rows,
        'summary' => $summary,
    ]);
}

/**
 * getRoomById
 * GET params: id (int)
 */
function getRoomById(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid room ID.');

    $room = Database::fetchOne(
        "SELECT id, room_number, room_type, daily_fee, is_active
         FROM   rooms WHERE id = ?",
        [$id]
    );

    if (!$room) jsonResponse(false, 'Room not found.');

    jsonResponse(true, 'Room loaded.', ['room' => $room]);
}

// ════════════════════════════════════════════════════════════
// POST HANDLERS
// ════════════════════════════════════════════════════════════

/**
 * createRoom
 * POST fields: room_number*, room_type*, daily_fee*, is_active
 */
function createRoom(): void
{
    $data = collectAndValidateRoomInput();

    // Duplicate room number
    $exists = Database::fetchOne(
        "SELECT id FROM rooms WHERE room_number = ?",
        [$data['room_number']]
    );
    if ($exists) jsonResponse(false, "Room number \"{$data['room_number']}\" already exists.");

    $id = Database::insert(
        "INSERT INTO rooms (room_number, room_type, daily_fee, is_active)
         VALUES (?, ?, ?, ?)",
        [$data['room_number'], $data['room_type'], $data['daily_fee'], $data['is_active']]
    );

    logActivity(getCurrentUserId(), 'room_created', [
        'id'     => $id,
        'number' => $data['room_number'],
        'type'   => $data['room_type'],
    ]);

    jsonResponse(true, "Room {$data['room_number']} has been added successfully.", ['id' => $id]);
}

/**
 * updateRoom
 * POST fields: id*, room_number*, room_type*, daily_fee*, is_active
 */
function updateRoom(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid room ID.');

    $existing = Database::fetchOne("SELECT id, room_number FROM rooms WHERE id = ?", [$id]);
    if (!$existing) jsonResponse(false, 'Room not found.');

    $data = collectAndValidateRoomInput();

    // Duplicate number — allow own
    $dup = Database::fetchOne(
        "SELECT id FROM rooms WHERE room_number = ? AND id != ?",
        [$data['room_number'], $id]
    );
    if ($dup) jsonResponse(false, "Room number \"{$data['room_number']}\" is already taken.");

    Database::execute(
        "UPDATE rooms SET room_number=?, room_type=?, daily_fee=?, is_active=? WHERE id=?",
        [$data['room_number'], $data['room_type'], $data['daily_fee'], $data['is_active'], $id]
    );

    logActivity(getCurrentUserId(), 'room_updated', [
        'id'     => $id,
        'number' => $data['room_number'],
    ]);

    jsonResponse(true, "Room {$data['room_number']} has been updated successfully.");
}

/**
 * deleteRoom
 * Blocked if any admission records reference this room.
 * POST fields: id*
 */
function deleteRoom(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid room ID.');

    $room = Database::fetchOne("SELECT id, room_number FROM rooms WHERE id = ?", [$id]);
    if (!$room) jsonResponse(false, 'Room not found.');

    // Block if occupied right now
    $occupied = (int) Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM admissions WHERE room_id = ? AND status = 'admitted'",
        [$id]
    )['cnt'];

    if ($occupied > 0) {
        jsonResponse(false, "Cannot delete Room {$room['room_number']} — it is currently occupied. Discharge the patient first.");
    }

    // Block if historical records exist
    $admCount = (int) Database::fetchOne(
        "SELECT COUNT(*) AS cnt FROM admissions WHERE room_id = ?",
        [$id]
    )['cnt'];

    if ($admCount > 0) {
        jsonResponse(
            false,
            "Cannot delete Room {$room['room_number']} — it has {$admCount} historical admission record(s). " .
            "Mark the room as inactive instead."
        );
    }

    Database::execute("DELETE FROM rooms WHERE id = ?", [$id]);

    logActivity(getCurrentUserId(), 'room_deleted', [
        'id'     => $id,
        'number' => $room['room_number'],
    ]);

    jsonResponse(true, "Room {$room['room_number']} has been deleted.");
}

/**
 * toggleRoom
 * Marks a room as under maintenance (inactive) or active.
 * Blocked if the room is currently occupied.
 * POST fields: id*
 */
function toggleRoom(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid room ID.');

    $room = Database::fetchOne(
        "SELECT id, room_number, is_active FROM rooms WHERE id = ?",
        [$id]
    );
    if (!$room) jsonResponse(false, 'Room not found.');

    // Cannot deactivate an occupied room
    if ((int) $room['is_active'] === 1) {
        $occupied = (int) Database::fetchOne(
            "SELECT COUNT(*) AS cnt FROM admissions WHERE room_id = ? AND status = 'admitted'",
            [$id]
        )['cnt'];

        if ($occupied > 0) {
            jsonResponse(
                false,
                "Cannot mark Room {$room['room_number']} as inactive — it is currently occupied."
            );
        }
    }

    $newStatus = $room['is_active'] ? 0 : 1;
    Database::execute("UPDATE rooms SET is_active = ? WHERE id = ?", [$newStatus, $id]);

    $label = $newStatus ? 'activated' : 'marked as under maintenance';
    logActivity(getCurrentUserId(), 'room_toggled', [
        'id'     => $id,
        'number' => $room['room_number'],
        'status' => $newStatus,
    ]);

    jsonResponse(true, "Room {$room['room_number']} has been {$label}.", ['is_active' => $newStatus]);
}

// ════════════════════════════════════════════════════════════
// SHARED VALIDATION
// ════════════════════════════════════════════════════════════

function collectAndValidateRoomInput(): array
{
    $roomNumber = strtoupper(trim($_POST['room_number'] ?? ''));
    $roomType   = trim($_POST['room_type']   ?? '');
    $dailyFee   = trim($_POST['daily_fee']   ?? '0');
    $isActive   = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;

    if (empty($roomNumber)) jsonResponse(false, 'Room number is required.');
    if (strlen($roomNumber) > 20) jsonResponse(false, 'Room number must not exceed 20 characters.');

    if (!in_array($roomType, ['general', 'private', 'icu'], true)) {
        jsonResponse(false, 'Room type must be General, Private, or ICU.');
    }

    $dailyFee = (float) $dailyFee;
    if ($dailyFee < 0) jsonResponse(false, 'Daily fee cannot be negative.');

    return [
        'room_number' => $roomNumber,
        'room_type'   => $roomType,
        'daily_fee'   => $dailyFee,
        'is_active'   => $isActive,
    ];
}
