<?php
/**
 * @var TimelineGroup $group
 * @var int $startYear
 * @var array $monthNames
 */

use App\Domain\Model\TimelineGroup;

// Place the items and give overlapping ones their own lane
$itemPositions = [];
$lanes = []; // per lane: the last item's id, start month and end month
$monthWidth = 20;

foreach ($group->items as $item) {
    $itemStartYear = (int) mb_substr($item->startDate, 0, 4);
    $itemStartMonth = (int) mb_substr($item->startDate, 5, 2);
    $itemEndYear = $item->endDate ? (int) mb_substr($item->endDate, 0, 4) : (int) date('Y');
    $itemEndMonth = $item->endDate ? (int) mb_substr($item->endDate, 5, 2) : (int) date('m');

    $startMonths = (($itemStartYear - $startYear) * 12) + ($itemStartMonth - 1);
    $endMonths = (($itemEndYear - $startYear) * 12) + ($itemEndMonth - 1);
    $durationMonths = max(1, $endMonths - $startMonths + 1);

    $lane = count($lanes);
    foreach ($lanes as $laneIndex => $last) {
        // An item may follow one that ends in the month it starts in (old phone out, new phone in);
        // the earlier bar is then drawn one month shorter so the two do not overlap
        $handsOver = $startMonths === $last['end'] && $last['end'] > $last['start'];
        if ($startMonths > $last['end'] || $handsOver) {
            $lane = $laneIndex;
            if ($handsOver) {
                $itemPositions[$last['id']]['trim'] = 1;
            }

            break;
        }
    }

    $lanes[$lane] = ['id' => $item->id, 'start' => $startMonths, 'end' => $endMonths];

    $itemPositions[$item->id] = [
        'left' => $startMonths * $monthWidth,
        'width' => $durationMonths * $monthWidth,
        'lane' => $lane,
        'trim' => 0,
    ];
}

$laneCount = max(1, count($lanes));
$itemCount = count($group->items);
$groupName = htmlspecialchars($group->name);

$formatMonth = static fn (string $date): string => $monthNames[(int) mb_substr($date, 5, 2) - 1] . ' ' . mb_substr($date, 0, 4);
?>

<div class="track" id="track-<?php echo $group->id; ?>"
     data-group-id="<?php echo $group->id; ?>"
     style="--group-color: <?php echo htmlspecialchars($group->color); ?>; --lanes: <?php echo $laneCount; ?>;"
     data-on:dragover="timeline.dragOverTrack(evt)"
     data-on:dragleave="timeline.dragLeaveTrack(evt)"
     data-on:drop="timeline.dropOnTrack(evt, <?php echo $group->id; ?>)">
    <div class="track-label">
        <span class="drag-handle"
              title="Drag to reorder"
              draggable="true"
              data-on:mousedown__stop="evt.stopPropagation()"
              data-on:dragstart="timeline.startDragGroup(evt, <?php echo $group->id; ?>)"
              data-on:dragend="timeline.endDragGroup(evt)">
            <svg width="10" height="16" viewBox="0 0 10 16" fill="currentColor" aria-hidden="true">
                <circle cx="2" cy="3" r="1.4"/><circle cx="8" cy="3" r="1.4"/>
                <circle cx="2" cy="8" r="1.4"/><circle cx="8" cy="8" r="1.4"/>
                <circle cx="2" cy="13" r="1.4"/><circle cx="8" cy="13" r="1.4"/>
            </svg>
        </span>
        <button type="button" class="track-info" title="Edit <?php echo $groupName; ?>"
                aria-label="Edit group <?php echo $groupName; ?>, <?php echo $itemCount; ?> <?php echo $itemCount === 1 ? 'item' : 'items'; ?>"
                data-on:click="@get('/query/groups/<?php echo $group->id; ?>')">
            <span class="track-icon" aria-hidden="true"><?php echo htmlspecialchars($group->icon); ?></span>
            <span class="track-name"><?php echo $groupName; ?></span>
            <span class="track-count" aria-hidden="true"><?php echo $itemCount; ?></span>
        </button>
    </div>
    <div class="track-lanes">
        <span class="track-caption" aria-hidden="true"><?php echo $groupName; ?></span>
        <?php if (empty($group->items)) { ?>
        <div class="track-empty">No items yet</div>
        <?php } else { ?>
            <?php foreach ($group->items as $item) {
                $pos = $itemPositions[$item->id];
                $itemColor = $item->color ?? $group->color;
                $range = $formatMonth($item->startDate) . ' → ' . ($item->endDate ? $formatMonth($item->endDate) : 'now');
                $spoken = $item->endDate
                    ? $formatMonth($item->startDate) . ' to ' . $formatMonth($item->endDate)
                    : 'since ' . $formatMonth($item->startDate) . ', ongoing';
                ?>
            <div class="timeline-item<?php echo $item->isOngoing() ? ' ongoing' : ''; ?>" id="item-<?php echo $item->id; ?>"
                 data-item-id="<?php echo $item->id; ?>"
                 data-group-id="<?php echo $item->groupId; ?>"
                 data-start-date="<?php echo $item->startDate; ?>"
                 data-end-date="<?php echo $item->endDate ?? ''; ?>"
                 style="--item-left: <?php echo $pos['left']; ?>; --item-width: <?php echo $pos['width']; ?>; --item-color: <?php echo htmlspecialchars($itemColor); ?>; --lane: <?php echo $pos['lane']; ?>;<?php echo $pos['trim'] ? ' --item-trim: 1;' : ''; ?>"
                 role="button" tabindex="-1"
                 aria-label="<?php echo htmlspecialchars($item->title) . ', ' . $groupName . ', ' . $spoken; ?>"
                 data-on:click="!timeline.wasResizing() && @get('/query/items/<?php echo $item->id; ?>')"
                 data-on:keydown="(evt.key === 'Enter' || evt.key === ' ') && (evt.preventDefault(), @get('/query/items/<?php echo $item->id; ?>'))"
                 title="<?php echo htmlspecialchars($item->title); ?>&#10;<?php echo $item->startDate; ?> → <?php echo $item->endDate ?? 'ongoing'; ?><?php echo $item->description ? '&#10;' . htmlspecialchars($item->description) : ''; ?>">
                <div class="resize-handle resize-handle-start" data-handle="start" data-on:click__prevent__stop="evt.stopPropagation()"></div>
                <span class="item-body">
                    <span class="item-title"><?php echo htmlspecialchars($item->title); ?></span>
                    <span class="item-dates"><?php echo $range; ?></span>
                    <?php if ($item->description) { ?>
                    <span class="item-description"><?php echo htmlspecialchars($item->description); ?></span>
                    <?php } ?>
                </span>
                <?php if ($item->isOngoing()) { ?>
                <span class="ongoing-indicator" aria-hidden="true">→</span>
                <?php } ?>
                <div class="resize-handle resize-handle-end" data-handle="end" data-on:click__prevent__stop="evt.stopPropagation()"></div>
            </div>
            <?php } ?>
        <?php } ?>
    </div>
</div>
