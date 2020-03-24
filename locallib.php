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
 * Library of interface functions and constants for mediaconversion.
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 * All the mediaconversion specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    local_mediaconversion
 * @copyright  2017 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/../../course/modlib.php');
require_once(__DIR__.'/../kaltura/API/KalturaClient.php');
require_once(__DIR__.'/../kaltura/migrationlib.php');
require_once(__DIR__.'/../../mod/kalvidres/lib.php');
require_once(__DIR__.'/../kaltura/locallib.php');

define('CATEGORY_PATH_END', 'InContext');
// Video height and width.
define('MC_KALTURA_HEIGHT', 402);
define('MC_KALTURA_WIDTH', 608);

/**
 * Gets the Kaltura client after setting the session based on
 * configsettings.
 *
 * @param stdClass $configsettings
 * @return \KalturaClient
 */
function local_cm_get_kaltura_client($configsettings) {
    global $USER;
    $config = new KalturaConfiguration($configsettings->partner_id);
    $config->format = KalturaClientBase::KALTURA_SERVICE_FORMAT_PHP;
    $client = new KalturaClient($config);
    try {
        $session = $client->session->start($configsettings->adminsecret,
                $USER->username, KalturaSessionType::ADMIN, $configsettings->partner_id);
    } catch (Exception $ex) {
        if (!isset($session)) {
            die("Could not establish Kaltura session. Please verify that you are using valid Kaltura partner credentials.");
        }
    }
    $client->setKs($session);
    return $client;
}

/**
 * Gets a string of the course admins who need to be collaborators and publishers
 * on Kaltura.
 *
 * @param stdClass $course
 * @return string  A comma separated string for Kaltura to know who should be
 *                 collaborators and publishers on all videos uploaded for a course.
 */
function local_cm_get_course_admin_usernames($course) {
     // Anyone who can grade Kaltura assignment is a course admin.
    $courseadmins = get_users_by_capability(context_course::instance($course->id),
            'mod/kalvidassign:gradesubmission');
    // Put the usernames in a comma-separated string.
    $usernames = array_map(function($admin) {
        return $admin->username;
    }, $courseadmins);
    return implode(',', $usernames);
}

/**
 * Uploads a video using its path and returns the resulting
 * media entry.
 *
 * @param KalturaClient $client
 * @param string $filepath
 * @param string $title
 * @param string $description
 * @param stdClass $course  Needed to set the entry collaborators.
 * @return KalturaMediaEntry|null
 */
function local_cm_upload_video_from_filepath(\KalturaClient $client, $filepath,
        $title, $description, $course) {
    $uploadtoken = $client->media->upload($filepath);
    $entry = new KalturaMediaEntry();
    $entry->entitledUsersEdit = local_cm_get_course_admin_usernames($course);
    $entry->entitledUsersPublish = $entry->entitledUsersEdit;
    $entry->name = $title;
    $entry->description = $description;
    $entry->mediaType = KalturaMediaType::VIDEO;
    try {
        $entry = $client->media->addFromUploadedFile($entry, $uploadtoken);
    } catch (Exception $ex) {
        echo get_string('faileduploaderror', 'local_mediaconversion');
        return null;
    }
    return $entry;
}

/**
 * Uploads a video from its URL and returns its media entry
 *
 * @param \KalturaClient $client
 * @param string $url
 * @param string $title
 * @param string $description
 * @return KalturaMediaEntry|null
 */
function local_cm_upload_video_from_url(\KalturaClient $client, $url, $title, $description) {
    $entry = new KalturaMediaEntry();
    $entry->name = $title;
    $entry->description = $description;
    $entry->mediaType = KalturaMediaType::VIDEO;
    try {
        $entry = $client->media->addFromUrl($entry, $url);
    } catch (Exception $ex) {
        echo get_string('faileduploaderror', 'local_mediaconversion');
        return null;
    }
    return $entry;
}

/**
 * Adds course admins as collaborators to an existing media entry
 *
 * @param \KalturaClient $client
 * @param string $entryid The entry_id for the media entry
 * @param stdClass $course
 * @return KalturaMediaEntry|null
 */
