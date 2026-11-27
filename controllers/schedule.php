<?php

use ElanEv\AvailSchedule\AvailabilityCounter;

/**
 * Shows the availability schedule of a course's participants.
 */
class ScheduleController extends PluginController
{
    public function before_filter(&$action, &$args)
    {
        parent::before_filter($action, $args);

        $this->course = Context::get();
        if (
            !$this->course
            || !Context::isCourse()
            || $this->course->isStudygroup()
            || !$GLOBALS['perm']->have_studip_perm('tutor', $this->course->id)
        ) {
            throw new AccessDeniedException();
        }

        PageLayout::setTitle(Context::getHeaderLine() . ' - ' . _('Verfügbarkeitsplan'));
        PageLayout::addStylesheet(
            $this->plugin->getPluginURL() . '/assets/avail-schedule.css?v=' . $this->plugin->getPluginVersion()
        );
        Navigation::activateItem('/course/members/availability');
    }

    public function index_action()
    {
        $this->semester = Semester::find(Request::option('semester_id')) ?? $this->getDefaultSemester();
        $this->include_single_dates = Request::bool('single_dates', false);

        $counter = new AvailabilityCounter($this->course, $this->semester, $this->include_single_dates);
        $this->participant_count = $counter->getParticipantCount();
        $this->enough_participants = $counter->hasEnoughParticipants();
        $this->min_participants = AvailabilityCounter::MIN_PARTICIPANTS;
        $this->min_course_count = AvailabilityCounter::MIN_COURSE_COUNT;

        if ($this->enough_participants) {
            $this->grid = $counter->buildGrid();
            $this->courses = $counter->getCourses();
            $this->weekdays = array_values(array_filter(
                $this->grid->weekdays(),
                fn(int $wd) => $wd <= 5 || $this->grid->hasOccupationOn($wd)
            ));
        }

        $this->setupSidebar();
    }

    /**
     * Course's start semester if it is not over yet, otherwise the current semester.
     */
    private function getDefaultSemester(): Semester
    {
        $start = $this->course->start_semester;
        if ($start && $start->ende >= time()) {
            return $start;
        }
        return Semester::findCurrent() ?? Semester::findNext() ?? $start;
    }

    private function setupSidebar(): void
    {
        $sidebar = Sidebar::get();

        // query parameters of the URL are kept as hidden inputs by the widget
        $semesterWidget = new SemesterSelectorWidget(
            $this->url_for('schedule', $this->include_single_dates ? ['single_dates' => 1] : []),
            'semester_id'
        );
        $semesterWidget->setSelection($this->semester->id);
        $sidebar->addWidget($semesterWidget);

        // a view filter, so plain GET links (OptionsWidget checkboxes are meant for POST actions)
        $views = new ViewsWidget();
        $views->setTitle(_('Berücksichtigte Termine'));
        $views->addLink(
            _('Nur regelmäßige Termine'),
            $this->url_for('schedule', ['semester_id' => $this->semester->id])
        )->setActive(!$this->include_single_dates);
        $views->addLink(
            _('Auch Einzel- und Blocktermine'),
            $this->url_for('schedule', ['semester_id' => $this->semester->id, 'single_dates' => 1])
        )->setActive($this->include_single_dates);
        $sidebar->addWidget($views);
    }
}
