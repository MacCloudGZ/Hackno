<?php
// Enable error reporting for debugging (commented out for production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'database.php'; // Include your database configuration
$response_message = '';

header('Content-Type: text/html'); // Ensure plain text response

// Function to filter and delete duplicate messages within 2 minutes
function filterDuplicateMessages($conn) {
    // Query to find consecutive duplicate messages from the same user within 2 minutes
    $query = "
        SELECT t1.chat_id
        FROM chats t1
        JOIN chats t2
        ON t1.id = t2.id
        AND t1.user_name = t2.user_name
        AND t1.message = t2.message
        AND t1.chat_id > t2.chat_id
        WHERE TIMESTAMPDIFF(MINUTE, t2.created_at, t1.created_at) <= 2
        ORDER BY t1.chat_id ASC
    ";

    $result = $conn->query($query);

    if ($result && $result->num_rows > 0) {
        $idsToDelete = [];
        while ($row = $result->fetch_assoc()) {
            $idsToDelete[] = $row['chat_id'];
        }

        // Delete the duplicate messages
        if (!empty($idsToDelete)) {
            $idsToDeleteString = implode(',', $idsToDelete);
            $deleteQuery = "DELETE FROM chats WHERE chat_id IN ($idsToDeleteString)";
            if ($conn->query($deleteQuery)) {
                // Optional: Log or output success message for debugging
                // echo "Duplicate messages deleted successfully.";
            } else {
                // Optional: Log or output error message for debugging
                // echo "Error deleting duplicates: " . $conn->error;
            }
        }
    }
}

// Connect to the database
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Run the duplicate filtering function
filterDuplicateMessages($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($username) || empty($message)) {
        echo 'All fields are required.';
        exit;
    }

    $user_ip = $_SERVER['REMOTE_ADDR'];
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($conn->connect_error) {
        echo 'Connection failed: ' . $conn->connect_error;
        exit;
    }

    // Get or insert IP
    $stmt_ip = $conn->prepare("SELECT id FROM ip_logins WHERE ip_address = ?");
    $stmt_ip->bind_param("s", $user_ip);
    $stmt_ip->execute();
    $stmt_ip->store_result();

    if ($stmt_ip->num_rows > 0) {
        $stmt_ip->bind_result($ip_id);
        $stmt_ip->fetch();
        $stmt_ip->close();
    } else {
        $stmt_ip->close();
        $stmt_insert_ip = $conn->prepare("INSERT INTO ip_logins (ip_address) VALUES (?)");
        $stmt_insert_ip->bind_param("s", $user_ip);
        $stmt_insert_ip->execute();
        $ip_id = $stmt_insert_ip->insert_id;
        $stmt_insert_ip->close();
    }

    // Check if username exists and if it matches the same ip_id
    $stmt_check_user = $conn->prepare("SELECT id FROM usernames WHERE user_name = ?");
    $stmt_check_user->bind_param("s", $username);
    $stmt_check_user->execute();
    $stmt_check_user->store_result();

    if ($stmt_check_user->num_rows === 0) {
        $stmt_check_user->close();
        $stmt_insert_user = $conn->prepare("INSERT INTO usernames (user_name, id) VALUES (?, ?)");
        $stmt_insert_user->bind_param("si", $username, $ip_id);
        $stmt_insert_user->execute();
        $stmt_insert_user->close();
    } else {
        $stmt_check_user->bind_result($existing_id);
        $stmt_check_user->fetch();
        if ($existing_id != $ip_id) {
            echo "⚠️ This username is already used with a different IP.";
            $stmt_check_user->close();
            $conn->close();
            exit;
        }
        $stmt_check_user->close();
    }

    // Check for duplicate messages
    $stmt_check_message = $conn->prepare("SELECT chat_id FROM chats WHERE id = ? AND user_name = ? AND message = ?");
    $stmt_check_message->bind_param("iss", $ip_id, $username, $message);
    $stmt_check_message->execute();
    $stmt_check_message->store_result();

    if ($stmt_check_message->num_rows > 0) {
        echo "⚠️ Duplicate message detected. Message not sent.";
        $stmt_check_message->close();
        $conn->close();
        exit;
    }
    $stmt_check_message->close();

    // Insert message
    $stmt = $conn->prepare("INSERT INTO chats (id, user_name, message, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iss", $ip_id, $username, $message);

    if ($stmt->execute()) {
        echo "✅ Message sent!";
    } else {
        echo "❌ Error: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
}