function local_cm_update_entry_collaborators(\KalturaClient $client, $entryid, $course) {
    $newentry = new KalturaMediaEntry();
    $newentry->entitledUsersEdit = local_cm_get_course_admin_usernames($course);
    $newentry->entitledUsersPublish = $newentry->entitledUsersEdit;
    try {
        $updatedentry = $client->media->update($entryid, $newentry);
    } catch (Exception $ex) {
        echo get_string('failedentryupdateerror', 'local_mediaconversion', $entryid);
        return null;
    }
    return $newentry;
}

/**
 * Retrieves the correct category for a course, if it exists. If it doesn't
 * exist, it creates the category (and its parent category).
 *
 * @param KalturaClient $client
 * @param stdClass $course
 * @param string $basecategorypath
 * @return KalturaCategory|null
 */
function local_cm_get_kaltura_category(\KalturaClient $client, $course, $basecategorypath) {
    // Set coursepath to either be course.id or shortname.
    $coursepath = $course->id;
    if (get_config('local_mediaconversion', 'useshortname')) {
        $coursepath = $course->shortname;
    }

    $filter = new KalturaCategoryFilter();
    $filter->fullNameEqual = $basecategorypath . '>' . $coursepath . '>' . CATEGORY_PATH_END;
    try {
        $result = $client->category->listAction($filter, new KalturaFilterPager());
    } catch (Exception $ex) {
        echo get_string('failedcaterror', 'local_mediaconversion');
        return null;
    }
    $category = null;
    if (count($result->objects) > 0) {
        // Whew. Looks like the category already exists.
        $category = $result->objects[0];
    } else {
        // Oh no ... we have to make a new category. Just our luck.
        $category = new KalturaCategory();

        // We need to get the parent category first.
        $filter = new KalturaCategoryFilter();
        $filter->fullNameEqual = $basecategorypath;
        try {
            $parentcategories = $client->category->listAction($filter, new KalturaFilterPager());
            if (!(count($parentcategories->objects) > 0)) {
                echo get_string('failedbasecaterror', 'local_mediaconversion');
                return null;
            }
            $parentcategory = $parentcategories->objects[0];
        } catch (Exception $ex) {
            echo get_string('failedbasecaterror', 'local_mediaconversion');
            return null;
        }

        // Set the course properties.
        $category->parentId = $parentcategory->id;
        $category->name = "$coursepath";
        // Now upload the course category.
        try {
            $category = $client->category->add($category);
        } catch (Exception $ex) {
            echo get_string('failedcoursecaterror', 'local_mediaconversion');
            return null;
        }

        // Surprise! We have to make yet another category underneath the other one.
        $leafcategory = new KalturaCategory();
        $leafcategory->name = CATEGORY_PATH_END;
        $leafcategory->parentId = $category->id;
        // Now upload the leaf category.
        try {
            $category = $client->category->add($leafcategory);
        } catch (Exception $ex) {
            echo get_string('failedleafcaterror', 'local_mediaconversion');
            return null;
        }

    }
    return $category;
}

/**
 * Adds the uploaded video entry to its category
 *
 * @param KalturaClient $client
 * @param int $categoryid
 * @param int $entryid
 * @return KalturaCategoryEntry|null
 */
function local_cm_add_category_entry(\KalturaClient $client, $categoryid, $entryid) {
    $categoryentry = new KalturaCategoryEntry();
    $categoryentry->categoryId = $categoryid;
    $categoryentry->entryId = $entryid;

    try {
        $categoryentry = $client->categoryEntry->add($categoryentry);
    } catch (Exception $ex) {
        echo get_string('failedentrycaterror', 'local_mediaconversion');
        return null;
    }

    return $categoryentry;
}

/**
 * Gets the Kaltura player ... (TODO) custom vs player?
 *
 * @param stdClass $configsettings
 * @return string
 */
function local_cm_get_player($configsettings) {
    return empty($configsettings->player) ? $configsettings->player_custom : $configsettings->player;
}

/**
 * This function takes a Kaltura entry id height, width and uiconf_id and returns a source URL pointing to the entry.
 * @param string $entryid The Kaltura entry id.
 * @param int $height The entry height.
 * @param int $width The entry width.
 * @return string A source URL.
 */
