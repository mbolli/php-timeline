<?php
/**
 * @var array $groups
 * @var array $bounds
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Life Timeline</title>
    <script type="importmap">
    {
        "imports": {
            "datastar": "https://cdn.jsdelivr.net/gh/starfederation/datastar@1.0.0-RC.7/bundles/datastar.js"
        }
    }
    </script>
    <script type="module" src="https://cdn.jsdelivr.net/gh/starfederation/datastar@1.0.0-RC.7/bundles/datastar.js"></script>
    <script type="module" src="/js/app.js"></script>
    <link rel="stylesheet" href="/css/timeline.css">
</head>
<body>
    <div id="app"
         data-signals='{
            "_zoom": 1,
            "_panX": 0,
            "_isDragging": false,
            "_lastMouseX": 0,
            "_showAddItem": false,
            "_showAddGroup": false,
            "newGroup": {"name": "", "icon": "📁", "color": "#3498db"}
         }'
         data-on-keys:left__noprevent="$_panX += 100"
         data-on-keys:right__noprevent="$_panX -= 100"
         data-on-keys:ctrl-0="$_zoom = 1; $_panX = 0"
         data-on-keys:meta-0="$_zoom = 1; $_panX = 0"
         data-on-keys:ctrl-equal="$_zoom = timeline.clampZoom($_zoom * 1.2)"
         data-on-keys:meta-equal="$_zoom = timeline.clampZoom($_zoom * 1.2)"
         data-on-keys:ctrl-minus="$_zoom = timeline.clampZoom($_zoom * 0.8)"
         data-on-keys:meta-minus="$_zoom = timeline.clampZoom($_zoom * 0.8)"
         data-on:mousemove__window="if($_isDragging) { $_panX += timeline.getDragDelta(event, $_lastMouseX); $_lastMouseX = event.clientX }"
         data-on:mouseup__window="$_isDragging = false"
         data-indicator="_connected"
         data-init="@get('/updates')">

        <!-- Header Toolbar -->
        <header class="toolbar">
            <div class="toolbar-left">
                <h1>📅 Life Timeline</h1>
            </div>
            <div class="toolbar-center">
                <button class="btn btn-icon" data-on:click="$_zoom = Math.max($_zoom / 1.5, 0.1)" title="Zoom Out">
                    🔍−
                </button>
                <span class="zoom-level" data-text="Math.round($_zoom * 100) + '%'">100%</span>
                <button class="btn btn-icon" data-on:click="$_zoom = Math.min($_zoom * 1.5, 10)" title="Zoom In">
                    🔍+
                </button>
                <button class="btn btn-icon" data-on:click="$_zoom = 1; $_panX = 0" title="Reset View">
                    ⟲
                </button>
            </div>
            <div class="toolbar-right">
                <button class="btn btn-primary" data-on:click="$_showAddGroup = true">
                    + Group
                </button>
                <button class="btn btn-primary" data-on:click="$_showAddItem = true">
                    + Item
                </button>
                <span class="connection-indicator">
                    <span data-show="$_connected">🟢</span>
                    <span data-show="!$_connected">🔴</span>
                </span>
            </div>
        </header>

        <!-- Timeline Container -->
        <main class="timeline-wrapper"
              data-on:wheel="const r = timeline.handleWheel(event, $_zoom, $_panX); if(r) { $_zoom = r.zoom; $_panX = r.panX }"
              data-on:mousedown="if(!timeline.isTimelineItem(event)) { $_isDragging = true; $_lastMouseX = event.clientX }"
              data-on:touchstart="if(event.touches.length === 1 && !timeline.isTimelineItem(event)) { $_isDragging = true; $_lastMouseX = timeline.getTouchX(event) }"
              data-on:touchmove__prevent="if($_isDragging && event.touches.length === 1) { $_panX += timeline.getTouchX(event) - $_lastMouseX; $_lastMouseX = timeline.getTouchX(event) }"
              data-on:touchend="$_isDragging = false"
              data-style:--zoom-level="$_zoom"
              data-style:--pan-x="$_panX + 'px'">
            <div id="timeline-container"
                 class="timeline-container">
                <?php include __DIR__ . '/partials/timeline.php'; ?>
            </div>
        </main>

        <!-- Add Item Modal -->
        <dialog class="modal" data-class:open="$_showAddItem">
            <div class="modal-content">
                <h2>Add Timeline Item</h2>
                <form data-on:submit__prevent="@post('/cmd/items', {contentType: 'form'}); $_showAddItem = false; this.reset()">
                    <div class="form-group">
                        <label>Title *</label>
                        <input type="text" name="title" required placeholder="e.g., iPhone 15 Pro">
                    </div>
                    <div class="form-group">
                        <label>Group *</label>
                        <select name="groupId" required>
                            <?php foreach ($groups as $group) { ?>
                            <option value="<?php echo $group->id; ?>"><?php echo htmlspecialchars($group->icon . ' ' . $group->name); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Start Date *</label>
                            <input type="month" name="startDate" required>
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="month" name="endDate" placeholder="Leave empty for ongoing">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Color</label>
                        <input type="color" name="color" value="#3498db">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" placeholder="Optional description..."></textarea>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn" data-on:click="$_showAddItem = false">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Item</button>
                    </div>
                </form>
            </div>
        </dialog>

        <!-- Add Group Modal -->
        <dialog class="modal" data-class:open="$_showAddGroup">
            <div class="modal-content">
                <h2>Add Group</h2>
                <form data-on:submit__prevent="@post('/cmd/groups'); $_showAddGroup = false; $_newGroup = {name: '', icon: '📁', color: '#3498db'}">
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" data-bind:newGroup.name required placeholder="e.g., Gaming Consoles">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Icon</label>
                            <input type="text" data-bind:newGroup.icon placeholder="📁" maxlength="4">
                        </div>
                        <div class="form-group">
                            <label>Color</label>
                            <input type="color" data-bind:newGroup.color value="#3498db">
                        </div>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn" data-on:click="$_showAddGroup = false">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Group</button>
                    </div>
                </form>
            </div>
        </dialog>

        <!-- Debug Panel (hidden by default) -->
        <details class="debug-panel">
            <summary>Debug Signals</summary>
            <pre data-json-signals></pre>
        </details>
    </div>
</body>
</html>
