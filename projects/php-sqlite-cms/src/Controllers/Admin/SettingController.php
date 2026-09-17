<?php

declare(strict_types=1);

namespace Cms\Controllers\Admin;

use Cms\Controllers\Controller;
use Cms\Support\Request;
use Cms\Support\Response;

/**
 * Site settings, grouped into tabs.
 */
final class SettingController extends Controller
{
    /** Field map: tab => [key => [label, type]] */
    private const TABS = [
        'general' => [
            'site_title' => ['Site title', 'text'],
            'site_tagline' => ['Tagline', 'text'],
            'site_description' => ['Meta description', 'textarea'],
            'site_keywords' => ['Keywords', 'text'],
            'footer_text' => ['Footer text', 'text'],
            'contact_email' => ['Contact email', 'email'],
            'timezone_display' => ['Display timezone', 'text'],
        ],
        'content' => [
            'posts_per_page' => ['Posts per page', 'number'],
            'default_status' => ['Default post status', 'select:draft,published'],
            'maintenance_mode' => ['Maintenance mode', 'toggle'],
        ],
        'comments' => [
            'comments_enabled' => ['Enable comments', 'toggle'],
            'comment_moderation' => ['Hold new comments for moderation', 'toggle'],
            'comment_auto_approve_known' => ['Auto-approve comments from known emails', 'toggle'],
        ],
        'appearance' => [
            'theme_accent' => ['Accent colour', 'color'],
            'theme_mode' => ['Default theme', 'select:dark,light'],
        ],
        'seo' => [
            'seo_title_template' => ['Title template (%s — %s)', 'text'],
            'analytics_snippet' => ['Analytics snippet', 'textarea'],
        ],
        'social' => [
            'social_twitter' => ['Twitter / X URL', 'text'],
            'social_github' => ['GitHub URL', 'text'],
            'social_linkedin' => ['LinkedIn URL', 'text'],
        ],
    ];

    public function index(): Response
    {
        $request = Request::capture();
        $tab = $request->string('tab', 'general');
        if (!isset(self::TABS[$tab])) {
            $tab = 'general';
        }

        $this->app->view()->setMeta(['title' => 'Settings', 'robots' => 'noindex,nofollow']);

        return $this->view('admin/settings/index', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'values' => $this->settings()->all(),
        ]);
    }

    public function update(): Response
    {
        $request = Request::capture();
        $tab = $request->string('tab', 'general');
        $fields = self::TABS[$tab] ?? [];

        $values = [];
        foreach ($fields as $key => [$label, $type]) {
            if ($type === 'toggle') {
                $values[$key] = $request->bool($key) ? '1' : '0';
                continue;
            }

            $value = (string) $request->input($key, '');
            if ($type === 'number') {
                $value = (string) max(1, (int) $value);
            }
            if ($type === 'color' && preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) !== 1) {
                $value = '#6366f1';
            }
            $values[$key] = $value;
        }

        $this->settings()->setMany($values);
        $this->settings()->refresh();

        foreach (['sidebar.popular', 'sidebar.tags', 'sidebar.archive', 'sidebar.categories', 'menu.pages'] as $cacheKey) {
            $this->app->cache()->forget($cacheKey);
        }

        audit()->record('settings.updated', 'settings', null, ['tab' => $tab, 'keys' => array_keys($values)]);
        $this->app->flash()->success('Settings saved.');

        return $this->redirect('/admin/settings?tab=' . urlencode($tab));
    }
}
