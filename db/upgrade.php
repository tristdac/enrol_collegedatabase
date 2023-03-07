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
 * Database enrolment plugin upgrade.
 *
 * @package    enrol_database
 * @copyright  2011 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
function xmldb_enrol_collegedatabase_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2017030901) {

        // Define table teachers_and_units to be created.
        $table = new xmldb_table('enrol_collegedb_teachunits');

        // Adding fields to table teachers_and_units.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('unitid', XMLDB_TYPE_INTEGER, '16', null, XMLDB_NOTNULL, null, null);
        $table->add_field('unitshortname', XMLDB_TYPE_CHAR, '128', null, null, null, null);
        $table->add_field('unitfullname', XMLDB_TYPE_CHAR, '257', null, null, null, null);

        // Adding keys to table teachers_and_units.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table teachers_and_units.
        $table->add_index('userid_index', XMLDB_INDEX_NOTUNIQUE, array('userid'));

        // Conditionally launch create table for teachers_and_units.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // savepoint reached.
        upgrade_plugin_savepoint(true, 2018062502, 'enrol', 'collegedatabase');
    }

    if ($oldversion < 2017032200) {
        
        // Define field unitdescription to be added to enrol_collegedb_teachunits.
        $table = new xmldb_table('enrol_collegedb_teachunits');
        $field = new xmldb_field('unitdescription', XMLDB_TYPE_CHAR, '276', null, null, null, null, 'unitfullname');

        // Conditionally launch add field unitdescription.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // savepoint reached.
        upgrade_plugin_savepoint(true, 2018062502, 'enrol', 'database2');
        
    }

    if ($oldversion < 2018062600) {
        
        // Define field unitdescription to be added to enrol_collegedb_teachunits.
        $table = new xmldb_table('enrol_collegedb_teachunits');
        $field = new xmldb_field('startdate', XMLDB_TYPE_INTEGER, '10', null, '0', null, null, 'unitdescription');

        // Conditionally launch add field 'startdate'.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('enddate', XMLDB_TYPE_INTEGER, '10', null, '0', null, null, 'startdate');

        // Conditionally launch add field 'enddate'.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // savepoint reached.
        upgrade_plugin_savepoint(true, 2018062600, 'enrol', 'database2');
        
    }
    
    
    return true;
}
