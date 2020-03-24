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
abstract class base_adhoc_task extends \core\task\adhoc_task {
    /**
     * Determines if we need to stop running adhoc tasks because we retried too
     * many times.
     *
     * Must be called in execute() before doing any further processing of adhoc tasks.
     *
     * See CCLE-8283 - User uploaded video to CCLE ended up generating multiple
     * versions of media in Kaltura KMC.
     *
     * @param int $adhoctaskid
     * @return boolean          True if we need to stop running task because fail
     *                          delay is too large.
     */
    protected function stop_executing() {
        // Try up to 3 times. Fail delay starts at 1 minute and doubles every
        // time the task fails. So failing 3 times would be 4 minutes.
        if ($this->get_fail_delay() > 4 * MINSECS) {
            $customdata = $this->get_custom_data();
            $data = $customdata->eventdata;
            mtrace(sprintf('ERROR: Stopping conversion of course module %s (%d) due to high fail delay.',
                    $data->other->modulename, $data->objectid));
            return true;
        }
        return false;
    }
}
