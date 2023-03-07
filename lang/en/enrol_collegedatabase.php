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
 * Strings for component 'enrol_collegedatabase', language 'en'.
 *
 * @package   enrol_collegedatabase
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['collegedatabase:config'] = 'Configure database enrol instances';
$string['collegedatabase:unenrol'] = 'Unenrol suspended users';
$string['collegedatabase:receiveerrorsemail'] = 'Receive messages when Edinburgh College databse synchronisation fails';
$string['dbencoding'] = 'Database encoding';
$string['dbhost'] = 'Database host';
$string['dbhost_desc'] = 'Type database server IP address or host name. Use a system DSN name if using ODBC.';
$string['dbname'] = 'Database name';
$string['dbname_desc'] = 'Leave empty if using a DSN name in database host.';
$string['dbpass'] = 'Database password';
$string['dbsetupsql'] = 'Database setup command';
$string['dbsetupsql_desc'] = 'SQL command for special database setup, often used to setup communication encoding - example for MySQL and PostgreSQL: <em>SET NAMES \'utf8\'</em>';
$string['dbsybasequoting'] = 'Use sybase quotes';
$string['dbsybasequoting_desc'] = 'Sybase style single quote escaping - needed for Oracle, MS SQL and some other databases. Do not use for MySQL!';
$string['dbtype'] = 'Database driver';
$string['dbtype_desc'] = 'ADOdb database driver name, type of the external database engine.';
$string['dbuser'] = 'Database user';
$string['debugdb'] = 'Debug ADOdb';
$string['debugdb_desc'] = 'Debug ADOdb connection to external database - use when getting empty page during login. Not suitable for production sites!';
$string['defaultrole'] = 'Default role';
$string['defaultrole_desc'] = 'The role that will be assigned by default if no other role is specified in external table.';
$string['ignorehiddencourses'] = 'Ignore hidden courses';
$string['ignorehiddencourses_desc'] = 'If enabled users will not be enrolled on courses that are set to be unavailable to students.';
$string['localcategoryfield'] = 'Local category field';
$string['localcoursefield'] = 'Local course field';
$string['localrolefield'] = 'Local role field';
$string['localuserfield'] = 'Local user field';
$string['newcoursetable'] = 'Remote new courses table';
$string['newcoursetable_desc'] = 'Specify of the name of the table that contains list of courses that should be created automatically. Empty means no courses are created.';
$string['newcoursecategory'] = 'New course category field';
$string['newcoursesubcategory'] = 'New course subcategory field';
$string['newcoursefullname'] = 'New course full name field';
$string['newcourseidnumber'] = 'New course ID number field';
$string['newcourseshortname'] = 'New course short name field';
$string['newcoursedescription'] = 'New course description field';
$string['newcourseusedates'] = 'Use start and end dates for new courses';
$string['newcoursestartdate'] = 'New course start date field';
$string['newcourseenddate'] = 'New course end date field';
$string['pluginname'] = 'Edinburgh College external database';
$string['pluginname_desc'] = 'You can use an external database (of nearly any kind) to control your enrolments. It is assumed your external database contains at least a field containing a course ID, and a field containing a user ID. These are compared against fields that you choose in the local course and user tables.';
$string['remotecoursefield'] = 'Remote course field';
$string['remotecoursefield_desc'] = 'The name of the field in the remote table that we are using to match entries in the course table.';
$string['remoteenroltable'] = 'Remote user enrolment table';
$string['remoteenroltable_desc'] = 'Specify the name of the table that contains list of user enrolments. Empty means no user enrolment sync.';
$string['remoteotheruserfield'] = 'Remote Other User field';
$string['remoteotheruserfield_desc'] = 'The name of the field in the remote table that we are using to flag "Other User" role assignments.';
$string['remoterolefield'] = 'Remote role field';
$string['remoterolefield_desc'] = 'The name of the field in the remote table that we are using to match entries in the roles table.';
$string['remoteuserfield'] = 'Remote user field';
$string['settingsheaderdb'] = 'External database connection';
$string['settingsheaderlocal'] = 'Local field mapping';
$string['settingsheaderremote'] = 'Remote enrolment sync';
$string['settingsheadernewcourses'] = 'Creation of new courses';
$string['remoteuserfield_desc'] = 'The name of the field in the remote table that we are using to match entries in the user table.';
$string['newcourseyear'] = 'Current course year';
$string['newcourseyear_desc'] = 'The year of the courses currently in the remote course table, courses will be created with this as the highest level category.';
$string['sync_enrolments'] = 'Synchronise enrolments for existing users and courses from Edinburgh College database';
$string['sync_users'] = 'Synchronise users (provision new and update only) from Edinburgh College database';
$string['sync_courses'] = 'Synchronise courses (provision new and update fullname, shortname, and dates (if option selected) only) from Edinburgh College database';
$string['sync_teachers_and_units'] = 'Synchronise teachers and units (potential enrolments) from Edinburgh College database';
$string['sync_meta'] = 'Synchronise meta links from course admin block';
$string['settingsheaderusers'] = 'User information';
$string['userstable'] = 'Remote user table';
$string['userstable_desc'] = 'Table of valid users to be created or updated. Leave empty or disable scheduled task to stop';
$string['usersusername'] = 'Username';
$string['usersfirstname'] = 'First name';
$string['userslastname'] = 'Last name';
$string['usersemail'] = 'Email address';
$string['messageprovider:errors'] = 'Error notifications from the users and enrolments synchronisation scheduled tasks';
$string['updatecoursedates'] = 'Update Course Dates';
$string['updatecoursedates_desc'] = 'Update course dates when a change detected in remote courses table.';
$string['ignoredatetag'] = 'Course tags to ignore';
$string['ignoredatetag_desc'] = 'Courses containing this "Tag" will be ignored when updating course start and end dates. If we are not updating course dates (above), this option is ignored.';
