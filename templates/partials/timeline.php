<?php
/**
 * @var array $groups TimelineGroup[]
 * @var array $bounds ['min' => string, 'max' => string]
 */

use App\Domain\Model\TimelineGroup;

$minDate = $bounds['min'] ?? date('Y-m');
$maxDate = $bounds['max'] ?? date('Y-m');

// One year of padding on both sides
$startYear = (int) mb_substr($minDate, 0, 4) - 1;
$endYear = (int) mb_substr($maxDate, 0, 4) + 1;
$currentYear = (int) date('Y');
$currentMonth = (int) date('m');

$years = range($startYear, max($endYear, $currentYear + 1));
$monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// Pixels per month at zoom 1
$monthWidth = 20;
$nowPosition = ((($currentYear - $startYear) * 12) + ($currentMonth - 1)) * $monthWidth;
$groupCount = count($groups);
?>

<div id="timeline-content" class="timeline-content" style="--now-position: <?php echo $nowPosition; ?>; --major-offset: <?php echo (5 - $startYear % 5) % 5; ?>;">
    <div class="ruler">
        <div class="ruler-corner">
            <span class="ruler-corner-title">Groups</span>
            <span class="ruler-corner-count" aria-label="<?php echo $groupCount; ?> groups"><?php echo $groupCount; ?></span>
            <button type="button" class="icon-btn" aria-label="Add group" title="Add group (G)"
                    data-on:click="$_showAddGroup = true">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            </button>
        </div>
        <div class="ruler-years">
            <?php foreach ($years as $year) { ?>
            <div class="year<?php echo $year % 5 === 0 ? ' year-major' : ''; ?><?php echo $year === $currentYear ? ' year-current' : ''; ?>" data-year="<?php echo $year; ?>">
                <span class="year-label"><?php echo $year; ?></span>
                <div class="months">
                    <?php for ($m = 1; $m <= 12; ++$m) { ?>
                    <div class="month<?php echo ($year === $currentYear && $m === $currentMonth) ? ' current' : ''; ?>"
                         data-month="<?php echo $m; ?>"
                         data-initial="<?php echo $monthNames[$m - 1][0]; ?>"
                         data-abbr="<?php echo $monthNames[$m - 1]; ?>"
                         title="<?php echo $monthNames[$m - 1] . ' ' . $year; ?>"></div>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
            <div class="now-flag" title="Today: <?php echo date('F Y'); ?>">Today</div>
        </div>
    </div>

    <div class="tracks">
        <?php if (empty($groups)) { ?>
        <div class="empty-state">
            <svg viewBox="0 0 64 40" aria-hidden="true">
                <rect x="2" y="4" width="34" height="8" rx="3"/><rect x="22" y="16" width="40" height="8" rx="3"/><rect x="8" y="28" width="26" height="8" rx="3"/>
            </svg>
            <p class="empty-state-title">Nothing on the timeline yet</p>
            <p>Groups are the rows of the timeline: phones, homes, jobs. Start with one.</p>
            <button type="button" class="btn btn-primary" data-on:click="$_showAddGroup = true">Add the first group</button>
        </div>
        <?php } else { ?>
            <?php foreach ($groups as $group) { ?>
            <?php include __DIR__ . '/group.php'; ?>
            <?php } ?>
        <?php } ?>
    </div>

    <div class="now-line" aria-hidden="true"></div>
</div>
