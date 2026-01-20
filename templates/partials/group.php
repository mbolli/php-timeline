<?php
/**
 * @var TimelineGroup $group
 * @var int $startYear
 */

use App\Domain\Model\TimelineGroup;

// Calculate item positions and assign lanes to avoid overlaps
$itemPositions = [];
$lanes = []; // Each lane tracks when it becomes free (end month position)

foreach ($group->items as $item) {
    $itemStartYear = (int) mb_substr($item->startDate, 0, 4);
    $itemStartMonth = (int) mb_substr($item->startDate, 5, 2);
    $itemEndYear = $item->endDate ? (int) mb_substr($item->endDate, 0, 4) : (int) date('Y');
    $itemEndMonth = $item->endDate ? (int) mb_substr($item->endDate, 5, 2) : (int) date('m');

    $monthWidth = 20;
    $startMonths = (($itemStartYear - $startYear) * 12) + ($itemStartMonth - 1);
    $endMonths = (($itemEndYear - $startYear) * 12) + ($itemEndMonth - 1);
    $durationMonths = max(1, $endMonths - $startMonths + 1);

    // Find first available lane
    $lane = 0;
    foreach ($lanes as $laneIndex => $laneEndMonth) {
        if ($startMonths > $laneEndMonth) {
            $lane = $laneIndex;
            break;
        }
        $lane = $laneIndex + 1;
    }

    // Update lane end position
    $lanes[$lane] = $endMonths;

    $itemPositions[$item->id] = [
        'left' => $startMonths * $monthWidth,
        'width' => $durationMonths * $monthWidth,
        'lane' => $lane,
    ];
}

$laneCount = empty($lanes) ? 1 : count($lanes);
$itemHeight = 32; // Height of each item in pixels
$trackPadding = 8; // Top/bottom padding
$trackHeight = ($laneCount * ($itemHeight + 4)) + ($trackPadding * 2);
?>

<div class="track" data-group-id="<?php echo $group->id; ?>">
    <div class="track-label" style="background-color: <?php echo htmlspecialchars($group->color); ?>20; border-left: 4px solid <?php echo htmlspecialchars($group->color); ?>; min-height: <?php echo $trackHeight; ?>px;">
        <span class="track-icon"><?php echo htmlspecialchars($group->icon); ?></span>
        <span class="track-name"><?php echo htmlspecialchars($group->name); ?></span>
        <span class="track-count">(<?php echo count($group->items); ?>)</span>
    </div>
    <div class="track-items" style="min-height: <?php echo $trackHeight; ?>px;">
        <?php if (empty($group->items)) { ?>
        <div class="track-empty">No items in this group</div>
        <?php } else { ?>
            <?php foreach ($group->items as $item) {
                $pos = $itemPositions[$item->id];
                $itemColor = $item->color ?? $group->color;
                $top = $trackPadding + ($pos['lane'] * ($itemHeight + 4));
                ?>
            <div class="timeline-item <?php echo $item->isOngoing() ? 'ongoing' : ''; ?>"
                 data-item-id="<?php echo $item->id; ?>"
                 style="--item-left: <?php echo $pos['left']; ?>; --item-width: <?php echo $pos['width']; ?>; top: <?php echo $top; ?>px; height: <?php echo $itemHeight; ?>px; background-color: <?php echo htmlspecialchars($itemColor); ?>;"
                 data-on:click="@get('/query/items/<?php echo $item->id; ?>')"
                 title="<?php echo htmlspecialchars($item->title); ?>&#10;<?php echo $item->startDate; ?> → <?php echo $item->endDate ?? 'ongoing'; ?>">
                <span class="item-title"><?php echo htmlspecialchars($item->title); ?></span>
                <?php if ($item->isOngoing()) { ?>
                <span class="ongoing-indicator">→</span>
                <?php } ?>
            </div>
            <?php } ?>
        <?php } ?>
    </div>
</div>
