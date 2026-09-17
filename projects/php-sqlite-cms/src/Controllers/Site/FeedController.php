<?php

declare(strict_types=1);

namespace Cms\Controllers\Site;

use Cms\Controllers\Controller;
use Cms\Service\Feed;
use Cms\Service\Sitemap;
use Cms\Support\Response;

/**
 * Machine-readable endpoints: RSS, JSON Feed, sitemap, robots and the
 * HTTP 404 for unknown paths.
 */
final class FeedController extends Controller
{
    public function rss(): Response
    {
        return Response::make((new Feed($this->app))->rss(), 200, [
            'Content-Type' => 'application/rss+xml; charset=utf-8',
        ]);
    }

    public function jsonFeed(): Response
    {
        return Response::make((new Feed($this->app))->json(), 200, [
            'Content-Type' => 'application/feed+json; charset=utf-8',
        ]);
    }

    public function sitemap(): Response
    {
        return Response::make((new Sitemap($this->app))->xml(), 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
        ]);
    }

    public function robots(): Response
    {
        return Response::make((new Sitemap($this->app))->robots(), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
