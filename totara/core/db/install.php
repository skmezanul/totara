<?php
/*
* This file is part of Totara LMS
*
* Copyright (C) 2012 Totara Learning Solutions LTD
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* @author Ciaran Irvine <ciaran.irvine@totaralms.com>
* @package totara
* @subpackage totara_core
*/

function xmldb_totara_core_install() {
    global $CFG, $DB, $SITE;

    $dbman = $DB->get_manager(); // loads ddl manager and xmldb classes
    $systemcontext = context_system::instance();
    // add coursetype and icon fields to course table

    $table = new xmldb_table('course');

    $field = new xmldb_field('coursetype');
    if (!$dbman->field_exists($table, $field)) {
        $field->set_attributes(XMLDB_TYPE_INTEGER, '4', XMLDB_UNSIGNED, null, null, null, null);
        $dbman->add_field($table, $field);
    }

    $field = new xmldb_field('icon');
    if (!$dbman->field_exists($table, $field)) {
        $field->set_attributes(XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $dbman->add_field($table, $field);
    }

    // rename the moodle 'manager' fullname to "Site Manager" to make it
    // distinct from the totara "Staff Manager"
    if ($managerroleid = $DB->get_field('role', 'id', array('shortname' => 'manager', 'name' => get_string('manager', 'role')))) {
        $todb = new stdClass();
        $todb->id = $managerroleid;
        $todb->name = get_string('sitemanager', 'totara_core');
        $DB->update_record('role', $todb);
    }

    // TODO: SCANMSG Need to look at the stuff below and figure out what is still needed

    //code from old postinst function:
    set_config('theme', 'totara');
    set_config("langmenu", 0);
    /// Insert default records
    $defaultdir = $CFG->dirroot.'/totara/core/db/default';
    $includes = array();
    if (is_dir($defaultdir)) {
        // files installed in alphabetical order so use
        // number prefix to set desired order
        foreach (scandir($defaultdir) as $file) {
            // exclude dot directories
            if ($file == '.' || $file == '..') {
                continue;
            }
            // not a php file
            if (substr($file, -4) != '.php') {
                continue;
            }
            // include default data file
            $includes[] = $CFG->dirroot.'/totara/core/db/default/'.$file;
        }
    }
    // sort so order of includes is known
    sort($includes);
    foreach ($includes as $include) {
        include($include);
    }

    // Create totara roles
    $manager             = $DB->get_record('role', array('shortname' => 'manager'));
    $managerrole         = $manager->id;
    $staffmanagerrole    = create_role(get_string('staffmanager'), 'staffmanager', get_string('staffmanagerdescription'), 'staffmanager');
    $assessorrole        = create_role(get_string('assessor'), 'assessor', get_string('assessordescription'));
    $regionalmanagerrole = create_role(get_string('regionalmanager'), 'regionalmanager', get_string('regionalmanagerdescription'));
    $regionaltrainerrole = create_role(get_string('regionaltrainer'), 'regionaltrainer', get_string('regionaltrainerdescription'));

    $defaultallowassigns = array(
        array($managerrole, $staffmanagerrole),
        array($managerrole, $assessorrole),
        array($managerrole, $regionalmanagerrole),
        array($managerrole, $regionaltrainerrole)
    );
    foreach ($defaultallowassigns as $allow) {
        list($fromroleid, $toroleid) = $allow;
        allow_assign($fromroleid, $toroleid);
    }

    $defaultallowoverrides = array(
        array($managerrole, $staffmanagerrole),
        array($managerrole, $assessorrole),
        array($managerrole, $regionalmanagerrole),
        array($managerrole, $regionaltrainerrole)
    );
    foreach ($defaultallowoverrides as $allow) {
        list($fromroleid, $toroleid) = $allow;
        allow_override($fromroleid, $toroleid); // There is a rant about this in MDL-15841.
    }

    $defaultallowswitch = array(
        array($managerrole, $staffmanagerrole),
    );
    foreach ($defaultallowswitch as $allow) {
        list($fromroleid, $toroleid) = $allow;
        allow_switch($fromroleid, $toroleid);
    }

    set_role_contextlevels($staffmanagerrole,   get_default_contextlevels('staffmanager'));
    assign_capability('moodle/user:viewdetails', CAP_ALLOW, $staffmanagerrole, $systemcontext->id, true);
    assign_capability('moodle/cohort:view', CAP_ALLOW, $staffmanagerrole, $systemcontext->id, true);
    assign_capability('moodle/comment:view', CAP_ALLOW, $staffmanagerrole, $systemcontext->id, true);
    assign_capability('moodle/comment:delete', CAP_ALLOW, $staffmanagerrole, $systemcontext->id, true);
    assign_capability('moodle/comment:post', CAP_ALLOW, $staffmanagerrole, $systemcontext->id, true);
    $systemcontext->mark_dirty();
    set_role_contextlevels($assessorrole,       get_default_contextlevels('teacher'));

    $role_to_modify = array(
        'editingteacher' => 'editingtrainer',
        'teacher' => 'trainer',
        'student' => 'learner'
    );

    foreach ($role_to_modify as $old => $new) {
        if ($old_role = $DB->get_record('role', array('shortname' => $old))) {
            $new_role = new stdClass();
            $new_role->id = $old_role->id;
            $new_role->name = get_string($new);
            $new_role->description = get_string($new . 'description');

            $DB->update_record('role', $new_role);
        }
    }


    // set up blocks
    totara_reset_frontpage_blocks();

    // set up frontpage
    set_config('frontpage', '');
    set_config('frontpageloggedin', '');
    set_config('allowvisiblecoursesinhiddencategories', '1');

    rebuild_course_cache($SITE->id);

    return true;
}