function local_kaltura_cm_build_source_url($entryid, $height, $width) {
    $localconfigsettings = get_config('local_mediaconversion');
    if (!isset($localconfigsettings->player_skin)) {
        echo get_string('missingplayerskinerror', 'local_mediaconversion');
        return null;
    }
    $playerskin = $localconfigsettings->player_skin;
    $newheight = MC_KALTURA_HEIGHT;
    $newwidth = MC_KALTURA_WIDTH;
    $url = 'https://'.KALTURA_URI_TOKEN."/browseandembed/index/"
            . "media/entryid/{$entryid}/showDescription/false/showTitle/false/showTags/true/showDuration/false/showOwner/";
    $url .= "false/showUploadDate/false/playerSize/{$newwidth}x{$newheight}/playerSkin/{$playerskin}/";
    return $url;
}

/**
 * This function builds the final source url (it replaces the kaltura uri token) and sets
 * the entry's height and width for later use.
 *
 * @param stdClass $configsettings
 * @param \KalturaMediaEntry $entry
 * @return string
 */
function local_cm_build_source_url($configsettings, \KalturaMediaEntry &$entry) {
    // Create the source URL.
    $player = local_cm_get_player($configsettings);
    $height = $configsettings->filter_player_height;
    $width = $configsettings->filter_player_width;
    $source = local_kaltura_cm_build_source_url($entry->id, $height, $width);
    $source = local_kaltura_add_kaf_uri_token($source);
    $entry->height = $height;
    $entry->width = $width;
    return $source;
}

/**
 * Packages up the metadata of the kaltura video resource
 *
 * @param stdClass $configsettings
 * @param \KalturaMediaEntry $entry
 * @return string
 */
function local_cm_get_metadata($configsettings, \KalturaMediaEntry $entry) {
    $metadata = new stdClass();
    $metadata->url = local_cm_build_source_url($configsettings, $entry);
    $metadata->width = $entry->width;
    $metadata->height = $entry->height;
    $metadata->entryid = $entry->id;
    $metadata->thumbnailurl = $entry->thumbnailUrl;
    $metadata->duration = $entry->duration;
    $metadata->description = $entry->description;
    $metadata->createdat = $entry->createdAt;
    $metadata->owner = $entry->userId;
    $metadata->tags = $entry->tags;
    $metadata->showtitle = '';
    $metadata->showdescription = '';
    $metadata->showduration = '';
    $metadata->showowner = '';
    $metadata->player = local_cm_get_player($configsettings);
    $metadata->size = '';
    return local_kaltura_encode_object_for_storage($metadata);
}

/**
 * Creates the module info that needs to be passed to add_moduleinfo in order
 * to create a new course module.
 *
 * @param stdClass $configsettings
 * @param \KalturaMediaEntry $entry
 * @param stdClass $argsinfo
 * @return \stdClass
 */
function local_cm_create_modinfo($configsettings, \KalturaMediaEntry $entry, $argsinfo) {
    global $DB;
    $modinfo = new stdClass();
    $modinfo->entry_id = $entry->id;
    $modinfo->source = local_cm_build_source_url($configsettings, $entry);
    $modinfo->video_title = $entry->name;
    $modinfo->uiconf_id = 1;
    $modinfo->widescreen = 1;
    $modinfo->height = strval(MC_KALTURA_HEIGHT);
    $modinfo->width = strval(MC_KALTURA_WIDTH);
    $modinfo->metadata = local_cm_get_metadata($configsettings, $entry);
    $modinfo->name = $argsinfo->name;
    $introeditor = new stdClass();
    $introeditor->text = $argsinfo->intro;
    $introeditor->format = $argsinfo->introformat;
    // Hard-coded text editor id (shouldn't matter for our purposes).
    $introeditor->itemid = 621676332;
    $modinfo->introeditor = (array) $introeditor;
    $modinfo->mform_isexpanded_id_video = 1;
    $modinfo->visible = $argsinfo->visible;
    $modinfo->cmidnumber = $argsinfo->cmidnumber;
    $modinfo->groupmode = strval($argsinfo->groupmode);
    $modinfo->groupingid = $argsinfo->groupingid;
    $modinfo->availabilityconditionsjson = $argsinfo->availabilityconditionsjson;
    $modinfo->course = intval($argsinfo->course);
    $modinfo->coursemodule = $argsinfo->coursemodule;
    $modinfo->section = intval($argsinfo->section);
    $modinfo->modulename = 'kalvidres';
    $modlisting = $DB->get_record('modules', ['name' => $modinfo->modulename]);
    $modinfo->module = $modlisting->id;
    // The instance ID is filled later by add_moduleinfo.
    $modinfo->instance = 0;
    $modinfo->add = 'kalvidres';
    $modinfo->update = 0;
    $modinfo->return = 0;
    $modinfo->sr = 0;
    $modinfo->competency_rule = '0';
    $modinfo->submitbutton = 'Save and Display';
    return $modinfo;
}

