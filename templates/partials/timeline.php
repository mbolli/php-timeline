<?php
/**
 * @var array $groups TimelineGroup[]
 * @var array $bounds ['min' => string, 'max' => string]
 */

use App\Domain\Model\TimelineGroup;

// Helper function - only define once
if (!function_exists('calculateTimelinePosition')) {
    function calculateTimelinePosition(int $year, int $month, int $startYear): float {
        $monthWidth = 20; // pixels per month at zoom 1
        $monthsFromStart = (($year - $startYear) * 12) + ($month - 1);

        return $monthsFromStart * $monthWidth;
    }
}

// Calculate timeline range
$minDate = $bounds['min'] ?? date('Y-m');
$maxDate = $bounds['max'] ?? date('Y-m');

// Extend range a bit for padding
$startYear = (int) mb_substr($minDate, 0, 4) - 1;
$endYear = (int) mb_substr($maxDate, 0, 4) + 1;
$currentYear = (int) date('Y');
$currentMonth = (int) date('m');

// Generate year markers
$years = range($startYear, max($endYear, $currentYear + 1));
?>

<div id="timeline-content" class="timeline-content">
    <!-- Year Header -->
    <div class="timeline-header">
        <div class="track-label-spacer"></div>
        <div class="timeline-years">
            <?php foreach ($years as $year) { ?>
            <div class="year-marker" data-year="<?php echo $year; ?>">
                <span class="year-label"><?php echo $year; ?></span>
                <div class="month-markers">
                    <?php for ($m = 1; $m <= 12; ++$m) { ?>
                    <div class="month-marker <?php echo ($year === $currentYear && $m === $currentMonth) ? 'current' : ''; ?>"
                         data-month="<?php echo $m; ?>"
                         title="<?php echo $year; ?>-<?php echo mb_str_pad((string) $m, 2, '0', STR_PAD_LEFT); ?>">
                    </div>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>

    <!-- Tracks/Groups -->
    <div class="timeline-tracks">
        <?php if (empty($groups)) { ?>
        <div class="empty-state">
            <p>No groups yet. Add a group to get started!</p>
        </div>
        <?php } else { ?>
            <?php foreach ($groups as $group) { ?>
            <?php include __DIR__ . '/group.php'; ?>
            <?php } ?>
        <?php } ?>
    </div>

    <!-- Now Line -->
    <div class="now-line"
         style="--now-position: <?php echo calculateTimelinePosition($currentYear, $currentMonth, $startYear); ?>;"
         title="Today: <?php echo date('F Y'); ?>">
        <span class="now-label">Now</span>
    </div>
</div>
