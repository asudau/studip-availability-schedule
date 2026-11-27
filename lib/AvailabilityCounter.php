<?php

namespace ElanEv\AvailSchedule;

use Course;
use DBManager;
use Semester;

/**
 * Collects when the participants of a course are busy in their other courses of a semester
 * and aggregates it into a SlotGrid.
 *
 * Participants are members with status autor/user. Their other courses count regardless of the
 * member status there (tutoring elsewhere also makes a student busy). Only aggregated numbers
 * leave this class; participant ids are used internally for counting distinct persons.
 */
class AvailabilityCounter
{
    /** The plan is only available with more than this many participants. */
    public const MIN_PARTICIPANTS = 5;

    /** Other courses are only named in the list if at least this many participants attend them. */
    public const MIN_COURSE_COUNT = 3;

    private ?array $participantIds = null;
    private ?array $memberships = null;
    private ?array $courses = null;

    public function __construct(
        private readonly Course $course,
        private readonly Semester $semester,
        private readonly bool $includeSingleDates = false
    ) {
    }

    /**
     * @return string[]
     */
    public function getParticipantIds(): array
    {
        if ($this->participantIds === null) {
            $this->participantIds = DBManager::get()->fetchFirst(
                "SELECT user_id
                 FROM seminar_user
                 WHERE Seminar_id = ? AND status IN ('autor', 'user')",
                [$this->course->id]
            );
        }
        return $this->participantIds;
    }

    public function getParticipantCount(): int
    {
        return count($this->getParticipantIds());
    }

    public function hasEnoughParticipants(): bool
    {
        return $this->getParticipantCount() > self::MIN_PARTICIPANTS;
    }

    /**
     * Other courses of the participants in the semester (including open-ended courses).
     *
     * @return array<string, array{name: string, number: string, biweekly: bool}> course id => info
     */
    public function getCourses(): array
    {
        $this->load();
        return $this->courses;
    }

    public function buildGrid(): SlotGrid
    {
        $grid = new SlotGrid();
        if (!$this->hasEnoughParticipants()) {
            return $grid;
        }

        $memberships = $this->loadMemberships(); // course id => user ids
        if (!$memberships) {
            return $grid;
        }

        foreach ($this->loadCycleDates(array_keys($memberships)) as $cycle) {
            $start = SlotGrid::timeToMinutes($cycle['start_time']);
            $end = SlotGrid::timeToMinutes($cycle['end_time']);
            foreach ($memberships[$cycle['seminar_id']] as $userId) {
                $grid->addOccupation($userId, $cycle['seminar_id'], (int) $cycle['weekday'], $start, $end);
            }
        }

        if ($this->includeSingleDates) {
            foreach ($this->loadSingleDates(array_keys($memberships)) as $date) {
                $weekday = (int) date('N', $date['date']);
                $start = (int) date('G', $date['date']) * 60 + (int) date('i', $date['date']);
                // dates spanning midnight are cut off at the end of the first day
                $end = date('Ymd', $date['date']) === date('Ymd', $date['end_time'])
                    ? (int) date('G', $date['end_time']) * 60 + (int) date('i', $date['end_time'])
                    : 24 * 60;
                foreach ($memberships[$date['range_id']] as $userId) {
                    $grid->addOccupation($userId, $date['range_id'], $weekday, $start, $end);
                }
            }
        }

        return $grid;
    }

    private function load(): void
    {
        if ($this->courses !== null) {
            return;
        }
        $this->courses = [];
        if (!$this->hasEnoughParticipants()) {
            return;
        }
        $ids = array_keys($this->loadMemberships());
        if (!$ids) {
            return;
        }
        $biweekly = DBManager::get()->fetchFirst(
            'SELECT DISTINCT seminar_id FROM seminar_cycle_dates WHERE seminar_id IN (?) AND cycle > 0',
            [$ids]
        );
        foreach (Course::findMany($ids, 'ORDER BY Name') as $course) {
            $this->courses[$course->id] = [
                'name'     => (string) $course->name,
                'number'   => (string) $course->veranstaltungsnummer,
                'biweekly' => in_array($course->id, $biweekly, true),
            ];
        }
    }

    /**
     * @return array<string, string[]> course id => participant user ids that are members there
     */
    private function loadMemberships(): array
    {
        if ($this->memberships !== null) {
            return $this->memberships;
        }
        $rows = DBManager::get()->fetchAll(
            'SELECT DISTINCT su.Seminar_id AS course_id, su.user_id
             FROM seminar_user AS su
             LEFT JOIN semester_courses AS sc ON sc.course_id = su.Seminar_id
             WHERE su.user_id IN (:users)
               AND su.Seminar_id != :course
               AND (sc.semester_id = :semester OR sc.semester_id IS NULL)',
            [
                ':users'    => $this->getParticipantIds(),
                ':course'   => $this->course->id,
                ':semester' => $this->semester->id,
            ]
        );

        $memberships = [];
        foreach ($rows as $row) {
            $memberships[$row['course_id']][] = $row['user_id'];
        }
        return $this->memberships = $memberships;
    }

    /**
     * Regular dates of the given courses that actually take place in the semester
     * (open-ended and multi-semester courses keep their cycles across semesters).
     */
    private function loadCycleDates(array $courseIds): array
    {
        return DBManager::get()->fetchAll(
            'SELECT c.metadate_id, c.seminar_id, c.weekday, c.start_time, c.end_time
             FROM seminar_cycle_dates AS c
             WHERE c.seminar_id IN (:courses)
               AND EXISTS (
                   SELECT 1 FROM termine AS t
                   WHERE t.metadate_id = c.metadate_id AND t.date BETWEEN :begin AND :end
               )',
            [
                ':courses' => $courseIds,
                ':begin'   => $this->semester->beginn,
                ':end'     => $this->semester->ende,
            ]
        );
    }

    /**
     * Single and block dates (not belonging to a cycle) of the given courses in the semester.
     */
    private function loadSingleDates(array $courseIds): array
    {
        return DBManager::get()->fetchAll(
            'SELECT range_id, date, end_time
             FROM termine
             WHERE range_id IN (:courses)
               AND metadate_id IS NULL
               AND date BETWEEN :begin AND :end',
            [
                ':courses' => $courseIds,
                ':begin'   => $this->semester->beginn,
                ':end'     => $this->semester->ende,
            ]
        );
    }
}
