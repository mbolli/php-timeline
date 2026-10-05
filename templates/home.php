<?php
/**
 * @var array $groups
 * @var array $bounds
 */
$isProduction = getenv('APP_ENV') === 'production';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>Life Timeline</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'%3E%3Ctext y='.9em' font-size='90'%3E%F0%9F%93%85%3C/text%3E%3C/svg%3E">
    <script type="importmap">
    {
        "imports": {
            "datastar": "/js/datastar.js"
        }
    }
    </script>
    <script type="module" src="/js/datastar.js"></script>
    <script type="module" src="/js/app.js"></script>
    <link rel="stylesheet" href="/css/timeline.css">
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
            "_showAddGroup": false
         }'
         data-on-keys:left__noprevent="timeline.canUseKeys() && ($_panX = timeline.clampPan($_panX + 100))"
         data-on-keys:right__noprevent="timeline.canUseKeys() && ($_panX = timeline.clampPan($_panX - 100))"
         data-on-keys:ctrl-0="$_zoom = 1; $_panX = 0"
         data-on-keys:meta-0="$_zoom = 1; $_panX = 0"
         data-on-keys:ctrl-equal="const r = timeline.zoomBy(1.2, $_zoom, $_panX); $_zoom = r.zoom; $_panX = r.panX"
         data-on-keys:meta-equal="const r = timeline.zoomBy(1.2, $_zoom, $_panX); $_zoom = r.zoom; $_panX = r.panX"
         data-on-keys:ctrl-minus="const r = timeline.zoomBy(1 / 1.2, $_zoom, $_panX); $_zoom = r.zoom; $_panX = r.panX"
         data-on-keys:meta-minus="const r = timeline.zoomBy(1 / 1.2, $_zoom, $_panX); $_zoom = r.zoom; $_panX = r.panX"
         data-on:mousemove__window="if($_isDragging) { $_panX = timeline.clampPan($_panX + timeline.getDragDelta(evt, $_lastMouseX)); $_lastMouseX = evt.clientX } else if($_isResizing) { timeline.handleResizeMove(evt) }"
         data-on:mouseup__window="$_isDragging = false; if($_isResizing) { timeline.finishResize(); $_isResizing = false }"
         data-on:keydown__window="evt.key === 'Escape' && $_isResizing && timeline.cancelResize() && ($_isResizing = false)"
         data-on:mousedown="timeline.startResize(evt) && ($_isResizing = true)"
         data-indicator="_connected"
         data-init="@get('/updates')">

        <header class="toolbar">
            <h1 class="toolbar-title"><span aria-hidden="true">📅</span> Life Timeline</h1>

            <div class="toolbar-zoom" role="group" aria-label="Zoom">
                <button type="button" class="btn btn-icon" aria-label="Zoom out" title="Zoom out (Ctrl −)"
                        data-on:click="const r = timeline.zoomBy(1 / 1.5, $_zoom, $_panX); $_zoom = r.zoom; $_panX = r.panX">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M8 11h6M20 20l-4-4"/></svg>
                </button>
                <output class="zoom-level" aria-live="polite" data-text="Math.round($_zoom * 100) + '%'">100%</output>
                <button type="button" class="btn btn-icon" aria-label="Zoom in" title="Zoom in (Ctrl +)"
                        data-on:click="const r = timeline.zoomBy(1.5, $_zoom, $_panX); $_zoom = r.zoom; $_panX = r.panX">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M8 11h6M11 8v6M20 20l-4-4"/></svg>
                </button>
                <button type="button" class="btn btn-icon" aria-label="Reset view" title="Reset view (Ctrl 0)"
                        data-on:click="$_zoom = 1; $_panX = 0">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 1 0 2.3-5.6"/><path d="M4 4v5h5"/></svg>
                </button>
            </div>

            <div class="toolbar-actions">
                <button type="button" class="btn btn-primary" data-on:click="$_showAddGroup = true">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> Group
                </button>
                <button type="button" class="btn btn-primary" data-on:click="$_showAddItem = true">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg> Item
                </button>
                <span class="connection" role="status" data-class:is-live="$_connected">
                    <span data-show="$_connected">Live</span>
                    <span data-show="!$_connected">Offline</span>
                </span>
            </div>
        </header>

        <main class="timeline-wrapper"
              data-on:wheel="const r = timeline.handleWheel(evt, $_zoom, $_panX); if(r) { $_zoom = r.zoom; $_panX = r.panX }"
              data-on:mousedown="if(!timeline.isTimelineItem(evt) && !timeline.isControl(evt)) { $_isDragging = true; $_lastMouseX = evt.clientX }"
              data-on:scroll="const p = timeline.panFromScroll(el); if (p !== null) { $_panX = p }"
              data-effect="const pan = $_panX; $_zoom; window.timeline?.applyPan(el, pan)"
              data-style:--zoom-level="$_zoom">
            <div id="timeline-container" class="timeline-container">
                <?php include __DIR__ . '/partials/timeline.php'; ?>
            </div>
        </main>

        <dialog class="modal" aria-labelledby="add-item-title"
                data-effect="$_showAddItem ? (el.open || el.showModal()) : (el.open && el.close())"
                data-on:close="$_showAddItem = false"
                data-on:click="evt.target === el && el.close()">
            <form class="modal-content" data-on:submit__prevent="@post('/cmd/items', {contentType: 'form'}); el.reset(); $_showAddItem = false">
                <h2 id="add-item-title">Add timeline item</h2>
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
                <div class="field">
                    <label for="add-item-color">Color</label>
                    <input id="add-item-color" type="color" name="color" value="#3498db">
                </div>
                <div class="field">
                    <label for="add-item-description">Description</label>
                    <textarea id="add-item-description" name="description" maxlength="2000" placeholder="Optional"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn" data-on:click="el.closest('dialog').close()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add item</button>
                </div>
            </form>
        </dialog>

        <dialog class="modal" aria-labelledby="add-group-title"
                data-effect="$_showAddGroup ? (el.open || el.showModal()) : (el.open && el.close())"
                data-on:close="$_showAddGroup = false"
                data-on:click="evt.target === el && el.close()">
            <form class="modal-content" data-on:submit__prevent="@post('/cmd/groups', {contentType: 'form'}); el.reset(); $_showAddGroup = false">
                <h2 id="add-group-title">Add group</h2>
                <div class="field">
                    <label for="add-group-name">Name</label>
                    <input id="add-group-name" type="text" name="name" required maxlength="100" pattern=".*\S.*" autofocus placeholder="e.g. Gaming consoles">
                </div>
                <div class="field-row">
                    <div class="field">
                        <label for="add-group-icon">Icon</label>
                        <input id="add-group-icon" type="text" name="icon" value="📁" maxlength="16">
                    </div>
                    <div class="field">
                        <label for="add-group-color">Color</label>
                        <input id="add-group-color" type="color" name="color" value="#3498db">
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn" data-on:click="el.closest('dialog').close()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add group</button>
                </div>
            </form>
        </dialog>

        <?php if (!$isProduction) { ?>
        <details class="debug-panel">
            <summary>Debug signals</summary>
            <pre data-json-signals></pre>
        </details>
        <?php } ?>
    </div>
</body>
</html>
