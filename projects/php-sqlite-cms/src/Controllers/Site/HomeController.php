<?php

declare(strict_types=1);

namespace Cms\Controllers\Site;

use Cms\Controllers\Controller;
use Cms\Support\Response;

/**
 * Public landing page.
 */
final class HomeController extends Controller
{
    public function index(): Response
    {
        $posts = $this->posts();
        $perPage = max(3, (int) $this->settings()->get('posts_per_page', '6'));

        $featured = $posts->featured(3);
        $latest = $posts->published(1, $perPage)['items'];
        $total = $posts->counts();

        $this->app->view()->setMeta([
            'title' => (string) $this->settings()->get('site_tagline'),
            'description' => (string) $this->settings()->get('site_description'),
            'canonical' => absolute_url('/'),
            'type' => 'website',
        ]);

        return $this->view('site/home', array_merge($this->sidebarData(), [
            'featured' => $featured,
            'latest' => $latest,
            'categories' => $this->categories()->all(),
            'stats' => [
                'articles' => $total['published'],
                'categories' => $this->categories()->count(),
                'tags' => $this->tags()->count(),
                'comments' => $this->comments()->counts()['approved'],
            ],
        ]));
    }

    public function about(): Response
    {
        return $this->view('site/about', [
            'meta' => [
                'title' => 'About this project',
                'description' => 'How Nova CMS is put together: PHP 8, SQLite, no framework and no build step.',
            ],
        ]);
    }
}
