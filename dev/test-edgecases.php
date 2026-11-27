<?php
/**
 * Integration check for edge cases of AvailabilityCounter against a real Stud.IP database.
 *
 * Creates the courses AVS-100 … AVS-1xx (removing earlier ones first), computes the plans with the
 * real AvailabilityCounter and compares them with expected values. Prints PASS/FAIL per check and
 * exits with 1 if anything fails. The data is left in place for manual checks in the browser.
 *
 * Usage (from the Stud.IP root):
 *   php public/plugins_packages/elan-ev/AvailSchedule/dev/test-edgecases.php [--clean]
 *
 * Needs the Stud.IP demo/test users (test_dozent, test_tutor, test_autor, testautor2 …) and at least
 * two semesters: the current one and the one before it. Not part of the plugin package.
 */

$studipRoot = getcwd();
require $studipRoot . '/cli/studip_cli_env.inc.php';
require $studipRoot . '/composer/autoload.php';
require __DIR__ . '/../lib/SlotGrid.php';
require __DIR__ . '/../lib/AvailabilityCounter.php';

use ElanEv\AvailSchedule\AvailabilityCounter;

const PREFIX = 'AVS-1';
const MON = 1, TUE = 2, WED = 3, THU = 4, FRI = 5, SAT = 6, SUN = 7;

// --- cleanup ---------------------------------------------------------------
foreach (Course::findBySQL('VeranstaltungsNummer LIKE ?', [PREFIX . '%']) as $old) {
    $old->delete();
}
foreach (User::findBySQL("username LIKE 'avs_edge_%'") as $old) {
    $old->delete();
}
if (in_array('--clean', $argv)) {
    echo "Removed edge-case courses and users.\n";
    exit(0);
}

// --- prerequisites ---------------------------------------------------------
$current = Semester::findCurrent();
$previous = $current ? Semester::findOneBySQL('beginn < ? ORDER BY beginn DESC', [$current->beginn]) : null;

// Dedicated students (variable not named $user: that would overwrite Stud.IP's global $user)
// that are in no other course, so the main seed data (AVS-000 …) can't interfere.
// They have no password and cannot log in.
$users = [];
foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G'] as $key) {
    $edgeUser = new User();
    $edgeUser->username = 'avs_edge_' . strtolower($key);
    $edgeUser->vorname = 'Randfall';
    $edgeUser->nachname = $key;
    $edgeUser->email = 'avs_edge_' . strtolower($key) . '@example.invalid';
    $edgeUser->perms = 'autor';
    $edgeUser->auth_plugin = 'standard';
    $edgeUser->password = '';
    $edgeUser->store();
    $users[$key] = $edgeUser;
}
// F: status "user" (Leser) in the target course; G: tutor in the target course, must not be counted
$users['TUT'] = User::findOneByUsername('test_tutor');  // tutor in the target course, for the browser test
$users['DOZ'] = User::findOneByUsername('test_dozent');
$institute = Institute::findOneBySQL('Institut_id = fakultaets_id ORDER BY Name');
if (!$current || !$previous || in_array(null, $users, true) || !$institute) {
    fwrite(STDERR, "Missing semesters, users or institute.\n");
    exit(1);
}
$semType = array_key_first($GLOBALS['SEM_TYPE']);

// --- helpers ---------------------------------------------------------------
function course(string $number, string $name, ?Semester $start, ?Semester $end = null, bool $openEnded = false): Course
{
    global $institute, $semType, $users;
    $course = new Course();
    $course->setId($course->getNewId());
    $course->veranstaltungsnummer = $number;
    $course->name = $name;
    $course->status = $semType;
    $course->institut_id = $institute->id;
    $course->visible = 1;
    $course->start_semester = $start;
    if ($end) {
        $course->end_semester = $end;
    }
    $course->store();
    if ($openEnded) {
        $course->end_semester = null;
        $course->store();
    }
    member($course, $users['DOZ'], 'dozent');
    return $course;
}

/** Writes the membership directly, so that any status can be set regardless of global perms. */
function member(Course $course, User $user, string $status): void
{
    $m = new CourseMember([$course->id, $user->id]);
    $m->status = $status;
    $m->label = '';
    $m->store();
}

function members(Course $course, string $keys, string $status = 'autor'): void
{
    global $users;
    foreach (str_split($keys) as $key) {
        member($course, $users[$key], $status);
    }
}

function cycle(Course $course, int $weekday, string $start, string $end, int $interval = 0): SeminarCycleDate
{
    $cycle = new SeminarCycleDate();
    $cycle->seminar_id = $course->id;
    $cycle->weekday = $weekday;
    $cycle->start_time = $start . ':00';
    $cycle->end_time = $end . ':00';
    $cycle->cycle = $interval;
    $cycle->week_offset = 0;
    $cycle->end_offset = null;
    $cycle->store();
    return $cycle;
}