/**
 * Gets the argsinfo to create the modinfo.
 *
 * @param array $courseandmodinfo  The first element is the course, and the
 *                                 second element is the cm_info object
 * @param string $name  The name of the resource
 * @param string $modname  The name of the module.
 * @return \stdClass
 */
function local_cm_package_argsinfo($courseandmodinfo, $name, $modname = 'resource') {
    global $DB;
    $groupcourse = $courseandmodinfo[0];
    $modinfo = $courseandmodinfo[1];
    $moddata = $DB->get_record($modname, array('id' => $modinfo->instance));
    $argsinfo = new stdClass();
    $argsinfo->name = $name;
    $argsinfo->visible = $modinfo->visible;
    $argsinfo->groupmode = $modinfo->groupmode;
    $argsinfo->groupingid = $groupcourse->defaultgroupingid;
    $argsinfo->availabilityconditionsjson = $modinfo->availability;
    $argsinfo->course = $groupcourse->id;
    // Save course object as a property of the argsinfo to save DB calls later.
    $argsinfo->courseobject = $groupcourse;
    $argsinfo->coursemodule = 0;
    $argsinfo->section = $modinfo->sectionnum;
    $argsinfo->intro = $moddata->intro;
    // Some modules have content that needs to be converted.
    if (isset($moddata->content)) {
        $argsinfo->content = $moddata->content;
    }
    $argsinfo->introformat = $moddata->introformat;
    $argsinfo->cmidnumber = $modinfo->idnumber;
    $argsinfo->description = '';
    return $argsinfo;
}

/**
 * Uploads the video and generates the corresponding modinfo for its
 * kaltura video resource.
 *
 * @param stored_file $file
 * @param stdClass $argsinfo
 * @param string $userid
 * @param string $cmid  The module ID the video is being converted for -
 *                      used only for error reporting.
 * @return stdClass|null
 */
function local_cm_convert_video(stored_file $file, $argsinfo, $userid, $cmid) {
    global $DB;
    // Set the user to the uploader.
    $user = $DB->get_record('user', array('id' => $userid));
    cron_setup_user($user);
    // Try to copy file to a temp location.
    if (!($pathtofile = $file->copy_content_to_temp())) {
        mtrace('Failed to copy file with id ' . $file->get_id() . ' for cm instance ' . $cmid);
        return null;
    }
    // Get Kaltura category settings from config settings.
    $localconfigsettings = get_config('local_mediaconversion');
    if (!isset($localconfigsettings->base_category_path)) {
        echo get_string('missingbasecatpatherror', 'local_mediaconversion');
        return null;
    }
    // Get the configsettings for the Kaltura plugin.
    $configsettings = local_kaltura_get_config();
    $client = local_cm_get_kaltura_client($configsettings);
    // Upload the file to Kaltura.
    if (!$entry = local_cm_upload_video_from_filepath($client,
            $pathtofile, $argsinfo->name, $argsinfo->description, $argsinfo->courseobject)) {
        return null;
    }
    // Get/make the appropriate category in the KMC for the video.
    if (!$category = local_cm_get_kaltura_category($client,
            $argsinfo->courseobject, $localconfigsettings->base_category_path)) {
        return null;
    }
    // Put the video in the category.
    if (!$categoryentry = local_cm_add_category_entry($client,
            $category->id, $entry->id)) {
        return null;
    }
    // Create the modinfo for a Kaltura video resource.
    $modinfo = local_cm_create_modinfo($configsettings, $entry, $argsinfo);
    // Delete the temp file.
    if (file_exists($pathtofile)) {
        unlink($pathtofile);
    } else {
        mtrace('Warning: temp file for cm instance ' . $cmid . ' could not be '
                . 'deleted since it was not found');
    }
    mtrace('Successfully uploaded video with entry ID ' . $modinfo->entry_id
            . ' for cm instance ' . $cmid);
    return $modinfo;
}

