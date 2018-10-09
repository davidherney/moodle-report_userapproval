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
 * Profile field filter.
 *
 * @package    report_userapproval
 * @copyright 2017 David Herney Bernal - cirano
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot.'/user/filters/lib.php');
require_once($CFG->dirroot.'/user/filters/profilefield.php');

/**
 * User filter based on values of custom profile fields.
 *
 * @package    report_userapproval
 * @copyright 2017 David Herney Bernal - cirano
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class userapproval_filter_profilefield extends user_filter_profilefield {

    /**
     * Returns an array of custom profile fields
     * @return array of profile fields
     */
    public function get_profile_fields() {
        global $DB, $USER;
        if (!$fields = $DB->get_records('user_info_field', null, 'shortname', 'id,shortname')) {
            return null;
        }
        $res = array(0 => get_string('anyfield', 'filters'));
        $isadmin = is_siteadmin($USER);
        foreach ($fields as $k => $v) {
            // The boss fields are not listed.
            if (!$isadmin && strpos($v->shortname, 'boss') !== false ) {
                continue;
            }
            $res[$k] = $v->shortname;
        }
        return $res;
    }
}
