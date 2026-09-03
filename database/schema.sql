CREATE TABLE IF NOT EXISTS connections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    host VARCHAR(255) NOT NULL,
    port INT UNSIGNED NOT NULL DEFAULT 3306,
    username VARCHAR(255) NOT NULL,
    password TEXT NULL,
    database_name VARCHAR(255) NULL,
    owner_username VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_connections_owner_username (owner_username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS query_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    connection_id INT UNSIGNED NULL,
    connection_name VARCHAR(100) NULL,
    db_username VARCHAR(255) NULL,
    db_host VARCHAR(255) NULL,
    client_ip VARCHAR(45) NULL,
    target_database VARCHAR(255) NULL,
    sql_text MEDIUMTEXT NOT NULL,
    status ENUM('success', 'error') NOT NULL,
    result_type VARCHAR(10) NULL,
    row_count INT NULL,
    affected_rows INT NULL,
    error_message TEXT NULL,
    execution_time_ms DECIMAL(10, 2) NULL,
    executed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_query_log_connection FOREIGN KEY (connection_id) REFERENCES connections(id) ON DELETE SET NULL,
    INDEX idx_query_log_db_username (db_username),
    INDEX idx_query_log_client_ip (client_ip),
    INDEX idx_query_log_executed_at (executed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lab_exam_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    db_username VARCHAR(255) NULL,
    client_ip VARCHAR(45) NULL,
    event_type VARCHAR(30) NOT NULL,
    detail VARCHAR(255) NULL,
    user_agent VARCHAR(255) NULL,
    occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_lab_exam_events_db_username (db_username),
    INDEX idx_lab_exam_events_occurred_at (occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