/**
 * Loops through files and finds the main video file.
 *
 * @param int $contextid
 * @param string $modulename
 * @param string $filearea
 * @return stored_file|null   Returns null on failure.
 */
function local_cm_get_video_file($contextid, $modulename = 'resource', $filearea = 'content') {
    // Get the file.
    $fs = get_file_storage();
    $files = $fs->get_area_files($contextid, "mod_$modulename", $filearea);
    // Find a video file.
    $mainfile = null;
    foreach ($files as $file) {
        // Check for a proper filesize and a video.
        if (intval($file->get_filesize()) > 0 && substr($file->get_mimetype(), 0, 5) === 'video') {
            $mainfile = $file;
        }
    }
    return $mainfile;
}

/**
 * This function gets the main video file, uploads it to Kaltura, and adds the new module.
 * It also converts files embedded in the intro text if necessary.
 *
 * @param int $contextid
 * @param array $courseandmodinfo (see the description at local_cm_package_argsinfo)
 * @param int $userid
 * @param int $cmid
 * @param string $cmname
 * @return boolean  Returns false if any step failed.
 */
function local_cm_convert_and_add_module($contextid, $courseandmodinfo, $userid,
        $cmid, $cmname) {
    // Get the video file.
    if (!($mainfile = local_cm_get_video_file($contextid))) {
        return false;
    }
    // Package info and try to convert the video.
    $argsinfo = local_cm_package_argsinfo($courseandmodinfo, $cmname);
    // If there are pluginfiles, we can't convert this video, since the
    // pluginfile URLs will break (and yes, we must manually check for
    // the boolean 'false' because strpos can return falsey offsets).
    if (strpos($argsinfo->intro, '@@PLUGINFILE@@/') !== false) {
        // See if we need to convert anything in the intro text.
        if ($newintro = local_cm_convert_and_get_new_text('resource', $cmid,
                $contextid, $userid, 'intro', $argsinfo)) {
            $modinfo = $courseandmodinfo[1];
            // Set the intro to the new converted intro.
            $DB->set_field('resource', 'intro', $newintro, ['id' => $modinfo->instance]);
        }
        mtrace('Couldn\'t convert the file resource with cmid ' . $cmid
                . ' to a Kaltura video resource because'
                . ' it had embedded pluginfiles');
        return false;
    }
    if (!$newmodinfo = local_cm_convert_video($mainfile, $argsinfo, $userid, $cmid)) {
        mtrace('Failed to convert video named ' . $mainfile->get_filename()
                . ' for cm instance ' . $cmid);
        return false;
    }
    $res = null;
    // Add the new module.
    $res = add_moduleinfo($newmodinfo, $courseandmodinfo[0]);
    // Check for entry_id to make sure Kaltura upload succeeded.
    if (empty($res) || empty($res->entry_id)) {
        mtrace('Failed to add the new Kaltura Video Resource module; the returned'
                . ' modinfo is empty or the Kaltura ID does not exist');
        return false;
    }
    mtrace('Successfully added new Kaltura Video Resource with id ' . $res->instance
            . ' replacing cm instance ' . $cmid);
    return true;
}

/**
 * Returns an array of filenames embedded in a module's intro text.
 *
 * @param string $intro
 * @param string $startmatch Text preceding the filename
 * @param string $endmatch Text after the filename
 * @return array
 */
function local_cm_get_filenames_from_intro($intro, $startmatch, $endmatch) {
    $filenames = [];
    preg_match_all('#(?<=' . preg_quote($startmatch)
            . ')[^(' . preg_quote($endmatch) . ')]*#', $intro, $filenames);
    return $filenames[0];
}

/**
 * Simple convenience function to find the right file given the filename.
 *
 * @param array(stored_file) $files
 * @param string $filename
 * @return stored_file|null
 */
