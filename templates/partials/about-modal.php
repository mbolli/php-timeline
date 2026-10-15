<dialog class="modal about-modal" aria-labelledby="about-title"
        data-effect="$_showAbout ? (el.open || el.showModal()) : (el.open && el.close())"
        data-on:close="$_showAbout = false"
        data-on:click="evt.target === el && el.close()">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="about-title">About Life Timeline</h2>
            <button type="button" class="btn btn-icon" aria-label="Close" autofocus data-on:click="el.closest('dialog').close()">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>

        <p>
            A full-screen timeline of life events (phones, cars, jobs, homes), each kind on its own track.
            Everyone who has the page open sees every change as it happens. It is a demo, and a template
            for PHP applications that keep a live connection to the browser.
        </p>

        <ul class="about-tech">
            <li><strong>PHP on Swoole</strong>: Mezzio with PSR-15 middleware runs inside one long-lived Swoole HTTP server, no PHP-FPM.</li>
            <li><strong>Server-Sent Events</strong>: each tab holds one stream open in a Swoole coroutine. A change goes through a command handler, which emits an event on an in-memory event bus, and every stream receives the re-rendered timeline as HTML.</li>
            <li><strong><a href="https://data-star.dev/" target="_blank" rel="noopener">Datastar</a></strong> patches that HTML into the page and keeps zoom, panning and dialogs in signals. A small TypeScript file does the timeline geometry: zooming, dragging and resizing.</li>
            <li><strong>SQLite</strong> for groups and items, plain PHP templates, commands and queries kept apart (CQRS).</li>
        </ul>

        <p class="about-links">
            <a href="https://github.com/mbolli/php-timeline" target="_blank" rel="noopener">Source code on GitHub</a>
        </p>

        <p class="about-footer">Maintained by <a href="https://zweiundeins.gmbh">zwei und eins gmbh</a>.</p>
    </div>
</dialog>
