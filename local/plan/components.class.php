<?php


abstract class dp_base_component {
    protected $plan;
    // get access to the plan object
    function __construct($plan) {
        $this->plan = $plan;

        // check that child classes implement required properties
        $properties = array(
            'component',
            'permissions',
        );
        foreach($properties as $property) {
            if(!property_exists($this, $property)) {
                throw new Exception("Property '$property' must be set in class " .
                    get_class($this));
            }
        }
    }

    function initialize_settings(&$settings) {
        // override this method in child classes to add component-specific
        // settings to plan's setting property
    }

    function get_setting($action) {
        return $this->plan->get_component_setting($this->component, $action);
    }

    function display_duedate($itemid, $duedate) {
        $plancompleted = $this->plan->status == DP_PLAN_STATUS_COMPLETE;
        $cansetduedate = !$plancompleted && ($this->get_setting('setduedate') == DP_PERMISSION_ALLOW);

        $out = '';

        // only show a form if they have permission to change due dates
        if($cansetduedate) {
            $out .= $this->display_duedate_as_form($duedate, "duedate[$itemid]");
        } else {
            $out .= $this->display_duedate_as_text($duedate);
        }

        return $out;

    }

    function display_duedate_as_form($duedate, $name) {
        // @todo add date picker?
        global $CFG;
        $duedatestr = !empty($duedate) ?
            userdate($duedate, '%d/%m/%y', $CFG->timezone, false) : '';
        return '<input type="text" name="'.$name.'" value="'. $duedatestr . '" size="8" maxlength="20"/>';
    }

    function display_duedate_as_text($duedate) {
        global $CFG;
        if (!empty($duedate)) {
            return userdate($duedate, '%e %h %Y', $CFG->timezone, false);
        } else {
            return '';
        }
    }

    function display_duedate_highlight_info($duedate) {
        $out = '';
        $now = time();
        if (!empty($duedate)) {
            if(($duedate < $now) && ($now - $duedate < 60*60*24)) {
                $out .= '<span class="plan_highlight">' . get_string('duetoday', 'local_plan') . '</span>';
            } else if($duedate < $now) {
                $out .= '<span class="plan_highlight">' . get_string('overdue', 'local_plan') . '</span>';
            } else if ($duedate - $now < 60*60*24*7) {
                $days = ceil(($duedate - $now)/(60*60*24));
                $out .= '<span class="plan_highlight">' . get_string('dueinxdays', 'local_plan', $days) . '</span>';
            }
        }
        return $out;
    }

    function display_priority($ca, $priorityvalues) {
        // @todo if $ca->priority is 0, but prioritymode has been
        // changed to required, it currently defaults to the highest value.
        // Change to use default priority instead
        $plancompleted = ($this->plan->status == DP_PLAN_STATUS_COMPLETE);
        $cansetpriority = !$plancompleted && ($this->get_setting('setpriority') == DP_PERMISSION_ALLOW);
        $priorityrequired = ($this->get_setting('prioritymode') == DP_PRIORITY_REQUIRED);
        $out = '';

        if ($cansetpriority) {
            // show a pulldown menu of priority options
            $out .= $this->display_priority_picker("priorities[{$ca->id}]", $ca->priority, $priorityvalues, $priorityrequired);
        } else {
            // just display priority if no permissions to set it
            $out .= $this->display_priority_as_text($ca->priority, $ca->priorityname, $priorityvalues);
        }

        return $out;
    }

    function display_priority_picker($name, $priorityid, $priorityvalues, $priorityrequired=false) {

        if (!$priorityvalues) {
            return '';
        }

        $choose = ($priorityrequired) ? null : get_string('none','local_plan');
        $chooseval = ($priorityrequired) ? null : 0;

        $options = array();

        foreach($priorityvalues as $id => $val) {
            $options[$id] = $val->name;
        }

        return choose_from_menu($options, $name, $priorityid, $choose, '', $chooseval, true);

    }

