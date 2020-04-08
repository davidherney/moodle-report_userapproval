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
 * A report to display an approval indicator summary
 *
 * @package    report_userapproval
 * @copyright 2017 David Herney Bernal - cirano
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once('locallib.php');
require_once('filters/lib.php');

$sort           = optional_param('sort', 'firstname', PARAM_ALPHANUM);
$dir            = optional_param('dir', 'ASC', PARAM_ALPHA);
$page           = optional_param('page', 0, PARAM_INT);
$perpage        = optional_param('perpage', 30, PARAM_INT);
$format         = optional_param('format', '', PARAM_ALPHA);
$who            = optional_param('who', 'summary', PARAM_ALPHA);


admin_externalpage_setup('reportuserapproval', '', null, '', array('pagelayout' => 'report'));

$baseurl = new moodle_url('/report/userapproval/index.php', array('sort' => $sort, 'dir' => $dir, 'perpage' => $perpage, 'page'=>$page));

require_login();

$context = context_system::instance();

// create the user filter form
$filtering = new userapproval_filtering();

if (has_capability('report/userapproval:viewall', $context)) {
    $extrasql = '';
} else {
    $fieldid = $DB->get_field('user_info_field', 'id', array('shortname' => 'boss'));
    $extrasql = "id IN (SELECT userid FROM {user_info_data} WHERE fieldid={$fieldid} AND data = '{$USER->username}')";
}

list($extrasql, $params) = $filtering->get_sql_filter($extrasql);

if ($format) {
    $perpage = 0;
}

$users = get_users_listing($sort, $dir, null, null, '', '', '',
        $extrasql, $params, $context);
$usercount = get_users(false);
$usersearchcount = get_users(false, '', false, null, "", '', '', '', '', '*', $extrasql, $params);

$courses = $DB->get_records('course', array('enablecompletion' => 1), '', '*', $page * $perpage, $perpage);
$coursecount = $DB->count_records('course', array('enablecompletion' => 1));

$coursestosummary = $DB->get_records('course', array('enablecompletion' => 1), '', '*');

