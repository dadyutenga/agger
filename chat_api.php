<?php
session_start();
require_once "config.php";

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// Get the action from the request
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Handle different actions
switch ($action) {
    case 'get_messages':
        // Get messages between users
        $receiver_type = $_GET['receiver_type'];
        $receiver_id = $_GET['receiver_id'];
        
        $sql = "SELECT m.*, 
                CASE 
                    WHEN m.sender_type = ? AND m.sender_id = ? THEN 1
                    ELSE 0
                END as is_sender
                FROM messages m
                WHERE ((sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?)
                OR (sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?))
                ORDER BY created_at ASC";
                
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssssssss", 
            $_SESSION["user_type"], $_SESSION["user_type"] === "doctor" ? $_SESSION["id"] : $_SESSION["username"],
            $_SESSION["user_type"], $_SESSION["user_type"] === "doctor" ? $_SESSION["id"] : $_SESSION["username"],
            $receiver_type, $receiver_id,
            $receiver_type, $receiver_id,
            $_SESSION["user_type"], $_SESSION["user_type"] === "doctor" ? $_SESSION["id"] : $_SESSION["username"]
        );
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $messages = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $messages[] = $row;
        }
        
        // Mark messages as read
        if (!empty($messages)) {
            $sql = "UPDATE messages SET is_read = 1 
                    WHERE receiver_type = ? AND receiver_id = ? 
                    AND sender_type = ? AND sender_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssss", 
                $_SESSION["user_type"], 
                $_SESSION["user_type"] === "doctor" ? $_SESSION["id"] : $_SESSION["username"],
                $receiver_type, $receiver_id
            );
            mysqli_stmt_execute($stmt);
        }
        
        echo json_encode($messages);
        break;
        
    case 'get_new_messages':
        // Get new messages since last message ID
        $receiver_type = $_GET['receiver_type'];
        $receiver_id = $_GET['receiver_id'];
        $last_id = $_GET['last_id'];
        
        $sql = "SELECT m.*, 
                CASE 
                    WHEN m.sender_type = ? AND m.sender_id = ? THEN 1
                    ELSE 0
                END as is_sender
                FROM messages m
                WHERE ((sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?)
                OR (sender_type = ? AND sender_id = ? AND receiver_type = ? AND receiver_id = ?))
                AND m.id > ?
                ORDER BY created_at ASC";
                
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssssssssssi", 
            $_SESSION["user_type"], $_SESSION["user_type"] === "doctor" ? $_SESSION["id"] : $_SESSION["username"],
            $_SESSION["user_type"], $_SESSION["user_type"] === "doctor" ? $_SESSION["id"] : $_SESSION["username"],
            $receiver_type, $receiver_id,
            $receiver_type, $receiver_id,
            $_SESSION["user_type"], $_SESSION["user_type"] === "doctor" ? $_SESSION["id"] : $_SESSION["username"],
            $last_id
        );
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $messages = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $messages[] = $row;
        }
        
        echo json_encode($messages);
        break;
        
    case 'typing':
        // Handle typing status
        $receiver_type = $_GET['receiver_type'];
        $receiver_id = $_GET['receiver_id'];
        $is_typing = isset($_GET['typing']) ? $_GET['typing'] === 'true' : true;
        
        // Store typing status in session
        $_SESSION['typing_status'][$receiver_type . '_' . $receiver_id] = [
            'is_typing' => $is_typing,
            'timestamp' => time()
        ];
        
        echo json_encode(['success' => true]);
        break;
        
    case 'check_typing':
        // Check if other user is typing
        $receiver_type = $_GET['receiver_type'];
        $receiver_id = $_GET['receiver_id'];
        
        $is_typing = false;
        if (isset($_SESSION['typing_status'][$receiver_type . '_' . $receiver_id])) {
            $typing_status = $_SESSION['typing_status'][$receiver_type . '_' . $receiver_id];
            // Clear typing status after 3 seconds
            if (time() - $typing_status['timestamp'] < 3) {
                $is_typing = $typing_status['is_typing'];
            } else {
                unset($_SESSION['typing_status'][$receiver_type . '_' . $receiver_id]);
            }
        }
        
        echo json_encode(['is_typing' => $is_typing]);
        break;
        
    case 'send_message':
        // Send a new message
        $receiver_type = $_GET['receiver_type'];
        $receiver_id = $_GET['receiver_id'];
        $message = $_POST['message'];
        
        if (empty($message)) {
            http_response_code(400);
            echo json_encode(["error" => "Message cannot be empty"]);
            exit;
        }
        
        $sql = "INSERT INTO messages (sender_type, sender_id, receiver_type, receiver_id, message) 
                VALUES (?, ?, ?, ?, ?)";
                
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssss", 
            $_SESSION["user_type"],
            $_SESSION["user_type"] === "doctor" ? $_SESSION["id"] : $_SESSION["username"],
            $receiver_type,
            $receiver_id,
            $message
        );
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(["success" => true, "message_id" => mysqli_insert_id($conn)]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Failed to send message"]);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(["error" => "Invalid action"]);
        break;
}
?> 
 
