<?php
session_start();
header('Content-Type: application/json');

require_once 'admin_db_config.php';
require_once '../middleware/AdminAuth.php';
require_once '../utils/mailer.php';

try {
    AdminAuth::requireAdmin();
    $db = AdminDatabase::getInstance();
    $conn = $db->getConnection();

    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'send':
            sendNotification($conn);
            break;
        case 'schedule':
            scheduleNotification($conn);
            break;
        case 'cancel':
            cancelNotification($conn);
            break;
        case 'list':
            listNotifications($conn);
            break;
        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    error_log("Error in manage_notifications: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function sendNotification($conn) {
    $type = filter_var($_POST['type'], FILTER_SANITIZE_STRING);
    $title = filter_var($_POST['title'], FILTER_SANITIZE_STRING);
    $message = filter_var($_POST['message'], FILTER_SANITIZE_STRING);
    $recipients = $_POST['recipients'] ?? 'all'; // 'all', 'active', 'inactive', or array of user_ids
    $channel = $_POST['channel'] ?? 'email'; // 'email', 'push', 'in-app'

    // Validate inputs
    if (empty($title) || empty($message)) {
        throw new Exception('Title and message are required');
    }

    // Get recipients
    $users = getRecipients($conn, $recipients);

    // Send notifications based on channel
    foreach ($users as $user) {
        switch ($channel) {
            case 'email':
                sendEmailNotification($user, $title, $message);
                break;
            case 'push':
                sendPushNotification($user, $title, $message);
                break;
            case 'in-app':
                saveInAppNotification($conn, $user['id'], $title, $message);
                break;
        }
    }

    // Log notification
    logNotification($conn, $type, $title, count($users), $channel);

    echo json_encode([
        'status' => 'success',
        'message' => 'Notification sent successfully',
        'recipients_count' => count($users)
    ]);
}

function scheduleNotification($conn) {
    $schedule = [
        'type' => filter_var($_POST['type'], FILTER_SANITIZE_STRING),
        'title' => filter_var($_POST['title'], FILTER_SANITIZE_STRING),
        'message' => filter_var($_POST['message'], FILTER_SANITIZE_STRING),
        'scheduled_time' => $_POST['scheduled_time'],
        'recipients' => $_POST['recipients'] ?? 'all',
        'channel' => $_POST['channel'] ?? 'email'
    ];

    $stmt = $conn->prepare("
        INSERT INTO scheduled_notifications 
        (type, title, message, scheduled_time, recipients, channel, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $schedule['type'],
        $schedule['title'],
        $schedule['message'],
        $schedule['scheduled_time'],
        json_encode($schedule['recipients']),
        $schedule['channel'],
        $_SESSION['admin_id']
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Notification scheduled successfully'
    ]);
}

function cancelNotification($conn) {
    $notificationId = filter_var($_POST['notification_id'], FILTER_VALIDATE_INT);

    if (!$notificationId) {
        throw new Exception('Invalid notification ID');
    }

    $stmt = $conn->prepare("
        UPDATE scheduled_notifications 
        SET status = 'cancelled', 
            cancelled_at = CURRENT_TIMESTAMP,
            cancelled_by = ?
        WHERE id = ? AND status = 'pending'
    ");

    $stmt->execute([$_SESSION['admin_id'], $notificationId]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Notification not found or already sent/cancelled');
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Notification cancelled successfully'
    ]);
}

function listNotifications($conn) {
    $status = $_GET['status'] ?? 'all';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = 20;
    $offset = ($page - 1) * $limit;

    $whereClause = $status !== 'all' ? "WHERE status = ?" : "";
    $params = $status !== 'all' ? [$status] : [];

    $stmt = $conn->prepare("
        SELECT *
        FROM scheduled_notifications
        $whereClause
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");

    array_push($params, $limit, $offset);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'data' => $notifications
    ]);
}

function getRecipients($conn, $recipients) {
    if (is_array($recipients)) {
        $placeholders = str_repeat('?,', count($recipients) - 1) . '?';
        $stmt = $conn->prepare("SELECT id, email, username FROM users WHERE id IN ($placeholders)");
        $stmt->execute($recipients);
    } else {
        switch ($recipients) {
            case 'active':
                $stmt = $conn->prepare("
                    SELECT DISTINCT u.id, u.email, u.username
                    FROM users u
                    JOIN workout_schedules ws ON u.id = ws.user_id
                    WHERE ws.created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                ");
                break;
            case 'inactive':
                $stmt = $conn->prepare("
                    SELECT id, email, username
                    FROM users u
                    WHERE NOT EXISTS (
                        SELECT 1 FROM workout_schedules ws
                        WHERE ws.user_id = u.id
                        AND ws.created_at >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                    )
                ");
                break;
            default: // 'all'
                $stmt = $conn->query("SELECT id, email, username FROM users");
        }
        $stmt->execute();
    }
    return $stmt->fetchAll();
}

function logNotification($conn, $type, $title, $recipientCount, $channel) {
    $stmt = $conn->prepare("
        INSERT INTO admin_activity_log (admin_id, action, details, ip_address)
        VALUES (:admin_id, 'send_notification', :details, :ip_address)
    ");

    $stmt->execute([
        ':admin_id' => $_SESSION['admin_id'],
        ':details' => json_encode([
            'type' => $type,
            'title' => $title,
            'recipient_count' => $recipientCount,
            'channel' => $channel
        ]),
        ':ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
}

function saveInAppNotification($conn, $userId, $title, $message) {
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, message)
        VALUES (?, 'admin', ?, ?)
    ");
    $stmt->execute([$userId, $title, $message]);
}
