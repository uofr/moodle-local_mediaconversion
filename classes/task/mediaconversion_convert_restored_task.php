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
 * This is the media conversion task class that extends adhoc task for restored courses.
 *
 * @package    local_mediaconversion
 * @copyright  2017 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mediaconversion_convert_restored_task extends \core\task\adhoc_task {
    /**
     * Executes the task.
     *
     * @throws Exception on error
     */
    public function execute() {
        $customdata = $this->get_custom_data();

        try {
            // Get course modinfo.
            $modinfo = get_fast_modinfo($customdata->courseid);
        } catch (\dml_exception $ex) {
            // Exception is thrown if course is not found.
            mtrace(sprintf('Could not find course %d', $customdata->courseid));
            return;
        }

        foreach ($modinfo->get_cms() as $cm) {
            try {
                // We only want to deal with file uploads.
                if ($cm->get_course_module_record(true)->modname !== 'resource') {
                    continue;
                }
                // Get the module context.
                $context = \context_module::instance($cm->id);
                // Only delete the old course module if the new one is added successfully.
                if (local_cm_convert_and_add_module($context->id, array($cm->get_course(), $cm),
                        $customdata->userid, $cm->id, $cm->name)) {
                    course_delete_module($cm->id);
                }
            } catch (\Exception $ex) {
                // Could not convert content.
                mtrace(sprintf('Could not convert course module (%d); skipping', $cm->id));
                continue;
            }
        }
    }

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskmediaconversion_convert_restored', 'local_mediaconversion');
    }
}