// Handle GET requests (fetching messages)
if($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $chat_id = $_GET['chat_id'] ?? 0;
    
    if($action === 'get_messages') {
        // Get all messages between the users
        $sql = "SELECT 
                m.*,
                CASE 
                    WHEN m.sender_type = ? AND m.sender_id = ? THEN 1 
                    ELSE 0 
                END as is_sent
                FROM messages m
                WHERE (
                    (m.sender_type = ? AND m.sender_id = ? AND m.receiver_id = ?)
                    OR 
                    (m.receiver_type = ? AND m.receiver_id = ? AND m.sender_id = ?)
                )
                ORDER BY m.created_at ASC";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sisiiiii", 
            $_SESSION['user_type'], $_SESSION['id'],
            $_SESSION['user_type'], $_SESSION['id'], $chat_id,
            $_SESSION['user_type'], $_SESSION['id'], $chat_id
        );
        
        // Mark messages as read
        $update_sql = "UPDATE messages 
                      SET is_read = 1 
                      WHERE receiver_type = ? 
                      AND receiver_id = ? 
                      AND sender_id = ? 
                      AND is_read = 0";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "sii", 
            $_SESSION['user_type'], $_SESSION['id'], $chat_id
        );
        mysqli_stmt_execute($update_stmt);
        
    } else if($action === 'get_new_messages') {
        $last_id = $_GET['last_id'] ?? 0;
        
        // Get only new messages
        $sql = "SELECT 
                m.*,
                CASE 
                    WHEN m.sender_type = ? AND m.sender_id = ? THEN 1 
                    ELSE 0 
                END as is_sent
                FROM messages m
                WHERE (
                    (m.sender_type = ? AND m.sender_id = ? AND m.receiver_id = ?)
                    OR 
                    (m.receiver_type = ? AND m.receiver_id = ? AND m.sender_id = ?)
                )
                AND m.id > ?
                ORDER BY m.created_at ASC";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sisiiiiii", 
            $_SESSION['user_type'], $_SESSION['id'],
            $_SESSION['user_type'], $_SESSION['id'], $chat_id,
            $_SESSION['user_type'], $_SESSION['id'], $chat_id,
            $last_id
        );
        
        // Mark new messages as read
        $update_sql = "UPDATE messages 
                      SET is_read = 1 
                      WHERE receiver_type = ? 
                      AND receiver_id = ? 
                      AND sender_id = ? 
                      AND is_read = 0";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param($update_stmt, "sii", 
            $_SESSION['user_type'], $_SESSION['id'], $chat_id
        );
        mysqli_stmt_execute($update_stmt);
    }
    
    if(isset($stmt) && mysqli_stmt_execute($stmt)) {
        $result = mysqli_stmt_get_result($stmt);
        $messages = [];
        while($row = mysqli_fetch_assoc($result)) {
            $messages[] = $row;
        }
        echo json_encode($messages);
    } else {
        echo json_encode(['error' => 'Failed to fetch messages']);
    }
}

// Handle POST requests (sending messages)
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if($action === 'send_message') {
        $receiver_id = $_POST['receiver_id'] ?? 0;
        $message = trim($_POST['message'] ?? '');
        
        if(empty($message)) {
            echo json_encode(['error' => 'Message cannot be empty']);
            exit;
        }
        
        // Determine receiver type based on current user
        $receiver_type = $_SESSION['user_type'] === 'doctor' ? 'caretaker' : 'doctor';
        
        $sql = "INSERT INTO messages (
                    sender_type, sender_id, 
                    receiver_type, receiver_id, 
                    message
                ) VALUES (?, ?, ?, ?, ?)";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "siiss", 
            $_SESSION['user_type'], $_SESSION['id'],
            $receiver_type, $receiver_id,
            $message
        );
        
        if(mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message_id' => mysqli_insert_id($conn)]);
        } else {
            echo json_encode(['error' => 'Failed to send message']);
        }
    }
}
?> 
 