-- Shard 2 初期化データ
-- 文字エンコーディング設定
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET character_set_client = utf8mb4;
SET character_set_connection = utf8mb4;
SET character_set_results = utf8mb4;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    shard_key VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_name VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- サンプルデータ
INSERT INTO users (name, email, shard_key) VALUES 
('鈴木三郎', 'suzuki@example.com', 'shard2'),
('高橋美和', 'takahashi@example.com', 'shard2'),
('伊藤健太', 'ito@example.com', 'shard2');

INSERT INTO orders (user_id, product_name, amount) VALUES 
(1, '商品D', 4500.00),
(2, '商品E', 5500.00),
(3, '商品F', 6500.00);
