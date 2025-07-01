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

// Include the navbar
include "navbar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - Patient Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --forest-green: #358927;
            --wattle-green: #D7DE50;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --dark-text: #2c3e50;
            --shadow: rgba(53, 137, 39, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--white);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }

        /* Override Bootstrap navbar colors */
        .navbar {
            background: linear-gradient(135deg, var(--forest-green) 0%, #2d7a23 100%) !important;
            box-shadow: 0 2px 10px var(--shadow);
        }

        .navbar-brand, .navbar-nav .nav-link {
            color: var(--white) !important;
            font-weight: 500;
        }

        .navbar-brand:hover, .navbar-nav .nav-link:hover,
        .navbar-brand:focus, .navbar-nav .nav-link:focus,
        .navbar-brand:active, .navbar-nav .nav-link:active {
            color: var(--white) !important;
            background-color: transparent !important;
            text-decoration: none !important;
        }

        .navbar-toggler {
            border-color: var(--white) !important;
        }

        .navbar-toggler:hover, .navbar-toggler:focus {
            border-color: var(--white) !important;
            box-shadow: none !important;
        }

        /* Chat container */
        .chat-container {
            background: var(--white);
            border-radius: 20px;
            margin: 30px auto;
            padding: 0;
            box-shadow: 0 10px 30px var(--shadow);
            border: 1px solid rgba(53, 137, 39, 0.1);
            height: calc(100vh - 160px);
            overflow: hidden;
        }

        .chat-header {
            background: linear-gradient(135deg, var(--forest-green), #2d7a23);
            color: var(--white);
            padding: 20px;
            text-align: center;
            border-radius: 20px 20px 0 0;
        }

        .chat-header h2 {
            color: var(--white);
            font-weight: 700;
            margin: 0;
            font-size: 1.8rem;
        }

        .participants-list {
            height: calc(100vh - 260px);
            overflow-y: auto;
            border-right: 2px solid rgba(53, 137, 39, 0.1);
            background: linear-gradient(135deg, var(--white), var(--light-gray));
        }

        .chat-messages {
            height: calc(100vh - 260px);
            display: flex;
            flex-direction: column;
            background: var(--white);
        }

        .messages-container {
            flex-grow: 1;
            overflow-y: auto;
            padding: 20px;
            background: linear-gradient(135deg, rgba(53, 137, 39, 0.02), rgba(215, 222, 80, 0.02));
        }

        .message {
            margin-bottom: 15px;
            max-width: 80%;
            display: flex;
            flex-direction: column;
        }

        .message.sent {
            align-self: flex-end;
            align-items: flex-end;
        }

        .message.received {
            align-self: flex-start;
            align-items: flex-start;
        }

        .message-content {
            padding: 12px 18px;
            border-radius: 18px;
            display: inline-block;
            word-wrap: break-word;
            max-width: 100%;
            font-size: 14px;
            line-height: 1.4;
        }

        .message.sent .message-content {
            background: linear-gradient(135deg, var(--forest-green), #2d7a23);
            color: var(--white);
            border-bottom-right-radius: 6px;
        }

        .message.received .message-content {
            background: linear-gradient(135deg, var(--light-gray), #e9ecef);
            color: var(--dark-text);
            border-bottom-left-radius: 6px;
            border: 1px solid rgba(53, 137, 39, 0.1);
        }

        .message-time {
            font-size: 0.7rem;
            color: #6c757d;
            margin-top: 5px;
            font-weight: 500;
        }

        .chat-input {
            padding: 20px;
            background: linear-gradient(135deg, var(--light-gray), var(--white));
            border-top: 2px solid rgba(53, 137, 39, 0.1);
            border-radius: 0 0 20px 20px;
        }

        .chat-input .form-control {
            border: 2px solid rgba(53, 137, 39, 0.1);
            border-radius: 25px;
            padding: 12px 20px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .chat-input .form-control:focus {
            border-color: var(--forest-green);
            box-shadow: 0 0 0 0.2rem rgba(53, 137, 39, 0.25);
        }

        .chat-input .btn {
            background: linear-gradient(135deg, var(--forest-green), #2d7a23);
            border: none;
            border-radius: 25px;
            padding: 12px 25px;
            font-weight: 600;
            color: var(--white);
            transition: all 0.3s ease;
        }

        .chat-input .btn:hover {
            background: linear-gradient(135deg, #2d7a23, var(--forest-green));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(53, 137, 39, 0.3);
        }

        .participant-item {
            padding: 15px 20px;
            border-bottom: 1px solid rgba(53, 137, 39, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
            background: transparent;
        }

        .participant-item:hover {
            background: rgba(53, 137, 39, 0.05);
            transform: translateX(5px);
        }

        .participant-item.active {
            background: linear-gradient(135deg, var(--forest-green), #2d7a23);
            color: var(--white);
            border-left: 4px solid var(--wattle-green);
        }

        .participant-item.active .text-muted {
            color: rgba(255, 255, 255, 0.8) !important;
        }

        .participant-name {
            font-weight: 600;
            color: var(--dark-text);
            font-size: 0.95rem;
        }

        .participant-item.active .participant-name {
            color: var(--white);
        }

        .participant-username {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 2px;
        }

        .unread-badge {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: var(--white);
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(220, 53, 69, 0.3);
        }

        .welcome-message {
            text-align: center;
            color: var(--forest-green);
            margin-top: 80px;
            padding: 40px;
        }

        .welcome-message i {
            font-size: 64px;
            color: var(--forest-green);
            margin-bottom: 20px;
            opacity: 0.7;
        }

        .welcome-message h4 {
            color: var(--forest-green);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .welcome-message p {
            color: #6c757d;
            font-size: 0.9rem;
        }

        /* Scrollbar styling */
        .participants-list::-webkit-scrollbar,
        .messages-container::-webkit-scrollbar {
            width: 6px;
        }

        .participants-list::-webkit-scrollbar-track,
        .messages-container::-webkit-scrollbar-track {
            background: var(--light-gray);
        }

        .participants-list::-webkit-scrollbar-thumb,
        .messages-container::-webkit-scrollbar-thumb {
            background: var(--forest-green);
            border-radius: 3px;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .chat-container {
                margin: 20px 10px;
                height: calc(100vh - 140px);
            }
            
            .participants-list {
                height: calc(100vh - 240px);
            }
            
            .chat-messages {
                height: calc(100vh - 240px);
            }
            
            .message {
                max-width: 95%;
            }
            
            .participant-item {
                padding: 12px 15px;
            }
        }

        /* Animation for new messages */
        @keyframes messageSlide {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message {
            animation: messageSlide 0.3s ease-out;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="chat-container">
            <div class="chat-header">
                <h2><i class="fas fa-comments me-3"></i>Patient Communication</h2>
            </div>
            
            <div class="row g-0 h-100">
                <!-- Participants List -->
                <div class="col-md-4 participants-list">
                    <?php if(mysqli_num_rows($participants) == 0): ?>
                        <div class="text-center p-4">
                            <i class="fas fa-users" style="font-size: 48px; color: var(--forest-green); opacity: 0.5;"></i>
                            <p class="text-muted mt-3">No conversations available</p>
                        </div>
                    <?php else: ?>
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
                                    <div class="flex-grow-1">
                                        <div class="participant-name"><?php echo htmlspecialchars($display_name); ?></div>
                                        <div class="participant-username text-muted"><?php echo htmlspecialchars($username); ?></div>
                                    </div>
                                    <?php if($row['unread_count'] > 0): ?>
                                        <span class="unread-badge"><?php echo $row['unread_count']; ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>

                <!-- Chat Messages -->
                <div class="col-md-8 chat-messages">
                    <div class="welcome-message" id="welcomeMessage">
                        <i class="fas fa-comment-dots"></i>
                        <h4>Welcome to Chat</h4>
                        <p>Select a conversation to start messaging</p>
                    </div>
                    
                    <div id="chatArea" style="display: none;">
                        <div class="messages-container" id="messagesContainer"></div>
                        <div class="chat-input">
                            <form id="messageForm" class="d-flex gap-3">
                                <input type="text" class="form-control" id="messageInput" placeholder="Type your message...">
                                <button type="submit" class="btn">
                                    <i class="fas fa-paper-plane me-2"></i>Send
                                </button>
                            </form>
                        </div>
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
                document.getElementById('chatArea').style.display = 'flex';
                document.getElementById('chatArea').style.flexDirection = 'column';
                document.getElementById('chatArea').style.height = '100%';
                
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