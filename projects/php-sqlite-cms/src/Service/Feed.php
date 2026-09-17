<?php

declare(strict_types=1);

namespace Cms\Service;

use Cms\App;
use Cms\Repository\PostRepository;

/**
 * RSS 2.0 + JSON Feed generators.
 */
final class Feed
{
    public function __construct(private App $app)
    {
    }

    /** @return list<array<string, mixed>> */
    public function items(int $limit = 20): array
    {
        return (new PostRepository($this->app->db()))
            ->published(1, $limit)['items'];
    }

    public function rss(int $limit = 20): string
    {
        $settings = $this->app->settings();
        $title = (string) $settings->get('site_title');
        $description = (string) $settings->get('site_description');
        $items = $this->items($limit);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n<channel>\n";
        $xml .= '  <title>' . $this->esc($title) . "</title>\n";
        $xml .= '  <link>' . $this->esc(absolute_url('/')) . "</link>\n";
        $xml .= '  <description>' . $this->esc($description) . "</description>\n";
        $xml .= '  <language>en</language>' . "\n";
        $xml .= '  <atom:link href="' . $this->esc(absolute_url('/feed.xml')) . '" rel="self" type="application/rss+xml" />' . "\n";
        $xml .= '  <lastBuildDate>' . date(DATE_RSS) . "</lastBuildDate>\n";

        foreach ($items as $post) {
            $url = absolute_url('/posts/' . $post['slug']);
            $published = (string) ($post['published_at'] ?? $post['created_at']);
            $xml .= "  <item>\n";
            $xml .= '    <title>' . $this->esc((string) $post['title']) . "</title>\n";
            $xml .= '    <link>' . $this->esc($url) . "</link>\n";
            $xml .= '    <guid isPermaLink="true">' . $this->esc($url) . "</guid>\n";
            $xml .= '    <pubDate>' . date(DATE_RSS, (int) strtotime($published)) . "</pubDate>\n";
            $xml .= '    <description>' . $this->esc((string) ($post['excerpt'] ?? '')) . "</description>\n";
            if (!empty($post['category_name'])) {
                $xml .= '    <category>' . $this->esc((string) $post['category_name']) . "</category>\n";
            }
            $xml .= '    <author>' . $this->esc((string) ($post['author_name'] ?? '')) . "</author>\n";
            $xml .= "  </item>\n";
        }

        return $xml . "</channel>\n</rss>\n";
    }

    public function json(int $limit = 20): string
    {
        $settings = $this->app->settings();
        $items = [];
        foreach ($this->items($limit) as $post) {
            $items[] = [
                'id' => (int) $post['id'],
                'url' => absolute_url('/posts/' . $post['slug']),
                'title' => (string) $post['title'],
                'summary' => (string) ($post['excerpt'] ?? excerpt((string) $post['content'], 200)),
                'date_published' => date('c', (int) strtotime((string) ($post['published_at'] ?? $post['created_at']))),
                'author' => ['name' => (string) ($post['author_name'] ?? '')],
                'tags' => array_column((new PostRepository($this->app->db()))->tagsFor((int) $post['id']), 'name'),
            ];
        }

        return (string) json_encode([
            'version' => 'https://jsonfeed.org/version/1.1',
            'title' => (string) $settings->get('site_title'),
            'home_page_url' => absolute_url('/'),
            'feed_url' => absolute_url('/feed.json'),
            'description' => (string) $settings->get('site_description'),
            'items' => $items,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
