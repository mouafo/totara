<?php
@raise_memory_limit('392M');
@ini_set('max_execution_time','3000');
print "Loading data for table 'position_depth'<br>";
$items = array(array('id' => '1','fullname' => 'General Positions','shortname' => 'General','description' => '','depthlevel' => '1','frameworkid' => '1','timecreated' => '1263434099','timemodified' => '1267682907','usermodified' => '2',),
);
print "\n";print "Inserting ".count($items)." records<br />\n";
$i=1;
foreach($items as $item) {
    if(get_field('position_depth', 'id', 'id', $item['id'])) {
        print "Record with id of {$item['id']} already exists!<br>\n";
        continue;
    }
    $newid = insert_record('position_depth',(object) $item);
    if($newid != $item['id']) {
        if(!set_field('position_depth', 'id', $item['id'], 'id', $newid)) {
            print "Could not change id from $newid to {$item['id']}<br>\n";
            continue;
        }
    }
    // record the highest id in the table
    $maxid = get_field_sql('SELECT '.sql_max('id').' FROM '.$CFG->prefix.'position_depth');
    // make sure sequence is higher than highest ID
    bump_sequence('position_depth', $CFG->prefix, $maxid);
    // print output
    // 1 dot per 10 inserts
    if($i%10==0) {
        print ".";
        flush();
    }
    // new line every 200 dots
    if($i%2000==0) {
        print $i." <br>";
    }
    $i++;
}
print "<br>";