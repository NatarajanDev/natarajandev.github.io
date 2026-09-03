# PHP Business Dashboard Demo

A small, production-style PHP/MySQL reporting application demonstrating clean separation between HTTP endpoints, data access, and reporting logic.

## What it demonstrates
- PDO-based database access
- Prepared SQL statements
- JSON API endpoint
- Server-side aggregation
- Simple HTML dashboard
- Configuration through environment variables

## Run
1. Create a MySQL database and import `database/schema.sql`.
2. Set `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS`.
3. Serve the `public/` directory with PHP 8+.

Example:

```bash
php -S localhost:8080 -t public
```

Then open `http://localhost:8080/`.

This is a portfolio sample using synthetic data; it contains no client code or credentials.
