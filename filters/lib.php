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
 * This file contains the User approval Filter API.
 *
 * @package    report_userapproval
 * @copyright 2017 David Herney Bernal - cirano
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/user/filters/lib.php');

/**
 * Userapproval filtering wrapper class.
 *
 * @package    report_userapproval
 * @copyright 2017 David Herney Bernal - cirano
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userapproval_filtering {
    /** @var array */
    public $_fields;
    /** @var \user_add_filter_form */
    public $_addform;
    /** @var \user_active_filter_form */
    public $_activeform;

    /**
     * Contructor
     * @param array $fieldnames array of visible user fields
     * @param string $baseurl base url used for submission/return, null if the same of current page
     * @param array $extraparams extra page parameters
     */
    public function __construct($fieldnames = null, $baseurl = null, $extraparams = null) {
        global $SESSION;

        if (!isset($SESSION->userapproval_filtering)) {
            $SESSION->cfiltering = array();
        }

        if (empty($fieldnames)) {
            $fieldnames = array('realname' => 0, 'lastname' => 1, 'firstname' => 1, 'username' => 1, 'email' => 1, 'city' => 1, 'country' => 1,
                                'confirmed' => 1, 'suspended' => 1, 'profile' => 1, 'courserole' => 1, 'systemrole' => 1,
                                'cohort' => 1, 'firstaccess' => 1, 'lastaccess' => 1, 'neveraccessed' => 1, 'timemodified' => 1,
                                'nevermodified' => 1, 'auth' => 1, 'mnethostid' => 1, 'idnumber' => 1);
        }

        $this->_fields  = array();

        foreach ($fieldnames as $fieldname => $advanced) {
            if ($field = $this->get_field($fieldname, $advanced)) {
                $this->_fields[$fieldname] = $field;
            }
        }

        // Fist the new filter form.
        $this->_addform = new user_add_filter_form($baseurl, array('fields' => $this->_fields, 'extraparams' => $extraparams));
        if ($adddata = $this->_addform->get_data()) {
            foreach ($this->_fields as $fname => $field) {
                $data = $field->check_data($adddata);
                if ($data === false) {
                    continue; // Nothing new.
                }
                if (!array_key_exists($fname, $SESSION->userapproval_filtering)) {
                    $SESSION->userapproval_filtering[$fname] = array();
                }
                $SESSION->userapproval_filtering[$fname][] = $data;
            }
            // Clear the form.
            $_POST = array();
            $this->_addform = new user_add_filter_form($baseurl, array('fields' => $this->_fields, 'extraparams' => $extraparams));
        }

        // Now the active filters.
        $this->_activeform = new user_active_filter_form($baseurl, array('fields' => $this->_fields, 'extraparams' => $extraparams));
        if ($adddata = $this->_activeform->get_data()) {
            if (!empty($adddata->removeall)) {
                $SESSION->userapproval_filtering = array();

            } else if (!empty($adddata->removeselected) and !empty($adddata->filter)) {
                foreach ($adddata->filter as $fname => $instances) {
                    foreach ($instances as $i => $val) {
                        if (empty($val)) {
                            continue;
                        }
                        unset($SESSION->userapproval_filtering[$fname][$i]);
                    }
                    if (empty($SESSION->userapproval_filtering[$fname])) {
                        unset($SESSION->userapproval_filtering[$fname]);
                    }
                }
            }
            // Clear+reload the form.
            $_POST = array();
            $this->_activeform = new user_active_filter_form($baseurl, array('fields' => $this->_fields, 'extraparams' => $extraparams));
        }
        // Now the active filters.
    }

    /**
     * Creates known user filter if present
     * @param string $fieldname
     * @param boolean $advanced
     * @return object filter
     */
    public function get_field($fieldname, $advanced) {
        global $USER, $CFG, $DB, $SITE;

        switch ($fieldname) {
            case 'username':    return new user_filter_text('username', get_string('username'), $advanced, 'username');
            case 'realname':    return new user_filter_text('realname', get_string('fullnameuser'), $advanced, $DB->sql_fullname());
            case 'lastname':    return new user_filter_text('lastname', get_string('lastname'), $advanced, 'lastname');
            case 'firstname':    return new user_filter_text('firstname', get_string('firstname'), $advanced, 'firstname');
            case 'email':       return new user_filter_text('email', get_string('email'), $advanced, 'email');
            case 'city':        return new user_filter_text('city', get_string('city'), $advanced, 'city');
            case 'country':     return new user_filter_select('country', get_string('country'), $advanced, 'country', get_string_manager()->get_list_of_countries(), $USER->country);
            case 'confirmed':   return new user_filter_yesno('confirmed', get_string('confirmed', 'admin'), $advanced, 'confirmed');
            case 'suspended':   return new user_filter_yesno('suspended', get_string('suspended', 'auth'), $advanced, 'suspended');
            case 'profile':     return new user_filter_profilefield('profile', get_string('profilefields', 'admin'), $advanced);
            case 'courserole':  return new user_filter_courserole('courserole', get_string('courserole', 'filters'), $advanced);
            case 'systemrole':  return new user_filter_globalrole('systemrole', get_string('globalrole', 'role'), $advanced);
            case 'firstaccess': return new user_filter_date('firstaccess', get_string('firstaccess', 'filters'), $advanced, 'firstaccess');
            case 'lastaccess':  return new user_filter_date('lastaccess', get_string('lastaccess'), $advanced, 'lastaccess');
            case 'neveraccessed': return new user_filter_checkbox('neveraccessed', get_string('neveraccessed', 'filters'), $advanced, 'firstaccess', array('lastaccess_sck', 'lastaccess_eck', 'firstaccess_eck', 'firstaccess_sck'));
            case 'timemodified': return new user_filter_date('timemodified', get_string('lastmodified'), $advanced, 'timemodified');
            case 'nevermodified': return new user_filter_checkbox('nevermodified', get_string('nevermodified', 'filters'), $advanced, array('timemodified', 'timecreated'), array('timemodified_sck', 'timemodified_eck'));
            case 'cohort':      return new user_filter_cohort($advanced);
            case 'idnumber':    return new user_filter_text('idnumber', get_string('idnumber'), $advanced, 'idnumber');
            case 'auth':
                $plugins = core_component::get_plugin_list('auth');
                $choices = array();
                foreach ($plugins as $auth => $unused) {
                    $choices[$auth] = get_string('pluginname', "auth_{$auth}");
                }
                return new user_filter_simpleselect('auth', get_string('authentication'), $advanced, 'auth', $choices);

            case 'mnethostid':
                // Include all hosts even those deleted or otherwise problematic.
                if (!$hosts = $DB->get_records('mnet_host', null, 'id', 'id, wwwroot, name')) {
                    $hosts = array();
                }
                $choices = array();
                foreach ($hosts as $host) {
                    if ($host->id == $CFG->mnet_localhost_id) {
                        $choices[$host->id] = format_string($SITE->fullname).' ('.get_string('local').')';
                    } else if (empty($host->wwwroot)) {
                        // All hosts.
                        continue;
                    } else {
                        $choices[$host->id] = $host->name.' ('.$host->wwwroot.')';
                    }
                }
                if ($usedhosts = $DB->get_fieldset_sql("SELECT DISTINCT mnethostid FROM {user} WHERE deleted=0")) {
                    foreach ($usedhosts as $hostid) {
                        if (empty($hosts[$hostid])) {
                            $choices[$hostid] = 'id: '.$hostid.' ('.get_string('error').')';
                        }
                    }
                }
                if (count($choices) < 2) {
                    return null; // Filter not needed.
                }
                return new user_filter_simpleselect('mnethostid', get_string('mnetidprovider', 'mnet'), $advanced, 'mnethostid', $choices);

            default:
                return null;
        }
    }

    /**
     * Returns sql where statement based on active user filters
     * @param string $extra sql
     * @param array $params named params (recommended prefix ex)
     * @return array sql string and $params
     */
    public function get_sql_filter($extra='', array $params=null) {
        global $SESSION;

        $sqls = array();
        if ($extra != '') {
            $sqls[] = $extra;
        }
        $params = (array)$params;

        if (!empty($SESSION->userapproval_filtering)) {
            foreach ($SESSION->userapproval_filtering as $fname => $datas) {
                if (!array_key_exists($fname, $this->_fields)) {
                    continue; // Filter not used.
                }
                $field = $this->_fields[$fname];
                foreach ($datas as $i => $data) {
                    list($s, $p) = $field->get_sql_filter($data);
                    $sqls[] = $s;
                    $params = $params + $p;
                }
            }
        }

        if (empty($sqls)) {
            return array('', array());
        } else {
            $sqls = implode(' AND ', $sqls);
            return array($sqls, $params);
        }
    }

    /**
     * Print the add filter form.
     */
    public function display_add() {
        $this->_addform->display();
    }

    /**
     * Print the active filter form.
     */
    public function display_active() {
        $this->_activeform->display();
    }

}

