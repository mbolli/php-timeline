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
    <script type="module" src="https://cdn.jsdelivr.net/gh/starfederation/datastar@1.0.0-RC.7/bundles/datastar.js"></script>
    <script type="module" src="/js/app.js"></script>
    <link rel="stylesheet" href="/css/timeline.css">
</head>
<body>
    <div id="app"
         data-signals='{
            "_zoom": 1,
            "_panX": 0,
            "_showAddItem": false,
            "_showAddGroup": false,
            "newItem": {"groupId": 1, "title": "", "startDate": "", "endDate": "", "color": "#3498db", "description": ""},
            "newGroup": {"name": "", "icon": "📁", "color": "#3498db"}
         }'
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
        <main class="timeline-wrapper">
            <div id="timeline-container"
                 class="timeline-container"
                 data-style:transform="'translateX(' + $_panX + 'px)'">
                <?php include __DIR__ . '/partials/timeline.php'; ?>
            </div>
        </main>

        <!-- Add Item Modal -->
        <dialog class="modal" data-class:open="$_showAddItem">
            <div class="modal-content">
                <h2>Add Timeline Item</h2>
                <form data-on:submit__prevent="@post('/cmd/items'); $_showAddItem = false; $_newItem = {groupId: 1, title: '', startDate: '', endDate: '', color: '#3498db', description: ''}">
                    <div class="form-group">
                        <label>Title *</label>
                        <input type="text" data-bind="$_newItem.title" required placeholder="e.g., iPhone 15 Pro">
                    </div>
                    <div class="form-group">
                        <label>Group *</label>
                        <select data-bind="$_newItem.groupId" required>
                            <?php foreach ($groups as $group) { ?>
                            <option value="<?php echo $group->id; ?>"><?php echo htmlspecialchars($group->icon . ' ' . $group->name); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Start Date *</label>
                            <input type="month" data-bind="$_newItem.startDate" required>
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="month" data-bind="$_newItem.endDate" placeholder="Leave empty for ongoing">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Color</label>
                        <input type="color" data-bind="$_newItem.color" value="#3498db">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea data-bind="$_newItem.description" placeholder="Optional description..."></textarea>
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