function singleDate(Course $course, Semester $semester, string $weekdayName, string $start, string $end): void
{
    $day = strtotime("next {$weekdayName}", $semester->vorles_beginn);
    $date = new CourseDate();
    $date->range_id = $course->id;
    $date->autor_id = $GLOBALS['user']->id;
    $date->date = strtotime($start, $day);
    $date->end_time = strtotime($end, $day);
    $date->date_typ = 1;
    $date->store();
}

// --- data ------------------------------------------------------------------
// target: A-E autor, F user (Leser), G + test_tutor tutor => 6 counted participants
$target = course(PREFIX . '00', 'AVS Randfälle: Zielveranstaltung', $current);
members($target, 'ABCDE');
member($target, $users['F'], 'user');
member($target, $users['G'], 'tutor');
member($target, $users['TUT'], 'tutor');
cycle($target, FRI, '14:00', '16:00'); // the target's own dates must not count

// boundary: exactly 5 participants => no plan
$five = course(PREFIX . '10', 'AVS Randfälle: genau 5 Teilnehmende', $current);
members($five, 'ABCDE');

$c = course(PREFIX . '01', 'AVS unbegrenzt', $current, null, true);
cycle($c, MON, '08:00', '10:00');
members($c, 'ABC');

$c = course(PREFIX . '02', 'AVS mehrsemestrig', $previous, $current);
cycle($c, TUE, '08:00', '10:00');
members($c, 'ABCD');

$c = course(PREFIX . '03', 'AVS nur Vorsemester', $previous);
cycle($c, WED, '08:00', '10:00');
members($c, 'ABCDE');

$c = course(PREFIX . '04', 'AVS 14-täglich', $current);
cycle($c, THU, '08:00', '10:00', 1);
members($c, 'ABC');

$c = course(PREFIX . '05', 'AVS Tutorin anderswo', $current);
cycle($c, FRI, '08:00', '10:00');
members($c, 'A', 'tutor');
members($c, 'BC');

$c = course(PREFIX . '06', 'AVS alle Termine ausgefallen', $current);
$cancelled = cycle($c, MON, '12:00', '14:00');
members($c, 'ABCDE');
foreach (CourseDate::findBySQL('metadate_id = ?', [$cancelled->id]) as $date) {
    $date->cancelDate();
}

$c = course(PREFIX . '07', 'AVS nur Leser', $current);
cycle($c, TUE, '12:00', '13:00');
members($c, 'F');

$c = course(PREFIX . '08', 'AVS Überlappung 1', $current);
cycle($c, WED, '12:00', '13:00');
members($c, 'AB');
$c = course(PREFIX . '09', 'AVS Überlappung 2', $current);
cycle($c, WED, '12:30', '13:30');
members($c, 'BC');

$c = course(PREFIX . '11', 'AVS nur Tutor der Zielveranstaltung', $current);
cycle($c, SAT, '10:00', '12:00');
members($c, 'G');

$c = course(PREFIX . '12', 'AVS Einzeltermin Sonntag', $current);
singleDate($c, $current, 'sunday', '10:00', '12:00');
members($c, 'A');

$c = course(PREFIX . '13', 'AVS kurzer Termin', $current);
cycle($c, THU, '12:00', '12:30');
members($c, 'DE');

// --- checks ----------------------------------------------------------------
$failures = 0;
function check(string $label, $expected, $actual): void
{
    global $failures;
    $ok = $expected === $actual;
    $failures += $ok ? 0 : 1;
    printf(
        "%s  %s%s\n",
        $ok ? 'PASS' : 'FAIL',
        $label,
        $ok ? '' : sprintf(' (erwartet %s, ist %s)', json_encode($expected), json_encode($actual))
    );
}

$byNumber = fn(string $nr) => Course::findOneBySQL('VeranstaltungsNummer = ?', [$nr])->id;

echo "== Teilnehmende und Mindestanzahl ==\n";
$five = new AvailabilityCounter($five, $current);
check('Zielveranstaltung mit genau 5 Teilnehmenden: kein Plan', false, $five->hasEnoughParticipants());
$wise = new AvailabilityCounter($target, $current);
check('AVS-100: 6 Teilnehmende (5 autor + 1 user, Tutor:innen nicht)', 6, $wise->getParticipantCount());
check('6 Teilnehmende: Plan wird angezeigt', true, $wise->hasEnoughParticipants());

