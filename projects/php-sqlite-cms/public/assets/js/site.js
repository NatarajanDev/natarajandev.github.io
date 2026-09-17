/* ==========================================================================
   Nova CMS — public site behaviour
   Vanilla JS, no dependencies. Everything degrades gracefully without JS.
   ========================================================================== */
(() => {
    'use strict';

    const $ = (selector, scope = document) => scope.querySelector(selector);
    const $$ = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));

    /* ------------------------------------------------------- theme switching */
    const root = document.documentElement;
    const stored = localStorage.getItem('cms-theme');
    if (stored === 'light' || stored === 'dark') {
        root.dataset.theme = stored;
    }

    $$('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
            root.dataset.theme = next;
            localStorage.setItem('cms-theme', next);
        });
    });

    /* --------------------------------------------------------- mobile nav */
    const navToggle = $('[data-nav-toggle]');
    const nav = $('[data-nav]');
    if (navToggle && nav) {
        navToggle.addEventListener('click', () => {
            const open = nav.classList.toggle('is-open');
            navToggle.setAttribute('aria-expanded', String(open));
        });
    }

    /* -------------------------------------------------------- live search */
    const searchForm = $('[data-search-form]');
    if (searchForm) {
        const input = $('[data-search-input]', searchForm);
        const panel = $('[data-search-results]', searchForm);
        let timer = null;
        let controller = null;

        const close = () => {
            if (panel) {
                panel.hidden = true;
                panel.innerHTML = '';
            }
        };

        const render = (payload) => {
            if (!panel) return;
            if (!payload.results || payload.results.length === 0) {
                panel.innerHTML = `<p class="search-box__empty">No matches for “${payload.query}”.</p>`;
            } else {
                panel.innerHTML = payload.results.map((item) => `
                    <a class="search-result" href="${item.url}">
                        <strong>${item.title}</strong>
                        <small>${item.category ? item.category + ' · ' : ''}${item.excerpt}</small>
                    </a>`).join('');
            }
            panel.hidden = false;
        };

        input?.addEventListener('input', () => {
            const term = input.value.trim();
            window.clearTimeout(timer);
            if (term.length < 2) {
                close();
                return;
            }
            timer = window.setTimeout(async () => {
                controller?.abort();
                controller = new AbortController();
                try {
                    const response = await fetch(`/api/search?q=${encodeURIComponent(term)}`, {
                        signal: controller.signal,
                        headers: { Accept: 'application/json' },
                    });
                    render(await response.json());
                } catch (error) {
                    if (error.name !== 'AbortError') close();
                }
            }, 250);
        });

        document.addEventListener('click', (event) => {
            if (!searchForm.contains(event.target)) close();
        });
        input?.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') close();
        });
    }

    /* ----------------------------------------------------- copy to clipboard */
    $$('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(button.dataset.copy);
                const original = button.textContent;
                button.textContent = 'Copied!';
                window.setTimeout(() => { button.textContent = original; }, 1600);
            } catch {
                window.prompt('Copy this link:', button.dataset.copy);
            }
        });
    });

    /* --------------------------------------------------------- comment form */
    const form = $('[data-comment-form]');
    if (form) {
        const parentInput = $('[data-reply-parent]', form);
        const label = $('[data-reply-label]', form);
        const cancel = $('[data-reply-cancel]', form);
        const textarea = $('textarea[name="body"]', form);

        const setReply = (id, name) => {
            if (!parentInput) return;
            parentInput.value = id || '';
            if (label) label.textContent = id ? `Replying to ${name}` : 'Leave a comment';
            if (cancel) cancel.hidden = !id;
            textarea?.focus();
        };

        $$('[data-reply-to]').forEach((button) => {
            button.addEventListener('click', () => {
                setReply(button.dataset.replyTo, button.dataset.replyName);
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });
        cancel?.addEventListener('click', () => setReply('', ''));
    }

    /* --------------------------------------------------------- scroll reveal */
    const revealTargets = $$('[data-reveal]');
    if (revealTargets.length > 0 && 'IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'none';
                    observer.unobserve(entry.target);
                }
            });
        }, { rootMargin: '0px 0px -60px 0px' });

        revealTargets.forEach((target) => {
            target.style.opacity = '0';
            target.style.transform = 'translateY(12px)';
            target.style.transition = 'opacity .5s ease, transform .5s ease';
            observer.observe(target);
        });
    }

    /* --------------------------------------------- reading progress (article) */
    const article = $('.article__body');
    if (article) {
        const bar = document.createElement('div');
        bar.style.cssText = 'position:fixed;top:0;left:0;height:3px;width:0;background:var(--accent);z-index:60;transition:width .1s linear';
        document.body.appendChild(bar);

        const progress = () => {
            const rect = article.getBoundingClientRect();
            const total = rect.height - window.innerHeight;
            const scrolled = Math.min(Math.max(-rect.top, 0), Math.max(total, 1));
            bar.style.width = `${total > 0 ? (scrolled / total) * 100 : 0}%`;
        };

        window.addEventListener('scroll', progress, { passive: true });
        progress();
    }
})();
