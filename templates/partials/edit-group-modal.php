<?php

use App\Domain\Model\TimelineGroup;

/**
 * @var TimelineGroup $group
 */
$itemCount = count($group->items);
$question = 'Delete the group “' . $group->name . '”'
    . ($itemCount > 0 ? ' and its ' . $itemCount . ($itemCount === 1 ? ' item' : ' items') : '') . '?';
$confirm = htmlspecialchars(json_encode($question, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT));
?>
<dialog id="edit-group-modal" class="modal" aria-labelledby="edit-group-title"
        data-init="el.showModal()"
        data-on:close="el.remove()"
        data-on:click="evt.target === el && el.close()">
    <form class="modal-content" data-on:submit__prevent="@put('/cmd/groups/<?php echo $group->id; ?>', {contentType: 'form'}); el.closest('dialog').close()">
        <h2 id="edit-group-title">Edit group</h2>
        <div class="field">
            <label for="edit-group-name">Name</label>
            <input id="edit-group-name" type="text" name="name" value="<?php echo htmlspecialchars($group->name); ?>" required maxlength="100" pattern=".*\S.*" autofocus>
        </div>
        <div class="field-row">
            <div class="field">
                <label for="edit-group-icon">Icon</label>
                <input id="edit-group-icon" type="text" name="icon" value="<?php echo htmlspecialchars($group->icon); ?>" maxlength="16">
            </div>
            <div class="field">
                <label for="edit-group-color">Color</label>
                <input id="edit-group-color" type="color" name="color" value="<?php echo htmlspecialchars($group->color); ?>">
            </div>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn btn-danger"
                    data-on:click="if (confirm(<?php echo $confirm; ?>)) { @delete('/cmd/groups/<?php echo $group->id; ?>'); el.closest('dialog').close() }">
                Delete
            </button>
            <button type="button" class="btn" data-on:click="el.closest('dialog').close()">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
        </div>
    </form>
</dialog>
