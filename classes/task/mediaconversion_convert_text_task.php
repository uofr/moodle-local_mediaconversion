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
 * This is the media conversion task class that extends adhoc task for mod intros
 *
 * @package    local_mediaconversion
 * @copyright  2017 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mediaconversion_convert_text_task extends base_adhoc_task {
    /**
     * Executes the task.
     */
    public function execute() {
        global $DB;

        if ($this->stop_executing()) {
            return;
        }

        $customdata = $this->get_custom_data();
        $data = $customdata->eventdata;
        try {
            // Convert the video files embedded in content text. Note that we don't need
            // to delete the old files as Moodle automatically cleans them up for us.
            if ($newintro = local_cm_convert_and_get_new_text($data->other->modulename,
                    $data->objectid, $data->contextid, $customdata->userid)) {
                // Change the intro text in the database to the new text.
                $DB->set_field($data->other->modulename, 'intro', $newintro, ['id' => $data->other->instanceid]);
            }
            // Page resources also have page content that must be replaced.
            if ($data->other->modulename === 'page' && $newcontent = local_cm_convert_and_get_new_text(
                    $data->other->modulename, $data->objectid, $data->contextid, $customdata->userid,
                    'content')) {
                // Change the content text for something with text content (like a page resource).
                $DB->set_field($data->other->modulename, 'content', $newcontent, ['id' => $data->other->instanceid]);
            }
        } catch (\Exception $ex) {
            // If an exception is thrown it is because the course module does
            // not exist or class has been deleted.
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
        return get_string('taskmediaconversion_convert_text', 'local_mediaconversion');
    }
}