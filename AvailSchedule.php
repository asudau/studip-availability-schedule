<?php
/**
 * AvailSchedule.php - adds the availability schedule ("Verfügbarkeitsplan") to the
 * participants tab of a course.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of
 * the License, or (at your option) any later version.
 *
 * @author      Till Glöggler <till.gloeggler@elan-ev.de>
 * @copyright   2013 ELAN e.V. <http://www.elan-ev.de>
 * @license     http://www.gnu.org/licenses/gpl-2.0.html GPL version 2
 * @category    Stud.IP
 */

class AvailSchedule extends StudIPPlugin implements SystemPlugin
{
    public function __construct()
    {
        parent::__construct();

        // Use the path below PLUGINS_PATH, not __DIR__: a symlinked plugin resolves __DIR__ to the
        // link target, which StudipAutoloader may mangle if it shares a prefix with the Stud.IP path.
        StudipAutoloader::addAutoloadPath(
            Config::get()->PLUGINS_PATH . '/' . $this->plugin_info['path'] . '/lib',
            'ElanEv\\AvailSchedule'
        );

        // System plugins are loaded on every page: only touch the navigation inside a course.
        if (!Context::isCourse()) {
            return;
        }
        $course = Context::get();
        if (
            $course->isStudygroup()
            || !$GLOBALS['perm']->have_studip_perm('tutor', $course->id)
            || !Navigation::hasItem('/course/members')
        ) {
            return;
        }

        Navigation::getItem('/course/members')->addSubNavigation(
            'availability',
            new Navigation(_('Verfügbarkeit'), PluginEngine::getURL($this, [], 'schedule'))
        );
    }
}
