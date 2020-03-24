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
 * The local_mediaconversion language file.
 *
 * @package    local_mediaconversion
 * @copyright  2017 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Media Conversion';
$string['settingstitle'] = 'Kaltura media conversion';
$string['basecategorytitle'] = 'Base Kaltura category path';
$string['basecategoryhelper'] = 'The path should look like this: kaf-server-name>site>channels';
$string['playerskintitle'] = 'Kaltura player skin';
$string['playerskinhelper'] = 'The skin controls the look and feel of the player';
$string['missingbasecatpatherror'] = 'The base category path must be set in admin settings';
$string['missingplayerskinerror'] = 'The player skin must be set in admin settings';
$string['faileduploaderror'] = 'Could not upload the video';
$string['failedcaterror'] = 'Could not retrieve categories';
$string['failedbasecaterror'] = 'Could not find the base category';
$string['failedleafcaterror'] = 'Could not create the leaf category';
$string['failedcoursecaterror'] = 'Could not create the course category';
$string['failedentrycaterror'] = 'Could not create the entry category';
$string['failedentryupdateerror'] = 'Could not update the media entry with id {$a}';
$string['taskmediaconversion_convert'] = 'Media conversion task for new file upload';
$string['taskmediaconversion_convert_restored'] = 'Media conversion task for restored course';
$string['taskmediaconversion_convert_text'] = 'Media conversion task for module text';
$string['taskmediaconversion_add_collaborators'] = 'Media conversion task for adding course admins to Kaltura resources';
$string['useshortname'] = 'Use shortname';
$string['useshortnamehelper'] = 'If enabled, will use shortname for the category name when converting media instead of courseid.';