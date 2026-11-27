<?php
/**
 * Prints the availability grid of a course as text, for checking the counting without the UI.
 *
 * Usage (from the Stud.IP root):
 *   php public/plugins_packages/elan-ev/AvailSchedule/dev/dump-plan.php <course_id|AVS-number> [semester_id] [--single]
 */

$studipRoot = getcwd();
require $studipRoot . '/cli/studip_cli_env.inc.php';
require $studipRoot . '/composer/autoload.php';
require __DIR__ . '/../lib/SlotGrid.php';
require __DIR__ . '/../lib/AvailabilityCounter.php';

use ElanEv\AvailSchedule\AvailabilityCounter;

$args = array_values(array_filter(array_slice($argv, 1), fn($a) => $a[0] !== '-'));
$course = Course::find($args[0] ?? '') ?? Course::findOneBySQL('VeranstaltungsNummer = ?', [$args[0] ?? 'AVS-000']);
$semester = isset($args[1]) ? Semester::find($args[1]) : (Semester::findCurrent() ?? Semester::findNext());
if (!$course || !$semester) {
    fwrite(STDERR, "Course or semester not found.\n");
    exit(1);
}

$counter = new AvailabilityCounter($course, $semester, in_array('--single', $argv));
printf("%s, %s, %d participants\n", $course->name, $semester->name, $counter->getParticipantCount());
if (!$counter->hasEnoughParticipants()) {
    echo "Not enough participants.\n";
    exit(0);
}

$grid = $counter->buildGrid();
$days = ['', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
echo "       " . implode('', array_map(fn($d) => str_pad($days[$d], 5, ' ', STR_PAD_LEFT), $grid->weekdays())) . "\n";
foreach ($grid->hours() as $hour) {
    printf('%02d:00  ', $hour);
    foreach ($grid->weekdays() as $wd) {
        printf('%5s', $grid->count($wd, $hour) ?: '.');
    }
    echo "\n";
}
printf("max %d\n\n", $grid->maxCount());

$courses = $counter->getCourses();
foreach ($grid->weekdays() as $wd) {
    foreach ($grid->hours() as $hour) {
        if ($n = $grid->count($wd, $hour)) {
            printf("%s %02d:00 (%d):", $days[$wd], $hour, $n);
            foreach ($grid->courseCounts($wd, $hour) as $id => $c) {
                printf(' %s%s=%d', $courses[$id]['number'] ?: $courses[$id]['name'], $courses[$id]['biweekly'] ? '*' : '', $c);
            }
            echo "\n";
        }
    }
}
