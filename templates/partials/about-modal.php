<dialog class="modal about-modal" aria-labelledby="about-title"
        data-effect="$_showAbout ? (el.open || el.showModal()) : (el.open && el.close())"
        data-on:close="$_showAbout = false"
        data-on:click="evt.target === el && el.close()">
    <div class="modal-content">
        <header class="modal-header">
            <h2 id="about-title">About Life Timeline</h2>
            <button type="button" class="icon-btn" aria-label="Close" autofocus data-on:click="el.closest('dialog').close()">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </header>

        <div class="modal-body">
            <p>
                A full-screen timeline of life events (phones, cars, jobs, homes), each kind on its own track.
                Everyone who has the page open sees every change as it happens. It is a demo, and a template
                for PHP applications that keep a live connection to the browser.
            </p>

            <section aria-labelledby="about-keys-title">
                <h3 id="about-keys-title">Keyboard and mouse</h3>
                <dl class="shortcuts">
                    <div><dt><kbd>←</kbd> <kbd>→</kbd></dt><dd>Pan</dd></div>
                    <div><dt><kbd>Ctrl</kbd> + wheel</dt><dd>Zoom at the pointer</dd></div>
                    <div><dt><kbd>Ctrl</kbd> <kbd>+</kbd> / <kbd>−</kbd></dt><dd>Zoom in and out</dd></div>
                    <div><dt><kbd>Ctrl</kbd> <kbd>0</kbd></dt><dd>Reset the view</dd></div>
                    <div><dt><kbd>T</kbd></dt><dd>Go to today</dd></div>
                    <div><dt><kbd>F</kbd></dt><dd>Fit the whole timeline</dd></div>
                    <div><dt><kbd>/</kbd></dt><dd>Find an item or a year</dd></div>
                    <div><dt><kbd>N</kbd></dt><dd>New item</dd></div>
                    <div><dt><kbd>G</kbd></dt><dd>New group</dd></div>
                    <div><dt><kbd>L</kbd></dt><dd>Lock or unlock editing</dd></div>
                    <div><dt><kbd>Ctrl</kbd> <kbd>Z</kbd></dt><dd>Undo the last change</dd></div>
                    <div><dt><kbd>Esc</kbd></dt><dd>Close a dialog, cancel a resize</dd></div>
                </dl>
                <p class="about-note">
                    <kbd>Tab</kbd> stops once per group. From there:
                </p>
                <dl class="shortcuts">
                    <div><dt><kbd>←</kbd> <kbd>→</kbd></dt><dd>Walk the group's items</dd></div>
                    <div><dt><kbd>↑</kbd> <kbd>↓</kbd></dt><dd>Previous and next group</dd></div>
                    <div><dt><kbd>Enter</kbd></dt><dd>Edit the item or group</dd></div>
                    <div><dt><kbd>Shift</kbd> <kbd>←</kbd> <kbd>→</kbd></dt><dd>Move an item's end</dd></div>
                    <div><dt><kbd>Alt</kbd> <kbd>←</kbd> <kbd>→</kbd></dt><dd>Move an item's start</dd></div>
                    <div><dt><kbd>Alt</kbd> <kbd>↑</kbd> <kbd>↓</kbd></dt><dd>Move the group</dd></div>
                </dl>
                <p class="about-note">
                    Drag the edges of an item to change its dates, and the dots next to a group to reorder it.
                    On a touch screen, the group's dialog has Move up and Move down.
                </p>
            </section>

            <section aria-labelledby="about-tech-title">
                <h3 id="about-tech-title">How it works</h3>
                <ul class="about-tech">
                    <li><strong>PHP on Swoole</strong>: Mezzio with PSR-15 middleware runs inside one long-lived Swoole HTTP server, no PHP-FPM.</li>
                    <li><strong>Server-Sent Events</strong>: each tab holds one stream open in a Swoole coroutine. A change goes through a command handler, which emits an event on an in-memory event bus, and every stream receives the re-rendered timeline as HTML.</li>
                    <li><strong><a href="https://data-star.dev/" target="_blank" rel="noopener">Datastar</a></strong> patches that HTML into the page and keeps zoom, panning and dialogs in signals. A small TypeScript file does the timeline geometry: zooming, dragging and resizing.</li>
                    <li><strong>SQLite</strong> for groups and items, plain PHP templates, commands and queries kept apart (CQRS).</li>
                </ul>
            </section>

            <p class="about-links">
                <a href="https://github.com/mbolli/php-timeline" target="_blank" rel="noopener">Source code on GitHub</a>
            </p>
        </div>

        <p class="about-footer">Maintained by <a href="https://zweiundeins.gmbh">zwei und eins gmbh</a>.</p>
    </div>
</dialog>
