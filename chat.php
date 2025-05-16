<?php
// DB config
include 'database.php'; // includes your DB config variables

// Connect to DB
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// If it's an AJAX call, return new chat messages as JSON
if (isset($_GET['fetch']) && $_GET['fetch'] === '1') {
    $lastTimestamp = isset($_GET['since']) ? $_GET['since'] : '1970-01-01 00:00:00';

    $stmt = $conn->prepare("SELECT user_name, message, created_at FROM chats WHERE created_at > ? ORDER BY created_at ASC");
    $stmt->bind_param("s", $lastTimestamp);
    $stmt->execute();
    $result = $stmt->get_result();

    $chats = [];
    while ($row = $result->fetch_assoc()) {
        $chats[] = $row;
    }

    header('Content-Type: application/json');
    echo json_encode($chats);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Chat Messages</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="stylesheet" href="chat.css">
    </head>
    <body>
        <div class="chat-container" id="chatContainer">
            <!-- Chat messages will load here -->
        </div>
        <div class="footer" id="buttom_part">૮ ˶ᵔ ᵕ ᵔ˶ ა [Latest Chat] ૮ ˶ᵔ ᵕ ᵔ˶ ა</div>
        <script>
            let lastTimestamp = '1970-01-01 00:00:00';

            async function fetchChats() {
                const response = await fetch('?fetch=1&since=' + encodeURIComponent(lastTimestamp));
                const data = await response.json();

                if (data.length > 0) {
                    const container = document.getElementById('chatContainer');

                    data.forEach(chat => {
                        const div = document.createElement('div');
                        div.className = 'chat-box';
                        div.innerHTML = `
                            <div class="dot"></div>
                            <div class="s">${chat.user_name}</div>
                            <span>${chat.message}</span>
                        `;
                        container.appendChild(div);
                        lastTimestamp = chat.created_at;
                    });

                    scrollToBottom();
                }
            }

            // Function to scroll to the end page div only if there are more than 4 chats
            function scrollToBottom() {
                const container = document.getElementById('chatContainer');
                const endPage = document.getElementById('buttom_part');
                const chatBoxes = container.getElementsByClassName('chat-box');

                if (chatBoxes.length > 6 || chatContainer.scrollHeight > container.clientHeight) {
                    endPage.style.display = 'block'; // Show the footer when there are more than 4 chats
                    endPage.scrollIntoView({ behavior: 'smooth' });
                } else {
                    endPage.style.display = 'none'; // Hide the footer when there are 4 or fewer chats
                }
            }

            // Scroll to the end page div on page load if there are more than 4 chats
            window.onload = scrollToBottom;

            // Poll for new messages every 3 seconds
            setInterval(fetchChats, 3000);
            fetchChats(); // initial load
        </script>
    </body>
</html>
