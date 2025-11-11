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
 * TODO describe file 
 *
 * @package    local_ltiusage
 * @copyright  2025 Michael Clark <michael.d.clark@glasgow.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_ltiusage\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use context_system;

class get_pagination extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'typeid' => new external_value(PARAM_INT, 'LTI tool type ID'),
            'page' => new external_value(PARAM_INT, 'Page number', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Items per page', VALUE_DEFAULT, 25),
        ]);
    }

    /**
     * Get paginated LTI data for a specific tool type
     *
     * @param int $typeid LTI tool type ID
     * @param int $page Page number
     * @param int $perpage Items per page
     * @return array
     */
    public static function execute($typeid, $page, $perpage) {
        global $DB;

        // Validate parameters
        $params = self::validate_parameters(self::execute_parameters(), [
            'typeid' => $typeid,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        // Check capability
        $context = context_system::instance();
        require_capability('local/ltiusage:viewltiusage', $context);

        // Check if user can delete
        $can_delete = is_siteadmin();

        // Get LTI activities for this type with efficient query
        $lti_sql = "SELECT l.id, l.course, l.name, l.typeid, l.toolurl,
                           c.fullname as coursename, cm.id as cmid, cm.visible, l.name as activityname
                    FROM {lti} l
                    JOIN {course} c ON c.id = l.course
                    JOIN {course_modules} cm ON cm.instance = l.id AND cm.module = (
                        SELECT id FROM {modules} WHERE name = 'lti'
                    )
                    WHERE l.typeid = ?
                    ORDER BY c.fullname, l.name";
    
        $ltirecords = $DB->get_records_sql($lti_sql, [$params['typeid']]);

        // Sort records
        usort($ltirecords, function($a, $b) {
            return [$a->coursename, $a->activityname] <=> [$b->coursename, $b->activityname];
        });

        // Get tool type name
        $typename = 'Custom/Manual LTI';
        if ($params['typeid'] > 0) {
            $type = $DB->get_record('lti_types', ['id' => $params['typeid']]);
            if ($type) {
                $typename = format_string($type->name);
            }
        }

        // Paginate
        $total = count($ltirecords);
        $start = $params['page'] * $params['perpage'];
        $pagedrecords = array_slice($ltirecords, $start, $params['perpage']);

        // Format rows
        $rows = [];
        foreach ($pagedrecords as $r) {
            $link = new \moodle_url('/mod/lti/view.php', ['id' => $r->cmid]);
            $deletelink = $can_delete ? new \moodle_url('/course/mod.php', ['delete' => $r->cmid, 'sesskey' => sesskey()]) : null;

            $rows[] = [
                'course' => format_string($r->coursename),
                'name' => format_string($r->activityname),
                'visible' => (int)$r->visible,
                'link' => $link->out(false),
                'deletelink' => $can_delete ? $deletelink->out(false) : '',
                'show_delete' => $can_delete,
                'cmid' => $r->cmid,
            ];
        }

        return [
            'typeid' => $params['typeid'],
            'typename' => $typename,
            'rows' => $rows,
            'total' => $total,
            'page' => $params['page'],
            'perpage' => $params['perpage'],
            'can_delete' => $can_delete,
        ];
    }

    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'typeid' => new external_value(PARAM_INT, 'LTI tool type ID'),
            'typename' => new external_value(PARAM_TEXT, 'Tool type name'),
            'rows' => new external_multiple_structure(
                new external_single_structure([
                    'course' => new external_value(PARAM_TEXT, 'Course name'),
                    'name' => new external_value(PARAM_TEXT, 'Activity name'),
                    'visible' => new external_value(PARAM_INT, 'Visibility status'),
                    'link' => new external_value(PARAM_URL, 'Activity link'),
                    'deletelink' => new external_value(PARAM_URL, 'Delete link', VALUE_OPTIONAL),
                    'show_delete' => new external_value(PARAM_BOOL, 'Show delete link'),
                    'cmid' => new external_value(PARAM_INT, 'Course module ID'),
                ])
            ),
            'total' => new external_value(PARAM_INT, 'Total records'),
            'page' => new external_value(PARAM_INT, 'Current page'),
            'perpage' => new external_value(PARAM_INT, 'Items per page'),
            'can_delete' => new external_value(PARAM_BOOL, 'User can delete'),
        ]);
    }
}