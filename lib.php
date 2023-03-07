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
 * Database enrolment plugin.
 *
 * This plugin synchronises enrolment and roles with external database table.
 *
 * @package    enrol_collegedatabase
 * @copyright  2010 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
// require_once($CFG->dirroot.'/user/lib.php');
require_once($CFG->dirroot.'/group/lib.php');
/**
 * Database enrolment plugin implementation.
 * @author  Petr Skoda - based on code by Martin Dougiamas, Martin Langhoff and others
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_collegedatabase_plugin extends enrol_plugin {
    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        if (!has_capability('enrol/collegedatabase:config', $context)) {
            return false;
        }
        if (!enrol_is_enabled('collegedatabase')) {
            return true;
        }
        if (!$this->get_config('dbtype') or !$this->get_config('remoteenroltable') or !$this->get_config('remotecoursefield') or !$this->get_config('remoteuserfield')) {
            return true;
        }

        //TODO: connect to external system and make sure no users are to be enrolled in this course
        return false;
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/collegedatabase:config', $context);
    }

    /**
     * Does this plugin allow manual unenrolment of a specific user?
     * Yes, but only if user suspended...
     *
     * @param stdClass $instance course enrol instance
     * @param stdClass $ue record from user_enrolments table
     *
     * @return bool - true means user with 'enrol/xxx:unenrol' may unenrol this user, false means nobody may touch this user enrolment
     */
    public function allow_unenrol_user(stdClass $instance, stdClass $ue) {
        if ($ue->status == ENROL_USER_SUSPENDED) {
            return true;
        }

        return false;
    }

    /**
     * Gets an array of the user enrolment actions.
     *
     * @param course_enrolment_manager $manager
     * @param stdClass $ue A user enrolment object
     * @return array An array of user_enrolment_actions
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        $actions = array();
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol_user($instance, $ue) && has_capability('enrol/collegedatabase:unenrol', $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $actions[] = new user_enrolment_action(new pix_icon('t/delete', ''), get_string('unenrol', 'enrol'), $url, array('class'=>'unenrollink', 'rel'=>$ue->id));
        }
        return $actions;
    }

    /**
     * Forces synchronisation of user enrolments with external database,
     * does not create new courses.
     *
     * @param stdClass $user user record
     * @return void
     */
    // public function sync_user_enrolments($user) {
    //     global $CFG, $DB;



    //     $rs->close();
    // }

    /**
     * Forces synchronisation of user enrolments with external database,
     * does not create new courses.
     *
     * @param stdClass $user user record
     * @return void
     */
    public function sync_user_enrolments($user) {
        global $CFG, $DB;

        // We do not create courses here intentionally because it requires full sync and is slow.
        if (!$this->get_config('dbtype') or !$this->get_config('remoteenroltable') or !$this->get_config('remotecoursefield') or !$this->get_config('remoteuserfield')) {
            return;
        }

        $table            = $this->get_config('remoteenroltable');
        $coursefield      = trim($this->get_config('remotecoursefield'));
        $userfield        = trim($this->get_config('remoteuserfield'));
        $rolefield        = trim($this->get_config('remoterolefield'));
        $otheruserfield   = trim($this->get_config('remoteotheruserfield'));

        // Lowercased versions - necessary because we normalise the resultset with array_change_key_case().
        $coursefield_l    = strtolower($coursefield);
        $userfield_l      = strtolower($userfield);
        $rolefield_l      = strtolower($rolefield);
        $otheruserfieldlower = strtolower($otheruserfield);

        $localrolefield   = $this->get_config('localrolefield');
        $localuserfield   = $this->get_config('localuserfield');
        $localcoursefield = $this->get_config('localcoursefield');

        $unenrolaction    = $this->get_config('unenrolaction');
        $defaultrole      = $this->get_config('defaultrole');

        $ignorehidden     = $this->get_config('ignorehiddencourses');

        if (!is_object($user) or !property_exists($user, 'id')) {
            throw new coding_exception('Invalid $user parameter in sync_user_enrolments()');
        }

        if (!property_exists($user, $localuserfield)) {
            debugging('Invalid $user parameter in sync_user_enrolments(), missing '.$localuserfield);
            $user = $DB->get_record('user', array('id'=>$user->id));
        }

		$student = ( (substr($user->username, 0, 3) == 'ec1') || (substr($user->username, 0, 3) == 'ec2') );
        
        // Create roles mapping.
        $allroles = get_all_roles();
        if (!isset($allroles[$defaultrole])) {
            $defaultrole = 0;
        }
        $roles = array();
        foreach ($allroles as $role) {
            $roles[$role->$localrolefield] = $role->id;
        }

        $roleassigns = array();
        $enrols = array();
        $instances = array();

        if (!$extdb = $this->db_init()) {
            // Can not connect to database, sorry.
            return;
        }

        // Read remote enrols and create instances.
        $sql = $this->db_get_sql($table, array($userfield=>$user->$localuserfield), array(), false);

        if ($rs = $extdb->Execute($sql)) {
            if (!$rs->EOF) {
                while ($fields = $rs->FetchRow()) {
                    $fields = array_change_key_case($fields, CASE_LOWER);
                    $fields = $this->db_decode($fields);

                    if (empty($fields[$coursefield_l])) {
                        // Missing course info.
                        continue;
                    }
                    if (!$course = $DB->get_record('course', array($localcoursefield=>$fields[$coursefield_l]), 'id,shortname,visible')) {
                        continue;
                    }
                    if (!$course->visible and $ignorehidden) {
                        continue;
                    }
											
					$unit = strpos($course->shortname, '/');	
					
					// Ignore any enrolments for units that have not been selected to be created, or will be created as part of parent or child meta 
					if($unit) {
						if(!$DB->record_exists('block_course_admin_courses', array('courseid'=>$fields[$coursefield_l])) && 
					    !$DB->record_exists('block_course_admin_meta', array('parentid'=>$fields[$coursefield_l])) &&
					    !$DB->record_exists('block_course_admin_meta', array('childid'=>$fields[$coursefield_l]))) {
					       continue;
						}
					}
					// If staff, don't enrol if they have requested not to be, or the course is a meta child (but not parent)
					if(!$student) {
						   if($DB->record_exists('block_course_admin_unenrol', array('userid'=>$user->username,'courseid'=>$fields[$coursefield_l])) || 
						   (!$DB->record_exists('block_course_admin_meta', array('parentid'=>$fields[$coursefield_l])) &&
						   $DB->record_exists('block_course_admin_meta', array('childid'=>$fields[$coursefield_l])))) {
						   // no thanks  
						   continue;
						}
					}
                    if (empty($fields[$rolefield_l]) or !isset($roles[$fields[$rolefield_l]])) {
                        if (!$defaultrole) {
                            // Role is mandatory.
                            continue;
                        }
                        $roleid = $defaultrole;
                    } else {
                        $roleid = $roles[$fields[$rolefield_l]];
                    }

                    $roleassigns[$course->id][$roleid] = $roleid;
                    if (empty($fields[$otheruserfieldlower])) {
                        $enrols[$course->id][$roleid] = $roleid;
                    }

                    if ($instance = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>'collegedatabase'), '*', IGNORE_MULTIPLE)) {
                        $instances[$course->id] = $instance;
                        continue;
                    }

                    $enrolid = $this->add_instance($course);
                    $instances[$course->id] = $DB->get_record('enrol', array('id'=>$enrolid));
                }
            }
            $rs->Close();
            $extdb->Close();
        } else {
            // Bad luck, something is wrong with the db connection.
            $extdb->Close();
            return;
        }

        // Enrol user into courses and sync roles.
        foreach ($roleassigns as $courseid => $roles) {
            if (!isset($instances[$courseid])) {
                // Ignored.
                continue;
            }
            $instance = $instances[$courseid];

            if (isset($enrols[$courseid])) {
				
				
                if ($e = $DB->get_record('user_enrolments', array('userid' => $user->id, 'enrolid' => $instance->id))) {
                    // Reenable enrolment when previously disable enrolment refreshed.
                    if ($e->status == ENROL_USER_SUSPENDED) {
                        $this->update_user_enrol($instance, $user->id, ENROL_USER_ACTIVE);
                    }
                } else {
                    $roleid = reset($enrols[$courseid]);
                    $this->enrol_user($instance, $user->id, $roleid, 0, 0, ENROL_USER_ACTIVE);
                }
            }

            if (!$context = context_course::instance($instance->courseid, IGNORE_MISSING)) {
                // Weird.
                continue;
            }
            $current = $DB->get_records('role_assignments', array('contextid'=>$context->id, 'userid'=>$user->id, 'component'=>'enrol_collegedatabase', 'itemid'=>$instance->id), '', 'id, roleid');

            $existing = array();
            foreach ($current as $r) {
                if (isset($roles[$r->roleid])) {
                    $existing[$r->roleid] = $r->roleid;
                } else {
                    role_unassign($r->roleid, $user->id, $context->id, 'enrol_collegedatabase', $instance->id);
                }
            }
            foreach ($roles as $rid) {
                if (!isset($existing[$rid])) {
                    role_assign($rid, $user->id, $context->id, 'enrol_collegedatabase', $instance->id);
                }
            }
        }

        // Unenrol as necessary.
        $sql = "SELECT e.*, c.visible AS cvisible, ue.status AS ustatus
                  FROM {enrol} e
                  JOIN {course} c ON c.id = e.courseid
                  JOIN {role_assignments} ra ON ra.itemid = e.id
             LEFT JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = ra.userid
                 WHERE ra.userid = :userid AND e.enrol = 'collegedatabase'";
        $rs = $DB->get_recordset_sql($sql, array('userid'=>$user->id));
        foreach ($rs as $instance) {
            if (!$instance->cvisible and $ignorehidden) {
                continue;
            }

            if (!$context = context_course::instance($instance->courseid, IGNORE_MISSING)) {
                // Very weird.
                continue;
            }

            if (!empty($enrols[$instance->courseid])) {
                // We want this user enrolled.
                continue;
            }

            // Deal with enrolments removed from external table
            if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
                $this->unenrol_user($instance, $user->id);

            } else if ($unenrolaction == ENROL_EXT_REMOVED_KEEP) {
                // Keep - only adding enrolments.

            } else if ($unenrolaction == ENROL_EXT_REMOVED_SUSPEND or $unenrolaction == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
                // Suspend users.
                if ($instance->ustatus != ENROL_USER_SUSPENDED) {
                    $this->update_user_enrol($instance, $user->id, ENROL_USER_SUSPENDED);
                }
                if ($unenrolaction == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
                    if (!empty($roleassigns[$instance->courseid])) {
                        // We want this "other user" to keep their roles.
                        continue;
                    }
                    role_unassign_all(array('contextid'=>$context->id, 'userid'=>$user->id, 'component'=>'enrol_collegedatabase', 'itemid'=>$instance->id));
                }
            }
        }
        $rs->close();
    }

