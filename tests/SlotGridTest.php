<?php

use ElanEv\AvailSchedule\SlotGrid;
use PHPUnit\Framework\TestCase;

final class SlotGridTest extends TestCase
{
    private const MON = 1;
    private const TUE = 2;

    public function testEmptyGridCountsZero(): void
    {
        $grid = new SlotGrid();

        $this->assertSame(0, $grid->count(self::MON, 10));
        $this->assertSame(0, $grid->maxCount());
        $this->assertSame([], $grid->courseCounts(self::MON, 10));
        $this->assertFalse($grid->hasOccupationOn(self::MON));
    }

    public function testHoursCoverEightToTwentyTwo(): void
    {
        $hours = (new SlotGrid())->hours();

        $this->assertSame(8, $hours[0]);
        $this->assertSame(21, end($hours));
        $this->assertCount(14, $hours);
    }

    public function testOccupationIsMinuteAccurate(): void
    {
        $grid = new SlotGrid();
        $grid->addOccupation('u1', 'c1', self::MON, $this->t('10:15'), $this->t('11:45'));

        $this->assertSame(0, $grid->count(self::MON, 9));
        $this->assertSame(1, $grid->count(self::MON, 10));
        $this->assertSame(1, $grid->count(self::MON, 11));
        $this->assertSame(0, $grid->count(self::MON, 12));
    }

    public function testSlotBoundariesAreExclusive(): void
    {
        $grid = new SlotGrid();
        $grid->addOccupation('u1', 'c1', self::MON, $this->t('10:00'), $this->t('12:00'));

        $this->assertSame(0, $grid->count(self::MON, 9));
        $this->assertSame(1, $grid->count(self::MON, 10));
        $this->assertSame(1, $grid->count(self::MON, 11));
        $this->assertSame(0, $grid->count(self::MON, 12));
    }

    public function testShortDateWithinOneHourCounts(): void
    {
        // the old implementation ignored 9:00-9:30 completely
        $grid = new SlotGrid();
        $grid->addOccupation('u1', 'c1', self::MON, $this->t('9:00'), $this->t('9:30'));

        $this->assertSame(1, $grid->count(self::MON, 9));
    }

    public function testPersonInParallelCoursesCountsOnce(): void
    {
        $grid = new SlotGrid();
        $grid->addOccupation('u1', 'c1', self::MON, $this->t('10:15'), $this->t('11:45'));
        $grid->addOccupation('u1', 'c2', self::MON, $this->t('10:15'), $this->t('11:45'));
        $grid->addOccupation('u2', 'c2', self::MON, $this->t('10:15'), $this->t('11:45'));

        $this->assertSame(2, $grid->count(self::MON, 10));
        $this->assertSame(2, $grid->maxCount());
        $this->assertSame(['c2' => 2, 'c1' => 1], $grid->courseCounts(self::MON, 10));
    }

    public function testSameCourseTwiceInSlotCountsOnce(): void
    {
        $grid = new SlotGrid();
        $grid->addOccupation('u1', 'c1', self::MON, $this->t('10:00'), $this->t('10:30'));
        $grid->addOccupation('u1', 'c1', self::MON, $this->t('10:30'), $this->t('11:00'));

        $this->assertSame(1, $grid->count(self::MON, 10));
        $this->assertSame(['c1' => 1], $grid->courseCounts(self::MON, 10));
    }

    public function testVisibleCourseCountsHideSmallCourses(): void
    {
        $grid = new SlotGrid();
        foreach (['u1', 'u2', 'u3'] as $user) {
            $grid->addOccupation($user, 'big', self::MON, $this->t('10:00'), $this->t('11:00'));
        }
        $grid->addOccupation('u4', 'small1', self::MON, $this->t('10:00'), $this->t('11:00'));
        $grid->addOccupation('u4', 'small2', self::MON, $this->t('10:00'), $this->t('11:00'));
        $grid->addOccupation('u5', 'small2', self::MON, $this->t('10:00'), $this->t('11:00'));

        $this->assertSame(
            ['named' => ['big' => 3], 'hidden' => 2],
            $grid->visibleCourseCounts(self::MON, 10, 3)
        );
        $this->assertSame(['named' => [], 'hidden' => 0], $grid->visibleCourseCounts(self::MON, 11, 3));
    }

    public function testWeekdaysAreSeparate(): void
    {
        $grid = new SlotGrid();
        $grid->addOccupation('u1', 'c1', self::TUE, $this->t('8:15'), $this->t('9:45'));

        $this->assertSame(0, $grid->count(self::MON, 8));
        $this->assertSame(1, $grid->count(self::TUE, 8));
        $this->assertTrue($grid->hasOccupationOn(self::TUE));
        $this->assertFalse($grid->hasOccupationOn(self::MON));
    }

    public function testSundayAsZeroOrSeven(): void
    {
        $grid = new SlotGrid();
        $grid->addOccupation('u1', 'c1', 0, $this->t('10:00'), $this->t('11:00'));

        $this->assertSame(1, $grid->count(7, 10));
        $this->assertSame(1, $grid->count(0, 10));
    }

    public function testTimesOutsideGridAreIgnored(): void
    {
        $grid = new SlotGrid();
        $grid->addOccupation('u1', 'c1', self::MON, $this->t('6:00'), $this->t('8:30'));
        $grid->addOccupation('u2', 'c1', self::MON, $this->t('21:30'), $this->t('23:00'));
        $grid->addOccupation('u3', 'c1', self::MON, $this->t('22:00'), $this->t('23:00'));

        $this->assertSame(1, $grid->count(self::MON, 8));
        $this->assertSame(1, $grid->count(self::MON, 21));
        $this->assertSame(0, $grid->count(self::MON, 22));
        $this->assertSame(1, $grid->maxCount());
    }

    public function testTimeToMinutes(): void
    {
        $this->assertSame(615, SlotGrid::timeToMinutes('10:15:00'));
        $this->assertSame(615, SlotGrid::timeToMinutes('10:15'));
        $this->assertSame(540, SlotGrid::timeToMinutes('9'));
    }

    private function t(string $time): int
    {
        return SlotGrid::timeToMinutes($time);
    }
}
