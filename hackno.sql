CREATE DATABASE hackno;
USE hackno;

CREATE TABLE ip_logins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE usernames (
    user_name VARCHAR(50) PRIMARY KEY,
    id INT NOT NULL,
    FOREIGN KEY (id) REFERENCES ip_logins(id)
);

CREATE TABLE chats (
    chat_id INT AUTO_INCREMENT PRIMARY KEY,
    id INT NOT NULL,
    user_name VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_name) REFERENCES usernames(user_name),
    FOREIGN KEY (id) REFERENCES ip_logins(id)
);
