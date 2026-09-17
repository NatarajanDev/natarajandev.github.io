<?php
/**
 * CLI installer.
 *
 *   php bin/install.php --email=me@example.com --password=secret --name="Jane" [--demo]
 *
 * Safe to re-run: migrations are idempotent, and an existing admin is kept.
 */

declare(strict_types=1);

/** @var \Cms\App $app */
$app = require dirname(__DIR__) . '/src/bootstrap.php';

use Cms\Repository\UserRepository;

$options = getopt('', ['name::', 'email::', 'password::', 'demo', 'reset', 'help']);

if (isset($options['help'])) {
    echo <<<TXT
    Nova CMS installer

      --name="Jane Doe"        administrator display name
      --email=jane@example.com administrator email
      --password=secret        password (min 8 characters)
      --demo                   also seed demo articles, pages and comments
      --reset                  drop every table before migrating (destructive!)

    With no options, values are read from CMS_ADMIN_NAME / CMS_ADMIN_EMAIL / CMS_ADMIN_PASSWORD.

    TXT;
    exit(0);
}

$installer = $app->installer();

if (isset($options['reset'])) {
    echo "Dropping all tables…\n";
    $installer->reset();
}

echo "Running migrations…\n";
$executed = $installer->migrate();
echo $executed === []
    ? "  • schema already up to date\n"
    : '  • applied: ' . implode(', ', $executed) . "\n";

$users = new UserRepository($app->db());
if ($users->countAdmins() > 0 && !isset($options['demo'])) {
    echo "An administrator already exists — skipping account creation.\n";
} else {
    $name = (string) ($options['name'] ?? getenv('CMS_ADMIN_NAME') ?: 'Administrator');
    $email = (string) ($options['email'] ?? getenv('CMS_ADMIN_EMAIL') ?: '');
    $password = (string) ($options['password'] ?? getenv('CMS_ADMIN_PASSWORD') ?: '');

    if ($email === '' || $password === '') {
        // Interactive fallback when a terminal is attached.
        if (function_exists('readline') && stream_isatty(STDIN)) {
            $email = $email !== '' ? $email : (string) readline('Admin email: ');
            while ($password === '') {
                $password = (string) readline('Admin password (min 8 chars): ');
            }
            $name = $name !== 'Administrator' ? $name : (string) (readline('Display name [Administrator]: ') ?: 'Administrator');
        } else {
            fwrite(STDERR, "Provide --email and --password (or set CMS_ADMIN_EMAIL / CMS_ADMIN_PASSWORD).\n");
            exit(1);
        }
    }

    if (strlen($password) < 8) {
        fwrite(STDERR, "Password must be at least 8 characters.\n");
        exit(1);
    }

    if ($users->emailExists($email)) {
        echo "  • {$email} already registered — leaving it untouched\n";
    } else {
        $adminId = $installer->createAdmin($name, $email, $password);
        echo "  • created administrator {$email} (#{$adminId})\n";

        if (isset($options['demo'])) {
            echo "Seeding demo content…\n";
            $installer->seedDemo($adminId);
            echo "  • demo posts, pages and comments created\n";
        }
    }
}

$settings = $app->settings();
echo "\nReady.\n";
echo '  • site title : ' . $settings->get('site_title') . "\n";
echo '  • database   : ' . ($app->config('database.driver') === 'sqlite' ? $app->config('database.sqlite') : 'mysql') . "\n";
echo "  • start it   : php -S localhost:8080 -t public public/router.php\n";
echo "  • admin panel: /admin\n";