if ($courses) {

    $categories = $DB->get_records('course_categories');

    $stringcolumns = array(
        'id' => 'id',
        'fullname' => get_string('course'),
        'startdate' => get_string('startdate', 'report_userapproval'),
        'enddate' => get_string('enddate', 'report_userapproval'),
        'timecompleted' => get_string('timecompleted', 'report_userapproval'),
        'category' => get_string('category'),
        'student' => get_string('student', 'report_userapproval'),
        'username' => get_string('username'),
        'notcompleted' => get_string('notcompletedlabel', 'report_userapproval'),
        'enrolledusers' => get_string('enrolledusers', 'enrol'),
        'completedpercent' => get_string('completedpercent', 'report_userapproval')
    );

    $strcsystem = get_string('categorysystem', 'report_userapproval');
    $strftimedate = get_string('strftimedatetimeshort');
    $strfdate = get_string('strftimedatefullshort');
    $strnever = get_string('never');

    // Only download data.
    if ($format) {
        $courseid = optional_param('courseid', 0, PARAM_INT);

        if ($courseid) {
            $course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
            $courses = array($course);
        }

        if ($who == 'summary') {
            $columns = array('id', 'fullname', 'category', 'enrolledusers', 'notcompleted', 'completedpercent');
        } else {
            $columns = array('id', 'fullname', 'category', 'username', 'student');

            if ($who != 'notcompleted') {
                $columns[] = 'timecompleted';
            }
        }

        $fields = array();
        foreach ($columns as $column) {
            $fields[$column] = $stringcolumns[$column];
        }

        $data = array();

        foreach ($courses as $row) {

            $coursecontext = context_course::instance($row->id);

            $textcats = '';

            if (!$row->category) {
                $textcats = $strcsystem;
            } else {
                $cats = trim($categories[$row->category]->path, '/');
                $cats = explode('/', $cats);
                foreach ($cats as $key => $cat) {
                    if (!empty($cat)) {
                        $cats[$key] = $categories[$cat]->name;
                    }
                }

                $textcats = implode(' / ', $cats);
            }

            $sql = 'SELECT ra.id, ra.roleid, cc.timecompleted AS timecompleted, ra.userid
                        FROM {role_assignments} AS ra
                        LEFT JOIN {course_completions} AS cc ON cc.course = :courseid AND cc.userid = ra.userid
                        WHERE ra.contextid = :contextid AND ra.roleid IN (' . $CFG->gradebookroles . ')';
            $rolecounts = $DB->get_records_sql($sql, array('contextid' => $coursecontext->id, 'courseid' => $row->id));

            $userslist = array();
            if ($rolecounts & count($rolecounts) > 0) {
                $enrolledusers = 0;
                $enrolleduserscompletion = 0;

                foreach ($rolecounts as $oneassign) {

                    $user = null;
                    foreach ($users as $oneuser) {
                        if ($oneuser->id == $oneassign->userid) {
                            $user = $oneuser;
                            break;
                        }
                    }

                    if (!$user) {
                        continue;
                    }

                    $enrolledusers++;

                    if ($who == 'summary') {
                        if ($oneassign->timecompleted) {
                            $enrolleduserscompletion++;
                        }
                    } else {

                        if ($who == 'completed' && !$oneassign->timecompleted) {
                            continue;
                        } else if ($who == 'notcompleted' && $oneassign->timecompleted) {
                            $enrolleduserscompletion++;
                            continue;
                        }

                        $userinfo = new stdClass();
                        $userinfo->id       = $user->id;
                        $userinfo->fullname = '';
                        $userinfo->category = '';
                        $userinfo->username = $user->username;
                        $userinfo->student  = fullname($user);

                        if ($who != 'notcompleted') {
                            if ($oneassign->timecompleted) {
                                $userinfo->timecompleted = userdate($oneassign->timecompleted, $strftimedate);
                                $enrolleduserscompletion++;
                            } else {
                                $userinfo->timecompleted = $stringcolumns['notcompleted'];
                            }
                        }

                        $userslist[] = $userinfo;
                    }
                }

                $enrolleduserspercent = $enrolledusers == 0 ? 0 : round($enrolleduserscompletion * 100 / $enrolledusers);

            } else {
                $enrolledusers = 0;
                $enrolleduserscompletion = 0;
                $enrolleduserspercent = 0;
            }

            $datarow = new stdClass();
            $datarow->id = $row->id;
            $datarow->fullname = $row->fullname;
            $datarow->category = $textcats;
            $data[] = $datarow;

            if ($who != 'summary') {
                $datarow->username = '';

                if ($who != 'notcompleted') {
                    if ($who == 'all') {
                        $datarow->student = $enrolledusers;
                        $datarow->timecompleted = $enrolleduserscompletion;
                    } else {
                        $datarow->student = $enrolleduserscompletion;
                    }
                } else {
                    $datarow->student = $enrolledusers - $enrolleduserscompletion;
                }


                $data = array_merge($data, $userslist);
            } else {
                $datarow->notcompleted = $enrolledusers - $enrolleduserscompletion;
                $datarow->enrolledusers = $enrolledusers;
                $datarow->completedpercent = $enrolledusers === 0 ? '' : $enrolleduserspercent . '%';
            }

        }

        switch ($format) {
            case 'csv' : userapproval_download_csv($fields, $data);
            case 'ods' : userapproval_download_ods($fields, $data);
            case 'xls' : userapproval_download_xls($fields, $data);

        }
        die;
    }
    // End download data.
}

echo $OUTPUT->header();

flush();


$content = '';

