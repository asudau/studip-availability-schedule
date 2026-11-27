<?php
/**
 * @var Course $course
 * @var Semester $semester
 * @var bool $include_single_dates
 * @var int $participant_count
 * @var bool $enough_participants
 * @var int $min_participants
 * @var int $min_course_count
 * @var ElanEv\AvailSchedule\SlotGrid|null $grid
 * @var array $courses
 * @var int[] $weekdays
 */
?>

<? if (!$enough_participants): ?>
    <?= MessageBox::info(sprintf(
        _('Der Verfügbarkeitsplan steht erst ab mehr als %u Teilnehmenden zur Verfügung. Diese Veranstaltung hat %u.'),
        $min_participants,
        $participant_count
    )) ?>
    <? return; ?>
<? endif ?>

<p>
    <?= sprintf(
        _('Die Übersicht zeigt, wie viele der %1$u Teilnehmenden zu welcher Zeit im %2$s andere Veranstaltungen belegt haben.'),
        $participant_count,
        htmlReady($semester->name)
    ) ?>
    <?= $include_single_dates
        ? _('Berücksichtigt werden regelmäßige Termine sowie Einzel- und Blocktermine.')
        : _('Berücksichtigt werden nur regelmäßige Termine.') ?>
</p>

<? $max = $grid->maxCount() ?>

<table class="default avail-schedule">
    <caption><?= sprintf(_('Belegte Teilnehmende pro Stunde, %s'), htmlReady($semester->name)) ?></caption>
    <thead>
        <tr>
            <th scope="col"><?= _('Uhrzeit') ?></th>
            <? foreach ($weekdays as $wd): ?>
                <th scope="col"><?= htmlReady(getWeekday($wd, false)) ?></th>
            <? endforeach ?>
        </tr>
    </thead>
    <tbody>
        <? foreach ($grid->hours() as $hour): ?>
            <tr>
                <th scope="row"><?= sprintf('%02u:00 – %02u:00', $hour, $hour + 1) ?></th>
                <? foreach ($weekdays as $wd): ?>
                    <? $count = $grid->count($wd, $hour) ?>
                    <td style="--avail-load: <?= $max ? round($count / $max, 2) : 0 ?>">
                        <? if ($count > 0): ?>
                            <a href="#avail-<?= $wd ?>-<?= $hour ?>">
                                <?= $count ?>
                                <span class="avail-percent">(<?= round($count / $participant_count * 100) ?> %)</span>
                            </a>
                        <? else: ?>
                            <span class="avail-free">0</span>
                        <? endif ?>
                    </td>
                <? endforeach ?>
            </tr>
        <? endforeach ?>
    </tbody>
</table>

<? if ($max > 0): ?>
    <p class="avail-legend">
        <span><?= _('frei') ?></span>
        <span class="avail-legend-scale" aria-hidden="true"></span>
        <span><?= sprintf(_('%u belegt (Maximum)'), $max) ?></span>
    </p>
<? endif ?>

<? if ($max === 0): ?>
    <?= MessageBox::info(_('Die Teilnehmenden haben in diesem Semester keine anderen Termine.')) ?>
<? else: ?>
    <h2><?= _('Belegte Zeiten im Detail') ?></h2>
    <p>
        <?= sprintf(
            _('Veranstaltungen werden nur namentlich genannt, wenn mindestens %u Teilnehmende sie belegen.'),
            $min_course_count
        ) ?>
    </p>
    <dl class="avail-schedule-list">
        <? foreach ($weekdays as $wd): ?>
            <? foreach ($grid->hours() as $hour): ?>
                <? $count = $grid->count($wd, $hour) ?>
                <? if ($count === 0) continue; ?>
                <? $visible = $grid->visibleCourseCounts($wd, $hour, $min_course_count) ?>
                <dt id="avail-<?= $wd ?>-<?= $hour ?>">
                    <?= sprintf(
                        _('%1$s, %2$02u:00 – %3$02u:00: %4$u Teilnehmende (%5$u %%)'),
                        htmlReady(getWeekday($wd, false)),
                        $hour,
                        $hour + 1,
                        $count,
                        round($count / $participant_count * 100)
                    ) ?>
                </dt>
                <dd>
                    <ul>
                        <? foreach ($visible['named'] as $course_id => $n): ?>
                            <li>
                                <?= htmlReady(trim($courses[$course_id]['number'] . ' ' . $courses[$course_id]['name'])) ?>
                                (<?= $n ?>)
                                <? if ($courses[$course_id]['biweekly']): ?>
                                    <em><?= _('nicht wöchentlich') ?></em>
                                <? endif ?>
                            </li>
                        <? endforeach ?>
                        <? if ($visible['hidden'] > 0): ?>
                            <li>
                                <?= sprintf(
                                    ngettext(
                                        '%u weitere Veranstaltung mit weniger als %u Teilnehmenden',
                                        '%u weitere Veranstaltungen mit jeweils weniger als %u Teilnehmenden',
                                        $visible['hidden']
                                    ),
                                    $visible['hidden'],
                                    $min_course_count
                                ) ?>
                            </li>
                        <? endif ?>
                    </ul>
                </dd>
            <? endforeach ?>
        <? endforeach ?>
    </dl>
<? endif ?>