echo "\n== Aktuelles Semester ({$current->name}), nur regelmäßige Termine ==\n";
$grid = $wise->buildGrid();
$courses = $wise->getCourses();
check('unbegrenzte Veranstaltung belegt (Mo 8)', 3, $grid->count(MON, 8));
check('unbegrenzte Veranstaltung belegt (Mo 9)', 3, $grid->count(MON, 9));
check('mehrsemestrige Veranstaltung belegt (Di 8)', 4, $grid->count(TUE, 8));
check('Veranstaltung nur im Vorsemester belegt niemanden (Mi 8)', 0, $grid->count(WED, 8));
check('14-tägliche Veranstaltung belegt (Do 8)', 3, $grid->count(THU, 8));
check('14-tägliche Veranstaltung: Zusatz "nicht wöchentlich"', true, $courses[$byNumber(PREFIX . '04')]['biweekly']);
check('wöchentliche Veranstaltung: kein Zusatz "nicht wöchentlich"', false, $courses[$byNumber(PREFIX . '01')]['biweekly']);
check('Teilnehmende ist anderswo Tutorin und dort belegt (Fr 8)', 3, $grid->count(FRI, 8));
check('ausgefallene Termine belegen niemanden (Mo 12)', 0, $grid->count(MON, 12));
check('Leser:in (user) der Zielveranstaltung ist Teilnehmende und belegt (Di 12)', 1, $grid->count(TUE, 12));
check('Überlappung: jede belegte Teilnehmende einmal (Mi 12)', 3, $grid->count(WED, 12));
check('Überlappung, Folgestunde (Mi 13)', 2, $grid->count(WED, 13));
check('eigene Termine der Zielveranstaltung belegen niemanden (Fr 14)', 0, $grid->count(FRI, 14));
check('Tutor:in der Zielveranstaltung ist keine Teilnehmende (Sa 10)', 0, $grid->count(SAT, 10));
check('Einzeltermin, nur regelmäßige Termine: belegt niemanden (So 10)', 0, $grid->count(SUN, 10));
check('Termin 12:00-12:30 belegt die Stunde 12 (Do 12)', 2, $grid->count(THU, 12));
check('Termin 12:00-12:30 belegt nicht die Stunde 13 (Do 13)', 0, $grid->count(THU, 13));
check('höchste Zahl im Raster', 4, $grid->maxCount());

echo "\n== Datenschutz: Nennung in der Liste (ab " . AvailabilityCounter::MIN_COURSE_COUNT . ") ==\n";
check(
    'andere Veranstaltung mit 3 Teilnehmenden wird genannt (Mo 8)',
    ['named' => [$byNumber(PREFIX . '01') => 3], 'hidden' => 0],
    $grid->visibleCourseCounts(MON, 8, AvailabilityCounter::MIN_COURSE_COUNT)
);
check(
    'andere Veranstaltung mit 1 Teilnehmenden wird nicht genannt (Di 12)',
    ['named' => [], 'hidden' => 1],
    $grid->visibleCourseCounts(TUE, 12, AvailabilityCounter::MIN_COURSE_COUNT)
);
check(
    'zwei andere Veranstaltungen mit je 2: keine genannt (Mi 12)',
    ['named' => [], 'hidden' => 2],
    $grid->visibleCourseCounts(WED, 12, AvailabilityCounter::MIN_COURSE_COUNT)
);

echo "\n== Aktuelles Semester mit Einzel- und Blockterminen ==\n";
$grid = (new AvailabilityCounter($target, $current, true))->buildGrid();
check('Einzeltermin, auch Einzeltermine: belegt (So 10)', 1, $grid->count(SUN, 10));
check('Sonntag wird als belegter Tag erkannt', true, $grid->hasOccupationOn(SUN));
check('regelmäßige Termine unverändert (Di 8)', 4, $grid->count(TUE, 8));

echo "\n== Vorsemester ({$previous->name}) ==\n";
$grid = (new AvailabilityCounter($target, $previous))->buildGrid();
check('Veranstaltung nur im Vorsemester belegt (Mi 8)', 5, $grid->count(WED, 8));
check('mehrsemestrige Veranstaltung belegt auch hier (Di 8)', 4, $grid->count(TUE, 8));
check('unbegrenzte Veranstaltung ohne Termine im Vorsemester belegt niemanden (Mo 8)', 0, $grid->count(MON, 8));
check('Veranstaltung nur im aktuellen Semester belegt niemanden (Do 8)', 0, $grid->count(THU, 8));

printf("\n%s: %d Fehler\n", $failures ? 'FEHLGESCHLAGEN' : 'OK', $failures);
printf("Zielveranstaltung AVS-100: cid=%s\n", $target->id);
exit($failures ? 1 : 0);