function local_cm_get_video_file_by_name($files, $filename) {
    foreach ($files as $introfile) {
        if (substr($introfile->get_mimetype(), 0, 5) === 'video'
                && $introfile->get_filename() === urldecode($filename)) {
            return $introfile;
        }
    }
    return null;
}

/**
 * Converts embedded video files in a module's text for a module and returns the
 * new intro text.
 *
 * @param string $modulename
 * @param string $cmid
 * @param string $contextid
 * @param string $userid
 * @param string $filearea
 * @param stdClass $argsinfo  Saves some DB lookups to pass it in rather than
 *                            look up manually.
 * @return string|null
 */
function local_cm_convert_and_get_new_text($modulename, $cmid, $contextid, $userid, $filearea = 'intro', $argsinfo = null) {
    // Get any embedded intro files for this module.
    $fs = get_file_storage();
    $introfiles = $fs->get_area_files($contextid, "mod_$modulename", $filearea);
    if (count($introfiles) <= 0) {
        return null;
    }
    // Get the argsinfo if there are intro files and if it wasn't already given.
    if (!$argsinfo) {
        $courseandmodinfo = get_course_and_cm_from_cmid($cmid);
        // Note the empty name field in this call - we'll have to get the name later.
        $argsinfo = local_cm_package_argsinfo($courseandmodinfo, '', $modulename);
    }
    // Figure out what our text is.
    $text = $argsinfo->$filearea;
    // Get the names of the embedded files.
    $startmatch = '<a href="@@PLUGINFILE@@/';
    $endmatch = '">';
    $filenames = local_cm_get_filenames_from_intro($text, $startmatch, $endmatch);
    $newtext = $text;
    $numconverted = 0;
    // Loop through each of the filenames found.
    foreach ($filenames as $filename) {
        // Find the appropriate video file.
        if (!$file = local_cm_get_video_file_by_name($introfiles, $filename)) {
            continue;
        }
        // Manually modify the argsinfo name here once we've got access to the
        // file - this is important since we call local_cm_package_argsinfo with
        // an empty string for the name field.
        $argsinfo->name = $file->get_filename();
        // Try to convert the video itself. A lot of the modinfo it returns is
        // useless for this task, but we do need the entry id.
        if (!$newtempmodinfo = local_cm_convert_video($file, $argsinfo, $userid, $cmid)) {
                mtrace('Failed to convert video named ' . $file->get_filename()
                        . ' for cm instance ' . $cmid);
            continue;
        }
        // Similar to what's in lib/editor/atto/plugins/kalturamedia/yui/src/button.js.
        $content = '<a href="' . local_kaltura_cm_build_source_url($newtempmodinfo->entry_id,
            $newtempmodinfo->height, $newtempmodinfo->width) . '">tinymce-kalturamedia-embed'
            . "||$filename||$newtempmodinfo->width||$newtempmodinfo->height</a>";
        // Replace the old embedded text with the new Kaltura text (str_replace is much
        // faster than preg_replace, which is why we need $startmatch and $endmatch).
        $numreplaced = 0;
        $newtext = str_replace($startmatch . $filename . $endmatch
                . urldecode($filename) . '</a>', $content, $newtext, $numreplaced);
        // If any strings were replaced, increment the counter for converted files.
        if ($numreplaced > 0) {
            $numconverted++;
            // If more than one string was replaced, issue a warning, since it's likely
            // that the target string was incorrect.
            if ($numreplaced > 1) {
                mtrace('Warning: More than one string replacement occurred when'
                        . ' replacing the embedded file ' . urldecode($filename)
                        . " in the $filearea text of cm instance " . $cmid);
            }
        } else {
            // Otherwise warn that a file was uploaded and not used.
            mtrace('Warning: Kaltura video with entry id ' . $newtempmodinfo->entry_id
                    . ' was uploaded but was not added to the text. This'
                    . ' video should probably be removed.');
        }
    }
    if ($numconverted > 0) {
        mtrace('Successfully replaced ' . $numconverted . '/' . count($filenames)
                . " embedded $filearea files with Kaltura videos for cm instance "
                . $cmid);
        rebuild_course_cache($courseandmodinfo[0]->id, true);
        return $newtext;
    }
    return null;
}