if ($coursestosummary) {
    $enrolledusers = 0;
    $enrolleduserscompletion = 0;
    $enrolledexists = false;

    foreach ($coursestosummary as $row) {

        $coursecontext = context_course::instance($row->id);

        $sql = 'SELECT ra.id, ra.roleid, cc.timecompleted AS timecompleted, ra.userid
                    FROM {role_assignments} AS ra
                    LEFT JOIN {course_completions} AS cc ON cc.course = :courseid AND cc.userid = ra.userid
                    WHERE ra.contextid = :contextid AND ra.roleid IN (' . $CFG->gradebookroles . ')';
        $rolecounts = $DB->get_records_sql($sql, array('contextid' => $coursecontext->id, 'courseid' => $row->id));

        if ($rolecounts && count($rolecounts) > 0) {
            $enrolledexists = true;

            foreach ($rolecounts as $oneassign) {
                $user = null;
                foreach ($users as $oneuser) {
                    if ($oneuser->id == $oneassign->userid) {
                        $user = $oneuser;
                        break;
                    }
                }

                if (!$user) {
                    continue;
                }

                $enrolledusers++;
                if ($oneassign->timecompleted) {
                    $enrolleduserscompletion++;
                }
            }
        }
    }

    $content .= html_writer::tag('h3', get_string('summary', 'report_userapproval'));
    if ($enrolledusers == 0) {
        if ($enrolledexists) {
            $content .= html_writer::tag('p', get_string('notenrolledusersfilter', 'report_userapproval'));
        } else {
            $content .= html_writer::tag('p', get_string('notenrolledusers', 'report_userapproval'));
        }
    } else {
        $enrolleduserspercent = $enrolledusers == 0 ? 0 : round($enrolleduserscompletion * 100 / $enrolledusers);

        if ($enrolleduserscompletion == 0) {
            $content .= html_writer::tag('span',get_string('userscompleted', 'report_userapproval', 0));
        } else {
            $url = $baseurl . '&format=xls&who=completed';
            $content .= html_writer::tag('a',
                get_string('userscompleted', 'report_userapproval', $enrolleduserscompletion), array('href' => $url));
        }
        $content .= ' - ';

        if (($enrolledusers - $enrolleduserscompletion) == 0) {
            $content .= html_writer::tag('span',get_string('usersuncompleted', 'report_userapproval', 0));
        } else {
            $url = $baseurl . '&format=xls&who=notcompleted';
            $content .= html_writer::tag('a',
                get_string('usersuncompleted', 'report_userapproval', $enrolledusers - $enrolleduserscompletion), array('href' => $url));
        }
        $content .= ' - ';

        $url = $baseurl . '&format=xls&who=all';
        $content .= html_writer::tag('a',
            get_string('usersenrolled', 'report_userapproval', $enrolledusers), array('href' => $url));

        $content .= html_writer::start_tag('div', array('class' => 'indicatorbox'));
        $content .= html_writer::tag('div', $enrolleduserspercent . '%', array('class' => 'percentlabel'));
        $content .= html_writer::tag('div', '', array('class' => 'percentbar', 'style' => 'width: ' . $enrolleduserspercent . '%;'));
        $content .= html_writer::end_tag('div');
    }
    $content .= '<hr />';

}

