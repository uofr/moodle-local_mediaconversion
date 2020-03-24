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
 * Unit tests for base_adhoc_task.
 *
 * @package    local_mediaconversion
 * @copyright  2019 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * PHPunit testcase class.
 *
 * @copyright  2019 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class base_adhoc_task_test extends advanced_testcase {
    /**
     * Setup.
     */
    protected function setUp() {
        $this->resetAfterTest(true);
    }

    /**
     * Makes sure that
     */
    public function test_stop_executing() {
        global $DB;
        
        // Create file resource.
        $this->setAdminUser();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_resource');
        $course = $this->getDataGenerator()->create_course();
        $resource = $generator->create_instance(array('course' => $course->id));

        // That should have put a mediaconversion_convert_task in the adhoc queue.
        $tasks = \core\task\manager::get_adhoc_tasks('\\local_mediaconversion\\task\\mediaconversion_convert_task');
        $this->assertEquals(1, count($tasks));
        $task = array_pop($tasks);

        // Should procedure no output.
        $task->execute();
        $this->expectOutputString('');

        // Put now set fail delay to higher than 4 minutes.
        $DB->set_field('task_adhoc', 'faildelay', 4 * MINSECS + 1);

        // Run task and expect error message.
        $tasks = \core\task\manager::get_adhoc_tasks('\\local_mediaconversion\\task\\mediaconversion_convert_task');
        $this->assertEquals(1, count($tasks));
        $task = array_pop($tasks);
        $task->execute();
        $this->expectOutputString(sprintf("ERROR: Stopping conversion of course module %s (%d) due to high fail delay.\n",
                    'resource', $resource->cmid));
    }
}