/**
 * Emails admins about sync errors
 *
 * @param string $notice The body of the email to be sent.
 */
protected function error_message_admins($notice) {

    $site = get_site();
    $admins = get_admins();
        foreach ($admins as $admin) {
            $message = new \core\message\message();
            $message->component         = 'enrol_collegedatabase';
            $message->name              = 'errors';
            $message->userfrom          = get_admin();
            $message->userto            = $admin;
            $message->subject           = 'Edinburgh College database synchronisation errors';
            $message->fullmessage       = $notice;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml   = '';
            $message->smallmessage      = '';
            $message->notification      = 1;
            message_send($message);
        }
}
    /**
     * Forces synchronisation of all enrolments with external database.
     *
     * @param progress_trace $trace
     * @param null|int $onecourse limit sync to one course only (used primarily in restore)
     * @return int 0 means success, 1 db connect failure, 2 db read failure
     */
    public function sync_enrolments(progress_trace $trace, $onecourse = null) {
        global $CFG, $DB;
		ini_set('mssql.timeout', 1800); 
		$email = '';
		
        // We do not create courses here intentionally because it requires full sync and is slow.
        if (!$this->get_config('dbtype') or !$this->get_config('remoteenroltable') or !$this->get_config('remotecoursefield') or !$this->get_config('remoteuserfield')) {
            $trace->output('User enrolment synchronisation skipped.');
            $trace->finished();
			$this->error_message_admins('Missing enrolment synchronisation config');
            return 0;
        }

        $trace->output('Starting user enrolment synchronisation...');

        if (!$extdb = $this->db_init()) {
            $trace->output('Error while communicating with external enrolment database');
            $trace->finished();
			$this->error_message_admins('Error while communicating with external enrolment database');
            return 1;
        }

        // We may need a lot of memory here.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        $table            = $this->get_config('remoteenroltable');
        $coursetable      = $this->get_config('newcoursetable');
        $coursefield      = trim($this->get_config('remotecoursefield'));
        $userfield        = trim($this->get_config('remoteuserfield'));
        $rolefield        = trim($this->get_config('remoterolefield'));
        $otheruserfield   = trim($this->get_config('remoteotheruserfield'));

        // Lowercased versions - necessary because we normalise the resultset with array_change_key_case().
        $coursefield_l    = strtolower($coursefield);
        $userfield_l      = strtolower($userfield);
        $rolefield_l      = strtolower($rolefield);
        $otheruserfieldlower = strtolower($otheruserfield);

        $localrolefield   = $this->get_config('localrolefield');
        $localuserfield   = $this->get_config('localuserfield');
        $localcoursefield = $this->get_config('localcoursefield');

        $unenrolaction    = $this->get_config('unenrolaction');
        $defaultrole      = $this->get_config('defaultrole');

        // Create roles mapping.
        $allroles = get_all_roles();
        if (!isset($allroles[$defaultrole])) {
            $defaultrole = 0;
        }
        $roles = array();
        foreach ($allroles as $role) {
            $roles[$role->$localrolefield] = $role->id;
        }

        if ($onecourse) {
            $sql = "SELECT c.id, c.visible, c.$localcoursefield AS mapping, c.shortname, c.fullname, e.id AS enrolid
                      FROM {course} c
                 LEFT JOIN {enrol} e ON (e.courseid = c.id AND e.enrol = 'collegedatabase')
                     WHERE c.id = :id";
            if (!$course = $DB->get_record_sql($sql, array('id'=>$onecourse))) {
                // Course does not exist, nothing to sync.
                return 0;
            }
            if (empty($course->mapping)) {
                // We can not map to this course, sorry.
                return 0;
            }
            if (empty($course->enrolid)) {
                $course->enrolid = $this->add_instance($course);
            }
            $existing = array($course->mapping=>$course);

            // Feel free to unenrol everybody, no safety tricks here.
            $preventfullunenrol = false;
            // Course being restored are always hidden, we have to ignore the setting here.
            $ignorehidden = false;

        } else {
            // Get a list of courses to be synced that are in external table.
            $externalcourses = array();
            $sql = $this->db_get_sql($table, array(), array($coursefield,), true);
            if ($rs = $extdb->Execute($sql)) {
                if (!$rs->EOF) {
                    while ($mapping = $rs->FetchRow()) {
                        $mapping = reset($mapping);
                        $mapping = $this->db_decode($mapping);
                        if (empty($mapping)) {
                            // invalid mapping
                            continue;
                        }
						$externalcourses[$mapping] = true;
                    }
                }
                $rs->Close();
            } else {
                $trace->output('Error reading data from the external enrolment table');
				$this->error_message_admins('Error reading data from the external enrolment table');
                $extdb->Close();
                return 2;
            }
            $preventfullunenrol = empty($externalcourses);
            if ($preventfullunenrol and $unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
                $trace->output('Preventing unenrolment of all current users, because it might result in major data loss, there has to be at least one record in external enrol table, sorry.', 1);
				$this->error_message_admins('Preventing unenrolment of all current users, because it might result in major data loss, there has to be at least one record in external enrol table, sorry.');
            }

            // First find all existing courses with enrol instance.
            $existing = array();
            $sql = "SELECT c.id, c.visible, c.$localcoursefield AS mapping, e.id AS enrolid, c.shortname, c.fullname
                      FROM {course} c
                      JOIN {enrol} e ON (e.courseid = c.id AND e.enrol = 'collegedatabase')";
            $rs = $DB->get_recordset_sql($sql); // Watch out for idnumber duplicates.
            foreach ($rs as $course) {
                if (empty($course->mapping)) {
                    continue;
                }
                $existing[$course->mapping] = $course;
                unset($externalcourses[$course->mapping]);
            }
            $rs->close();
			
            // Add necessary enrol instances that are not present yet.
            $params = array();
            $localnotempty = "";
            if ($localcoursefield !== 'id') {
                $localnotempty =  "AND c.$localcoursefield <> :lcfe";
                $params['lcfe'] = '';
            }
            $sql = "SELECT c.id, c.visible, c.$localcoursefield AS mapping, c.shortname, c.fullname
                      FROM {course} c
                 LEFT JOIN {enrol} e ON (e.courseid = c.id AND e.enrol = 'collegedatabase')
                     WHERE e.id IS NULL $localnotempty";
            $rs = $DB->get_recordset_sql($sql, $params);
            foreach ($rs as $course) {
                if (empty($course->mapping)) {
                    continue;
                }
                if (!isset($externalcourses[$course->mapping])) {
                    // Course not synced or duplicate.
                    continue;
                }
                $course->enrolid = $this->add_instance($course);
                $existing[$course->mapping] = $course;
                unset($externalcourses[$course->mapping]);
            }
            $rs->close();
            // Print list of missing courses.
            if ($externalcourses) {
                $list = implode(', ', array_keys($externalcourses));
                $trace->output("error: following courses do not exist - $list", 1);
				$email .= "error: following courses do not exist - $list \n";
                unset($list);
            }

            // Free memory.
            unset($externalcourses);

            $ignorehidden = $this->get_config('ignorehiddencourses');
        }

        // Sync user enrolments.
        $sqlfields = array($userfield);
        if ($rolefield) {
            $sqlfields[] = $rolefield;
        }
        if ($otheruserfield) {
            $sqlfields[] = $otheruserfield;
        }
        foreach ($existing as $course) {
            if ($ignorehidden and !$course->visible) {
                continue;
            }
            if (!$instance = $DB->get_record('enrol', array('id'=>$course->enrolid))) {
                continue; // Weird!
            }
            $context = context_course::instance($course->id);

            // Get current list of enrolled users with their roles.
            $currentroles  = array();
            $currentenrols = array();
            $currentstatus = array();
            $usermapping   = array();
            $sql = "SELECT u.$localuserfield AS mapping, u.id AS userid, ue.status, ra.roleid
                      FROM {user} u
                      JOIN {role_assignments} ra ON (ra.userid = u.id AND ra.component = 'enrol_collegedatabase' AND ra.itemid = :enrolid)
                 LEFT JOIN {user_enrolments} ue ON (ue.userid = u.id AND ue.enrolid = ra.itemid)
                     WHERE u.deleted = 0";
            $params = array('enrolid'=>$instance->id);
            if ($localuserfield === 'username') {
                $sql .= " AND u.mnethostid = :mnethostid";
                $params['mnethostid'] = $CFG->mnet_localhost_id;
            }
            $rs = $DB->get_recordset_sql($sql, $params);
            foreach ($rs as $ue) {
                $currentroles[$ue->userid][$ue->roleid] = $ue->roleid;
                $usermapping[$ue->mapping] = $ue->userid;

                if (isset($ue->status)) {
                    $currentenrols[$ue->userid][$ue->roleid] = $ue->roleid;
                    $currentstatus[$ue->userid] = $ue->status;
                }
            }
            $rs->close();

            // Get list of users that need to be enrolled and their roles.
            $requestedroles  = array();
            $requestedenrols = array();
            $sql = $this->db_get_sql($table, array($coursefield=>$course->mapping), $sqlfields);
            if ($rs = $extdb->Execute($sql)) {
                if (!$rs->EOF) {
                    $usersearch = array('deleted' => 0);
                    if ($localuserfield === 'username') {
                        $usersearch['mnethostid'] = $CFG->mnet_localhost_id;
                    }
                    while ($fields = $rs->FetchRow()) {
                        // error_log(print_r($fields, 1));
                        $fields = array_change_key_case($fields, CASE_LOWER);
                        if (empty($fields[$userfield_l])) {
                            $trace->output("error: skipping user without mandatory $localuserfield in course '$course->mapping'", 1);
							$email .= "error: skipping user without mandatory $localuserfield in course '$course->mapping' \n ";
                            continue;
                        }
                        $mapping = $fields[$userfield_l];
                        if (!isset($usermapping[$mapping])) {
                            $usersearch[$localuserfield] = $mapping;
                            if (!$user = $DB->get_record('user', $usersearch, 'id', IGNORE_MULTIPLE)) {
                                $trace->output("error: skipping unknown user $localuserfield '$mapping' in course '$course->mapping'", 1);
								$email .= "error: skipping unknown user $localuserfield '$mapping' in course '$course->mapping' \n ";
                                continue;
                            }
                            $usermapping[$mapping] = $user->id;
                            $userid = $user->id;
                        } else {
                            $userid = $usermapping[$mapping];
                        }
                        // error_log(print_r($instance, 1));
                        // $fwcourse = $DB->get_record_sql("SELECT c.id, c.fullname
                        //                                     FROM {course} c
                        //                                     JOIN {customfield_data} cfd ON cfd.value = c.fullname 
                        //                                     JOIN {customfield_field} cff ON cff.id = cfd.fieldid 
                        //                                     WHERE cff.shortname = 'framework' 
                        //                                     AND cfd.value = $course->");
                        // // error_log('fwcourse: ',0);
                        // // error_log(print_r($fwcourse->id, 2));
                        // $context = context_course::instance($fwcourse->id);
                        // if (is_enrolled($context, $userid)) {
                        //     $groups = groups_get_all_groups($fwcourse->id);
                        //     $sql = "SELECT c.fullname FROM $table e JOIN $coursetable c on c.idnumber = e.courseid WHERE e.userid = '".$mapping."' AND c.description = '".$fwcourse->fullname."'";
                        //     $enrolments1 = array();
                        //     if ($records1 = $extdb->Execute($sql)) {
                        //         if (!$records1->EOF) {
                        //             while ($fields1 = $records1->FetchRow()) {
                        //                 $results1 = $this->db_decode($results1);
                        //                 $enrolments1[] = $results1['fullname'];
                        //             }   
                        //         }
                        //         $records1->Close();
                        //     }
                        //     foreach ($groups as $group) {
                        //         if (in_array($group->name, $enrolments1)) {
                        //             if (groups_is_member($group->id, $userid)) {
                        //                 // do nothing
                        //                 // $trace->output("do nothing 1");
                        //             } else {
                        //                 groups_add_member($group->id, $userid);
                        //                 $trace->output("Adding user $userid ($mapping) to group ($group->name)", 1);
                        //             }
                        //         } else {
                        //             if (!groups_is_member($group->id, $userid)) {
                        //                 // do nothing
                        //                 // $trace->output("do nothing 2");
                        //             } else {
                        //                 groups_remove_member($group->id, $userid);
                        //                 $trace->output("Removing $mapping from group $group->name");
                        //             }
                        //         }
                        //     }
                        //     unset($enrolments1);
                        //     unset($records1);
                        // }
                        $student = ( (substr($mapping, 0, 3) == 'ec1') || (substr($mapping, 0, 3) == 'ec2') );
						$unit = strpos($course->shortname, '/');	
						// Ignore any enrolments for units that have not been selected to be created, or will be created as part of parent or child meta
                        if($unit) {
                            // $trace->output("this is a unit: $course->shortname",1);
							if(!$DB->record_exists('block_course_admin_courses', array('courseid'=>$course->mapping)) && 
						    !$DB->record_exists('block_course_admin_meta', array('parentid'=>$course->mapping)) &&
						    !$DB->record_exists('block_course_admin_meta', array('childid'=>$course->mapping))) {
                               continue;
							}
						}

                        // If staff, don't enrol if they have requested not to be, or the course is a meta child (but not parent), or if a unit
						if(!$student) {
						   if($DB->record_exists('block_course_admin_unenrol', array('userid'=>$mapping,'courseid'=>$course->mapping)) || 
						   (!$DB->record_exists('block_course_admin_meta', array('parentid'=>$course->mapping)) &&
						   $DB->record_exists('block_course_admin_meta', array('childid'=>$course->mapping)))) {
								// no thanks  
								continue;
							}
						}
						// error_log(print_r($fields, 1));
						if (empty($fields[$rolefield_l]) or !isset($roles[$fields[$rolefield_l]])) {
                            if (!$defaultrole) {
                                $trace->output("error: skipping user '$userid' in course '$course->mapping' - missing course and default role", 1);
								$email .= "error: skipping user '$userid' in course '$course->mapping' - missing course and default role \n ";
                                continue;
                            }
                            $roleid = $defaultrole;
                            $trace->output("empty role field for $userid ($user->username)... assigning default role [$defaultrole]", 1);
                        } else {
                            $roleid = $roles[$fields[$rolefield_l]];
                        }

                        $requestedroles[$userid][$roleid] = $roleid;
                        if (empty($fields[$otheruserfieldlower])) {
                            $requestedenrols[$userid][$roleid] = $roleid;
                        }
                    }
                }
                $rs->Close();
            } else {
                $trace->output("error: skipping course '$course->mapping' - could not match with external database", 1);
				$email .= "error: skipping course '$course->mapping' - could not match with external database \n ";
                continue;
            }
            unset($usermapping);

            // Enrol all users and sync roles.
            foreach ($requestedenrols as $userid => $userroles) {
                $user = $DB->get_record('user', array('id' => $userid));
                // error_log(print_r($user->username, 1));
                
                foreach ($userroles as $roleid) {
                    // error_log(print_r($roleid, 1));
                    if (empty($currentenrols[$userid])) {
                        $this->enrol_user($instance, $userid, $roleid, 0, 0, ENROL_USER_ACTIVE);
                        $currentroles[$userid][$roleid] = $roleid;
                        $currentenrols[$userid][$roleid] = $roleid;
                        $currentstatus[$userid] = ENROL_USER_ACTIVE;
                        $trace->output("Enrolling user $userid ($user->username) to $course->fullname as ".$allroles[$roleid]->shortname, 1);
                        // Add users to groups
                        $iscourse = strpos($course->shortname, '-');
                        if ($iscourse) {
                            $groups = groups_get_all_groups($course->id);
                            $sql = "SELECT c.fullname FROM $table e JOIN $coursetable c on c.idnumber = e.courseid WHERE e.userid = '".$user->username."' AND c.description = '".$course->fullname."'";
                            $enrolments2 = array();
                            if ($rs2 = $extdb->Execute($sql)) {
                                if (!$rs2->EOF) {
                                    while ($fields2 = $rs2->FetchRow()) {
                                        $fields2 = $this->db_decode($fields2);
                                        $enrolments2[] = $fields2['fullname'];
                                    }   
                                }
                                $rs2->Close();
                            }
                            foreach ($groups as $group) {
                                if (in_array($group->name, $enrolments2)) {
                                    if (groups_is_member($group->id, $userid)) {
                                        // do nothing
                                    } else {
                                        $member = groups_add_member($group->id, $userid);
                                        $trace->output("Adding user $userid ($user->username) to group ($group->name)", 1);
                                    }
                                } else {
                                    if (!groups_is_member($group->id, $userid)) {
                                        // do nothing
                                    } else {
                                        $member = groups_remove_member($group->id, $userid);
                                        $trace->output("is member... removing $user->username from group $group->name");
                                    }
                                }
                            }
                            unset($enrolments2);
                            unset($rs2);
                        }
                    }
                }

                // Reenable enrolment when previously disable enrolment refreshed.
                if ($currentstatus[$userid] == ENROL_USER_SUSPENDED) {
                    $this->update_user_enrol($instance, $userid, ENROL_USER_ACTIVE);
                    $trace->output("unsuspending: $userid ==> $course->shortname", 1);
                }
            }

            foreach ($requestedroles as $userid => $userroles) {
                // Assign extra roles.
                foreach ($userroles as $roleid) {
                    if (empty($currentroles[$userid][$roleid])) {
                        role_assign($roleid, $userid, $context->id, 'enrol_collegedatabase', $instance->id);
                        $currentroles[$userid][$roleid] = $roleid;
                        $trace->output("assigning roles: $userid ==> $course->shortname as ".$allroles[$roleid]->shortname, 1);
                    }
                }

                // Unassign removed roles.
                foreach ($currentroles[$userid] as $cr) {
                    if (empty($userroles[$cr])) {
                        role_unassign($cr, $userid, $context->id, 'enrol_collegedatabase', $instance->id);
                        unset($currentroles[$userid][$cr]);
                        $trace->output("unsassigning roles: $userid ==> $course->shortname", 1);
                    }
                }

                unset($currentroles[$userid]);
            }

            foreach ($currentroles as $userid => $userroles) {
                // These are roles that exist only in Moodle, not the external database
                // so make sure the unenrol actions will handle them by setting status.
                $currentstatus += array($userid => ENROL_USER_ACTIVE);
            }

            // Deal with enrolments removed from external table.
            if ($unenrolaction == ENROL_EXT_REMOVED_UNENROL) {
                $user = $DB->get_record('user', array('id' => $userid));
                
                if (!$preventfullunenrol) {
                    // Unenrol.
                    foreach ($currentstatus as $userid => $status) {
                        if (isset($requestedenrols[$userid])) {
                            continue;
                        }
                        $this->unenrol_user($instance, $userid);
                        $trace->output("unenrolling: $userid ==> $course->shortname", 1);
                    }
                }

            } else if ($unenrolaction == ENROL_EXT_REMOVED_KEEP) {
                // Keep - only adding enrolments.

            } else if ($unenrolaction == ENROL_EXT_REMOVED_SUSPEND or $unenrolaction == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
                // Suspend enrolments.
                foreach ($currentstatus as $userid => $status) {
                    if (isset($requestedenrols[$userid])) {
                        continue;
                    }
                    if ($status != ENROL_USER_SUSPENDED) {
                        $this->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
                        $trace->output("suspending: $userid ==> $course->shortname", 1);
                    }
                    if ($unenrolaction == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
                        if (isset($requestedroles[$userid])) {
                            // We want this "other user" to keep their roles.
                            continue;
                        }
                        role_unassign_all(array('contextid'=>$context->id, 'userid'=>$userid, 'component'=>'enrol_collegedatabase', 'itemid'=>$instance->id));

                        $trace->output("unsassigning all roles: $userid ==> $course->shortname", 1);
                    }
                }
            }
        }

        // Close db connection.
        $extdb->Close();

        $trace->output('...user enrolment synchronisation finished.');
        $trace->finished();
			
		if($email) {
			$this->error_message_admins('Enrolment Sync Scheduled Task: '.$email);
		}       
		return 0;
    }


    /**
     * Performs a full sync with external database.
     *
     * First it creates new courses if necessary, then
     * enrols and unenrols users.
     *
     * @param progress_trace $trace
     * @return int 0 means success, 1 db connect failure, 2 db read failure
     */
    public function sync_courses(progress_trace $trace) {
        global $CFG, $DB;

		$erroremail = '';
		
        if (!$this->get_config('dbtype')) {
            echo 'no dbtype';
        }
        if (!$this->get_config('newcoursetable')) {
            echo 'no table';
        }
        if (!$this->get_config('newcoursefullname')) {
            echo 'no fullname';
        }
        if (!$this->get_config('newcourseshortname')) {
            echo 'no shortname';
        }
        if (!$this->get_config('newcourseidnumber')) {
            echo 'no idnumber';
        }
        if (!$this->get_config('newcoursecategory')) {
            echo 'no category';
        }
        if (!$this->get_config('newcoursesubcategory')) {
            echo 'no subcategory';
        }
        if (!$this->get_config('newcourseyear')) {
            echo 'no year';
        }

        // Make sure we sync either enrolments or courses.
        if (!$this->get_config('dbtype') or !$this->get_config('newcoursetable') or !$this->get_config('newcoursefullname') or !$this->get_config('newcourseshortname') or !$this->get_config('newcourseidnumber') or !$this->get_config('newcoursecategory') or !$this->get_config('newcoursesubcategory') or !$this->get_config('newcourseyear')) {
            $trace->output('Course synchronisation skipped.');
            $trace->finished();
			$this->error_message_admins('Missing course synchronisation config');
            return 0;
        }

        $trace->output('Starting course synchronisation...');

        // We may need a lot of memory here.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        if (!$extdb = $this->db_init()) {
            $trace->output('Error while communicating with external course database');
            $trace->finished();
			$this->error_message_admins('Error while communicating with external course database');
            return 1;
        }

        $table     = $this->get_config('newcoursetable');
        $fullname  = trim($this->get_config('newcoursefullname'));
        $shortname = trim($this->get_config('newcourseshortname'));
        $idnumber  = trim($this->get_config('newcourseidnumber'));
        $category  = trim($this->get_config('newcoursecategory'));
		$subcategory  = trim($this->get_config('newcoursesubcategory'));
		$description  = trim($this->get_config('newcoursedescription'));
        $startdate = trim($this->get_config('newcoursestartdate'));
        $enddate = trim($this->get_config('newcourseenddate'));
		$year  = $this->get_config('newcourseyear');
		
        // Lowercased versions - necessary because we normalise the resultset with array_change_key_case().
        $fullname_l  = strtolower($fullname);
        $shortname_l = strtolower($shortname);
        $idnumber_l  = strtolower($idnumber);
        $category_l  = strtolower($category);
		$subcategory_l  = strtolower($subcategory);
		$description_l  = strtolower($description);
        $startdate_l  = strtolower($startdate);
        $enddate_l  = strtolower($enddate);

        $localcategoryfield = $this->get_config('localcategoryfield', 'name');
        /* $defaultcategory    = $this->get_config('defaultcategory');

        if (!$DB->record_exists('course_categories', array('id'=>$defaultcategory))) {
            $trace->output("default course category does not exist!", 1);
            $categories = $DB->get_records('course_categories', array(), 'sortorder', 'id', 0, 1);
            $first = reset($categories);
            $defaultcategory = $first->id;
        } */

        $sqlfields = array($fullname, $shortname);
        if ($category) {
            $sqlfields[] = $category;
        }
		if ($subcategory) {
            $sqlfields[] = $subcategory;
        }
		if ($description) {
            $sqlfields[] = $description;
        }
        if ($idnumber) {
            $sqlfields[] = $idnumber;
        }
        if ($startdate) {
            $sqlfields[] = $startdate;
        }
        if ($enddate) {
            $sqlfields[] = $enddate;
        }
        $sql = $this->db_get_sql($table, array(), $sqlfields, true);
        $createcourses = array();
		$categoriescreated = false;
        if ($rs = $extdb->Execute($sql)) {
            if (!$rs->EOF) {
                while ($fields = $rs->FetchRow()) {
                    $fields = array_change_key_case($fields, CASE_LOWER);
                    $fields = $this->db_decode($fields);
                    if (empty($fields[$shortname_l]) or empty($fields[$fullname_l]) or empty($fields[$idnumber_l]) or empty($fields[$category_l]) or empty($fields[$subcategory_l]) or strcasecmp($fields[$category_l], 'unknown') == 0 or strcasecmp($fields[$subcategory_l], 'unknown') == 0) {
                        $trace->output('error: invalid external course record, idnumber, category, subcategory, shortname and fullname are mandatory: ' . json_encode($fields), 1); // Hopefully every geek can read JS, right?
                        $erroremail .= 'error: invalid external course record, idnumber, category, subcategory, shortname and fullname are mandatory: ' . json_encode($fields) . " \n ";
						continue;
                    }
					//is this something we are interested in? all courses are created but
					if(strpos($fields[$shortname_l], '/')) {
						// this is a unit
                        $coursetype = 'Units';
                        $groupmode = 0;
						if(!$DB->record_exists('block_course_admin_courses', array('courseid'=>$fields[$idnumber_l])) && 
						   !$DB->record_exists('block_course_admin_meta', array('parentid'=>$fields[$idnumber_l])) &&
						   !$DB->record_exists('block_course_admin_meta', array('childid'=>$fields[$idnumber_l]))) {
						   // not selected to be created, nor parent or child -- we are not interested  
						   continue;
						}
		
					} else {
                        $coursetype = 'Courses';
                        $groupmode = 1;
                        $groupmodeforce = 1;
                    }
					
					$currentcourse = $DB->get_record('course', array('idnumber'=>$fields[$idnumber_l]));
                    if ($currentcourse) {
						$somethingtoupdate = false;
						//Check fullname
						if(strcasecmp(trim($fields[$fullname_l]), $currentcourse->fullname) <> 0) {
							$currentcourse->fullname = trim($fields[$fullname_l]);
							$somethingtoupdate = true;
						}
						//Check shortname
						if(strcasecmp(trim($fields[$shortname_l]), $currentcourse->shortname) <> 0) {
							// No duplicate shortnames
							if ($DB->record_exists('course', array('shortname'=>trim($fields[$shortname_l])))) {
								$trace->output('error: duplicate shortname, can not update course shortname: '. $currentcourse->shortname . ' -> ' . $fields[$shortname_l].' ['.$fields[$idnumber_l].']', 1);
								$erroremail .= 'error: duplicate shortname, can not update course shortname: '. $currentcourse->shortname . ' -> ' . $fields[$shortname_l].' ['.$fields[$idnumber_l].']' . " \n ";
							} else {
								$currentcourse->shortname = trim($fields[$shortname_l]);
								$somethingtoupdate = true;
							}
						}
                        $updatecoursedates = $this->get_config('updatecoursedates');
                        $ignoredatetag = $this->get_config('ignoredatetag');
                        // $currentcoursetagid = $DB->get_field('tag_instance', 'tagid', array('itemtype'=>'course','itemid'=>$currentcourse->id));
                        $currentcoursetags = core_tag_tag::get_item_tags_array('core', 'course', $currentcourse->id);
                        if(($updatecoursedates == '1') && (!in_array($ignoredatetag, $currentcoursetags))) {
                            if(strcasecmp(trim($fields[$startdate_l]), $currentcourse->startdate) <> 0) {
                                $currentcourse->startdate = trim($fields[$startdate_l]);
                                $somethingtoupdate = true;
                            }
                            if(strcasecmp(trim($fields[$enddate_l]), $currentcourse->enddate) <> 0) {
                                $currentcourse->enddate = trim($fields[$enddate_l]);
                                $somethingtoupdate = true;
                            }
                        }
						if($somethingtoupdate) {
							$DB->update_record('course',$currentcourse);
                            $trace->output('Updating course dates: '.$fields[$shortname_l].' ['.$fields[$idnumber_l].']', 1);
						}
                        continue;
                    }
                    // No duplicate shortnames
                    if ($DB->record_exists('course', array('shortname'=>$fields[$shortname_l]))) {
                        $trace->output('error: duplicate shortname, can not create course: '.$fields[$shortname_l].' ['.$fields[$idnumber_l].']', 1);
						$erroremail .= 'error: duplicate shortname, can not create course: '.$fields[$shortname_l].' ['.$fields[$idnumber_l].']' . " \n ";
                        continue;
                    }
                    $course = new stdClass();
                    $course->fullname  = trim($fields[$fullname_l]);
                    $course->shortname = trim($fields[$shortname_l]);
                    $course->idnumber  = trim($fields[$idnumber_l]);
					$course->summary = trim($fields[$description_l]);
                    $course->startdate = trim($fields[$startdate_l]);
                    $course->enddate = trim($fields[$enddate_l]);
                    $course->groupmode = $groupmode;
                    $course->groupmodeforce = $groupmodeforce;
                    $course->customfield_framework = trim($fields[$description_l]);
                    $course->customfield_type = $coursetype;
                    // Check for year category
					$yearcategory = $DB->get_record('course_categories', array($localcategoryfield=>$year,'parent'=>'0'), 'id');
					if (!$yearcategory) {
						$newcategory = new stdClass();
						$yearcategory = new stdClass();
						$newcategory->name = $year;
					    $newcategory->descriptionformat = FORMAT_MOODLE;
                        $newcategory->description = '';
						$newcategory->depth = 1;
						$newcategory->visibleold = 1; 
						$newcategory->visible = 1;
						$newcategory->sortorder = 0;
						$newcategory->timemodified = time();
						$newcategory->parent = 0;
						$yearcategory->id = $DB->insert_record('course_categories', $newcategory);
						$path = '/' . $yearcategory->id;
						$DB->set_field('course_categories', 'path', $path, array('id' => $yearcategory->id));
						context_coursecat::instance($yearcategory->id)->mark_dirty();
						$categorycontext = context_coursecat::instance($yearcategory->id);
						$event = \core\event\course_category_created::create(array(
							'objectid' => $yearcategory->id,
							'context' => $categorycontext
						));
						$event->trigger();
						$categoriescreated = true;
					}
					if (!$yearcategory->id) {
                        $trace->output('error: could not create year category: '.$year, 1);
						$erroremail .= 'error: could not create year category: '.$year . " \n ";
                        continue;
                    }
					// Check for category
					$coursecategory = $DB->get_record('course_categories', array($localcategoryfield=>$fields[$category_l],'parent'=>$yearcategory->id), 'id');
					if (!$coursecategory) {
						$newcategory = new stdClass();
						$newcategory->name = trim($fields[$category_l]);
						$newcategory->parent = $yearcategory->id;
						$newcategory->descriptionformat = FORMAT_MOODLE;
                        $newcategory->description = '';
						$newcategory->depth = 2;
						$newcategory->visibleold = 1; 
						$newcategory->visible = 1;
						$newcategory->sortorder = 0;
						$newcategory->timemodified = time();
						$coursecategory = new stdClass();
						$coursecategory->id = $DB->insert_record('course_categories', $newcategory);
						$path = '/' . $yearcategory->id . '/' . $coursecategory->id;
						$DB->set_field('course_categories', 'path', $path, array('id' => $coursecategory->id));
						context_coursecat::instance($coursecategory->id)->mark_dirty();
						$categorycontext = context_coursecat::instance($coursecategory->id);
						$event = \core\event\course_category_created::create(array(
							'objectid' => $coursecategory->id,
							'context' => $categorycontext
						));
						$event->trigger();
						$categoriescreated = true;
					}
					if (!$coursecategory->id) {
                        $trace->output('error: could not create course category: '.$fields[$category_l], 1);
						$erroremail .= 'error: could not create course category: '.$fields[$category_l]. " \n ";
                        continue;
                    }
					// Check for subcategory
					$coursesubcategory = $DB->get_record('course_categories', array($localcategoryfield=>$fields[$subcategory_l],'parent'=>$coursecategory->id), 'id');
					if (!$coursesubcategory) {
						$newcategory = new stdClass();
						$newcategory->name = trim($fields[$subcategory_l]);
						$newcategory->parent = $coursecategory->id;
						$newcategory->descriptionformat = FORMAT_MOODLE;
                        $newcategory->description = '';
						$newcategory->depth = 3;
						$newcategory->visibleold = 1; 
						$newcategory->visible = 1;
						$newcategory->sortorder = 0;
						$newcategory->timemodified = time();
						$coursesubcategory = new stdClass();
						$coursesubcategory->id = $DB->insert_record('course_categories', $newcategory);
						$path = '/' . $yearcategory->id . '/' . $coursecategory->id . '/' . $coursesubcategory->id;
						$DB->set_field('course_categories', 'path', $path, array('id' => $coursesubcategory->id));
						context_coursecat::instance($coursesubcategory->id)->mark_dirty();
						$categorycontext = context_coursecat::instance($coursesubcategory->id);
						$event = \core\event\course_category_created::create(array(
							'objectid' => $coursesubcategory->id,
							'context' => $categorycontext
						));
						$event->trigger();
						$categoriescreated = true;
					}
					if (!$coursesubcategory->id) {
                        $trace->output('error: could not create course sub category: '.$fields[$subcategory_l], 1);
						$erroremail .= 'error: could not create course sub category: '.$fields[$subcategory_l]. " \n ";						
                        continue;
                    }
                    $course->category = $coursesubcategory->id;
                    $createcourses[] = $course;
                }
            }
			if($categoriescreated) {
				cache_helper::purge_by_event('changesincoursecat');
            }
			$rs->Close();
        } else {
            $extdb->Close();
            $trace->output('Error reading data from the external course table');
            $trace->finished();
			$this->error_message_admins('Error reading data from the external course table');
            return 2;
        }
        if ($createcourses) {
            require_once("$CFG->dirroot/course/lib.php");
            require_once("$CFG->dirroot/course/externallib.php");
    
            // $templatecourse = $this->get_config('templatecourse');

            // Attempt at restoring course on creation!!!
            // $selectedtemplate = $DB->get_record('block_course_admin_courses', array('courseid'=>$fields[$idnumber_l]), 'template');
            // $trace->output($selectedtemplate);
            // if ($selectedtemplate) {
            //     $templatecourse = $DB->get_record('course', array('idnumber'=>$selectedtemplate), 'id');
            //     $trace->output("Template found for $fields[$shortname]: $selectedtemplate", 1);
            // }

            $template = false;
            // if ($templatecourse) {
            //     if ($template = $DB->get_record('course', array('shortname'=>$templatecourse))) {
            //         $template = fullclone(course_get_format($template)->get_course());
            //         unset($template->id);
            //         unset($template->fullname);
            //         unset($template->shortname);
            //         unset($template->idnumber);
            //     } else {
            //         $trace->output("can not find template for new course!", 1);
            //     }
            // } 
            if (!$template) {
                $courseconfig = get_config('moodlecourse');
                $template = new stdClass();
                $template->summary        = '';
                $template->summaryformat  = FORMAT_HTML;
                $template->format         = $courseconfig->format;
                $template->numsections    = $courseconfig->numsections;
                $template->newsitems      = $courseconfig->newsitems;
                $template->showgrades     = $courseconfig->showgrades;
                $template->showreports    = $courseconfig->showreports;
                $template->maxbytes       = $courseconfig->maxbytes;
                $template->groupmode      = $courseconfig->groupmode;
                $template->groupmodeforce = $courseconfig->groupmodeforce;
                $template->visible        = $courseconfig->visible;
                $template->lang           = $courseconfig->lang;
            }

            foreach ($createcourses as $fields) {
                // $newcourse = duplicate_course($templatecourse->id, $fields->fullname, $fields->shortname, $fields->categoryid, 1, $options = array());
                $newcourse = clone($template);
                $newcourse->fullname  = $fields->fullname;
                $newcourse->shortname = $fields->shortname;
                $newcourse->idnumber  = $fields->idnumber;
                $newcourse->category  = $fields->category;
				$newcourse->summary  = $fields->summary;
                $newcourse->startdate  = $fields->startdate;
                $newcourse->enddate  = $fields->enddate;
                $newcourse->groupmode = $fields->groupmode;
                $newcourse->groupmodeforce = $fields->groupmodeforce;
                $newcourse->showreports = 1;
                $newcourse->customfield_framework = $fields->customfield_framework;
                $newcourse->customfield_type = $fields->customfield_type;
                // Detect duplicate data once again, above we can not find duplicates
                // in external data using DB collation rules...
                if ($DB->record_exists('course', array('shortname' => $newcourse->shortname))) {
                    $trace->output("can not insert new course, duplicate shortname detected: ".$newcourse->shortname, 1);
					$erroremail .= "error: can not insert new course, duplicate shortname detected: ".$newcourse->shortname. " \n ";	
                    continue;
                } else if (!empty($newcourse->idnumber) and $DB->record_exists('course', array('idnumber' => $newcourse->idnumber))) {
                    $trace->output("can not insert new course, duplicate idnumber detected: ".$newcourse->idnumber, 1);
					$erroremail .= "error: can not insert new course, duplicate idnumber detected: ".$newcourse->idnumber. " \n ";	
                    continue;
                }
                $c = create_course($newcourse);
                $trace->output("creating $fields->customfield_type: $c->id, $c->fullname, $c->shortname, $c->idnumber, $c->category", 1);
                // $trace->output("creating $coursetype", 1):
                // Add a courseinfo module to the course if it's a course page, add a unitinfo module if it's a unit
                if(strpos($c->shortname, '-') !== false){
                    require_once("$CFG->dirroot/mod/courseinfo/lib.php");
                    $trace->output('Adding Course Info module into course '.$c->shortname); 
                    $myCourseinfo = new stdClass();
                    $myCourseinfo->modulename='courseinfo';
                    $myCourseinfo->name = 'Course Info';
                    $myCourseinfo->introeditor = array('text' => '','format' => 0);
                    $myCourseinfo->introformat = 0;
                    $myCourseinfo->course = $c->id;
                    $myCourseinfo->section = 0;
                    $myCourseinfo->visible = 1;
                    // $myCourseinfo->subguide = 0;
                    // $myCourseinfo->handbook = NULL;
                    $myCourseinfo2 = create_module($myCourseinfo);
                    $trace->output('Added Course Info module successfully'); 
                }
                if(strpos($c->shortname, '/') !== false){
                    require_once("$CFG->dirroot/mod/unitinfo/lib.php");
                    $trace->output('Adding Unit Info module into course '.$c->shortname); 
                    $myUnitinfo = new stdClass();
                    $myUnitinfo->modulename='unitinfo';
                    $myUnitinfo->name = 'Unit Info';
                    $myUnitinfo->introeditor = array('text' => '','format' => 0);
                    $myUnitinfo->introformat = 0;
                    $myUnitinfo->course = $c->id;
                    $myUnitinfo->section = 0;
                    $myUnitinfo->visible = 1;
                    $myUnitinfo->unit = strtok($c->shortname, '/');
                    $myUnitinfo2 = create_module($myUnitinfo);
                    $trace->output('Added Unit Info module successfully'); 
                }
                // Create groups
                if ($fields->customfield_type === 'Courses') {
                    $trace->output("Creating Groups...", 1);
                    $sql = $this->db_get_sql($table, array($description_l=>$fields->fullname), array('fullname','idnumber'), true);
                    if ($rs = $extdb->Execute($sql)) {
                        if (!$rs->EOF) {
                            while ($fields = $rs->FetchRow()) {
                                $fields = array_change_key_case($fields, CASE_LOWER);
                                $fields = $this->db_decode($fields);
                                // if ($fields[$fullname_l] !== $c->fullname) {
                                    $data = new stdClass();
                                    $data->courseid = $c->id;
                                    $data->name = $fields[$fullname_l];
                                    $data->description = '';
                                    $data->descriptionformat = FORMAT_PLAIN;

                                    $newgroup = groups_create_group($data);
                                    $trace->output("Creating group: $fields[$fullname_l]", 1);
                                // }
                            }
                        }
                        $rs->Close();
                    }
                } else if ($fields->customfield_type === 'Courses') {
                    $trace->output("Creating Groups...", 1);
                    $data = new stdClass();
                    $data->courseid = $c->id;
                    $data->name = $c->shortname;
                    $data->description = '';
                    $data->descriptionformat = FORMAT_PLAIN;
                    $newgroup = groups_create_group($data);
                    $trace->output("Creating group: $c->fullname", 1);
                }
            }
			fix_course_sortorder();
            unset($createcourses);
            unset($template);
        }

        // Close db connection.
        $extdb->Close();

        $trace->output('...course synchronisation finished.');
        $trace->finished();
		if($erroremail) {
			$this->error_message_admins('Course Sync Scheduled Task: '.$erroremail);
		}    
        return 0;
    }
	
	/**
     * Performs a user sync with external database.
     *
     * @param progress_trace $trace
     * @return int 0 means success, 1 db connect failure, 2 db read failure
     */
    public function sync_users(progress_trace $trace) {
        global $CFG, $DB;

		$erroremail = '';
		
        if (!$this->get_config('dbtype') or !$this->get_config('userstable') or !$this->get_config('usersusername') or !$this->get_config('usersfirstname') or !$this->get_config('userslastname') or !$this->get_config('usersemail')) {
            $trace->output('User sync not configured, skipped');
            $trace->finished();
			$this->error_message_admins('Missing user synchronisation config');	
            return 0;
        }

        $trace->output('Starting user synchronisation...');

        // We may need a lot of memory here.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        if (!$extdb = $this->db_init()) {
            $trace->output('Error while communicating with external user database');
            $trace->finished();
			$this->error_message_admins('Error while communicating with external user database');
            return 1;
        }

        $table     = trim($this->get_config('userstable'));
        $username  = trim($this->get_config('usersusername'));
        $firstname = trim($this->get_config('usersfirstname'));
        $lastname  = trim($this->get_config('userslastname'));
        $email  = trim($this->get_config('usersemail'));

        // Lowercased versions - necessary because we normalise the resultset with array_change_key_case().
        $username_l  = strtolower($username);
        $firstname_l = strtolower($firstname);
        $lastname_l  = strtolower($lastname);
        $email_l  = strtolower($email);

        $sqlfields = array($username, $firstname, $lastname, $email);

        $sql = $this->db_get_sql($table, array(), $sqlfields, true);
        $createusers = array();
        if ($rs = $extdb->Execute($sql)) {
            if (!$rs->EOF) {
                while ($fields = $rs->FetchRow()) {
                    $fields = array_change_key_case($fields, CASE_LOWER);
                    $fields = $this->db_decode($fields);
                    if (empty($fields[$username_l]) or empty($fields[$firstname_l]) or empty($fields[$lastname_l]) or empty($fields[$email_l])) {
                        $trace->output('error: invalid external user record, username, firstname, lastname and email are mandatory: ' . json_encode($fields), 1); // Hopefully every geek can read JS, right?
                        $erroremail .= 'error: invalid external user record, username, firstname, lastname and email are mandatory: ' . json_encode($fields) . " \n ";
						continue;
                    }
					$currentuser = $DB->get_record('user', array('username'=>$fields[$username_l]));
                    if ($currentuser) {
						$somethingtoupdate = false;
						//Check email 
						if(strcasecmp(trim($fields[$email_l]), $currentuser->email) <> 0) {
							$currentuser->email = trim(strtolower($fields[$email_l]));
							$somethingtoupdate = true;
						}
						//Check name
						if(mb_strtolower(trim($fields[$firstname_l]), "UTF-8") <> mb_strtolower($currentuser->firstname, "UTF-8") 
						|| mb_strtolower(trim($fields[$lastname_l]), "UTF-8") <> mb_strtolower($currentuser->lastname, "UTF-8")) {
							$currentuser->firstname = mb_convert_case(trim($fields[$firstname_l]), MB_CASE_TITLE, "UTF-8");
							$currentuser->lastname = mb_convert_case(trim($fields[$lastname_l]), MB_CASE_TITLE, "UTF-8");
							$somethingtoupdate = true;
						}	
						if($somethingtoupdate) {
							user_update_user($currentuser, false);
						}
                        // Already exists, skip.
                        continue;
                    }

                    $user = new stdClass();
                    $user->username  = mb_strtolower(trim($fields[$username_l]), "UTF-8");
					$user->firstname  = mb_convert_case(trim($fields[$firstname_l]), MB_CASE_TITLE, "UTF-8");
					$user->lastname  = mb_convert_case(trim($fields[$lastname_l]), MB_CASE_TITLE, "UTF-8");
					$user->email  = trim($fields[$email_l]);
                    $createusers[] = $user;
                }
            }
            $rs->Close();
        } else {
            $extdb->Close();
            $trace->output('Error reading data from the external user table');
            $trace->finished();
			$this->error_message_admins('Error reading data from the external user table');
            return 2;
        }
        if ($createusers) {
            // require_once("$CFG->dirroot/course/lib.php");
            require_once("$CFG->dirroot/user/lib.php");
			foreach ($createusers as $user) {
            // Prep a few params
                $user->modified   = time();
                $user->confirmed  = 1;
                $user->auth       = 'ldap';
                $user->mnethostid = $CFG->mnet_localhost_id;
                if (empty($user->lang)) {
                    $user->lang = $CFG->lang;
                }
                if (empty($user->calendartype)) {
                    $user->calendartype = $CFG->calendartype;
                }
				try {
					user_create_user($user, false);
					$trace->output("created user: $user->username, $user->firstname, $user->lastname, $user->email", 1);
				} catch (Exception $e) {
					$erroremail .= "error: exception when creating $user->username, $user->firstname, $user->lastname, $user->email -> " . $e->getMessage() . " \n ";
				}
                
            }

            unset($createusers);
        }

        // Close db connection.
        $extdb->Close();

        $trace->output('...user synchronisation finished.');
        $trace->finished();
		if($erroremail) {
			$this->error_message_admins('User Sync Scheduled Task: '.$erroremail);
		}    
        return 0;
    }
	
	/**
     * Performs a full sync with external database.
     *
     * Replaces teachers and units table with fresh info
     *
     * @param progress_trace $trace
     * @return int 0 means success, 1 db connect failure, 2 db read failure
     */
    public function sync_teachers_and_units(progress_trace $trace) {
        global $CFG, $DB;

		$erroremail = '';
		
		
		
        // Check config.
        if (!$this->get_config('dbtype') or !$this->get_config('newcoursetable') or !$this->get_config('newcoursefullname') or !$this->get_config('newcourseshortname') or !$this->get_config('newcourseidnumber') or !$this->get_config('newcoursedescription') or !$this->get_config('remoteenroltable') or !$this->get_config('remotecoursefield') or !$this->get_config('remoteuserfield') or !$this->get_config('remoterolefield')) {
            $trace->output('Teachers and units synchronisation skipped.');
            $trace->finished();
			$this->error_message_admins('Missing teachers and units synchronisation config');
            return 0;
        }

        $trace->output('Starting teachers and units synchronisation...');

        // We may need a lot of memory here.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

        if (!$extdb = $this->db_init()) {
            $trace->output('Error while communicating with external teachers and units database');
            $trace->finished();
			$this->error_message_admins('Error while communicating with external teachers and units database');
            return 1;
        }

        $coursetable     = $this->get_config('newcoursetable');
        $fullname  = trim($this->get_config('newcoursefullname'));
        $shortname = trim($this->get_config('newcourseshortname'));
        $idnumber  = trim($this->get_config('newcourseidnumber'));
		$description  = trim($this->get_config('newcoursedescription'));
        $startdate  = $this->get_config('newcoursestartdate');
        $enddate  = $this->get_config('newcourseenddate');

		$enrolmenttable = $this->get_config('remoteenroltable');
        $enrolmentcourseid = trim($this->get_config('remotecoursefield'));
        $enrolmentuserid = trim($this->get_config('remoteuserfield'));
		$enrolmentrolefield = trim($this->get_config('remoterolefield'));

        // Lowercased versions - necessary because we normalise the resultset with array_change_key_case().
        $fullname_l  = strtolower($fullname);
        $shortname_l = strtolower($shortname);
        $idnumber_l  = strtolower($idnumber);
		$description_l  = strtolower($description);
		$enrolmentuserid_l  = strtolower($enrolmentuserid);
        $startdate_l = strtolower($startdate);
        $enddate_l = strtolower($enddate);
        
		$sqlfields = array('c'.$fullname, 'c'.$shortname, 'c'.$idnumber, 'c'.$description, 'c'.$startdate, 'c'.$enddate, 'e'.$enrolmentuserid);

        $fields = '';
        $fields = $fields ? implode(',', $fields) : "*";
		// units have shortnames with a slash in, courses don't 
        $where = "WHERE ($enrolmentrolefield = '3' OR $enrolmentrolefield = 'staff') AND $shortname like '%/%'";
        echo $shortname;

        $distinct = "DISTINCT";
        $sql = "SELECT $distinct $fields
                  FROM $enrolmenttable e INNER JOIN $coursetable c ON e.$enrolmentcourseid = c.$idnumber
                 $where";
				 
        if ($rs = $extdb->Execute($sql)) {
            if (!$rs->EOF) {
			    $transaction = $DB->start_delegated_transaction();
				try {
					$atleastonesuccess = false;
					$DB->delete_records('enrol_collegedb_teachunits'); 
					while ($fields = $rs->FetchRow()) {
						$fields = array_change_key_case($fields, CASE_LOWER);
						$fields = $this->db_decode($fields);
						if (empty($fields[$shortname_l]) or empty($fields[$fullname_l]) or empty($fields[$idnumber_l]) or empty($fields[$enrolmentuserid_l]) or empty($fields[$description_l])) {
							$trace->output('error: invalid external teachers and units record, teacher username, unit idnumber, shortname, fullname and description are mandatory: ' . json_encode($fields), 1); // Hopefully every geek can read JS, right?
							$erroremail .= 'error: invalid external teachers and units record, teacher username, unit idnumber, shortname, fullname and description are mandatory: ' . json_encode($fields) . " \n ";
							continue;
						}
					
						$row = new stdClass();
						$row->userid  = trim($fields[$enrolmentuserid_l]);
						$row->unitid  = trim($fields[$idnumber_l]);
						$row->unitshortname  = trim($fields[$shortname_l]);
						$row->unitfullname  = trim($fields[$fullname_l]);
						$row->unitdescription  = trim($fields[$description_l]);
                        $row->startdate = trim($fields[$startdate_l]);
                        $row->enddate = trim($fields[$enddate_l]);
                            echo "<br><br>Add New:<br>";
					       print_r ($row);
                           echo '<br><br>';
						try {
						$DB->insert_record('enrol_collegedb_teachunits', $row);
						$atleastonesuccess = true;					
						} catch (Exception $e) {
							$erroremail .= "error: exception when inserting $row->userid, $row->unitid, $row->unitshortname, $row->unitfullname, $row->unitdescription, $row->startdate, $row->enddate " . $e->getMessage() . " \n ";
                            $trace->output($e->getMessage());
						}
					}
					if($atleastonesuccess) {
						$transaction->allow_commit();
					} else {
                        $transaction->rollback($e);
                        // $DB->force_transaction_rollback($e);
					}
				} catch (Exception $e) {
					// $transaction->rollback(e);
                    $DB->force_transaction_rollback();
                    $transaction->rollback($e);	

					$erroremail .= "error: exception when deleting records -> " . $e->getMessage() . " \n ";
				}
			} 	
			$rs->Close();
        } else {
            $extdb->Close();
            $trace->output('Error reading data from the external teachers and units tables');
            $trace->finished();
			$this->error_message_admins('Error reading data from the external teachers and units tables');
            return 2;
        }
        

        // Close db connection.
        $extdb->Close();

        $trace->output('...teachers and units synchronisation finished.');
        $trace->finished();
		if($erroremail) {
			$this->error_message_admins('Teachers and Units Sync Scheduled Task: '.$erroremail);
		}    
        return 0;
    }
	
	/**
     * Performs a full sync with external database.
     *
     * Replaces meta links with fresh info
     *
     * @param progress_trace $trace
     * @return int 0 means success, 1 db read failure
     */
    public function sync_meta(progress_trace $trace) {
        global $CFG, $DB;

		$erroremail = '';
		
        $trace->output('Starting meta synchronisation...');

        // We may need a lot of memory here.
        core_php_time_limit::raise();
        raise_memory_limit(MEMORY_HUGE);

		$newmeta = $DB->get_records('block_course_admin_meta');
		
		$existingsql = "SELECT * FROM {enrol} WHERE enrol = 'meta' AND " . $DB->sql_compare_text('customtext1') . " = 'enrol_collegedatabase'"; 
		$existingmeta = $DB->get_records_sql($existingsql);
		
		$removablemeta = array();
		$plugin = enrol_get_plugin('meta');
		
		//arrange in parent child structure
		foreach($existingmeta as $a) {
			if(!isset($removablemeta[$a->courseid])) {
				$removablemeta[$a->courseid] = array();
			}
			$removablemeta[$a->courseid][$a->customint1] = $a->id;
			//none of our metas should be disabled
			if($a->status == 1) {
				$instances = enrol_get_instances($a->courseid, false);
			    $instance = $instances[$a->id];
                if ($instance->status != ENROL_INSTANCE_ENABLED) {
                    $plugin->update_status($instance, ENROL_INSTANCE_ENABLED);
                }
				$trace->output("Existing meta link found in disabled state $a->id");
			}
		}

		foreach($newmeta as $meta)
		{
			//lookup moodle courseids
			$parentid = $DB->get_field('course', 'id', array('idnumber'=>$meta->parentid));
			$childid = $DB->get_field('course', 'id', array('idnumber'=>$meta->childid));
            $parentshortname = $DB->get_field('course', 'shortname', array('id'=>$parentid));
            $childshortname = $DB->get_field('course', 'shortname', array('id'=>$childid));
            $child = new \stdClass();
            $child->id = $childid;
            $child->summary = 'Child of '.$parentshortname.'<a href="'.$CFG->wwwroot.'/course/view.php?id='.$parentid.'">  <i class="fa fa-external-link"></i></a>';
            $parent = new \stdClass();
            $parent->id = $parentid;
            $parent->summary = 'Multiple Groups';
			
			if(!$parentid) {
				$erroremail .= "Parent course $meta->parentid cannot be found in Moodle \n";	
				$trace->output("Parent course $meta->parentid cannot be found in Moodle");
                continue;
			}
			
			if(!$childid) {
				$erroremail .= "Child course $meta->childid cannot be found in Moodle \n";	
				$trace->output("Child course $meta->childid cannot be found in Moodle");
                continue;
			}
			
			if(isset($removablemeta[$parentid][$childid])) {
				unset($removablemeta[$parentid][$childid]); 
			} else {
				$enrol = enrol_get_plugin('meta');
				$course = $DB->get_record('course', array('id'=>$parentid), '*', MUST_EXIST);
				$trace->output("Creating meta link for $meta->parentid $meta->childid ...");
				try {
					$eid = $enrol->add_instance($course, array('customint1' => $childid,'customtext1'=>'enrol_collegedatabase'));
					require_once("$CFG->dirroot/enrol/meta/locallib.php");
					enrol_meta_sync($course->id);
                    // Alter child course description to show its parent
                    $trace->output('Updating course summary for child '.$childshortname.' : '.$child->summary);
                    $DB->update_record('course', $child);
                    // Alter parent course description to indicate that it's a parent
                    $trace->output('Updating course summary for parent '.$parentshortname.' : '.$parent->summary);
                    $DB->update_record('course', $parent);
				} catch (Exception $e) {
					$erroremail .= "error: exception when creating meta link $parentid $childid -> " . $e->getMessage() . " \n ";
					$trace->output("error: exception when creating meta link $parentid $childid -> " . $e->getMessage());
				}							   
				if(!$eid) {
					$erroremail .= "Meta link could not be created for $meta->parentid $meta->childid $parentid $childid \n";	
					$trace->output("Meta link could not be created for $meta->parentid $meta->childid $parentid $childid");
				} else {
					$trace->output("OK");
				}
			}
			
		}   

		foreach($removablemeta as $par => $remove) {
		    foreach($remove as $child => $instanceid) {
			    $trace->output("Removing meta link for $par $child ...");
				$instances = enrol_get_instances($par, false);
          		$instance = $instances[$instanceid];
				try {
   			       $plugin->delete_instance($instance);
				   $trace->output("OK");
				   $childidnumber = $DB->get_field('course', 'idnumber', array('id'=>$child));
				   $childshortname = $DB->get_field('course', 'shortname', array('id'=>$child));
                   $parentidnumber = $DB->get_field('course', 'idnumber', array('id'=>$par));
                   $parentshortname = $DB->get_field('course', 'shortname', array('id'=>$par));
                   $child_out = new \stdClass();
                    $child_out->id = $child;
                    $child_out->summary = $DB->get_field('enrol_collegedb_teachunits', 'unitdescription', array('unitshortname'=>$childshortname));
                    $parent_out = new \stdClass();
                    $parent_out->id = $par;
                    $parent_out->summary = $DB->get_field('enrol_collegedb_teachunits', 'unitdescription', array('unitshortname'=>$parentshortname));
                    // Revert child course description to original when child link is removed
                      $trace->output('Updating course summary for course '.$childshortname.' : '.$child_out->summary);
                      $DB->update_record('course', $child_out);
                      // Revert parent course description to original when last child link is removed
                      if (!$DB->record_exists('block_course_admin_meta', array('parentid'=>$parentidnumber))) {
                          $trace->output('Updating course summary for course '.$parentshortname.' : '.$parent_out->summary);
                          $DB->update_record('course', $parent_out);
                      }
				   // unhide the child if it is not a child in another meta link and either a course or requested unit
                   if($childshortname && (!strpos($childshortname, '/') || $DB->record_exists('block_course_admin_courses', array('courseid'=>$childidnumber))) && !$DB->record_exists('block_course_admin_meta', array('childid'=>$childidnumber)) && !$DB->record_exists('block_course_admin_unenrol', array('courseid'=>$childidnumber)) ) {
				      require_once("$CFG->dirroot/course/lib.php");
					  course_change_visibility($child, true);
				   }

				} catch (Exception $e) {
					$erroremail .= "error: exception when removing meta link $par $child -> " . $e->getMessage() . " \n ";
				    $trace->output("error: exception when removing meta link $par $child -> " . $e->getMessage());
				}
			}
		}
		
		//hide children that are not also parents
		$hidethese = $DB->get_records_sql('SELECT id FROM {course} WHERE visible = 1 AND idnumber IN (SELECT distinct(childid) FROM {block_course_admin_meta}) AND idnumber NOT IN (SELECT distinct(parentid) FROM {block_course_admin_meta})');
		foreach($hidethese as $hide) {
		        $trace->output("Hiding child $hide->id ...");
			    require_once("$CFG->dirroot/course/lib.php");
				course_change_visibility($hide->id, false);
				$trace->output("OK");
		}
	
        $trace->output('...meta synchronisation finished.');
        $trace->finished();
		if($erroremail) {
			$this->error_message_admins('Teachers and Units Sync Scheduled Task: '.$erroremail);
		}    
        return 0;
    }
	
    protected function db_get_sql($table, array $conditions, array $fields, $distinct = false, $sort = "") {
        $fields = $fields ? implode(',', $fields) : "*";
        $where = array();
        if ($conditions) {
            foreach ($conditions as $key=>$value) {
                $value = $this->db_encode($this->db_addslashes($value));

                $where[] = "$key = '$value'";
            }
        }
        $where = $where ? "WHERE ".implode(" AND ", $where) : "";
        $sort = $sort ? "ORDER BY $sort" : "";
        $distinct = $distinct ? "DISTINCT" : "";
        $sql = "SELECT $distinct $fields
                  FROM $table
                 $where
                  $sort";

        return $sql;
    }

    /**
     * Tries to make connection to the external database.
     *
     * @return null|ADONewConnection
     */
    protected function db_init() {
        global $CFG;
        require_once($CFG->libdir.'/adodb/adodb.inc.php');

        // Connect to the external database (forcing new connection).
        $extdb = ADONewConnection($this->get_config('dbtype'));
        if ($this->get_config('debugdb')) {
            $extdb->debug = true;
            ob_start(); // Start output buffer to allow later use of the page headers.
        }

        // The dbtype my contain the new connection URL, so make sure we are not connected yet.
        if (!$extdb->IsConnected()) {
            $result = $extdb->Connect($this->get_config('dbhost'), $this->get_config('dbuser'), $this->get_config('dbpass'), $this->get_config('dbname'), true);
            if (!$result) {
                return null;
            }
        }

        $extdb->SetFetchMode(ADODB_FETCH_ASSOC);
        if ($this->get_config('dbsetupsql')) {
            $extdb->Execute($this->get_config('dbsetupsql'));
        }
        return $extdb;
    }

    protected function db_addslashes($text) {
        // Use custom made function for now - it is better to not rely on adodb or php defaults.
        if ($this->get_config('dbsybasequoting')) {
            $text = str_replace('\\', '\\\\', $text);
            $text = str_replace(array('\'', '"', "\0"), array('\\\'', '\\"', '\\0'), $text);
        } else {
            $text = str_replace("'", "''", $text);
        }
        return $text;
    }

    protected function db_encode($text) {
        $dbenc = $this->get_config('dbencoding');
        if (empty($dbenc) or $dbenc == 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach($text as $k=>$value) {
                $text[$k] = $this->db_encode($value);
            }
            return $text;
        } else {
            return core_text::convert($text, 'utf-8', $dbenc);
        }
    }

    protected function db_decode($text) {
        $dbenc = $this->get_config('dbencoding');
        if (empty($dbenc) or $dbenc == 'utf-8') {
            return $text;
        }
        if (is_array($text)) {
            foreach($text as $k=>$value) {
                $text[$k] = $this->db_decode($value);
            }
            return $text;
        } else {
            return core_text::convert($text, $dbenc, 'utf-8');
        }
    }

    /**
     * Automatic enrol sync executed during restore.
     * @param stdClass $course course record
     */
    public function restore_sync_course($course) {
        $trace = new null_progress_trace();
        $this->sync_enrolments($trace, $course->id);
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB;

        if ($instance = $DB->get_record('enrol', array('courseid'=>$course->id, 'enrol'=>$this->get_name()))) {
            $instanceid = $instance->id;
        } else {
            $instanceid = $this->add_instance($course);
        }
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    /**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $oldinstancestatus
     * @param int $userid
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        global $DB;

        if ($this->get_config('unenrolaction') == ENROL_EXT_REMOVED_UNENROL) {
            // Enrolments were already synchronised in restore_instance(), we do not want any suspended leftovers.
            return;
        }
        if (!$DB->record_exists('user_enrolments', array('enrolid'=>$instance->id, 'userid'=>$userid))) {
            $this->enrol_user($instance, $userid, null, 0, 0, ENROL_USER_SUSPENDED);
        }
    }

    /**
     * Restore role assignment.
     *
     * @param stdClass $instance
     * @param int $roleid
     * @param int $userid
     * @param int $contextid
     */
    public function restore_role_assignment($instance, $roleid, $userid, $contextid) {
        if ($this->get_config('unenrolaction') == ENROL_EXT_REMOVED_UNENROL or $this->get_config('unenrolaction') == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
            // Role assignments were already synchronised in restore_instance(), we do not want any leftovers.
            return;
        }
        role_assign($roleid, $userid, $contextid, 'enrol_'.$this->get_name(), $instance->id);
    }

    /**
     * Test plugin settings, print info to output.
     */
    public function test_settings() {
        global $CFG, $OUTPUT;

        // NOTE: this is not localised intentionally, admins are supposed to understand English at least a bit...

        raise_memory_limit(MEMORY_HUGE);

        $this->load_config();

        $enroltable = $this->get_config('remoteenroltable');
        $coursetable = $this->get_config('newcoursetable');

        if (empty($enroltable)) {
            echo $OUTPUT->notification('External enrolment table not specified.', 'notifyproblem');
        }

        if (empty($coursetable)) {
            echo $OUTPUT->notification('External course table not specified.', 'notifyproblem');
        }

        if (empty($coursetable) and empty($enroltable)) {
            return;
        }

        $olddebug = $CFG->debug;
        $olddisplay = ini_get('display_errors');
        ini_set('display_errors', '1');
        $CFG->debug = DEBUG_DEVELOPER;
        $olddebugdb = $this->config->debugdb;
        $this->config->debugdb = 1;
        error_reporting($CFG->debug);

        $adodb = $this->db_init();

        if (!$adodb or !$adodb->IsConnected()) {
            $this->config->debugdb = $olddebugdb;
            $CFG->debug = $olddebug;
            ini_set('display_errors', $olddisplay);
            error_reporting($CFG->debug);
            ob_end_flush();

            echo $OUTPUT->notification('Cannot connect the database.', 'notifyproblem');
            return;
        }

        if (!empty($enroltable)) {
            $rs = $adodb->Execute("SELECT *
                                     FROM $enroltable");
            if (!$rs) {
                echo $OUTPUT->notification('Can not read external enrol table.', 'notifyproblem');

            } else if ($rs->EOF) {
                echo $OUTPUT->notification('External enrol table is empty.', 'notifyproblem');
                $rs->Close();

            } else {
                $fields_obj = $rs->FetchObj();
                $columns = array_keys((array)$fields_obj);

                echo $OUTPUT->notification('External enrolment table contains following columns:<br />'.implode(', ', $columns), 'notifysuccess');
                $rs->Close();
            }
        }

        if (!empty($coursetable)) {
            $rs = $adodb->Execute("SELECT *
                                     FROM $coursetable");
            if (!$rs) {
                echo $OUTPUT->notification('Can not read external course table.', 'notifyproblem');

            } else if ($rs->EOF) {
                echo $OUTPUT->notification('External course table is empty.', 'notifyproblem');
                $rs->Close();

            } else {
                $fields_obj = $rs->FetchObj();
                $columns = array_keys((array)$fields_obj);

                echo $OUTPUT->notification('External course table contains following columns:<br />'.implode(', ', $columns), 'notifysuccess');
                $rs->Close();
            }
        }

        $adodb->Close();

        $this->config->debugdb = $olddebugdb;
        $CFG->debug = $olddebug;
        ini_set('display_errors', $olddisplay);
        error_reporting($CFG->debug);
        ob_end_flush();
    }
}
