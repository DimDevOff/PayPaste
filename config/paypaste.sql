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
    credits INT DEFAULT 100,
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
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
    service ENUM('donatello', 'tg_stars') NOT NULL, -- Через який сервіс
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
    type ENUM('topup', 'purchase', 'sale', 'creation_fee') NOT NULL,
      -- topup: поповнення балансу
      -- purchase: купівля платної пасти
      -- sale: продаж своєї платної пасти (хтось купив)
      -- creation_fee: зняття комісії за створення платної пасти
    service VARCHAR(50) NULL, -- Деталі джерела (наприклад, donatello, tg_stars)
    related_paste_id VARCHAR(50) NULL, -- Якщо транзакція пов'язана з пастою
    related_order_id VARCHAR(50) NULL, -- Якщо транзакція пов'язана з замовленням (для topup)
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_paste_id) REFERENCES pastes(id) ON DELETE SET NULL,
    FOREIGN KEY (related_order_id) REFERENCES orders(id) ON DELETE SET NULL
);

-- 4. Passkeys (WebAuthn/FIDO2 credentials)
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

-- 5. Теги паст (для швидкого пошуку та фільтрації)
CREATE TABLE IF NOT EXISTS paste_tags (
    paste_id VARCHAR(50) NOT NULL,
    tag VARCHAR(50) NOT NULL,
    PRIMARY KEY (paste_id, tag),
    FOREIGN KEY (paste_id) REFERENCES pastes(id) ON DELETE CASCADE,
    INDEX idx_tag (tag)
);

-- 6. Історія лімітів запитів (Rate Limiting)
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action_key VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action_key (action_key),
    INDEX idx_created_at (created_at)
);
