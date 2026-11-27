<?php

namespace ElanEv\AvailSchedule;

/**
 * Weekly grid of hourly slots that counts how many distinct persons are busy in each slot.
 *
 * Pure data structure without database access. Weekdays follow ISO-8601 (1 = Monday ... 7 = Sunday);
 * 0 is accepted as Sunday, as Stud.IP's cycle dates allow both. Times are minutes since midnight.
 * A slot for hour h covers [h:00, h+1:00); an occupation counts for every slot it overlaps.
 */
final class SlotGrid
{
    public const FIRST_HOUR = 8;
    public const LAST_HOUR = 22; // exclusive: the last slot is 21:00-22:00

    /** @var array<int, array<int, array<string, array<string, true>>>> weekday => hour => user => course */
    private array $slots = [];

    public function addOccupation(
        string $userId,
        string $courseId,
        int $weekday,
        int $startMinutes,
        int $endMinutes
    ): void {
        $weekday = self::normalizeWeekday($weekday);
        foreach ($this->hours() as $hour) {
            if ($startMinutes < ($hour + 1) * 60 && $endMinutes > $hour * 60) {
                $this->slots[$weekday][$hour][$userId][$courseId] = true;
            }
        }
    }

    /**
     * Number of distinct persons busy in the given slot.
     */
    public function count(int $weekday, int $hour): int
    {
        return count($this->slots[self::normalizeWeekday($weekday)][$hour] ?? []);
    }

    /**
     * Distinct persons per course in the given slot, sorted by count descending.
     *
     * @return array<string, int> course id => number of persons
     */
    public function courseCounts(int $weekday, int $hour): array
    {
        $counts = [];
        foreach ($this->slots[self::normalizeWeekday($weekday)][$hour] ?? [] as $courses) {
            foreach (array_keys($courses) as $courseId) {
                $counts[$courseId] = ($counts[$courseId] ?? 0) + 1;
            }
        }
        arsort($counts);
        return $counts;
    }

    /**
     * Course counts of a slot split for display: courses with at least $minCount persons are
     * listed individually, smaller ones are only counted, so that no course can be linked to
     * a single person.
     *
     * @return array{named: array<string, int>, hidden: int} named: course id => persons,
     *                                                       hidden: number of smaller courses
     */
    public function visibleCourseCounts(int $weekday, int $hour, int $minCount): array
    {
        $named = [];
        $hidden = 0;
        foreach ($this->courseCounts($weekday, $hour) as $courseId => $count) {
            if ($count >= $minCount) {
                $named[$courseId] = $count;
            } else {
                $hidden++;
            }
        }
        return ['named' => $named, 'hidden' => $hidden];
    }

    public function maxCount(): int
    {
        $max = 0;
        foreach ($this->slots as $hours) {
            foreach ($hours as $users) {
                $max = max($max, count($users));
            }
        }
        return $max;
    }

    public function hasOccupationOn(int $weekday): bool
    {
        return !empty($this->slots[self::normalizeWeekday($weekday)]);
    }

    /**
     * @return int[] start hours of all slots
     */
    public function hours(): array
    {
        return range(self::FIRST_HOUR, self::LAST_HOUR - 1);
    }

    /**
     * @return int[] ISO weekdays 1..7
     */
    public function weekdays(): array
    {
        return range(1, 7);
    }

    /**
     * Converts "HH:MM[:SS]" to minutes since midnight.
     */
    public static function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time) + [1 => 0]);
        return $hours * 60 + $minutes;
    }

    private static function normalizeWeekday(int $weekday): int
    {
        return $weekday === 0 ? 7 : $weekday;
    }
}
