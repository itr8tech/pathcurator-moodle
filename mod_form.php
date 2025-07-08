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
 * The main mod_pathcurator configuration form.
 *
 * @package     mod_pathcurator
 * @copyright   2025 Your Name <you@example.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form.
 *
 * @package    mod_pathcurator
 * @copyright  2025 Your Name <you@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_pathcurator_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG;

        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('pathcuratorname', 'pathcurator'), array('size' => '64'));

        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }

        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'pathcuratorname', 'pathcurator');

        // Adding the standard "intro" and "introformat" fields.
        $this->standard_intro_elements();

        // Adding the JSON file upload field.
        $mform->addElement('filepicker', 'jsonfile', get_string('jsonfile', 'pathcurator'), null,
                           array('accepted_types' => '.json'));
        $mform->addHelpButton('jsonfile', 'jsonfile', 'pathcurator');

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }

    /**
     * Form validation
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $USER;
        
        $errors = parent::validation($data, $files);

        // Validate JSON file if uploaded.
        if (!empty($data['jsonfile'])) {
            $draftitemid = $data['jsonfile'];
            $usercontext = context_user::instance($USER->id);
            $fs = get_file_storage();
            $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
            
            if ($files) {
                $file = reset($files);
                $content = $file->get_content();
                $json = json_decode($content, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors['jsonfile'] = get_string('invalidjson', 'pathcurator');
                } else {
                    // Validate PathCurator JSON structure
                    $validationErrors = $this->validate_pathcurator_json($json);
                    if (!empty($validationErrors)) {
                        $errors['jsonfile'] = implode(', ', $validationErrors);
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Process data before saving
     *
     * @param stdClass $data
     * @return void
     */
    public function data_preprocessing(&$data) {
        parent::data_preprocessing($data);
    }

    /**
     * Process form data after submission
     *
     * @param stdClass $data
     * @return void
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        // File processing will be handled in lib.php add_instance function
    }

    /**
     * Validate PathCurator JSON structure
     *
     * @param array $json
     * @return array Array of validation errors
     */
    private function validate_pathcurator_json($json) {
        $errors = array();
        
        // Check if it's an array (should be array of pathways)
        if (!is_array($json)) {
            $errors[] = 'JSON must be an array of pathways';
            return $errors;
        }
        
        if (empty($json)) {
            $errors[] = 'JSON cannot be empty';
            return $errors;
        }
        
        // Check first pathway structure
        $pathway = reset($json);
        if (!is_array($pathway)) {
            $errors[] = 'Each pathway must be an object';
            return $errors;
        }
        
        // Check required fields
        if (empty($pathway['steps']) || !is_array($pathway['steps'])) {
            $errors[] = 'Pathway must contain a "steps" array';
        }
        
        // Validate steps structure
        if (!empty($pathway['steps'])) {
            foreach ($pathway['steps'] as $index => $step) {
                if (!is_array($step)) {
                    $errors[] = "Step {$index} must be an object";
                    continue;
                }
                
                if (empty($step['name'])) {
                    $errors[] = "Step {$index} must have a name";
                }
                
                if (empty($step['bookmarks']) || !is_array($step['bookmarks'])) {
                    $errors[] = "Step {$index} must contain a bookmarks array";
                    continue;
                }
                
                // Validate bookmarks
                foreach ($step['bookmarks'] as $bIndex => $bookmark) {
                    if (!is_array($bookmark)) {
                        $errors[] = "Step {$index}, bookmark {$bIndex} must be an object";
                        continue;
                    }
                    
                    $required_fields = ['title', 'url', 'type'];
                    foreach ($required_fields as $field) {
                        if (empty($bookmark[$field])) {
                            $errors[] = "Step {$index}, bookmark {$bIndex} missing required field: {$field}";
                        }
                    }
                    
                    // Validate bookmark type
                    if (!empty($bookmark['type']) && !in_array(strtolower($bookmark['type']), ['required', 'bonus'])) {
                        $errors[] = "Step {$index}, bookmark {$bIndex} type must be 'Required' or 'Bonus'";
                    }
                }
            }
        }
        
        return $errors;
    }
}