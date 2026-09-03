CREATE DATABASE IF NOT EXISTS portfolio_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE portfolio_demo;

CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product VARCHAR(160) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    order_date DATE NOT NULL,
    INDEX idx_orders_date (order_date),
    INDEX idx_orders_product (product)
);

INSERT INTO orders (product, amount, order_date) VALUES
('Puzzle Workbook', 14.99, '2026-06-03'),
('Puzzle Workbook', 14.99, '2026-06-17'),
('Business Planner', 9.99, '2026-06-20'),
('Puzzle Workbook', 14.99, '2026-07-02'),
('Chess Collection', 19.99, '2026-07-09'),
('Business Planner', 9.99, '2026-07-14');
