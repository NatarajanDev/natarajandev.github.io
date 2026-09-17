<?php

declare(strict_types=1);

namespace Cms\Controllers\Admin;

use Cms\Controllers\Controller;
use Cms\Support\Response;

/**
 * Admin overview: counters, traffic chart, moderation queue and activity feed.
 */
final class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = $this->app->auth()->user();
        $scopedUserId = $this->app->auth()->canModerate() ? null : $this->app->auth()->id();

        $this->app->view()->setMeta(['title' => 'Dashboard', 'robots' => 'noindex,nofollow']);

        return $this->view('admin/dashboard', [
            'summary' => $this->app->dashboard()->summary($scopedUserId),
            'chart' => $this->app->dashboard()->chart(14),
            'activity' => $this->app->dashboard()->activity(8),
            'pendingComments' => $this->app->dashboard()->pendingComments(5),
            'recentPosts' => $this->app->dashboard()->recentPosts(5, $scopedUserId),
            'topPosts' => $this->app->dashboard()->topPosts(5),
            'storage' => $this->app->dashboard()->storage(),
            'user' => $user,
            'greeting' => $this->greeting(),
        ]);
    }

    private function greeting(): string
    {
        $hour = (int) date('G');

        return match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }
}
