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
 * Database enrolment plugin settings and presets.
 *
 * @package    enrol_collegedatabase
 * @copyright  2010 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    //--- general settings -----------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_collegedatabase_settings', '', get_string('pluginname_desc', 'enrol_collegedatabase')));

    $settings->add(new admin_setting_heading('enrol_collegedatabase_exdbheader', get_string('settingsheaderdb', 'enrol_collegedatabase'), ''));

    $options = array('', "access","ado_access", "ado", "ado_mssql", "borland_ibase", "csv", "db2", "fbsql", "firebird", "ibase", "informix72", "informix", "mssql", "mssql_n", "mssqlnative", "mysql", "mysqli", "mysqlt", "oci805", "oci8", "oci8po", "odbc", "odbc_mssql", "odbc_oracle", "oracle", "postgres64", "postgres7", "postgres", "proxy", "sqlanywhere", "sybase", "vfp");
    $options = array_combine($options, $options);
    $settings->add(new admin_setting_configselect('enrol_collegedatabase/dbtype', get_string('dbtype', 'enrol_collegedatabase'), get_string('dbtype_desc', 'enrol_collegedatabase'), '', $options));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/dbhost', get_string('dbhost', 'enrol_collegedatabase'), get_string('dbhost_desc', 'enrol_collegedatabase'), 'localhost'));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/dbuser', get_string('dbuser', 'enrol_collegedatabase'), '', ''));

    $settings->add(new admin_setting_configpasswordunmask('enrol_collegedatabase/dbpass', get_string('dbpass', 'enrol_collegedatabase'), '', ''));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/dbname', get_string('dbname', 'enrol_collegedatabase'), get_string('dbname_desc', 'enrol_collegedatabase'), ''));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/dbencoding', get_string('dbencoding', 'enrol_collegedatabase'), '', 'utf-8'));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/dbsetupsql', get_string('dbsetupsql', 'enrol_collegedatabase'), get_string('dbsetupsql_desc', 'enrol_collegedatabase'), ''));

    $settings->add(new admin_setting_configcheckbox('enrol_collegedatabase/dbsybasequoting', get_string('dbsybasequoting', 'enrol_collegedatabase'), get_string('dbsybasequoting_desc', 'enrol_collegedatabase'), 0));

    $settings->add(new admin_setting_configcheckbox('enrol_collegedatabase/debugdb', get_string('debugdb', 'enrol_collegedatabase'), get_string('debugdb_desc', 'enrol_collegedatabase'), 0));



    $settings->add(new admin_setting_heading('enrol_collegedatabase_localheader', get_string('settingsheaderlocal', 'enrol_collegedatabase'), ''));

    $options = array('idnumber'=>'idnumber');
    $settings->add(new admin_setting_configselect('enrol_collegedatabase/localcoursefield', get_string('localcoursefield', 'enrol_collegedatabase'), '', 'idnumber', $options));

    $options = array('id'=>'id', 'idnumber'=>'idnumber', 'email'=>'email', 'username'=>'username'); // only local users if username selected, no mnet users!
    $settings->add(new admin_setting_configselect('enrol_collegedatabase/localuserfield', get_string('localuserfield', 'enrol_collegedatabase'), '', 'username', $options));

    $options = array('id'=>'id', 'shortname'=>'shortname');
    $settings->add(new admin_setting_configselect('enrol_collegedatabase/localrolefield', get_string('localrolefield', 'enrol_collegedatabase'), '', 'shortname', $options));

    $options = array('name'=>'name');
    $settings->add(new admin_setting_configselect('enrol_collegedatabase/localcategoryfield', get_string('localcategoryfield', 'enrol_collegedatabase'), '', 'id', $options));


    $settings->add(new admin_setting_heading('enrol_collegedatabase_remoteheader', get_string('settingsheaderremote', 'enrol_collegedatabase'), ''));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/remoteenroltable', get_string('remoteenroltable', 'enrol_collegedatabase'), get_string('remoteenroltable_desc', 'enrol_collegedatabase'), 'EC_enrolments'));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/remotecoursefield', get_string('remotecoursefield', 'enrol_collegedatabase'), get_string('remotecoursefield_desc', 'enrol_collegedatabase'), 'courseid'));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/remoteuserfield', get_string('remoteuserfield', 'enrol_collegedatabase'), get_string('remoteuserfield_desc', 'enrol_collegedatabase'), 'userid'));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/remoterolefield', get_string('remoterolefield', 'enrol_collegedatabase'), get_string('remoterolefield_desc', 'enrol_collegedatabase'), 'role'));

    $otheruserfieldlabel = get_string('remoteotheruserfield', 'enrol_collegedatabase');
    $otheruserfielddesc  = get_string('remoteotheruserfield_desc', 'enrol_collegedatabase');
    $settings->add(new admin_setting_configtext('enrol_collegedatabase/remoteotheruserfield', $otheruserfieldlabel, $otheruserfielddesc, ''));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_collegedatabase/defaultrole', get_string('defaultrole', 'enrol_collegedatabase'), get_string('defaultrole_desc', 'enrol_collegedatabase'), $student->id, $options));
    }

    $settings->add(new admin_setting_configcheckbox('enrol_collegedatabase/ignorehiddencourses', get_string('ignorehiddencourses', 'enrol_collegedatabase'), get_string('ignorehiddencourses_desc', 'enrol_collegedatabase'), 0));

    $options = array(ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
                     ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
                     ENROL_EXT_REMOVED_SUSPEND        => get_string('extremovedsuspend', 'enrol'),
                     ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'));
    $settings->add(new admin_setting_configselect('enrol_collegedatabase/unenrolaction', get_string('extremovedaction', 'enrol'), get_string('extremovedaction_help', 'enrol'), ENROL_EXT_REMOVED_UNENROL, $options));



    $settings->add(new admin_setting_heading('enrol_collegedatabase_newcoursesheader', get_string('settingsheadernewcourses', 'enrol_collegedatabase'), ''));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/newcoursetable', get_string('newcoursetable', 'enrol_collegedatabase'), get_string('newcoursetable_desc', 'enrol_collegedatabase'), ''));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/newcoursefullname', get_string('newcoursefullname', 'enrol_collegedatabase'), '', 'fullname'));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/newcourseshortname', get_string('newcourseshortname', 'enrol_collegedatabase'), '', 'shortname'));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/newcourseidnumber', get_string('newcourseidnumber', 'enrol_collegedatabase'), '', 'idnumber'));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/newcoursecategory', get_string('newcoursecategory', 'enrol_collegedatabase'), '', 'category'));
	
	$settings->add(new admin_setting_configtext('enrol_collegedatabase/newcoursesubcategory', get_string('newcoursesubcategory', 'enrol_collegedatabase'), '', 'subcategory'));
	
	$settings->add(new admin_setting_configtext('enrol_collegedatabase/newcoursedescription', get_string('newcoursedescription', 'enrol_collegedatabase'), '', 'description'));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/newcoursestartdate', get_string('newcoursestartdate', 'enrol_collegedatabase'), '', ''));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/newcourseenddate', get_string('newcourseenddate', 'enrol_collegedatabase'), '', ''));

    $settings->add(new admin_setting_configcheckbox('enrol_collegedatabase/updatecoursedates', get_string('updatecoursedates', 'enrol_collegedatabase'), get_string('updatecoursedates_desc', 'enrol_collegedatabase'), 0));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/ignoredatetag', get_string('ignoredatetag', 'enrol_collegedatabase'), get_string('ignoredatetag_desc', 'enrol_collegedatabase'), ''));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/newcourseyear', get_string('newcourseyear', 'enrol_collegedatabase'), get_string('newcourseyear_desc', 'enrol_collegedatabase'), ''));

	$settings->add(new admin_setting_heading('enrol_collegedatabase_usersheader', get_string('settingsheaderusers', 'enrol_collegedatabase'), ''));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/userstable', get_string('userstable', 'enrol_collegedatabase'), get_string('userstable_desc', 'enrol_collegedatabase'), 'EC_users'));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/usersusername', get_string('usersusername', 'enrol_collegedatabase'), '', 'userid'));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/usersfirstname', get_string('usersfirstname', 'enrol_collegedatabase'), '', 'forename'));

    $settings->add(new admin_setting_configtext('enrol_collegedatabase/userslastname', get_string('userslastname', 'enrol_collegedatabase'), '', 'surname'));
    
	$settings->add(new admin_setting_configtext('enrol_collegedatabase/usersemail', get_string('usersemail', 'enrol_collegedatabase'), '', 'email_address'));

	
	}


