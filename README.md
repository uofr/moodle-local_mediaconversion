# Intro

Moodle plugin to automatically convert videos uploaded to the Kaltura streaming
video platform

## Requirements

* Administrator access to the Kaltura Application Framework (KAF) and
Kaltura Management Console (KMC) to obtain or create the settings needed for
this plugin.
* Kaltura Moodle plugin (version >=4.2.5 is installed and working. See https://knowledge.kaltura.com/help/moodle
* Moodle 3.5+ and that Moodle cron is running.

## Installation

Place the code for this plugin in your Moodle install under local/mediaconversion

For example, if using git you can use the following command:

`git clone https://github.com/ucla/moodle-local_mediaconversion.git local/mediaconversion`

## Settings

Must set the following settings to enable the plugin:

* Base Kaltura category path (local_mediaconversion | base_category_path)
 * The location in the KAF where converted files will reside. You can find the right category to use by going to your KAF under: Configuration Management > Categories > rootCategory. Take the rootCategory and append ">site>channels".
* Kaltura player skin (local_mediaconversion | player_skin)
 * The same player skin as defined in your KMC for other Kaltura content.

## Notes

* Will convert video files uploaded as a file resource or embedded in a label or
embedded in the description of any activity or resource.
* Will not convert video files in forum posts, quiz questions, assignments, etc.
We will accept any pull requests that does figure out how to implement
conversion for those areas.
* Will automatically add any other users in the course with the capability
'mod/kalvidassign:gradesubmission' as a co-editor and co-publisher to the
converted video. This enables fellow teachers to be able to view the analytics
and edit converted videos.