    function display_priority_as_text($priorityid, $priorityname, $priorityvalues) {
        // class (for styling priorities) is of the format:
        // priorityXofY
        // theme only defines styles up to DP_MAX_PRIORITY_OPTIONS so limit
        // the highest values set to this range
        $class = 'priority' .
            min($priorityvalues[$priorityid]->sortorder, DP_MAX_PRIORITY_OPTIONS) .
            'of' .
            min(count($priorityvalues), DP_MAX_PRIORITY_OPTIONS);

        return '<span class="'.$class.'">'.$priorityname.'</span>';
    }

    function display_back_to_index_link() {
        global $CFG;
        return '<p><a href="' . $CFG->wwwroot . '/local/plan/components/' .
            $this->component . '/index.php?id=' . $this->plan->id . '">' .
            get_string('backtoallx','local_plan', $this->get_setting('name')) .
            '</a></p>';
    }

    /**
     * Display approval functionality for a component assignment
     *
     * @param $obj stdClass the assignment object
     * @param $canapprove boolean if approve/decline actions are allowed
     * @return $out string an html string
     */
    function display_approval($obj, $canapprove) {
        global $CFG;
        // @todo lang strings
        $id = $obj->id;
        $approvalstatus = $obj->approved;
        $murl = new moodle_url(qualified_me());
        $out = '';

        switch($approvalstatus) {
        case DP_APPROVAL_DECLINED:
            $out .= '<span class="plan_highlight">' . get_string('declined', 'local_plan') . '</span>';
            break;
        case DP_APPROVAL_UNAPPROVED:
            // @todo create new icon instead of reusing XSS one
            $out .= '<img src="'.$CFG->pixpath.'/i/risk_xss.gif" />';
            $out .= '<span class="plan_highlight">' . get_string('pendingapproval', 'local_plan') . '</span><br />';
            if($canapprove) {
                $out .= $this->display_approval_options($obj, $approvalstatus);
            } else {
                // @todo write reminder code
                $murl->param('assignmentid', $obj->id);
                $murl->param('type', $this->component);
                $murl->param('action', 'approvalremind');
                $out .= '<a href="'.$murl->out_action().'">'.
                    '<img src="'.$CFG->themewww.'/'.$CFG->theme.'/pix/i/marker.gif" title="'.get_string('sendapprovalreminder', 'local_plan').'" alt="'.get_string('sendapprovalreminder', 'local_plan').'" /></a> ';
            }
            break;
        case DP_APPROVAL_APPROVED:
        default:
            // display nothing
            break;
        }

        return $out;
    }

    /**
     * Display approval options for most components
     *
     * This method is overridded by competency subclass to show links instead
     *
     * @param stdClass $obj, The assignment object
     * @param integer $approvalstatus The currently selected approval status
     *
     * @return string The html for an approval picker
     */
    function display_approval_options($obj, $approvalstatus) {
        $name = 'approve[' . $obj->id . ']';
        return dp_display_approval_options($name, $approvalstatus);
    }

    /**
    * Get items assigned to this component (if relevant - to be overridden by children classes
    *
    * @return array and array of data objects
    */
    function get_assigned_items() {
        return array();
    }

    function process_action($action) {
        // General component actions can come in here
        // Override this method in children for more specific actions
    }

    abstract static public function add_settings_form(&$mform, $id);
    abstract static public function process_settings_form($fromform, $id);

