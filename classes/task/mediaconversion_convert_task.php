<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The media conversion task.
 *
 * @package    local_mediaconversion
 * @copyright  2017 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_mediaconversion\task;
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/../../locallib.php');

/**
 * This is the media conversion task class that extends adhoc task.
 *
 * @package    local_mediaconversion
 * @copyright  2017 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mediaconversion_convert_task extends base_adhoc_task {
    /**
     * Executes the task.
     */
    public function execute() {

        if ($this->stop_executing()) {
            return;
        }

        $customdata = $this->get_custom_data();
        $data = $customdata->eventdata;
        try {
            // Get modinfo (along with course info).
            $courseandmodinfo = get_course_and_cm_from_cmid($data->objectid, $data->other->modulename, $data->courseid);
            // Only delete the old course module if the new one is added successfully.
            if (local_cm_convert_and_add_module($data->contextid, $courseandmodinfo,
                    $customdata->userid, $data->objectid, $data->other->name)) {
                course_delete_module($data->objectid);
            }
        } catch (\Exception $ex) {
            // If an exception is thrown it is because the course module does
            // not exist or cannot be deleted. Most likely it is because the
            // course module has been deleted since the task has been created.
            // Or class has been deleted.
            mtrace(sprintf('Could not convert course module %s (%d)',
                    $data->other->modulename, $data->objectid));
        }
    }

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskmediaconversion_convert', 'local_mediaconversion');
    }
}