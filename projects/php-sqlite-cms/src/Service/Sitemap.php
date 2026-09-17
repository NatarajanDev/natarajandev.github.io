<?php

declare(strict_types=1);

namespace Cms\Service;

use Cms\App;
use Cms\Repository\CategoryRepository;
use Cms\Repository\PageRepository;
use Cms\Repository\PostRepository;
use Cms\Repository\TagRepository;

/**
 * XML sitemap and robots.txt generation.
 */
final class Sitemap
{
    public function __construct(private App $app)
    {
    }

    public function xml(): string
    {
        $posts = (new PostRepository($this->app->db()))->published(1, 200);
        $pages = (new PageRepository($this->app->db()))->published();
        $categories = (new CategoryRepository($this->app->db()))->all();
        $tags = (new TagRepository($this->app->db()))->popular(50);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        $entries = [
            ['loc' => absolute_url('/'), 'lastmod' => date('Y-m-d'), 'priority' => '1.0'],
            ['loc' => absolute_url('/posts'), 'lastmod' => date('Y-m-d'), 'priority' => '0.8'],
        ];

        foreach ($posts['items'] as $post) {
            $entries[] = [
                'loc' => absolute_url('/posts/' . $post['slug']),
                'lastmod' => date('Y-m-d', (int) strtotime((string) ($post['updated_at'] ?? $post['published_at']))),
                'priority' => '0.9',
            ];
        }

        foreach ($pages as $page) {
            $entries[] = [
                'loc' => absolute_url('/pages/' . $page['slug']),
                'lastmod' => date('Y-m-d', (int) strtotime((string) ($page['updated_at'] ?? $page['created_at']))),
                'priority' => '0.6',
            ];
        }

        foreach ($categories as $category) {
            if ((int) ($category['published_count'] ?? 0) === 0) {
                continue;
            }
            $entries[] = ['loc' => absolute_url('/category/' . $category['slug']), 'lastmod' => date('Y-m-d'), 'priority' => '0.5'];
        }

        foreach ($tags as $tag) {
            $entries[] = ['loc' => absolute_url('/tag/' . $tag['slug']), 'lastmod' => date('Y-m-d'), 'priority' => '0.4'];
        }

        foreach ($entries as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($entry['loc'], ENT_XML1) . "</loc>\n";
            $xml .= '    <lastmod>' . $entry['lastmod'] . "</lastmod>\n";
            $xml .= '    <priority>' . $entry['priority'] . "</priority>\n";
            $xml .= "  </url>\n";
        }

        return $xml . "</urlset>\n";
    }

    public function robots(): string
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /install',
            'Disallow: /api/',
            '',
            'Sitemap: ' . absolute_url('/sitemap.xml'),
        ];

        return implode("\n", $lines) . "\n";
    }
}
