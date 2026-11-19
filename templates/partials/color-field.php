<?php
/**
 * A color input with preset swatches that fill it. Items can take their group's color instead:
 * the input is then disabled, so the form sends no color.
 *
 * @var string    $colorId
 * @var string    $colorValue
 * @var null|bool $colorInherit null for groups, else whether "same as the group" starts ticked
 */
$presets = [
    '#3498db' => 'Blue',
    '#1abc9c' => 'Teal',
    '#27ae60' => 'Green',
    '#f39c12' => 'Orange',
    '#e74c3c' => 'Red',
    '#e91e63' => 'Pink',
    '#9b59b6' => 'Purple',
    '#795548' => 'Brown',
    '#34495e' => 'Slate',
];
$colorInherit ??= null;
?>
<div class="field">
    <label for="<?php echo $colorId; ?>">Color</label>
    <div class="color-picker">
        <?php if ($colorInherit !== null) { ?>
        <label class="check">
            <input type="checkbox" class="color-inherit" <?php echo $colorInherit ? 'checked' : ''; ?>
                   data-on:change="timeline.syncColorFields(el.form)">
            Same as the group
        </label>
        <?php } ?>
        <div class="color-choice">
            <input id="<?php echo $colorId; ?>" type="color" name="color" value="<?php echo htmlspecialchars($colorValue); ?>"<?php echo $colorInherit ? ' disabled' : ''; ?>>
            <div class="swatches" role="group" aria-label="Color presets">
                <?php foreach ($presets as $hex => $label) { ?>
                <button type="button" class="swatch" style="--swatch: <?php echo $hex; ?>;"
                        aria-label="<?php echo $label; ?>" title="<?php echo $label; ?>"
                        data-on:click="timeline.pickColor(el, '<?php echo $hex; ?>')"></button>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php unset($colorInherit); ?>
