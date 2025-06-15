<?php
session_start();
require_once "config.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// Get the list of chat participants
if($_SESSION["user_type"] === "doctor") {
    // Get all caretakers for this doctor's patients
    $sql = "SELECT DISTINCT 
            c.id as caretaker_id,
            c.username as caretaker_username,
            p.full_name as patient_name,
            (SELECT COUNT(*) FROM messages 
             WHERE receiver_type = 'doctor' 
             AND receiver_id = ? 
             AND sender_id = c.id 
             AND is_read = 0) as unread_count
            FROM caretaker_credentials c
            JOIN patients p ON c.patient_id = p.id
            WHERE p.doctor_id = ?
            ORDER BY p.full_name";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $_SESSION["id"], $_SESSION["id"]);
} else {
    // Get the doctor for this caretaker's patient
    $sql = "SELECT 
            d.id as doctor_id,
            d.username as doctor_username,
            d.full_name as doctor_name,
            (SELECT COUNT(*) FROM messages 
             WHERE receiver_type = 'caretaker' 
             AND receiver_id = ? 
             AND sender_id = d.id 
             AND is_read = 0) as unread_count
            FROM doctors d
            JOIN patients p ON d.id = p.doctor_id
            JOIN caretaker_credentials c ON p.id = c.patient_id
            WHERE c.id = ?";
    
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $_SESSION["id"], $_SESSION["id"]);
}

mysqli_stmt_execute($stmt);
$participants = mysqli_stmt_get_result($stmt);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Patient Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <style>
        .chat-container {
            height: calc(100vh - 100px);
            margin-top: 20px;
        }
        .participants-list {
            height: 100%;
            overflow-y: auto;
            border-right: 1px solid #dee2e6;
        }
        .chat-messages {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .messages-container {
            flex-grow: 1;
            overflow-y: auto;
            padding: 20px;
        }
        .message {
            margin-bottom: 15px;
            max-width: 80%;
        }
        .message.sent {
            margin-left: auto;
        }
        .message.received {
            margin-right: auto;
        }
        .message-content {
            padding: 10px 15px;
            border-radius: 15px;
            display: inline-block;
        }
        .message.sent .message-content {
            background: #007bff;
            color: white;
        }
        .message.received .message-content {
            background: #e9ecef;
        }
        .message-time {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 5px;
        }
        .chat-input {
            padding: 20px;
            background: white;
            border-top: 1px solid #dee2e6;
        }
        .participant-item {
            padding: 10px 15px;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
        }
        .participant-item:hover {
            background: #f8f9fa;
        }
        .participant-item.active {
            background: #e9ecef;
        }
        .unread-badge {
            background: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.75rem;
        }
        .welcome-message {
            text-align: center;
            color: #6c757d;
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container chat-container">
        <div class="row h-100">
            <!-- Participants List -->
            <div class="col-md-4 participants-list">
                <div class="list-group">
                    <?php while($row = mysqli_fetch_assoc($participants)): ?>
                        <?php
                        if($_SESSION["user_type"] === "doctor") {
                            $chat_id = $row['caretaker_id'];
                            $display_name = $row['patient_name'] . "'s Caretaker";
                            $username = $row['caretaker_username'];
                        } else {
                            $chat_id = $row['doctor_id'];
                            $display_name = "Dr. " . $row['doctor_name'];
                            $username = $row['doctor_username'];
                        }
                        ?>
                        <div class="participant-item" data-chat-id="<?php echo $chat_id; ?>" 
                             data-name="<?php echo htmlspecialchars($display_name); ?>">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo htmlspecialchars($display_name); ?></strong>
                                    <div class="text-muted small"><?php echo htmlspecialchars($username); ?></div>
                                </div>
                                <?php if($row['unread_count'] > 0): ?>
                                    <span class="unread-badge"><?php echo $row['unread_count']; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>

            <!-- Chat Messages -->
            <div class="col-md-8 chat-messages">
                <div class="welcome-message" id="welcomeMessage">
                    <i class='bx bx-message-square-dots' style='font-size: 48px;'></i>
                    <h4 class="mt-3">Welcome to Chat</h4>
                    <p>Select a conversation to start messaging</p>
                </div>
                
                <div id="chatArea" style="display: none;">
                    <div class="messages-container" id="messagesContainer"></div>
                    <div class="chat-input">
                        <form id="messageForm" class="d-flex gap-2">
                            <input type="text" class="form-control" id="messageInput" placeholder="Type your message...">
                            <button type="submit" class="btn btn-primary">Send</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentChatId = null;
        let currentChatName = null;
        let lastMessageId = 0;

        // Handle participant selection
        document.querySelectorAll('.participant-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.participant-item').forEach(p => p.classList.remove('active'));
                this.classList.add('active');
                
                currentChatId = this.dataset.chatId;
                currentChatName = this.dataset.name;
                
                document.getElementById('welcomeMessage').style.display = 'none';
                document.getElementById('chatArea').style.display = 'block';
                
                // Load messages
                loadMessages();
            });
        });

        // Handle message submission
        document.getElementById('messageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            if (!currentChatId) return;

            const input = document.getElementById('messageInput');
            const message = input.value.trim();
            if (!message) return;

            sendMessage(message);
            input.value = '';
        });

        // Load messages for the selected chat
        function loadMessages() {
            fetch(`chat_api.php?action=get_messages&chat_id=${currentChatId}`)
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('messagesContainer');
                    container.innerHTML = '';
                    
                    data.forEach(message => {
                        const messageElement = createMessageElement(message);
                        container.appendChild(messageElement);
                        lastMessageId = Math.max(lastMessageId, message.id);
                    });
                    
                    container.scrollTop = container.scrollHeight;
                });
        }

        // Send a new message
        function sendMessage(message) {
            const formData = new FormData();
            formData.append('action', 'send_message');
            formData.append('receiver_id', currentChatId);
            formData.append('message', message);

            fetch('chat_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadMessages();
                }
            });
        }

        // Create message element
        function createMessageElement(message) {
            const div = document.createElement('div');
            div.className = `message ${message.is_sent ? 'sent' : 'received'}`;
            
            const content = document.createElement('div');
            content.className = 'message-content';
            content.textContent = message.message;
            
            const time = document.createElement('div');
            time.className = 'message-time';
            time.textContent = new Date(message.created_at).toLocaleString();
            
            div.appendChild(content);
            div.appendChild(time);
            
            return div;
        }

        // Check for new messages periodically
        setInterval(() => {
            if (currentChatId) {
                fetch(`chat_api.php?action=get_new_messages&chat_id=${currentChatId}&last_id=${lastMessageId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            const container = document.getElementById('messagesContainer');
                            data.forEach(message => {
                                const messageElement = createMessageElement(message);
                                container.appendChild(messageElement);
                                lastMessageId = Math.max(lastMessageId, message.id);
                            });
                            container.scrollTop = container.scrollHeight;
                        }
                    });
            }
        }, 5000);
    </script>
</body>
</html> 