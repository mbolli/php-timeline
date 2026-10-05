<?php
/**
 * The add-item form's group list. Also sent with every live update, so new groups show up without a reload.
 *
 * @var array $groups TimelineGroup[]
 */
?>
<select id="add-item-group" name="groupId" required>
    <?php foreach ($groups as $group) { ?>
    <option value="<?php echo $group->id; ?>"><?php echo htmlspecialchars($group->icon . ' ' . $group->name); ?></option>
    <?php } ?>
</select>
