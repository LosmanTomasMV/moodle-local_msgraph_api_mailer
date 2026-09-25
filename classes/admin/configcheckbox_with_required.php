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
 * Enable checkbox that blocks saving when required sibling settings are empty.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_msgraph_api_mailer\admin;

/**
 * Enable checkbox that blocks saving when required sibling settings are empty.
 *
 * When the checkbox is ticked, write_setting() inspects the other fields
 * submitted in the same POST and returns an error string if any required
 * ones are blank, preventing the setting from being saved.
 *
 * @package    local_msgraph_api_mailer
 * @copyright  2026 Krishna Gupta
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class configcheckbox_with_required extends \admin_setting_configcheckbox {
    /** @var string[] Bare setting names that must be non-empty when enabled. */
    protected array $requirednames;

    /** @var string Error shown to the admin when a required field is missing. */
    protected string $errormsg;

    /**
     * Constructor.
     *
     * @param string   $name           Setting name, e.g. 'local_msgraph_api_mailer/enabled'.
     * @param string   $visiblename    Visible name shown in admin UI.
     * @param string   $description    Description text.
     * @param int      $defaultsetting Default value.
     * @param string[] $requirednames  Bare setting names that must be non-empty, e.g. ['tenant_id', 'client_id'].
     * @param string   $errormsg       Validation error shown when a required field is missing.
     */
    public function __construct(
        $name,
        $visiblename,
        $description,
        $defaultsetting,
        array $requirednames,
        string $errormsg
    ) {
        parent::__construct($name, $visiblename, $description, $defaultsetting);
        $this->requirednames = $requirednames;
        $this->errormsg      = $errormsg;
    }

    /**
     * Block enabling the plugin when required fields are not filled in.
     *
     * @param  string $data '1' = checkbox ticked, '0' = unticked.
     * @return string '' on success, error message on failure.
     */
    public function write_setting($data) {
        if ($data == '1') {
            foreach ($this->requirednames as $fieldname) {
                // Moodle POST key for a plugin setting is s_{plugin}_{name}.
                $postkey = 's_' . str_replace('/', '_', $this->plugin) . '_' . $fieldname;
                $value   = optional_param($postkey, '', PARAM_TEXT);

                // If the field was not submitted, fall back to the saved DB value.
                if ($value === '') {
                    $value = (string) get_config($this->plugin, $fieldname);
                }

                // Production may keep the client secret outside the database.
                if (
                    $fieldname === 'client_secret' &&
                    !empty($GLOBALS['CFG']->local_msgraph_api_mailer_client_secret)
                ) {
                    $value = (string) $GLOBALS['CFG']->local_msgraph_api_mailer_client_secret;
                }

                if (trim($value) === '') {
                    return $this->errormsg;
                }
            }
            if (get_config($this->plugin, 'patch_status') !== 'ok') {
                return get_string('enabled_requires_patch', 'local_msgraph_api_mailer');
            }
        }
        return parent::write_setting($data);
    }
}
