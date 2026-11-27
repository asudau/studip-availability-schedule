# CLAUDE.md

Guidance for Claude Code when working on this plugin.

## What this is

**AvailSchedule** ("Verfügbarkeitsplan") is a Stud.IP plugin by ELAN e.V. In a course it adds
the tab *Teilnehmende → Verfügbarkeit* for tutors/lecturers. It shows a weekly grid of how many
of the course's participants are busy at each time slot because of their *other* courses in a
chosen semester, which helps when picking a date for an extra session.

Forked from `tgloeggl/studip-availability-schedule` (upstream remote `upstream`). The original
depended on `lib/raumplan/*` (`createPlanData`, `printTable`, `printList`, `ConflictCounter`),
which existed only in the Osnabrück Stud.IP fork. **That code does not exist and must not be
required.** Its functionality is re-implemented here on top of core models.

## Goals and constraints

- Target **Stud.IP 6.x** (developed against 6.1.2, tested on 6.1.4). Only core APIs, no Osnabrück-specific
  dependencies.
- **Privacy**: show only aggregated numbers (count of distinct participants per slot), never
  names or per-person data. Keep the minimum-participants rule: the plan is only available when
  the course has **more than 5** participants (`MIN_PARTICIPANTS`). Don't add features that let
  a viewer narrow a count down to individuals (e.g. filters on tiny groups) without a threshold.
- Code style follows current elan-ev plugins, e.g. `elan-ev/studip-aufgaben`: Trails
  controllers in `controllers/`, views in `views/`, classes in `lib/` (or `classes/`), German
  msgids, 4 spaces, single-quoted strings, 120 cols.

## Legacy reference and decided behaviour

The old Osnabrück code (Stud.IP 5.5) is in `dev/legacy-raumplan/` for reference only (git-excluded,
never commit or copy it). The logic is in `ConflictCounter.class.php` (`createPlanData`, `printTable`,
`printList`). The rest of that folder belongs to the room-plan PNG generator and is irrelevant.

Kept from the original:
- Participants = target-course members with status `autor`/`user`. No plan at `<= 5` of them.
- "Other courses" = every other course these participants are members of in the chosen
  semester (incl. unlimited courses), **any member status** there (tutoring elsewhere also
  makes a student busy).
- Weekly grid Mon–Sun with **hourly slots 08:00–22:00** (last row 21:00–22:00), shaded by
  relative load; a list below the grid with one entry per busy slot; grid cells link to it.
- Semester choice. The original offered only semesters that are not over yet.

Deliberately changed (decided 2026-11-27):
- A cell counts **distinct participants** busy in that slot (the original summed per course,
  so people in two parallel courses counted twice). Max = participant count, so show a percentage too.
- Slot occupancy is **minute-accurate**: a date occupies every hour it overlaps
  (10:15–11:45 → 10 and 11). The original ignored minutes and only used `start_hour <= h < end_hour`.
- List: per slot the total, then course names with the number of our participants **only for
  courses with >= 3 of them** (`MIN_COURSE_COUNT`). Smaller ones are aggregated as
  "weitere Veranstaltungen (n)". The original listed every course, even with 1 person.
- Optionally include single/block dates (`CourseDate` without `metadate_id`). The original only
  used regular cycles. Mark biweekly cycles in the list.
- Load data in bulk queries. The original instantiated `Seminar` once per course and slot.

## Stud.IP core reference

The Stud.IP source is at `/home/asudau/projects/studip` (branch `studip-6.1.4`); see its
`CLAUDE.md`. **Look up models and APIs there instead of guessing.** Relevant pieces:

- Plugin base: `lib/plugins/core/StudIPPlugin.php`, `SystemPlugin.php`,
  `TranslatablePluginTrait.php`. Routing: if the plugin class has `<action>_action`, it is called
  directly (`DefaultLegacyRouteStrategy`); otherwise Trails controllers in `controllers/` are used
  (`TrailsLegacyRouteStrategy`, controllers extend `PluginController`).
- Templates: `Flexi\Factory` (`Flexi_TemplateFactory` is only a class alias).
- Models: `Course`, `CourseMember` (`seminar_user`), `Semester` (`semester_data`; courses
  link to semesters via `semester_courses`, where **no row means an unlimited course**),
  `SeminarCycleDate` (`seminar_cycle_dates`: `weekday` 1=Mon..7=Sun, `start_time`/`end_time`
  as `HH:MM:SS`, `cycle` 0=weekly/1=biweekly/2=every 3 weeks), `CourseDate` (`termine`: `date`/
  `end_time` timestamps, `metadate_id` NULL = single/block date).
- Course navigation: `lib/modules/CoreParticipants.php` builds `/course/members`.
- Sidebar widgets: `lib/classes/sidebar/` (e.g. `SemesterSelectorWidget`).

## Development setup

- Plugin repo: `/home/asudau/projects/studip-availability-schedule`, symlinked to
  `studip/public/plugins_packages/elan-ev/AvailSchedule`. Instance: `http://localhost/studip/`.
- Test data: `php public/plugins_packages/elan-ev/AvailSchedule/dev/seed-testdata.php`
  (run from the Stud.IP root; `--clean` removes it). Creates courses `AVS-000…008` in the
  current semester; `AVS-000` is the target course with all 15 test students.
- Edge cases: `php public/plugins_packages/elan-ev/AvailSchedule/dev/test-edgecases.php` seeds AVS-100…113
  with dedicated users `avs_edge_*` and asserts expected counts (PASS/FAIL). Keep TESTPROTOKOLL.md in sync.
- `dev/` and `tests/` are not part of the release ZIP.
- Don't register/enable the plugin while `AvailSchedule.class.php` still requires `lib/raumplan`:
  as a SystemPlugin it loads on every page and would fatal the whole instance.
- Package: ZIP with `plugin.manifest` at top level, install via Admin → System → Plugins.
