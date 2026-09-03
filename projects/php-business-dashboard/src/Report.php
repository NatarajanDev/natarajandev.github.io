<?php
declare(strict_types=1);

require_once __DIR__ . '/Database.php';

final class Report
{
    public function __construct(private PDO $db) {}

    public function monthlySales(): array
    {
        $sql = <<<'SQL'
            SELECT DATE_FORMAT(order_date, '%Y-%m') AS month,
                   COUNT(*) AS orders,
                   ROUND(SUM(amount), 2) AS revenue
            FROM orders
            GROUP BY DATE_FORMAT(order_date, '%Y-%m')
            ORDER BY month
        SQL;

        return $this->db->query($sql)->fetchAll();
    }

    public function topProducts(int $limit = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT product, COUNT(*) AS orders, ROUND(SUM(amount), 2) AS revenue
             FROM orders GROUP BY product ORDER BY revenue DESC LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