$user_ip = $_SERVER['REMOTE_ADDR'];
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

    if ($conn->connect_error) {
        die('Connection failed: ' . $conn->connect_error);
    }

    if ($stmt = $conn->prepare("SELECT u.user_name FROM ip_logins i JOIN usernames u ON i.id = u.id WHERE i.ip_address = ?")) {
        $stmt->bind_param("s", $user_ip);
        $stmt->execute();
        $stmt->bind_result($existing_username);

        if ($stmt->fetch()) {
            $username_placeholder = '<input type="text" name="username_display" id="usernameInput" placeholder="' . htmlspecialchars($existing_username) . '" disabled>';
            $username_placeholder .= '<input type="hidden" name="username" value="' . htmlspecialchars($existing_username) . '">';
        } else {
            $username_placeholder = '<input type="text" name="username" id="usernameInput" placeholder="Your Username" required>';
        }

        $stmt->close();
    }
        $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HackNo Internet Tips</title>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/svgs/solid/shield-halved.svg" type="image/svg+xml">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free/css/all.min.css">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <script src="index.js" defer></script>
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            // Disable alert messages in the form submission logic
            const form = document.getElementById("chatForm");
            form.addEventListener("submit", async (e) => {
                e.preventDefault();

                const formData = new FormData(form);
                const recaptchaResponse = grecaptcha.getResponse();

                if (!recaptchaResponse) {
                    // alert("Please complete the reCAPTCHA."); // Disabled alert
                    return;
                }

                formData.append("g-recaptcha-response", recaptchaResponse);

                try {
                    const response = await fetch("", {
                        method: "POST",
                        body: formData
                    });

                    const text = await response.text();

                    if (text.includes("✅ Message sent!")) {
                        // alert("✅ Message sent!"); // Disabled alert
                        form.reset();
                        grecaptcha.reset();
                        document.getElementById("chatIframe").contentWindow.location.reload();
                    } else if (text.includes("⚠️") || text.includes("❌")) {
                        // alert(text); // Disabled alert
                    } else {
                        // alert("⚠️ Unexpected response."); // Disabled alert
                        console.log("Server response:", text);
                    }
                } catch (err) {
                    console.error("Submission error:", err);
                    // alert("❌ There was an error sending your message."); // Disabled alert
                }
            });
        });
    </script>
