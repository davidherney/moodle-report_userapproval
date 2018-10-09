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
 * This file extends the User Filter API.
 *
 * @package    report_userapproval
 * @copyright 2017 David Herney Bernal - cirano
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/user/filters/lib.php');
require_once($CFG->dirroot . '/report/userapproval/filters/profilefield.php');

/**
 * Userapproval filtering wrapper class.
 *
 * @package    report_userapproval
 * @copyright 2017 David Herney Bernal - cirano
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userapproval_filtering extends user_filtering {

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
            case 'profile':     return new userapproval_filter_profilefield('profile', get_string('profilefields', 'admin'), $advanced);
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

}
