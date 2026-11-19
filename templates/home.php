<?php
/**
 * @var array $groups
 * @var array $bounds
 */
$isProduction = getenv('APP_ENV') === 'production';
$closeIcon = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';
$zoomTo = 'const r = timeline.zoomBy(%s, $_zoom, $_panX); $_zoom = r.zoom; $_panX = r.panX';
$goHome = 'const r = timeline.home(); $_zoom = r.zoom; $_panX = r.panX';
$goFit = 'const r = timeline.fit(); $_zoom = r.zoom; $_panX = r.panX';
$goToday = '$_panX = timeline.todayPan($_zoom)';
$colorField = static function (string $colorId, string $colorValue, ?bool $colorInherit = null): void {
    include __DIR__ . '/partials/color-field.php';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>Life Timeline</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='8' fill='%231c1b18'/%3E%3Crect x='5' y='8' width='14' height='4' rx='2' fill='%23f0ede6'/%3E%3Crect x='11' y='14' width='16' height='4' rx='2' fill='%23ff6b4a'/%3E%3Crect x='7' y='20' width='11' height='4' rx='2' fill='%23f0ede6'/%3E%3C/svg%3E">
    <script type="importmap">
    {
        "imports": {
            "datastar": "/js/datastar.js"
        }
    }
    </script>
    <script type="module" src="/js/app.js?v=<?php echo filemtime(__DIR__ . '/../public/js/app.js'); ?>"></script>
    <link rel="stylesheet" href="/css/timeline.css?v=<?php echo filemtime(__DIR__ . '/../public/css/timeline.css'); ?>">
</head>
<body>
    <div id="app"
         data-signals='{
            "_zoom": 1,
            "_panX": 0,
            "_isDragging": false,
            "_isResizing": false,
            "_lastMouseX": 0,
            "_showAddItem": false,
            "_showAddGroup": false,
            "_showAbout": false
         }'
         data-on-keys:left__noprevent="timeline.canPan() && ($_panX = timeline.clampPan($_panX + 100))"
         data-on-keys:right__noprevent="timeline.canPan() && ($_panX = timeline.clampPan($_panX - 100))"
         data-on-keys:ctrl-0="<?php echo $goHome; ?>"
         data-on-keys:meta-0="<?php echo $goHome; ?>"
         data-on-keys:t__noprevent="timeline.canUseKeys() && (<?php echo $goToday; ?>)"
         data-on-keys:f__noprevent="timeline.canUseKeys() && (($_zoom = timeline.fit().zoom), ($_panX = timeline.fit().panX))"
         data-on-keys:n__noprevent="timeline.canUseKeys() && !timeline.blockedByLock() && (evt.preventDefault(), $_showAddItem = true)"
         data-on-keys:g__noprevent="timeline.canUseKeys() && !timeline.blockedByLock() && (evt.preventDefault(), $_showAddGroup = true)"
         data-on-keys:l__noprevent="timeline.lockKey(evt)"
         data-on-keys:d__noprevent="timeline.descriptionsKey(evt)"
         data-on-keys:ctrl-z__noprevent="timeline.undoKey(evt)"
         data-on-keys:meta-z__noprevent="timeline.undoKey(evt)"
         data-on:pointermove__window="if($_isDragging) { $_panX = timeline.clampPan($_panX + timeline.getDragDelta(evt, $_lastMouseX)); $_lastMouseX = evt.clientX } else if($_isResizing) { timeline.handleResizeMove(evt) }"
         data-on:pointerup__window="$_isDragging = false; if($_isResizing) { timeline.finishResize(); $_isResizing = false }"
         data-on:pointercancel__window="$_isDragging = false; if($_isResizing) { timeline.cancelResize(); $_isResizing = false }"
         data-on:keydown__window="evt.key === 'Escape' && $_isResizing && timeline.cancelResize() && ($_isResizing = false)"
         data-on:pointerdown="timeline.startResize(evt) && ($_isResizing = true)"
         data-indicator="_connected"
         data-init="@get('/updates')">

        <header class="appbar">
            <div class="brand">
                <svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true">
                    <rect class="brand-mark-bg" width="32" height="32" rx="8"/>
                    <rect x="5" y="8" width="14" height="4" rx="2"/>
                    <rect class="brand-mark-accent" x="11" y="14" width="16" height="4" rx="2"/>
                    <rect x="7" y="20" width="11" height="4" rx="2"/>
                </svg>
                <h1 class="brand-name">Life Timeline</h1>
            </div>

            <span class="connection" role="status" data-class:is-live="$_connected">
                <span data-show="$_connected">Live</span>
                <span data-show="!$_connected">Offline</span>
            </span>

            <div class="search" role="search"
                 data-on:keydown="timeline.searchKey(evt)"
                 data-on:focusout="el.contains(evt.relatedTarget) || (el.querySelector('ul').hidden = true)">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg>
                <input id="search" type="search" placeholder="Find an item or a year" autocomplete="off"
                       aria-label="Find an item or jump to a year" aria-controls="search-results" title="Search (/)"
                       data-on:input="timeline.search(el.value)"
                       data-on:focus="timeline.search(el.value)">
                <ul id="search-results" class="search-results" aria-label="Search results" hidden></ul>
            </div>

            <div class="appbar-actions">
                <button type="button" id="descriptions-toggle" class="btn btn-toggle" aria-pressed="false" title="Show descriptions (D)"
                        data-on:click="timeline.toggleDescriptions()">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
                    <span class="btn-label">Details</span>
                </button>
                <button type="button" id="lock-toggle" class="btn btn-toggle" aria-pressed="false" title="Lock editing (L)"
                        data-on:click="timeline.toggleLock()">
                    <svg class="lock-closed" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7.5a4 4 0 0 1 8 0V11"/></svg>
                    <svg class="lock-open" viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7.5a4 4 0 0 1 7.6-1.75"/></svg>
                    <span class="btn-label">Lock</span>
                </button>
                <button type="button" class="btn btn-primary btn-new edit-only" title="New item (N)" data-on:click="timeline.isLocked() || ($_showAddItem = true)">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> <span class="btn-label">New item</span>
                </button>
                <button type="button" class="icon-btn" aria-label="About" title="About and shortcuts" aria-haspopup="dialog"
                        data-on:click="$_showAbout = true">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
                </button>
            </div>
        </header>

        <div class="stage"
             data-on:keydown__window="const f = timeline.zoomKeyFactor(evt); if (f) { <?php echo sprintf($zoomTo, 'f'); ?> }">
            <main class="timeline-wrapper"
                  data-on:wheel="const r = timeline.handleWheel(evt, $_zoom, $_panX); if(r) { $_zoom = r.zoom; $_panX = r.panX }"
                  data-on:mousedown="if(!timeline.isTimelineItem(evt) && !timeline.isControl(evt)) { $_isDragging = true; $_lastMouseX = evt.clientX }"
                  data-on:keydown="timeline.navKey(evt)"
                  data-on:scroll="const p = timeline.panFromScroll(el); if (p !== null) { $_panX = p }"
                  data-init="window.timeline && ($_panX = timeline.todayPan(1))"
                  data-effect="const pan = $_panX; $_zoom; window.timeline?.applyPan(el, pan)"
                  data-class:is-panning="$_isDragging"
                  data-style:--zoom-level="$_zoom">
                <div id="timeline-container" class="timeline-container">
                    <?php include __DIR__ . '/partials/timeline.php'; ?>
                </div>
            </main>

            <div class="dock" role="group" aria-label="Zoom and navigation">
                <button type="button" class="dock-btn" aria-label="Zoom out" title="Zoom out (Ctrl −)"
                        data-on:click="<?php echo sprintf($zoomTo, '1 / 1.5'); ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/></svg>
                </button>
                <button type="button" class="dock-btn dock-zoom" aria-label="Reset view" title="Reset view (Ctrl 0)"
                        data-on:click="<?php echo $goHome; ?>">
                    <output class="zoom-level" aria-live="polite" data-text="Math.round($_zoom * 100) + '%'">100%</output>
                </button>
                <button type="button" class="dock-btn" aria-label="Zoom in" title="Zoom in (Ctrl +)"
                        data-on:click="<?php echo sprintf($zoomTo, '1.5'); ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                </button>
                <span class="dock-sep" aria-hidden="true"></span>
                <button type="button" class="dock-btn" aria-label="Fit timeline" title="Fit the whole timeline (F)"
                        data-on:click="<?php echo $goFit; ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7v10M20 7v10M8 12h8M8 12l2.5-2.5M8 12l2.5 2.5M16 12l-2.5-2.5M16 12l-2.5 2.5"/></svg>
                </button>
                <button type="button" class="dock-btn dock-today" title="Go to today (T)"
                        data-on:click="<?php echo $goToday; ?>">
                    Today
                </button>
            </div>
        </div>

        <dialog class="modal" aria-labelledby="add-item-title"
                data-effect="$_showAddItem ? (el.open || el.showModal()) : (el.open && el.close())"
                data-on:close="$_showAddItem = false"
                data-on:click="evt.target === el && el.close()">
            <form class="modal-content" data-reset data-on:submit__prevent="timeline.submitForm(el, 'POST', '/cmd/items')">
                <header class="modal-header">
                    <h2 id="add-item-title">New item</h2>
                    <button type="button" class="icon-btn" aria-label="Close" data-on:click="el.closest('dialog').close()"><?php echo $closeIcon; ?></button>
                </header>
                <div class="modal-body">
                    <p class="form-error" role="alert" hidden></p>
                    <div class="field">
                        <label for="add-item-name">Title</label>
                        <input id="add-item-name" type="text" name="title" required maxlength="200" pattern=".*\S.*" autofocus placeholder="e.g. iPhone 15 Pro">
                    </div>
                    <div class="field">
                        <label for="add-item-group">Group</label>
                        <?php include __DIR__ . '/partials/group-select.php'; ?>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label for="add-item-start">Start</label>
                            <input id="add-item-start" type="month" name="startDate" required
                                   data-on:input="el.form.elements.endDate.min = el.value">
                        </div>
                        <div class="field">
                            <label for="add-item-end">End <small>(empty if ongoing)</small></label>
                            <input id="add-item-end" type="month" name="endDate">
                        </div>
                    </div>
                    <?php $colorField('add-item-color', '#3498db', true); ?>
                    <div class="field">
                        <label for="add-item-description">Description</label>
                        <textarea id="add-item-description" name="description" maxlength="2000" placeholder="Optional"></textarea>
                    </div>
                </div>
                <footer class="modal-actions">
                    <button type="button" class="btn" data-on:click="el.closest('dialog').close()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add item</button>
                </footer>
            </form>
        </dialog>

        <dialog class="modal" aria-labelledby="add-group-title"
                data-effect="$_showAddGroup ? (el.open || el.showModal()) : (el.open && el.close())"
                data-on:close="$_showAddGroup = false"
                data-on:click="evt.target === el && el.close()">
            <form class="modal-content" data-reset data-on:submit__prevent="timeline.submitForm(el, 'POST', '/cmd/groups')">
                <header class="modal-header">
                    <h2 id="add-group-title">New group</h2>
                    <button type="button" class="icon-btn" aria-label="Close" data-on:click="el.closest('dialog').close()"><?php echo $closeIcon; ?></button>
                </header>
                <div class="modal-body">
                    <p class="form-error" role="alert" hidden></p>
                    <div class="field-row">
                        <div class="field field-icon">
                            <label for="add-group-icon">Icon</label>
                            <input id="add-group-icon" type="text" name="icon" value="📁" maxlength="16">
                        </div>
                        <div class="field">
                            <label for="add-group-name">Name</label>
                            <input id="add-group-name" type="text" name="name" required maxlength="100" pattern=".*\S.*" autofocus placeholder="e.g. Gaming consoles">
                        </div>
                    </div>
                    <?php $colorField('add-group-color', '#3498db'); ?>
                </div>
                <footer class="modal-actions">
                    <button type="button" class="btn" data-on:click="el.closest('dialog').close()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add group</button>
                </footer>
            </form>
        </dialog>

        <?php include __DIR__ . '/partials/about-modal.php'; ?>

        <dialog id="confirm-dialog" class="modal confirm-modal" role="alertdialog" aria-labelledby="confirm-title" aria-describedby="confirm-message"
                data-on:click="evt.target === el && el.close()">
            <div class="modal-content">
                <div class="modal-body">
                    <h2 id="confirm-title">Delete?</h2>
                    <p id="confirm-message" class="confirm-message"></p>
                    <p class="confirm-note">This cannot be undone.</p>
                    <p class="form-error" role="alert" hidden></p>
                </div>
                <footer class="modal-actions">
                    <button type="button" class="btn" autofocus data-on:click="el.closest('dialog').close()">Cancel</button>
                    <button type="button" class="btn btn-danger-solid" data-on:click="timeline.runConfirmed()">Delete</button>
                </footer>
            </div>
        </dialog>

        <div id="toasts" class="toasts" role="status" aria-live="polite" popover="manual"></div>

        <?php if (!$isProduction) { ?>
        <details class="debug-panel">
            <summary>Debug signals</summary>
            <pre data-json-signals></pre>
        </details>
        <?php } ?>
    </div>
</body>
</html>
