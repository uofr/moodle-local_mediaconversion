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
 * The admin settings page for this plugin
 *
 * @package    local_mediaconversion
 * @copyright  2017 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once(__DIR__.'/locallib.php');

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_mediaconversion', get_string('settingstitle', 'local_mediaconversion'));

    $ADMIN->add('localplugins', $settings);

    $adminsetting = new admin_setting_configtext (
            'local_mediaconversion/base_category_path',
            get_string('basecategorytitle', 'local_mediaconversion'),
            get_string('basecategoryhelper', 'local_mediaconversion'),
            '',
            PARAM_TEXT
    );
    $settings->add($adminsetting);
    $adminsetting = new admin_setting_configtext (
            'local_mediaconversion/player_skin',
            get_string('playerskintitle', 'local_mediaconversion'),
            get_string('playerskinhelper', 'local_mediaconversion'),
            '26365392',
            PARAM_NUMBER
    );
    $settings->add($adminsetting);
    $adminsetting = new admin_setting_configcheckbox (
            'local_mediaconversion/useshortname',
            get_string('useshortname', 'local_mediaconversion'),
            get_string('useshortnamehelper', 'local_mediaconversion'),
            0
    );
    $settings->add($adminsetting);
}