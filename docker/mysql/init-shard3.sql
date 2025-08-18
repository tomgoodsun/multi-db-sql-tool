-- Shard 3 初期化データ
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    shard_key VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_name VARCHAR(100) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- サンプルデータ
INSERT INTO users (name, email, shard_key) VALUES 
('渡辺雄一', 'watanabe@example.com', 'shard3'),
('小林奈々', 'kobayashi@example.com', 'shard3'),
('加藤誠', 'kato@example.com', 'shard3');

INSERT INTO orders (user_id, product_name, amount) VALUES 
(1, '商品G', 7500.00),
(2, '商品H', 8500.00),
(3, '商品I', 9500.00);
