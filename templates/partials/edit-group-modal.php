<?php
/**
 * @var \App\Domain\Model\TimelineGroup $group
 */
?>
<dialog id="edit-group-modal" class="modal open">
    <div class="modal-content">
        <h2>Edit Group</h2>
        <form data-on:submit__prevent="@put('/cmd/groups/<?php echo $group->id; ?>', {contentType: 'form'}); document.getElementById('edit-group-modal').remove()">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($group->name); ?>" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Icon</label>
                    <input type="text" name="icon" value="<?php echo htmlspecialchars($group->icon); ?>" maxlength="4">
                </div>
                <div class="form-group">
                    <label>Color</label>
                    <input type="color" name="color" value="<?php echo $group->color; ?>">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-danger"
                        data-on:click="@delete('/cmd/groups/<?php echo $group->id; ?>'); document.getElementById('edit-group-modal').remove()">
                    Delete
                </button>
                <button type="button" class="btn" data-on:click="document.getElementById('edit-group-modal').remove()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</dialog>
