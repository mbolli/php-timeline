<?php

use App\Domain\Model\TimelineItem;

/**
 * @var TimelineItem $item
 * @var array $groups
 */
$confirm = htmlspecialchars(json_encode('Delete “' . $item->title . '”?', JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT));
$colorId = 'edit-item-color';
$colorValue = $item->color ?? '#3498db';
$colorInherit = $item->color === null;
?>
<dialog id="edit-item-modal" class="modal" aria-labelledby="edit-item-title"
        data-init="timeline.openDialog(el)"
        data-on:close="timeline.closeDialog(el)"
        data-on:click="evt.target === el && el.close()">
    <form class="modal-content" data-on:submit__prevent="timeline.submitForm(el, 'PUT', '/cmd/items/<?php echo $item->id; ?>')">
        <header class="modal-header">
            <h2 id="edit-item-title">Edit item</h2>
            <button type="button" class="icon-btn" aria-label="Close" data-on:click="el.closest('dialog').close()">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </header>
        <div class="modal-body">
            <p class="form-error" role="alert" hidden></p>
            <div class="field">
                <label for="edit-item-name">Title</label>
                <input id="edit-item-name" type="text" name="title" value="<?php echo htmlspecialchars($item->title); ?>" required maxlength="200" pattern=".*\S.*" autofocus>
            </div>
            <div class="field">
                <label for="edit-item-group">Group</label>
                <select id="edit-item-group" name="groupId" required>
                    <?php foreach ($groups as $group) { ?>
                    <option value="<?php echo $group->id; ?>" <?php echo $group->id === $item->groupId ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($group->icon . ' ' . $group->name); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="edit-item-start">Start</label>
                    <input id="edit-item-start" type="month" name="startDate" value="<?php echo htmlspecialchars($item->startDate); ?>" required
                           data-on:input="el.form.elements.endDate.min = el.value">
                </div>
                <div class="field">
                    <label for="edit-item-end">End <small>(empty if ongoing)</small></label>
                    <input id="edit-item-end" type="month" name="endDate" value="<?php echo htmlspecialchars($item->endDate ?? ''); ?>" min="<?php echo htmlspecialchars($item->startDate); ?>">
                </div>
            </div>
            <?php include __DIR__ . '/color-field.php'; ?>
            <div class="field">
                <label for="edit-item-description">Description</label>
                <textarea id="edit-item-description" name="description" maxlength="2000"><?php echo htmlspecialchars($item->description ?? ''); ?></textarea>
            </div>
        </div>
        <footer class="modal-actions">
            <button type="button" class="btn btn-danger"
                    data-on:click="timeline.confirmDelete(el, '/cmd/items/<?php echo $item->id; ?>', <?php echo $confirm; ?>)">
                Delete
            </button>
            <button type="button" class="btn" data-on:click="el.closest('dialog').close()">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
        </footer>
    </form>
</dialog>
