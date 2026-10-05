<?php

use App\Domain\Model\TimelineItem;

/**
 * @var TimelineItem $item
 * @var array $groups
 */
?>
<dialog id="edit-item-modal" class="modal open">
    <div class="modal-content">
        <h2>Edit Item</h2>
        <form data-on:submit__prevent="@put('/cmd/items/<?php echo $item->id; ?>', {contentType: 'form'}); document.getElementById('edit-item-modal').remove()">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($item->title); ?>" required>
            </div>
            <div class="form-group">
                <label>Group *</label>
                <select name="groupId" required>
                    <?php foreach ($groups as $group) { ?>
                    <option value="<?php echo $group->id; ?>" <?php echo $group->id === $item->groupId ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($group->icon . ' ' . $group->name); ?>
                    </option>
                    <?php } ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Start Date *</label>
                    <input type="month" name="startDate" value="<?php echo $item->startDate; ?>" required>
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="month" name="endDate" value="<?php echo $item->endDate ?? ''; ?>">
                </div>
            </div>
            <div class="form-group">
                <label>Color</label>
                <input type="color" name="color" value="<?php echo $item->color ?? '#3498db'; ?>">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description"><?php echo htmlspecialchars($item->description ?? ''); ?></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-danger"
                        data-on:click="@delete('/cmd/items/<?php echo $item->id; ?>'); setTimeout(() => document.getElementById('edit-item-modal')?.remove(), 100)">
                    Delete
                </button>
                <button type="button" class="btn" data-on:click="document.getElementById('edit-item-modal').remove()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</dialog>
