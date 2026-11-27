<?php
/**
 * Seeds test data for developing the AvailSchedule plugin.
 *
 * Usage (from the Stud.IP root):
 *   php public/plugins_packages/elan-ev/AvailSchedule/dev/seed-testdata.php [--clean]
 *
 * Creates courses with number prefix "AVS-" in the semester that contains the
 * current date (or the next one). Re-running removes previously seeded data first.
 * Not part of the plugin package.
 */

$studipRoot = getcwd();
require $studipRoot . '/cli/studip_cli_env.inc.php';
require $studipRoot . '/composer/autoload.php';

const PREFIX = 'AVS-';

$semester = Semester::findCurrent() ?? Semester::findNext();
if (!$semester) {
    fwrite(STDERR, "Kein aktuelles Semester gefunden.\n");
    exit(1);
}

// --- cleanup ---------------------------------------------------------------
$old = Course::findBySQL('VeranstaltungsNummer LIKE ?', [PREFIX . '%']);
foreach ($old as $course) {
    $course->delete();
}
echo 'Removed ' . count($old) . " seeded courses.\n";
if (in_array('--clean', $argv)) {
    exit(0);
}

// --- prerequisites ---------------------------------------------------------
$dozent = User::findOneByUsername('test_dozent');
$students = User::findBySQL("perms = 'autor' ORDER BY username");
$institute = Institute::findOneBySQL('Institut_id = fakultaets_id AND 1 ORDER BY Name')
    ?? Institute::findOneBySQL('1');
$semType = array_key_first($GLOBALS['SEM_TYPE']);

if (!$dozent || count($students) < 6 || !$institute) {
    fwrite(STDERR, "Need user test_dozent, >= 6 autor users and an institute.\n");
    exit(1);
}

mt_srand(42); // reproducible

function createCourse(string $number, string $name, Semester $semester, Institute $institute, $semType, User $dozent): Course
{
    $course = new Course();
    $course->setId($course->getNewId());
    $course->veranstaltungsnummer = $number;
    $course->name = $name;
    $course->status = $semType;
    $course->institut_id = $institute->id;
    $course->visible = 1;
    $course->start_semester = $semester;
    $course->store();
    $course->addMember($dozent, 'dozent', false, false);
    return $course;
}

function addCycle(Course $course, int $weekday, string $start, string $end): void
{
    $cycle = new SeminarCycleDate();
    $cycle->seminar_id = $course->id;
    $cycle->weekday = $weekday;
    $cycle->start_time = $start . ':00';
    $cycle->end_time = $end . ':00';
    $cycle->cycle = 0;
    $cycle->week_offset = 0;
    $cycle->end_offset = null;
    $cycle->store();
}

function addSingleDate(Course $course, int $start, int $end): void
{
    $date = new CourseDate();
    $date->range_id = $course->id;
    $date->autor_id = $GLOBALS['user']->id;
    $date->date = $start;
    $date->end_time = $end;
    $date->date_typ = 1;
    $date->store();
}

// --- target course: all students -------------------------------------------
$target = createCourse(PREFIX . '000', 'AVS Zielveranstaltung (alle Studierenden)', $semester, $institute, $semType, $dozent);
addCycle($target, 3, '14:15', '15:45');
foreach ($students as $s) {
    $target->addMember($s, 'autor', false, false);
}

// --- other courses with overlapping slots ----------------------------------
$others = [
    // name, [[weekday(1=Mo..7=So), start, end], ...], share of students
    ['AVS Mathematik I',          [[1, '08:15', '09:45'], [3, '08:15', '09:45']], 0.8],
    ['AVS Programmierung',        [[1, '10:15', '11:45'], [4, '12:15', '13:45']], 0.6],
    ['AVS Statistik',             [[2, '08:15', '09:45']],                         0.5],
    ['AVS Soziologie Grundkurs',  [[2, '10:15', '11:45'], [5, '10:15', '11:45']], 0.4],
    ['AVS Englisch B2',           [[1, '10:15', '11:45']],                         0.3],
    ['AVS Seminar Methoden',      [[4, '14:15', '17:45']],                         0.35],
    ['AVS Abendkolloquium',       [[3, '18:15', '19:45']],                         0.2],
];

$n = 1;
foreach ($others as [$name, $cycles, $share]) {
    $course = createCourse(sprintf('%s%03d', PREFIX, $n++), $name, $semester, $institute, $semType, $dozent);
    foreach ($cycles as [$wd, $start, $end]) {
        addCycle($course, $wd, $start, $end);
    }
    foreach ($students as $s) {
        if (mt_rand() / mt_getrandmax() < $share) {
            $course->addMember($s, 'autor', false, false);
        }
    }
}

// --- course with only single / block dates ----------------------------------
$block = createCourse(sprintf('%s%03d', PREFIX, $n++), 'AVS Blockseminar (nur Einzeltermine)', $semester, $institute, $semType, $dozent);
$day = strtotime('next friday', $semester->vorles_beginn);
for ($i = 0; $i < 3; $i++) {
    $d = strtotime("+{$i} day", $day); // Fr, Sa, So
    addSingleDate($block, strtotime('09:00', $d), strtotime('16:00', $d));
}
addSingleDate($block, strtotime('+3 weeks 10:00', $day), strtotime('+3 weeks 12:00', $day));
foreach ($students as $s) {
    if (mt_rand() / mt_getrandmax() < 0.5) {
        $block->addMember($s, 'autor', false, false);
    }
}

printf(
    "Seeded %d courses in %s. Target course: %s (cid=%s)\n",
    $n,
    $semester->name,
    $target->name,
    $target->id
);
