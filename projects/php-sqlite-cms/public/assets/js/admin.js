/* ==========================================================================
   Nova CMS — admin panel behaviour
   Sidebar, flash messages, bulk selection, confirmations, Markdown editor
   with live preview, media picker modal and drag-and-drop uploads.
   ========================================================================== */
(() => {
    'use strict';

    const $ = (selector, scope = document) => scope.querySelector(selector);
    const $$ = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));

    /* ------------------------------------------------------------- sidebar */
    const sidebar = $('[data-sidebar]');
    $('[data-sidebar-toggle]')?.addEventListener('click', () => sidebar?.classList.toggle('is-open'));

    /* --------------------------------------------------------------- flash */
    $$('[data-flash]').forEach((flash) => {
        $('[data-flash-close]', flash)?.addEventListener('click', () => flash.remove());
        window.setTimeout(() => {
            flash.style.transition = 'opacity .4s ease';
            flash.style.opacity = '0';
            window.setTimeout(() => flash.remove(), 400);
        }, 6000);
    });

    /* ------------------------------------------------------- confirmations */
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-confirm]');
        if (!trigger) return;
        if (!window.confirm(trigger.dataset.confirm || 'Are you sure?')) {
            event.preventDefault();
            event.stopImmediatePropagation();
        }
    });

    /* ----------------------------------------------------- bulk selection */
    $$('[data-bulk-form]').forEach((form) => {
        const counter = $('[data-bulk-count]');
        const master = $('[data-check-all]', form) || $(`[data-check-all][form="${form.id}"]`);
        const items = () => $$('[data-check-item]').filter((box) => box.form === form || form.contains(box));

        const refresh = () => {
            const boxes = items();
            const checked = boxes.filter((box) => box.checked).length;
            if (counter) counter.textContent = String(checked);
            if (master) master.checked = checked > 0 && checked === boxes.length;
        };

        master?.addEventListener('change', () => {
            items().forEach((box) => { box.checked = master.checked; });
            refresh();
        });
        document.addEventListener('change', (event) => {
            if (event.target.matches('[data-check-item]')) refresh();
        });
        refresh();
    });

    /* --------------------------------------------------------- copy button */
    document.addEventListener('click', async (event) => {
        const trigger = event.target.closest('[data-copy]');
        if (!trigger) return;
        try {
            await navigator.clipboard.writeText(trigger.dataset.copy);
            const original = trigger.innerHTML;
            trigger.innerHTML = '✓';
            window.setTimeout(() => { trigger.innerHTML = original; }, 1400);
        } catch {
            window.prompt('Copy this URL:', trigger.dataset.copy);
        }
    });

    /* ----------------------------------------------- minimal Markdown (JS) */
    const escapeHtml = (value) => value
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    const inline = (text) => text
        .replace(/!\[([^\]]*)\]\(([^)\s]+)\)/g, '<img src="$2" alt="$1">')
        .replace(/\[([^\]]+)\]\(([^)\s]+)\)/g, '<a href="$2">$1</a>')
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>')
        .replace(/~~([^~]+)~~/g, '<del>$1</del>');

    const renderMarkdown = (source) => {
        const blocks = [];
        let text = source.replace(/```([a-zA-Z0-9_+-]*)\n([\s\S]*?)```/g, (_, language, code) => {
            blocks.push(`<pre><code class="language-${language}">${escapeHtml(code)}</code></pre>`);
            return `\u0000BLOCK${blocks.length - 1}\u0000`;
        });

        text = escapeHtml(text);
        const lines = text.split('\n');
        const out = [];
        let listType = null;

        const closeList = () => {
            if (listType) { out.push(`</${listType}>`); listType = null; }
        };

        lines.forEach((line, index) => {
            if (/^\s*$/.test(line)) { closeList(); return; }

            const heading = line.match(/^(#{1,6})\s+(.*)$/);
            if (heading) {
                closeList();
                out.push(`<h${heading[1].length}>${inline(heading[2])}</h${heading[1].length}>`);
                return;
            }
            if (/^\s*(---|\*\*\*|___)\s*$/.test(line)) { closeList(); out.push('<hr>'); return; }

            const quote = line.match(/^\s*>\s?(.*)$/);
            if (quote) { closeList(); out.push(`<blockquote><p>${inline(quote[1])}</p></blockquote>`); return; }

            const ul = line.match(/^\s*[-*+]\s+(.*)$/);
            if (ul) {
                if (listType !== 'ul') { closeList(); out.push('<ul>'); listType = 'ul'; }
                out.push(`<li>${inline(ul[1])}</li>`);
                return;
            }
            const ol = line.match(/^\s*\d+[.)]\s+(.*)$/);
            if (ol) {
                if (listType !== 'ol') { closeList(); out.push('<ol>'); listType = 'ol'; }
                out.push(`<li>${inline(ol[1])}</li>`);
                return;
            }

            closeList();
            out.push(`<p>${inline(line)}</p>`);
        });
        closeList();

        return out.join('\n').replace(/\u0000BLOCK(\d+)\u0000/g, (_, i) => blocks[Number(i)]);
    };

    /* -------------------------------------------------------------- editor */
    $$('[data-editor]').forEach((editor) => {
        const textarea = $('[data-editor-content]', editor);
        const preview = $('[data-editor-preview]', editor);
        const panes = $('[data-editor-panes]', editor);
        const counter = $('[data-editor-count]', editor);
        if (!textarea || !preview) return;

        const update = () => {
            preview.innerHTML = renderMarkdown(textarea.value);
            if (counter) {
                const words = textarea.value.trim().split(/\s+/).filter(Boolean).length;
                const minutes = Math.max(1, Math.round(words / 220));
                counter.textContent = `${words} words · ${minutes} min read`;
            }
        };

        textarea.addEventListener('input', update);
        panes?.setAttribute('data-mode', 'split');
        update();

        $$('[data-editor-mode]', editor).forEach((button) => {
            button.addEventListener('click', () => {
                $$('[data-editor-mode]', editor).forEach((b) => b.classList.remove('is-active'));
                button.classList.add('is-active');
                panes?.setAttribute('data-mode', button.dataset.editorMode);
                update();
            });
        });

        const insert = (snippet) => {
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const value = textarea.value;
            textarea.value = value.slice(0, start) + snippet + value.slice(end);
            textarea.selectionStart = textarea.selectionEnd = start + snippet.length;
            textarea.focus();
            update();
        };

        $$('[data-insert]', editor).forEach((button) => {
            button.addEventListener('click', () => insert(button.dataset.insert.replace(/\\n/g, '\n')));
        });

        $('[data-insert-media]', editor)?.addEventListener('click', () => {
            openMediaPicker((media) => insert(`![${media.alt || 'image'}](${media.url})`));
        });
    });

    /* ---------------------------------------------------------- media modal */
    const modal = $('[data-modal]');
    const modalTitle = $('[data-modal-title]');
    const modalBody = $('[data-modal-body]');
    let pickerHandler = null;

    const closeModal = () => { if (modal) { modal.hidden = true; modalBody.innerHTML = ''; } };
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-modal-close]')) closeModal();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeModal();
    });

    const openModal = (title, html) => {
        if (!modal) return;
        modalTitle.textContent = title;
        modalBody.innerHTML = html;
        modal.hidden = false;
    };

    function openMediaPicker(onPick) {
        pickerHandler = onPick;
        openModal('Choose media', '<p class="muted">Loading…</p>');

        fetch('/admin/media/picker', { headers: { Accept: 'application/json' } })
            .then((response) => response.json())
            .then((payload) => {
                if (!payload.items || payload.items.length === 0) {
                    modalBody.innerHTML = '<p class="muted">No images uploaded yet. Add files in the media library first.</p>';
                    return;
                }
                modalBody.innerHTML = `
                    <div class="media-grid">
                        ${payload.items.map((item) => `
                            <figure class="media-card">
                                <div class="media-card__frame"><img src="${item.thumb}" alt="${item.alt}"></div>
                                <figcaption class="media-card__meta">
                                    <strong>${item.name}</strong>
                                    <small>${item.size}</small>
                                    <button class="btn btn--primary btn--sm" type="button" data-pick='${JSON.stringify(item).replace(/'/g, '&#39;')}'>Insert</button>
                                </figcaption>
                            </figure>`).join('')}
                    </div>`;
            })
            .catch(() => { modalBody.innerHTML = '<p class="muted">Could not load the media library.</p>'; });
    }

    document.addEventListener('click', (event) => {
        const pick = event.target.closest('[data-pick]');
        if (pick && pickerHandler) {
            pickerHandler(JSON.parse(pick.dataset.pick));
            pickerHandler = null;
            closeModal();
            return;
        }

        if (event.target.closest('[data-media-pick]')) {
            const field = event.target.closest('[data-media-field]');
            openMediaPicker((media) => {
                $('[data-media-input]', field).value = media.url.replace(/^\//, '');
                $('[data-media-preview]', field).src = media.thumb;
            });
        }

        const strip = event.target.closest('[data-media-url]');
        if (strip) {
            const url = strip.dataset.mediaUrl;
            const field = $('[data-media-field]');
            if (field) {
                $('[data-media-input]', field).value = url;
                $('[data-media-preview]', field).src = strip.querySelector('img').src;
            }
        }

        if (event.target.closest('[data-media-clear]')) {
            const field = event.target.closest('[data-media-field]');
            $('[data-media-input]', field).value = '';
            $('[data-media-preview]', field).src = field.dataset.placeholder || '';
        }

        const edit = event.target.closest('[data-media-edit]');
        if (edit) {
            openModal('Media details', `
                <form method="post" action="${edit.dataset.url}" class="form">
                    <input type="hidden" name="_token" value="${window.CMS_CSRF || ''}">
                    <input type="hidden" name="_method" value="PUT">
                    <label class="field"><span>Alt text</span>
                        <input type="text" name="alt_text" value="${edit.dataset.alt}"></label>
                    <label class="field"><span>Caption</span>
                        <textarea name="caption" rows="3">${edit.dataset.caption}</textarea></label>
                    <button class="btn btn--primary" type="submit">Save details</button>
                </form>`);
        }
    });

    /* ------------------------------------------------------------ dropzone */
    $$('[data-dropzone]').forEach((zone) => {
        const input = $('[data-dropzone-input]', zone);
        const browse = $('[data-dropzone-browse]', zone);
        const token = $('input[name="_token"]', zone)?.value || '';

        browse?.addEventListener('click', () => input.click());

        const upload = (file) => {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('_token', token);

            const info = document.createElement('p');
            info.className = 'hint';
            info.textContent = `Uploading ${file.name}…`;
            zone.appendChild(info);

            fetch(zone.getAttribute('action'), {
                method: 'POST',
                body: formData,
                headers: { Accept: 'application/json' },
            })
                .then((response) => response.json())
                .then((payload) => {
                    if (payload.ok) {
                        info.textContent = 'Uploaded — refreshing…';
                        window.location.reload();
                    } else {
                        info.textContent = payload.error || 'Upload failed.';
                    }
                })
                .catch(() => { info.textContent = 'Upload failed.'; });
        };

        input?.addEventListener('change', () => {
            Array.from(input.files).forEach(upload);
        });

        ['dragenter', 'dragover'].forEach((type) => {
            zone.addEventListener(type, (event) => {
                event.preventDefault();
                zone.classList.add('is-dragging');
            });
        });
        ['dragleave', 'drop'].forEach((type) => {
            zone.addEventListener(type, (event) => {
                event.preventDefault();
                zone.classList.remove('is-dragging');
            });
        });
        zone.addEventListener('drop', (event) => {
            Array.from(event.dataTransfer?.files || []).forEach(upload);
        });
    });

    /* ------------------------------------------------------- media pickers */
    $$('[data-media-pick]').forEach((button) => {
        button.addEventListener('click', () => {
            const field = button.closest('[data-media-field]');
            openMediaPicker((media) => {
                $('[data-media-input]', field).value = media.url.replace(/^\//, '');
                $('[data-media-preview]', field).src = media.thumb;
            });
        });
    });

    /* ------------------------------------------------ slug from the title */
    const titleInput = $('[data-editor-title]');
    const slugInput = $('input[name="slug"]');
    if (titleInput && slugInput) {
        const slugify = (value) => value.toLowerCase().trim()
            .replace(/[^a-z0-9\s-]/g, '').replace(/\s+/g, '-').replace(/-+/g, '-').slice(0, 80);
        titleInput.addEventListener('blur', () => {
            if (!slugInput.value) slugInput.value = slugify(titleInput.value);
        });
    }
})();
