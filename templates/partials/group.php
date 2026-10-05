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

<div class="track"
     data-group-id="<?php echo $group->id; ?>"
     data-on:dragover="timeline.dragOverTrack(evt)"
     data-on:dragleave="timeline.dragLeaveTrack(evt)"
     data-on:drop="timeline.dropOnTrack(evt, <?php echo $group->id; ?>)">
    <div class="track-label"
         style="--group-color: <?php echo htmlspecialchars($group->color); ?>; min-height: <?php echo $trackHeight; ?>px;">
        <span class="drag-handle"
              title="Drag to reorder"
              draggable="true"
              data-on:mousedown__stop="evt.stopPropagation()"
              data-on:dragstart="timeline.startDragGroup(evt, <?php echo $group->id; ?>)"
              data-on:dragend="timeline.endDragGroup(evt)">
            <svg width="12" height="16" viewBox="0 0 12 16" fill="currentColor">
                <circle cx="3" cy="3" r="1.5"/><circle cx="9" cy="3" r="1.5"/>
                <circle cx="3" cy="8" r="1.5"/><circle cx="9" cy="8" r="1.5"/>
                <circle cx="3" cy="13" r="1.5"/><circle cx="9" cy="13" r="1.5"/>
            </svg>
        </span>
        <button type="button" class="track-info" title="Edit <?php echo htmlspecialchars($group->name); ?>"
                data-on:click="@get('/query/groups/<?php echo $group->id; ?>')">
            <span class="track-icon" aria-hidden="true"><?php echo htmlspecialchars($group->icon); ?></span>
            <span class="track-name"><?php echo htmlspecialchars($group->name); ?></span>
            <span class="track-count" aria-label="<?php echo count($group->items); ?> items"><?php echo count($group->items); ?></span>
        </button>
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
                 data-group-id="<?php echo $item->groupId; ?>"
                 data-start-date="<?php echo $item->startDate; ?>"
                 data-end-date="<?php echo $item->endDate ?? ''; ?>"
                 style="--item-left: <?php echo $pos['left']; ?>; --item-width: <?php echo $pos['width']; ?>; --item-color: <?php echo htmlspecialchars($itemColor); ?>; top: <?php echo $top; ?>px; height: <?php echo $itemHeight; ?>px;"
                 role="button" tabindex="0"
                 data-on:click="!timeline.wasResizing() && @get('/query/items/<?php echo $item->id; ?>')"
                 data-on:keydown="(evt.key === 'Enter' || evt.key === ' ') && (evt.preventDefault(), @get('/query/items/<?php echo $item->id; ?>'))"
                 title="<?php echo htmlspecialchars($item->title); ?>&#10;<?php echo $item->startDate; ?> → <?php echo $item->endDate ?? 'ongoing'; ?>">
                <div class="resize-handle resize-handle-start" data-handle="start" data-on:click__prevent__stop="evt.stopPropagation()"></div>
                <span class="item-title"><?php echo htmlspecialchars($item->title); ?></span>
                <?php if ($item->isOngoing()) { ?>
                <span class="ongoing-indicator" aria-label="ongoing">→</span>
                <?php } ?>
                <div class="resize-handle resize-handle-end" data-handle="end" data-on:click__prevent__stop="evt.stopPropagation()"></div>
            </div>
            <?php } ?>
        <?php } ?>
    </div>
</div>
