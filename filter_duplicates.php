<?php
// Database configuration
$db_host = 'localhost';
$db_name = 'hackno';
$db_user = 'root';
$db_pass = '';

// Connect to the database
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query to find consecutive duplicate messages from the same user
$query = "
    SELECT t1.chat_id
    FROM chats t1
    JOIN chats t2
    ON t1.id = t2.id
    AND t1.user_name = t2.user_name
    AND t1.message = t2.message
    AND t1.chat_id > t2.chat_id
    WHERE t1.created_at > t2.created_at
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
            echo "Duplicate messages deleted successfully.";
        } else {
            echo "Error deleting duplicates: " . $conn->error;
        }
    } else {
        echo "No duplicates found.";
    }
} else {
    echo "No duplicates found.";
}

$conn->close();
?>
