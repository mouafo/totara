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


require_once('../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once('../lib.php');
require_once('lib.php');

$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);

/// Setup / loading data
$sitecontext = get_context_instance(CONTEXT_SYSTEM);

// Setup page and check permissions
admin_externalpage_setup('objectivescales');

if($delete) {
    if(!$scale = get_record('dp_objective_scale', 'id', $delete)) {
       print_error('error:invalidobjectivescaleid', 'local_plan');
    }

    if($confirm) {
        if (!confirm_sesskey()) {
            print_error('confirmsesskeybad', 'error');
        }

        delete_records('dp_objective_scale_value', 'objscaleid', $scale->id); // Delete scale values
        delete_records('dp_objective_scale', 'id', $scale->id); // Delete scale itself
        totara_set_notification(get_string('deletedobjectivescalevalue', 'local_plan'), $CFG->wwwroot.'/local/plan/objectivescales/index.php');

    } else {
        $returnurl = "{$CFG->wwwroot}/local/plan/objectivescales/index.php";
        $deleteurl = "{$CFG->wwwroot}/local/plan/objectivescales/index.php?delete={$delete}&amp;confirm=1&amp;sesskey=" . sesskey();

        admin_externalpage_print_header();
        $strdelete = get_string('deletecheckobjective', 'local_plan');

        notice_yesno(
            "{$strdelete}<br /><br />".format_string($scale->name),
            $deleteurl,
            $returnurl
        );

        print_footer();
        exit;
    }
}

/// Build page
admin_externalpage_print_header();

//$idp = new idp();
$priorities = dp_get_objectives();
dp_objective_display_table($priorities, $editingon=1);

admin_externalpage_print_footer();

?>