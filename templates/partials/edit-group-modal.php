<?php

use App\Domain\Model\TimelineGroup;

/**
 * @var TimelineGroup $group
 */
$itemCount = count($group->items);
$question = 'Delete the group “' . $group->name . '”'
    . ($itemCount > 0 ? ' and its ' . $itemCount . ($itemCount === 1 ? ' item' : ' items') : '') . '?';
$confirm = htmlspecialchars(json_encode($question, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT));
$colorId = 'edit-group-color';
$colorValue = $group->color;
?>
<dialog id="edit-group-modal" class="modal" aria-labelledby="edit-group-title"
        data-init="timeline.openDialog(el)"
        data-on:close="timeline.closeDialog(el)"
        data-on:click="evt.target === el && el.close()">
    <form class="modal-content" data-on:submit__prevent="timeline.submitForm(el, 'PUT', '/cmd/groups/<?php echo $group->id; ?>')">
        <header class="modal-header">
            <h2 id="edit-group-title">Edit group</h2>
            <button type="button" class="icon-btn" aria-label="Close" data-on:click="el.closest('dialog').close()">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </header>
        <div class="modal-body">
            <p class="form-error" role="alert" hidden></p>
            <div class="field-row">
                <div class="field field-icon">
                    <label for="edit-group-icon">Icon</label>
                    <input id="edit-group-icon" type="text" name="icon" value="<?php echo htmlspecialchars($group->icon); ?>" maxlength="16">
                </div>
                <div class="field">
                    <label for="edit-group-name">Name</label>
                    <input id="edit-group-name" type="text" name="name" value="<?php echo htmlspecialchars($group->name); ?>" required maxlength="100" pattern=".*\S.*" autofocus>
                </div>
            </div>
            <?php include __DIR__ . '/color-field.php'; ?>
            <div class="field">
                <span class="field-label" id="edit-group-position">Position</span>
                <div class="btn-row" role="group" aria-labelledby="edit-group-position">
                    <button type="button" class="btn" data-on:click="timeline.moveGroup(<?php echo $group->id; ?>, -1)">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M6 11l6-6 6 6"/></svg> Move up
                    </button>
                    <button type="button" class="btn" data-on:click="timeline.moveGroup(<?php echo $group->id; ?>, 1)">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M6 13l6 6 6-6"/></svg> Move down
                    </button>
                </div>
            </div>
        </div>
        <footer class="modal-actions">
            <button type="button" class="btn btn-danger"
                    data-on:click="timeline.confirmDelete(el, '/cmd/groups/<?php echo $group->id; ?>', <?php echo $confirm; ?>)">
                Delete
            </button>
            <button type="button" class="btn" data-on:click="el.closest('dialog').close()">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
        </footer>
    </form>
</dialog>
