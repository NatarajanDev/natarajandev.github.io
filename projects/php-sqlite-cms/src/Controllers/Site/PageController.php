<?php

declare(strict_types=1);

namespace Cms\Controllers\Site;

use Cms\Controllers\Controller;
use Cms\Support\Request;
use Cms\Support\Response;

/**
 * Static pages (about, privacy, contact, ...) and the contact form endpoint.
 */
final class PageController extends Controller
{
    public function show(string $slug): Response
    {
        $page = $this->pages()->findBySlug($slug);
        if ($page === null) {
            return new Response($this->app->view()->render('errors/404', [
                'meta' => ['title' => 'Page not found'],
            ]), 404);
        }

        $this->app->view()->setMeta([
            'title' => (string) ($page['seo_title'] ?: $page['title']),
            'description' => (string) ($page['seo_description'] ?: excerpt((string) $page['content'], 160)),
            'canonical' => absolute_url('/pages/' . $page['slug']),
        ]);

        return $this->view('site/page', [
            'page' => $page,
            'children' => array_values(array_filter(
                $this->pages()->published(),
                static fn (array $candidate): bool => (int) ($candidate['parent_id'] ?? 0) === (int) $page['id']
            )),
            'contactEmail' => (string) $this->settings()->get('contact_email'),
        ]);
    }

    /** POST /contact — stores the message in the admin inbox. */
    public function contact(): Response
    {
        $request = Request::capture();
        $input = $request->all();

        if ($request->string('website') !== '') { // honeypot
            return $this->redirect('/pages/contact');
        }

        $validator = $this->validate($input, [
            'name' => 'required|min:2|max:80',
            'email' => 'required|email|max:190',
            'subject' => 'required|min:3|max:150',
            'body' => 'required|min:10|max:4000',
        ], ['name' => 'Name', 'email' => 'Email', 'subject' => 'Subject', 'body' => 'Message']);

        if ($validator->fails()) {
            return $this->validationFailed($validator, '/pages/contact', $input);
        }

        $this->messages()->create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'subject' => $request->string('subject'),
            'body' => $request->string('body'),
            'ip' => $request->ip(),
        ]);

        audit()->record('message.received', 'message', null, ['email' => $request->string('email')]);
        $this->app->flash()->success('Thanks — your message is in our inbox.');

        return $this->redirect('/pages/contact');
    }
}
