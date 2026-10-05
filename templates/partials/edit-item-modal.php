<?php

use App\Domain\Model\TimelineItem;

/**
 * @var TimelineItem $item
 * @var array $groups
 */
$confirm = htmlspecialchars(json_encode('Delete “' . $item->title . '”?', JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT));
?>
<dialog id="edit-item-modal" class="modal" aria-labelledby="edit-item-title"
        data-init="el.showModal()"
        data-on:close="el.remove()"
        data-on:click="evt.target === el && el.close()">
    <form class="modal-content" data-on:submit__prevent="@put('/cmd/items/<?php echo $item->id; ?>', {contentType: 'form'}); el.closest('dialog').close()">
        <h2 id="edit-item-title">Edit item</h2>
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
        <div class="field">
            <label for="edit-item-color">Color</label>
            <input id="edit-item-color" type="color" name="color" value="<?php echo htmlspecialchars($item->color ?? '#3498db'); ?>">
        </div>
        <div class="field">
            <label for="edit-item-description">Description</label>
            <textarea id="edit-item-description" name="description" maxlength="2000"><?php echo htmlspecialchars($item->description ?? ''); ?></textarea>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-danger"
                    data-on:click="if (confirm(<?php echo $confirm; ?>)) { @delete('/cmd/items/<?php echo $item->id; ?>'); el.closest('dialog').close() }">
                Delete
            </button>
            <button type="button" class="btn" data-on:click="el.closest('dialog').close()">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</dialog>
