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

defined('MOODLE_INTERNAL') || die();

global $OUTPUT, $DB;

require_once ("$CFG->dirroot/totara/core/db/utils.php");
$dbman = $DB->get_manager(); // loads ddl manager and xmldb classes

//fix 1.1-series capabilities
$result = totara_upgrade_capabilities();
if ($result) {
    upgrade_log(UPGRADE_LOG_NORMAL, 'totara/core', 'Capabilities upgraded from 1.1');
    echo $OUTPUT->notification(get_string('success'), 'notifysuccess');
    print_upgrade_separator();
} else {
    throw new upgrade_exception("Totara Upgrade Capabilities Failed", '1.1 to 2.2 upgrade');
}
//fix blocks where name has changed
$sql = "UPDATE {block} SET name=? WHERE name=?";
$params = array('totara_quicklinks', 'quicklinks');
$result = $result && $DB->execute($sql, $params);
if ($result) {
    upgrade_log(UPGRADE_LOG_NORMAL, 'totara/core', 'Totara Blocks renamed');
    echo $OUTPUT->notification(get_string('success'), 'notifysuccess');
    print_upgrade_separator();
} else {
    throw new upgrade_exception("Totara Set Block Names Failed", '1.1 to 2.2 upgrade');
}

//rename manager to staff_manager to avoid breaking roles
$mgrrole = $DB->get_record('role', array('shortname' => 'manager'));
if ($mgrrole) {
    $mgrrole->shortname = 'staffmanager';
    $mgrrole->name = get_string('staffmanager', 'totara_core');
    $DB->update_record('role', $mgrrole);
    //remove legacy/manager capability: this is used later during upgrade_core to add archetype='manager',
    //which in turn then means this role would get lots of module capabilities during the upgrade_non_core module upgrade process
    $params = array('capability' => 'moodle/legacy:manager', 'roleid' => $mgrrole->id);
    $DB->delete_records('role_capabilities', $params);
    upgrade_log(UPGRADE_LOG_NORMAL, 'totara/core', 'Totara Manager Role renamed');
    echo $OUTPUT->heading('Totara Manager Role renamed');
    echo $OUTPUT->notification(get_string('success'), 'notifysuccess');
    print_upgrade_separator();
}

//remove extensions and spaces from icons in database - 5 possible tables
$tables = array('course', 'prog', 'pos_type', 'org_type', 'comp_type');
foreach ($tables as $table) {
    $like_sql = $DB->sql_like('icon', '?');
    $sql = "SELECT id, icon FROM {{$table}}
        WHERE ($like_sql OR $like_sql)";
    $rs = $DB->get_records_sql($sql, array('%.gif', '%.png'));
    foreach ($rs as $r) {
        $r->icon = str_replace(" ", "-", $r->icon);
        $r->icon = str_replace(".png", "", $r->icon);
        $r->icon = str_replace(".gif", "", $r->icon);
        $DB->update_record($table, $r);
        upgrade_set_timeout();
    }
}
upgrade_log(UPGRADE_LOG_NORMAL, 'totara/core', 'Totara icon extensions fixed');
echo $OUTPUT->notification(get_string('success'), 'notifysuccess');
print_upgrade_separator();

// Changing nullability of char fields
totara_fix_nullable_charfield('comp_type', 'description', 'shortname', 'medium', null, null, null, XMLDB_TYPE_TEXT);
totara_fix_nullable_charfield('comp_scale', 'description', 'name', 'medium', null, null, null, XMLDB_TYPE_TEXT);
totara_fix_nullable_charfield('course_info_field', 'shortname', 'fullname', '100');
totara_fix_nullable_charfield('course_info_field', 'datatype', 'shortname', '255');
totara_fix_nullable_charfield('oldpassword', 'hash', 'uid', '100');

$idx = array();
$idx[] = new xmldb_index('remi_typ_ix', XMLDB_INDEX_NOTUNIQUE, array('type'));
totara_fix_nullable_charfield('reminder', 'type', 'title', '10', null, null, $idx);

$idx = array();
$idx[] = new xmldb_index('remimess_typ_ix', XMLDB_INDEX_NOTUNIQUE, array('type'));
totara_fix_nullable_charfield('reminder_message', 'type', 'reminderid', '10', null, null, $idx);

totara_fix_nullable_charfield('errorlog', 'version', 'timeoccured', '255');
totara_fix_nullable_charfield('errorlog', 'build', 'version', '255');
totara_fix_nullable_charfield('errorlog', 'hash', 'details', '32');
upgrade_log(UPGRADE_LOG_NORMAL, 'totara/core', 'Change nullable character fields');
echo $OUTPUT->notification(get_string('success'), 'notifysuccess');
print_upgrade_separator();

//Remove obsolete report heading table
$table = new xmldb_table('report_heading_items');
if ($dbman->table_exists($table)) {
    $dbman->drop_table($table);
}
upgrade_log(UPGRADE_LOG_NORMAL, 'totara/core', 'Remove obsolete report heading table');
echo $OUTPUT->notification(get_string('success'), 'notifysuccess');
print_upgrade_separator();

//remove obsolete category database tables
$removetables = array('course', 'comp_type', 'org_type', 'pos_type');
foreach ($removetables as $prefix) {
    $table = new xmldb_table($prefix . '_info_category');
    if ($dbman->table_exists($table)) {
        $dbman->drop_table($table);
    }
}
upgrade_log(UPGRADE_LOG_NORMAL, 'totara/core', 'Remove obsolete info_category tables');
echo $OUTPUT->notification(get_string('success'), 'notifysuccess');
print_upgrade_separator();

// rename 'completedate' field to 'timeend' in 'course_completion_criteria'
$table = new xmldb_table('course_completion_criteria');
$field = new xmldb_field('completedate', XMLDB_TYPE_INTEGER, 10, XMLDB_UNSIGNED, null, null, null, 'enrolperiod');
$dbman->rename_field($table, $field, 'timeend');

upgrade_log(UPGRADE_LOG_NORMAL, 'totara/core', 'Course completion criteria field renamed');
echo $OUTPUT->notification(get_string('success'), 'notifysuccess');
print_upgrade_separator();

?>