</head>
<body>
    <div class="main-container">
        <div class="left-container">
            <div class="head-container">
                <!-- Font Awesome icon used correctly -->
                <i class="fa-solid fa-shield-halved"></i>
                <div class="logo">
                    <div class="logo-title">
                        HackNo
                    </div>
                    <div class="logo-subtitle">
                        "Not Today, Hackers!"
                    </div>
                </div>
            </div>
            <div class="tab-container">
                <div class="tabs">
                    <!-- Initial 'active' class here will be overridden by JS on load -->
                    <div class="tab active" id="tab-home">
                        <i class="fa-solid fa-house"></i>
                        <div class="tabs-labels">Home</div>
                    </div>
                    <div class="tab" id="tab-passwords">
                        <i class="fa-solid fa-key"></i>
                        <div class="tabs-labels">Password Security</div>
                    </div>
                    <div class="tab" id="tab-connectivity">
                        <i class="fa-solid fa-users"></i>
                        <div class="tabs-labels">Internet Interaction</div>
                    </div>
                    <div class="tab" id="tab-cloud">
                        <i class="fa-solid fa-cloud"></i>
                        <div class="tabs-labels">Cloud Security</div>
                    </div>
                    <div class="tab" id="tab-contact">
                        <i class="fa-solid fa-comment"></i>
                        <div class="tabs-labels">Chat</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="right-container">
            <div class="box-container">
                <!-- Initial 'active' class here will be overridden by JS on load -->
                <div class="box active" id="box-home">
                    <div class="content-container">
                        <!-- Hero Section -->
                        <div class="content-box">
                            <div class="content-title">
                                Stay Safe Online: Simple Cybersecurity Tips for Everyday Use
                            </div>
                            <div class="content-subtitle">
                                Protect your data, privacy, and digital life with these easy-to-follow guidelines.
                            </div>
                        </div>
                        <!-- Password Security -->
                        <div class="content-box target" onclick="gotoTab('box-passwords');">
                            <div class="content-title">
                                🔐 Password Security
                            </div>
                            <div class="content-subtitle">
                                Use strong, unique passwords and enable multi-factor authentication to keep your accounts secure.
                            </div>
                        </div>
                        <!-- Internet Interaction -->
                        <div class="content-box target" onclick="gotoTab('box-connectivity');">
                            <div class="content-title">
                                🌐 Internet Interaction
                            </div>
                            <div class="content-subtitle">
                                Be cautious of suspicious links, avoid public Wi-Fi for sensitive tasks, and verify URLs before clicking.
                            </div>
                        </div>
                        <!-- Cloud Security -->
                        <div class="content-box target" onclick="gotoTab('box-cloud');">
                            <div class="content-title">
                                ☁️ Cloud Security
                            </div>
                            <div class="content-subtitle">
                                Choose trusted cloud services, enable encryption, and manage file access permissions carefully.
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="content-footer">
                            © 2023 HackNo. All rights reserved.
                        </div>
                    </div>
                    <div class="background-mascot">
                        <img src="image/image3.png" alt="IMAGE">
                    </div>
                </div>
                <div class="box" id="box-passwords">
                        <div class="content-container">
                            <!-- Introduction -->
                            <div class="content-box">
                                <div class="content-title">
                                    Stay Safe Online: Password Security Matters
                                </div>
                                <div class="content-subtitle">
                                    Passwords are the first line of defense for your digital life. Learn how to create, manage, and protect your passwords effectively.
                                </div>
                            </div>

                            <!-- Password Creation Tips -->
                            <div class="content-box">
                                <div class="content-title">
                                    🔑 Create Strong Passwords
                                </div>
                                <div class="content-subtitle">
                                    Use at least 12 characters with a mix of uppercase, lowercase, numbers, and symbols. Avoid using names, birthdays, or common words.
                                </div>
                            </div>

                            <!-- Password Management -->
                            <div class="content-box">
                                <div class="content-title">
                                    🧠 Manage Passwords Wisely
                                </div>
                                <div class="content-subtitle">
                                    Never reuse passwords across sites. Use a trusted password manager to generate and store your passwords securely.
                                </div>
                            </div>

                            <!-- Extra Protection Tips -->
                            <div class="content-box">
                                <div class="content-title">
                                    🛡️ Enhance Password Security
                                </div>
                                <div class="content-subtitle">
                                    Enable multi-factor authentication (MFA), change compromised passwords immediately, and avoid saving passwords in browsers.
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="content-footer">
                                © 2023 HackNo. All rights reserved.
                            </div>
                        </div>

                    <div class="background-mascot">
                        <img src="image/image2.png" alt="IMAGE">
                    </div>
                </div>
                <div class="box" id="box-connectivity">
                    <div class="content-container">
                        <!-- Introduction -->
                        <div class="content-box">
                            <div class="content-title">
                                Stay Safe Online: Smart Internet Interaction Tips
                            </div>
                            <div class="content-subtitle">
                                The internet is full of opportunities—and risks. Learn how to browse, communicate, and interact safely in the digital world.
                            </div>
                        </div>

                        <!-- Think Before You Click -->
                        <div class="content-box">
                            <div class="content-title">
                                ⚠️ Think Before You Click
                            </div>
                            <div class="content-subtitle">
                                Avoid clicking suspicious links, pop-ups, or email attachments from unknown sources. Always verify before you interact.
                            </div>
                        </div>

                        <!-- Safe Browsing Practices -->
                        <div class="content-box">
                            <div class="content-title">
                                🌐 Practice Safe Browsing
                            </div>
                            <div class="content-subtitle">
                                Use secure websites (look for "https://"), avoid illegal downloads, and steer clear of shady websites to protect your device and data.
                            </div>
                        </div>

                        <!-- Public Wi-Fi Awareness -->
                        <div class="content-box">
                            <div class="content-title">
                                📶 Be Cautious with Public Wi-Fi
                            </div>
                            <div class="content-subtitle">
                                Avoid accessing sensitive accounts on public Wi-Wi. If needed, use a VPN to encrypt your connection and keep your data private.
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="content-footer">
                            © 2023 HackNo. All rights reserved.
                        </div>
                    </div>
                    <div class="background-mascot">
                        <img src="image/image1.png" alt="IMAGE">
                    </div>
                </div>
                <div class="box" id="box-cloud">
                    <div class="content-container">
                        <!-- Introduction -->
                        <div class="content-box">
                            <div class="content-title">
                                Stay Safe Online: Cloud Security Essentials
                            </div>
                            <div class="content-subtitle">
                                Cloud storage makes life easier—but it also requires smart security practices. Learn how to protect your data in the cloud.
                            </div>
                        </div>

                        <!-- Choose Trusted Providers -->
                        <div class="content-box">
                            <div class="content-title">
                                ☁️ Use Trusted Cloud Services
                            </div>
                            <div class="content-subtitle">
                                Stick to reputable providers like Google Drive, OneDrive, or Dropbox. Check their privacy policies and security features before uploading files.
                            </div>
                        </div>
                        <!-- Enable Encryption -->
                        <div class="content-box">
                            <div class="content-title">
                                🔒 Enable Encryption
                            </div>
                            <div class="content-subtitle">
                                Use services that offer encryption for your data both in transit and at rest. For extra protection, encrypt files before uploading.
                            </div>
                        </div>

                        <!-- Manage Access Carefully -->
                        <div class="content-box">
                            <div class="content-title">
                                👥 Control Who Has Access
                            </div>
                            <div class="content-subtitle">
                                Regularly review shared links and permissions. Remove access when no longer needed to prevent unauthorized file viewing or editing.
                            </div>
                        </div>
                        <!-- Footer -->
                        <div class="content-footer">
                            © 2023 HackNo. All rights reserved.
                        </div>
                    </div>
                    <div class="background-mascot">
                        <img src="image/image4.png" alt="IMAGE">
                    </div>
                </div>
                <div class="box" id="box-contact">
                    <div class="feedback-container">
                        <div class="live-feedback">
                            <!-- Added ID to the iframe -->
                            <iframe src="chat.php" frameborder="0" width="100%" height="100%" id="chatIframe"></iframe>
                        </div>
                        <div class="c">
                            <div class="c-box">
                                <div class="c-title">
                                    Share your thoughts and interact with fellow visitors using the Global chat
                                </div>
                            </div>
                        </div>
                        <div class="a-f-container">
                            <form id="chatForm" method="POST">
                                <div class="chat-input-container">
                                    <div class="chat-box">
                                        <?php echo $username_placeholder; ?>
                                        <label for="messageInput" class="visually-hidden">Message:</label>
                                        <textarea name="message" id="messageInput" placeholder="Enter your chat here" rows="3" required></textarea>
                                        <div id="recaptchaPlaceholder" class="g-recaptcha" data-sitekey="6LdcSzMrAAAAACQkHLEM745outL0-5cPo8CiGubW">
                                        </div>
                                        <button type="submit">Send Message</button>
                                        <label>you can press the mascot to hide him (only in chat)</label>
                                    </div>
                                </div>
                            </form> <!-- End of form -->
                        </div>
                    </div>
                    <!-- <div class="background-mascot" onclick="imageToggle()" >
                        <img src="image/image5.png" alt="IMAGE" id="mascotImage">
                    </div> -->
                </div>
            </div>
        </div>
    </div>

</body>
</html>
