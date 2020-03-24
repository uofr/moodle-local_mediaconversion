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
 * This task adds course administrators as co-editors and co-publishers for Kaltura content.
 *
 * @package    local_mediaconversion
 * @copyright  2017 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mediaconversion_add_collaborators_task extends \core\task\adhoc_task {
    /**
     * Executes the task.
     *
     * @throws Exception on error
     */
    public function execute() {
        global $DB;
        $customdata = $this->get_custom_data();
        $data = $customdata->eventdata;
        try {
            // Get entry id from DB.
            $moddata = $DB->get_record('kalvidres', ['id' => $data->other->instanceid]);
            // Get course.
            $course = get_course($data->courseid);
            // Update collaborators.
            $client = local_cm_get_kaltura_client(local_kaltura_get_config());
            $idstring = "for entry with id $moddata->entry_id in kalvidres with id $data->objectid";
            if (!local_cm_update_entry_collaborators($client, $moddata->entry_id, $course)) {
                mtrace('Failed to update collaborators ' . $idstring);
                return;
            }
            mtrace('Successfully added course admins as collaborators ' . $idstring);
        } catch (\Exception $ex) {
            // If an exception is thrown it is because the kalvidres or course
            // does not exist.
            mtrace(sprintf('Could not add collaborators for kalvidres %d',
                    $data->other->instanceid));
            return;
        }
    }

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskmediaconversion_add_collaborators', 'local_mediaconversion');
    }
}