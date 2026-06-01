-- Створення бази даних
CREATE DATABASE IF NOT EXISTS paypaste DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE paypaste;

-- 1. Актуальні дані (Користувачі та їх баланс)
CREATE TABLE IF NOT EXISTS users (
    id VARCHAR(50) PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    telegram_id BIGINT NULL UNIQUE,
    github_id VARCHAR(255) NULL UNIQUE,
    nickname VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    theme VARCHAR(20) NOT NULL DEFAULT 'retro',
    api_key VARCHAR(64) UNIQUE NULL,
    credits INT DEFAULT 100,
    email_verified TINYINT NOT NULL DEFAULT 0,
    verification_code VARCHAR(6) DEFAULT NULL,
    verification_expires_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Таблиця паст (пов'язана з користувачами)
CREATE TABLE IF NOT EXISTS pastes (
    id VARCHAR(50) PRIMARY KEY,
    title VARCHAR(255) DEFAULT 'Без назви',
    content LONGTEXT NOT NULL,
    user_id VARCHAR(50) NULL,
    is_paid BOOLEAN DEFAULT FALSE,
    view_cost INT DEFAULT 0,
    is_private BOOLEAN DEFAULT FALSE,
    expires_at TIMESTAMP NULL,
    is_pending_rewrite BOOLEAN DEFAULT FALSE,
    moderation_status ENUM('pending', 'approved', 'rejected', 'moderation_failed') DEFAULT 'pending',
    moderation_result JSON NULL COMMENT 'JSON-масив категорій порушень при rejected',
    language VARCHAR(50) DEFAULT 'plaintext',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_moderation_status (moderation_status),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- Таблиця для куплених підписок/доступів до платних паст
CREATE TABLE IF NOT EXISTS unlocked_pastes (
    user_id VARCHAR(50),
    paste_id VARCHAR(50),
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, paste_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (paste_id) REFERENCES pastes(id) ON DELETE CASCADE
);

-- 2. Тимчасові дані (активні замовлення на поповнення балансу)
CREATE TABLE IF NOT EXISTS orders (
    id VARCHAR(50) PRIMARY KEY, -- ID замовлення (наприклад, order_12345)
    user_id VARCHAR(50) NOT NULL,
    service ENUM('donatello', 'tg_stars', 'unknown') NOT NULL DEFAULT 'unknown', -- Через який сервіс
    amount_credits INT NOT NULL, -- Скільки кредитів має бути зараховано
    status ENUM('pending', 'completed', 'canceled') DEFAULT 'pending',
    external_provider_id VARCHAR(255) NULL, -- ID операції на стороні провайдера
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 3. Історія транзакцій (Рух кредитів: поповнення та списання)
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    amount INT NOT NULL, -- Позитивне значення (поповнення) або негативне (списання)
    type ENUM('topup', 'purchase', 'sale', 'creation_fee', 'api_usage', 'ad_reward') NOT NULL,
      -- topup: поповнення балансу
      -- purchase: купівля платної пасти
      -- sale: продаж своїї платної пасти (хтось купив)
      -- creation_fee: зняття комісії за створення платної пасти
      -- ad_reward: нагорода за перегляд реклами (квест)
    service VARCHAR(50) NULL, -- Деталі джерела (наприклад, donatello, tg_stars)
    related_paste_id VARCHAR(50) NULL, -- Якщо транзакція пов'язана з пастою
    related_order_id VARCHAR(50) NULL, -- Якщо транзакція пов'язана з замовленням (для topup)
    description VARCHAR(255) NULL,
    idempotency_key VARCHAR(255) NULL UNIQUE COMMENT 'Ідемпотентний ключ: повторна операція з тим самим ключем ігнорується',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_paste_id) REFERENCES pastes(id) ON DELETE SET NULL,
    FOREIGN KEY (related_order_id) REFERENCES orders(id) ON DELETE SET NULL
);

-- 4. Серверні рекламні події (ad_events) — TASK-70
-- Замінює довіру до сесій при підрахунку прогресу рекламного квесту.
CREATE TABLE IF NOT EXISTS ad_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paste_id VARCHAR(50) NOT NULL,
    user_id VARCHAR(50) NULL COMMENT 'NULL для анонімів — ключем є user_session_hash',
    user_session_hash VARCHAR(64) NOT NULL COMMENT 'Хеш відбитка сесії + користувача',
    quest_id VARCHAR(32) NOT NULL COMMENT 'ID квесту',
    nonce VARCHAR(32) NOT NULL COMMENT 'Одноразовий ідентифікатор події з токена',
    step TINYINT NOT NULL COMMENT 'Номер зарахованої події (1-3)',
    accepted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ad_event_nonce (paste_id, user_session_hash, nonce),
    INDEX idx_ad_events_paste_user (paste_id, user_session_hash),
    INDEX idx_ad_events_quest (quest_id),
    FOREIGN KEY (paste_id) REFERENCES pastes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Passkeys (WebAuthn/FIDO2 credentials)
CREATE TABLE IF NOT EXISTS passkeys (
    id VARCHAR(50) PRIMARY KEY,
    user_id VARCHAR(50) NOT NULL,
    credential_id VARCHAR(255) NOT NULL UNIQUE,
    public_key_pem TEXT NOT NULL,
    counter INT DEFAULT 0,
    aaguid VARCHAR(255) NULL,
    transports VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 7. Теги паст (для швидкого пошуку та фільтрації)
CREATE TABLE IF NOT EXISTS paste_tags (
    paste_id VARCHAR(50) NOT NULL,
    tag VARCHAR(50) NOT NULL,
    PRIMARY KEY (paste_id, tag),
    FOREIGN KEY (paste_id) REFERENCES pastes(id) ON DELETE CASCADE,
    INDEX idx_tag (tag)
);

-- 8. Історія лімітів запитів (Rate Limiting)
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action_key VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action_key (action_key),
    INDEX idx_created_at (created_at)
);

-- 9. Черга фонових задач (MySQL-backed queue для зовнішніх інтеграцій)
CREATE TABLE IF NOT EXISTS jobs (
    id VARCHAR(50) PRIMARY KEY,
    type ENUM('moderation_check', 'moderation_rewrite', 'email_verify', 'email_changed', 'email_custom') NOT NULL,
    status ENUM('queued', 'processing', 'completed', 'failed', 'dead') DEFAULT 'queued',
    payload JSON NOT NULL,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    idempotency_key VARCHAR(255) NULL UNIQUE COMMENT 'Захист від дублювання задач',
    scheduled_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Час планованого виконання (для backoff)',
    started_at DATETIME NULL COMMENT 'Час початку обробки',
    completed_at DATETIME NULL COMMENT 'Час завершення',
    last_error TEXT NULL COMMENT 'Остання помилка',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status_scheduled (status, scheduled_at),
    INDEX idx_type_status (type, status),
    INDEX idx_idempotency (idempotency_key)
);

-- 10. Журнал аудиту дій адміністратора (Audit logs) — TASK-145
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id VARCHAR(50) NULL,
    action_type VARCHAR(50) NOT NULL COMMENT 'Тип дії: delete_paste, delete_user, approve_moderation, reject_moderation, edit_settings',
    target_id VARCHAR(255) NULL COMMENT 'ID цілі дії',
    ip_address VARCHAR(45) NOT NULL COMMENT 'IP-адреса адміністратора',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_action_type (action_type),
    INDEX idx_admin_id (admin_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 11. Глобальні налаштування системи
CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (`key`, `value`) VALUES ('moderation_strict_mode', '1')
ON DUPLICATE KEY UPDATE `value` = `value`;