if ($courses) {

    foreach ($courses as $row) {

        $coursecontext = context_course::instance($row->id);

        // Prepare a cell to display the status of the entry.
        $statusclass = '';
        if (!$row->visible) {
            $statusclass = 'dimmed_text';
        }

        if (!$row->category) {
            $textcats = $strcsystem;
        } else {
            $cats = trim($categories[$row->category]->path, '/');
            $cats = explode('/', $cats);
            foreach ($cats as $key => $cat) {
                if (!empty($cat)) {
                    $cats[$key] = html_writer::tag('a',
                                    html_writer::tag('span', $categories[$cat]->name, array('class' => 'singleline')),
                                    array('href' => new moodle_url('/course/index.php',
                                                        array('categoryid' => $categories[$cat]->id)))
                                );
                }
            }

            $textcats = implode(' / ', $cats);
        }

        $sql = 'SELECT ra.id, ra.roleid, cc.timecompleted AS timecompleted, ra.userid
                    FROM {role_assignments} AS ra
                    LEFT JOIN {course_completions} AS cc ON cc.course = :courseid AND cc.userid = ra.userid
                    WHERE ra.contextid = :contextid AND ra.roleid IN (' . $CFG->gradebookroles . ')';
        $rolecounts = $DB->get_records_sql($sql, array('contextid' => $coursecontext->id, 'courseid' => $row->id));

        if ($rolecounts && count($rolecounts) > 0) {
            $enrolledexists = true;
            $enrolledusers = 0;
            $enrolleduserscompletion = 0;
            foreach ($rolecounts as $oneassign) {
                $user = null;
                foreach ($users as $oneuser) {
                    if ($oneuser->id == $oneassign->userid) {
                        $user = $oneuser;
                        break;
                    }
                }

                if (!$user) {
                    continue;
                }

                $enrolledusers++;
                if ($oneassign->timecompleted) {
                    $enrolleduserscompletion++;
                }
            }

            $enrolleduserspercent = $enrolledusers == 0 ? 0 : round($enrolleduserscompletion * 100 / $enrolledusers);
        } else {
            $enrolledexists = false;
            $enrolledusers = 0;
            $enrolleduserscompletion = 0;
            $enrolleduserspercent = 0;
        }

        $coursename = html_writer::tag('a', $row->fullname,
                        array('href' => new moodle_url('/course/view.php', array('id' => $row->id))));

        $coursecontent = html_writer::tag('h3', $coursename);

        if ($enrolledusers == 0) {
            if ($enrolledexists) {
                $coursecontent .= html_writer::tag('p', get_string('notenrolledusersfilter', 'report_userapproval'));
            } else {
                $coursecontent .= html_writer::tag('p', get_string('notenrolledusers', 'report_userapproval'));
            }
        } else {
            if ($enrolleduserscompletion == 0) {
                $coursecontent .= html_writer::tag('span',get_string('userscompleted', 'report_userapproval', 0));
            } else {
                $url = $baseurl . '&format=xls&who=completed&courseid=' . $row->id;
                $coursecontent .= html_writer::tag('a',
                    get_string('userscompleted', 'report_userapproval', $enrolleduserscompletion), array('href' => $url));
            }
            $coursecontent .= ' - ';

            if (($enrolledusers - $enrolleduserscompletion) == 0) {
                $coursecontent .= html_writer::tag('span',get_string('usersuncompleted', 'report_userapproval', 0));
            } else {
                $url = $baseurl . '&format=xls&who=notcompleted&courseid=' . $row->id;
                $coursecontent .= html_writer::tag('a',
                    get_string('usersuncompleted', 'report_userapproval', $enrolledusers - $enrolleduserscompletion), array('href' => $url));
            }
            $coursecontent .= ' - ';

            $url = $baseurl . '&format=xls&who=all&courseid=' . $row->id;
            $coursecontent .= html_writer::tag('a',
                get_string('usersenrolled', 'report_userapproval', $enrolledusers), array('href' => $url));

            $coursecontent .= html_writer::start_tag('div', array('class' => 'indicatorbox'));
            $coursecontent .= html_writer::tag('div', $enrolleduserspercent . '%', array('class' => 'percentlabel'));
            $coursecontent .= html_writer::tag('div', '', array('class' => 'percentbar', 'style' => 'width: ' . $enrolleduserspercent . '%;'));
            $coursecontent .= html_writer::end_tag('div');
        }
        $coursecontent .= html_writer::tag('p', $textcats);


        $content .= $OUTPUT->box_start('userapprovalcourse ' . $statusclass) . $coursecontent . $OUTPUT->box_end();

    }

}

echo $OUTPUT->heading($coursecount . ' ' . get_string('courses'));

echo $OUTPUT->paging_bar($coursecount, $page, $perpage, $baseurl);

// Add filters.
$filtering->display_add();
$filtering->display_active();

if (!empty($content)) {
    echo $OUTPUT->box_start();

    echo $content;

    echo $OUTPUT->box_end();

    echo $OUTPUT->paging_bar($coursecount, $page, $perpage, $baseurl);


    // Download form.
    echo $OUTPUT->heading(get_string('download', 'admin'));

    echo $OUTPUT->box_start();
    echo '<form action="' . $baseurl . '">';
    echo '  <select name="format">';
    echo '    <option value="csv">' . get_string('downloadtext') . '</option>';
    echo '    <option value="ods">' . get_string('downloadods') . '</option>';
    echo '    <option value="xls">' . get_string('downloadexcel') . '</option>';
    echo '  </select>';
    echo '  <select name="who">';
    echo '    <option value="summary">' . get_string('summary', 'report_userapproval') . '</option>';
    echo '    <option value="all">' . get_string('allusers', 'report_userapproval') . '</option>';
    echo '    <option value="completed">' . get_string('onlycompleted', 'report_userapproval') . '</option>';
    echo '    <option value="notcompleted">' . get_string('notcompleted', 'report_userapproval') . '</option>';
    echo '  </select>';
    echo '  <input type="submit" value="' . get_string('export', 'report_userapproval') . '" />';
    echo '</form>';
    echo $OUTPUT->box_end();

} else {
    echo $OUTPUT->heading(get_string('notcoursesfound', 'report_userapproval'), 3);
}

echo $OUTPUT->footer();