    /**
     * Get all instances of $componentrequired linked to the specified item
     *
     * @todo doesn't current exclude unapproved items
     * that is currently handled inside display_linked_*() methods
     * but might be better to do it here?
     *
     * @param integer $id Identifies the item to get linked items for
     * @param string $componentrequired Get linked items of this type
     *
     * @return array Array of IDs of all linked items, or false
     */
    function get_linked_components($id, $componentrequired) {
        global $CFG;
        // name of the current component
        $thiscomponent = $this->component;

        // component relations are stored alphabetically
        // first component is in component1
        // Figure out which order to perform query
        switch (strcmp($thiscomponent, $componentrequired)) {
        case -1:
            $matchedcomp = 'component1';
            $matchedid = 'itemid1';
            $searchedcomp = 'component2';
            $searchedid = 'itemid2';
            break;
        case 1:
            $matchedcomp = 'component2';
            $matchedid = 'itemid2';
            $searchedcomp = 'component1';
            $searchedid = 'itemid1';
            break;
        case 0:
        default:
            // linking within the same component not supported
            return false;
        }

        // find all matching relations
        $sql = "SELECT id, $searchedid " . sql_as() . " itemid
            FROM {$CFG->prefix}dp_plan_component_relation
            WHERE $matchedcomp = '$thiscomponent' AND
                $matchedid = $id AND
                $searchedcomp = '$componentrequired'";
        // return an array of IDs
        if($result = get_records_sql($sql)) {
            $out = array();
            foreach($result as $item) {
                $out[] = $item->itemid;
            }
            return $out;
        } else {
            // no matches
            return false;
        }
    }

    /**
     * Count instances of $componentrequired linked to items of this component type
     *
     * @param string $componentrequired Get linked items of this type
     * @return array Array of matches
     */
    function get_all_linked_components($componentrequired) {
        global $CFG;
        // name of the current component
        $thiscomponent = $this->component;

        // component relations are stored alphabetically
        // first component is in component1
        // Figure out which order to perform query
        switch (strcmp($thiscomponent, $componentrequired)) {
        case -1:
            $matchedcomp = 'component1';
            $matchedid = 'itemid1';
            $searchedcomp = 'component2';
            $searchedid = 'itemid2';
            break;
        case 1:
            $matchedcomp = 'component2';
            $matchedid = 'itemid2';
            $searchedcomp = 'component1';
            $searchedid = 'itemid1';
            break;
        case 0:
        default:
            // linking within the same component not supported
            return false;
        }

        // @todo doesn't current exclude unapproved items
        $sql = "SELECT $matchedid " . sql_as() . " id,
                COUNT($searchedid) " . sql_as() . " items
            FROM {$CFG->prefix}dp_plan_component_relation
            WHERE $matchedcomp = '$thiscomponent' AND
                  $searchedcomp = '$componentrequired'
            GROUP BY $matchedid";
        // @todo return as assocative array instead?
        // key = itemid
        // value = count
        // use get_records_sql_menu()?
        return get_records_sql($sql);
    }


    /**
     * Update assigned items
     *
     * @access  public
     * @param   $items  array   Array of item ids
     * @return  void
     */
    public function update_assigned_items($items) {

        // Get currently assigned items
        $assigned = $this->get_assigned_items();

        if ($items) {
            foreach ($items as $itemid) {

                // Validate id
                if (!is_numeric($itemid)) {
                    error('Supplied bad data - non numeric id');
                }

                // Check if not already assigned
                if (!isset($assigned[$itemid])) {
                    $this->assign_new_item($itemid);
                }

                // Remove from list to prevent deletion
                unset($assigned[$itemid]);
            }
        }

        // Remaining items to be deleted
        foreach ($assigned as $item) {
            $this->unassign_item($item);
        }
    }


    /**
     * Unassign an item from a plan
     *
     * @access  public
     * @return  mixed   An ADODB RecordSet object with the results from the SQL call or false.
     */
    public function unassign_item($item) {
        return delete_records(
            'dp_plan_'.$this->component.'_assign',
            'id', $item->itemid,
            'planid', $this->plan->id
        );
    }

    public function get_default_priority() {
        if (!$comp = $this->plan->get_component($this->component)) {
            return 0;
        }
        if ($comp->get_setting('prioritymode') != DP_PRIORITY_REQUIRED) {
            // Don't bother if priorities aren't required
            return 0;
        }

        $scale = get_record('dp_priority_scale', 'id', $comp->get_setting('priorityscale'));

        return $scale ? $scale->defaultid : 0;
    }
}