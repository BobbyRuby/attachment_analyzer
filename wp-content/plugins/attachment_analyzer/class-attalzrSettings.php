<?php
/**
 * Based off of http://www.hughlashbrooke.com/2014/02/complete-versatile-options-page-class-wordpress-plugin/
 * Some simple changes to fit this implementation.
 */
if (!defined('ABSPATH')) exit;

class attalzrSettings
{
    private $dir;
    private $file;
    private $assets_dir;
    public $assets_url;
    private $settings_base;
    private $settings;
    private $pageHooks;

    public function __construct($file)
    {
        $this->file = $file;
        $this->dir = dirname($this->file);
        $this->assets_dir = trailingslashit($this->dir) . 'assets';
        $this->assets_url = esc_url(trailingslashit(plugins_url('/assets/', $this->file)));
        $this->settings_base = 'attalzr_';

        // Initialise settings
        add_action('admin_init', array($this, 'init'));

        // Register plugin settings
        add_action('admin_init', array($this, 'register_settings'));

        // Add settings page to menu
        add_action('admin_menu', array($this, 'add_menu_item'));

        // Add settings link to plugins page
        add_filter('plugin_action_links_' . plugin_basename($this->file), array($this, 'add_settings_link'));
    }

    /**
     * Initialise settings
     * @return void
     */
    public function init()
    {
        $this->settings = $this->_settings_fields();
    }

    /**
     * Add settings page to admin menu
     * @return void
     */
    public function add_menu_item()
    {
        (!$this->settings) ? $settings = $this->_settings_fields() : $settings = $this->settings;
        foreach ($settings as $page) {
            $pageHook = add_menu_page(__($page['title'], 'plugin_textdomain'), __($page['title'], 'plugin_textdomain'), 'read', $page['slug'], array($this, $page['slug'] . '_settings_page'));
            add_action('admin_print_styles-' . $pageHook, array($this, 'settings_CSS_assets'));
            add_action('admin_print_scripts-' . $pageHook, array($this, 'settings_JS_assets'));
            $this->pageHooks[] = $pageHook;
            // Add function to do additional things on save of settings fields
            add_action('load-' . $pageHook, array($this, 'submit_settings_fields'));
        }
    }

    /**
     * Load settings CSS
     * @return void
     */
    public function settings_CSS_assets()
    {
        wp_enqueue_style('attalzr-admin-css', $this->assets_url . 'css/settings.css');
    }

    /**
     * Load settings JS
     * @return void
     */
    public function settings_JS_assets()
    {
        // We're including the farbtastic script & styles here because they're needed for the colour picker
        // If you're not including a colour picker field then you can leave these calls out as well as the farbtastic dependency for the wpt-admin-js script below
        wp_enqueue_style('farbtastic');
        wp_enqueue_script('farbtastic');

        // We're including the WP media scripts here because they're needed for the image upload field
        // If you're not including an image upload then you can leave this function call out
        wp_enqueue_media();

        wp_register_script('attalzr-admin-js', $this->assets_url . 'js/settings.js', array('farbtastic', 'jquery'), '1.0.0');
        wp_enqueue_script('attalzr-admin-js');
    }

    /**
     * Add settings link to plugin list table
     * @param  array $links Existing links
     * @return array        Modified links
     */
    public function add_settings_link($links)
    {
        (!$this->settings) ? $settings = $this->_settings_fields() : $settings = $this->settings;
        foreach ($settings as $page) {
            $settings_link = '<a href="options-general.php?page=' . $page['slug'] . '">' . __($page['title'], 'plugin_textdomain') . '</a>';
            array_push($links, $settings_link);
        }
        return $links;
    }

    /**
     * Build settings fields
     * @return array Fields to be displayed on settings page arranged by page[section][fields]
     */
    private function _settings_fields()
    {
        // Begin Page
        $pageSlug = 'attalzr';
        $settings[$pageSlug] = array(
            'title' => __('Attachment Analyzer', 'plugin_textdomain'),
            'slug' => $pageSlug,
            'sections' => array(
                'analyzer_upload' => array(
                    'title' => __('', 'plugin_textdomain'),
                    'description' => __('', 'plugin_textdomain'),
                    'page_slug' => $pageSlug,
                    'fields' => array(
                        array(
                            'id' => 'file',
                            'label' => 'Upload',
                            'type' => 'file',
                            'description' => 'Select a CSV of Poles',
                        ),
                        array(
                            'id' => 'pLowDiff',
                            'label' => 'Minimum distance allowed between the lowest Power and PHOA in <i>inches</i>',
                            'type' => 'number',
                            'description' => '( Primarys, Secondarys, and Tranformer Drip Loops ) Default - 40"',
                            'placeholder' => '40',
                        ),
                        array(
                            'id' => 'slDiff',
                            'label' => 'Minimum distance allowed between the lowest circuit and PHOA in <i>inches</i>',
                            'type' => 'number',
                            'description' => '( Street light drip loops and traffic circuits )  Default - 12"',
                            'placeholder' => '12',
                        ),
                        array(
                            'id' => 'stltDiff',
                            'label' => 'Minimum distance allowed between the lowest street light bottom and PHOA in <i>inches</i>',
                            'type' => 'number',
                            'description' => 'Default - 4"',
                            'placeholder' => '4',
                        ),
                        array(
                            'id' => 'trnsDiff',
                            'label' => 'Minimum distance allowed between the trasformer bottom and PHOA in <i>inches</i>',
                            'type' => 'number',
                            'description' => 'Default - 30"',
                            'placeholder' => '30',
                        ),
                        array(
                            'id' => 'install_url',
                            'type' => 'hidden',
                            'default' => plugin_dir_url($this->file)
                        )
                    ) // end fields
                ) // end section
            )
        ); // end page

        return $settings;
    }

    /**
     * Register plugin settings
     * @return void
     */
    public function register_settings()
    {
        if (is_array($this->settings)) {
            foreach ($this->settings as $page) {
                foreach ($page['sections'] as $section => $data) {
                    // Add section to page
                    add_settings_section($section, $data['title'], array($this, 'settings_section'), $page['slug']);

                    foreach ($data['fields'] as $field) {

                        if ($field['type'] === 'hidden') {
                        } else {
                            // Validation callback for field
                            $validation = '';
                            if (isset($field['callback'])) {
                                $validation = $field['callback'];
                            }

                            // Register field
                            $option_name = $this->settings_base . $field['id'];
                            register_setting($page['slug'], $option_name, $validation);

                            // Add field to page
                            add_settings_field($field['id'], $field['label'], array($this, 'display_field'), $page['slug'], $section, array('field' => $field));
                        }
                    }
                }
            }
        }
    }

    public function settings_section($section)
    {
        foreach ($this->settings as $page) {
            foreach ($page as $key => $data) {
                if ($key === 'sections' && is_array($data)) {
                    $html = '<p> ' . $data[$section['id']]['description'] . '</p>' . "\n";
                }
            }
        }
        echo $html;
    }

    /**
     * Changes only need to be made if creating custom submit buttons for a page. (Like to hook into with id or class)
     * @return string - the submit button
     */
    private function _get_submit_button()
    {
        $submit_button = '<input name="Submit" type="submit" class="button-primary" value="' . esc_attr(__('Save Settings', 'plugin_textdomain')) . '" />' . "\n";
        foreach ($this->settings as $page) {
            foreach ($page as $key => $data) {
                if ($key === 'slug') {
                    $submit_button = '<input name="submit_' . $data . '" type="submit" class="button-primary" value="' . esc_attr(__('Analyze Poles', 'plugin_textdomain')) . '" />' . "\n";
                }
            }
        }
        return $submit_button;
    }

    private function _display_hidden_fields()
    {
        foreach ($this->settings as $page) {
            foreach ($page['sections'] as $section => $data) {
                foreach ($data['fields'] as $field) {
                    if ($field['type'] === 'hidden') {
                        $option_name = $this->settings_base . $field['id'];
                        $html = '<input id="' . esc_attr($field['id']) . '" type="' . $field['type'] . '" name="' . esc_attr($option_name) . '" value="' . $field['default'] . '"/>' . "\n";
                    }
                }
            }
        }
        return $html;
    }

    /**
     * Generate HTML for displaying fields
     * @param  array $args Field data
     * @return void
     */
    public function display_field($args)
    {

        $field = $args['field'];

        $html = '';

        $option_name = $this->settings_base . $field['id'];
        $option = get_option($option_name);

        $data = '';
        if (isset($field['default'])) {
            $data = $field['default'];
            if ($option) {
                $data = $option;
            }
        }

        switch ($field['type']) {

            case 'text':
            case 'password':
            case 'number':
                if (isset($field['grab_array'])) {
                    $html .= '<input id="' . esc_attr($field['id']) . '" type="' . $field['type'] . '" name="' . esc_attr($option_name) . '[]" placeholder="' . esc_attr($field['placeholder']) . '" value="' . $data . '"/>' . "\n";
                } else {
                    $html .= '<input id="' . esc_attr($field['id']) . '" type="' . $field['type'] . '" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '" value="' . $data . '"/>' . "\n";
                }
                break;

            case 'text_secret':
                $html .= '<input id="' . esc_attr($field['id']) . '" type="text" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '" value=""/>' . "\n";
                break;

            case 'textarea':
                $html .= '<textarea id="' . esc_attr($field['id']) . '" rows="5" cols="50" name="' . esc_attr($option_name) . '" placeholder="' . esc_attr($field['placeholder']) . '">' . $data . '</textarea><br/>' . "\n";
                break;

            case 'checkbox':
                $checked = '';
                if ($option && 'on' == $option) {
                    $checked = 'checked="checked"';
                }
                $html .= '<input id="' . esc_attr($field['id']) . '" type="' . $field['type'] . '" name="' . esc_attr($option_name) . '" ' . $checked . '/>' . "\n";
                break;

            case 'checkbox_multi':
                foreach ($field['options'] as $k => $v) {
                    $checked = FALSE;
                    if (in_array($k, $data)) {
                        $checked = true;
                    }
                    $html .= '<label for="' . esc_attr($field['id'] . '_' . $k) . '"><input type="checkbox" ' . checked($checked, true, FALSE) . ' name="' . esc_attr($option_name) . '[]" value="' . esc_attr($k) . '" id="' . esc_attr($field['id'] . '_' . $k) . '" /> ' . $v . '</label> ';
                }
                break;

            case 'radio':
                foreach ($field['options'] as $k => $v) {
                    $checked = FALSE;
                    if ($k == $data) {
                        $checked = true;
                    }
                    $html .= '<label for="' . esc_attr($field['id'] . '_' . $k) . '"><input type="radio" ' . checked($checked, true, FALSE) . ' name="' . esc_attr($option_name) . '" value="' . esc_attr($k) . '" id="' . esc_attr($field['id'] . '_' . $k) . '" /> ' . $v . '</label> ';
                }
                break;

            case 'select':
                $html .= '<select name="' . esc_attr($option_name) . '" id="' . esc_attr($field['id']) . '">';
                foreach ($field['options'] as $k => $v) {
                    $selected = FALSE;
                    if ($k == $data) {
                        $selected = true;
                    }
                    $html .= '<option ' . selected($selected, true, FALSE) . ' value="' . esc_attr($k) . '">' . $v . '</option>';
                }
                $html .= '</select> ';
                break;

            case 'select_multi':
                $html .= '<select name="' . esc_attr($option_name) . '[]" id="' . esc_attr($field['id']) . '" multiple="multiple">';
                foreach ($field['options'] as $k => $v) {
                    $selected = FALSE;
                    if (in_array($k, $data)) {
                        $selected = true;
                    }
                    $html .= '<option ' . selected($selected, true, FALSE) . ' value="' . esc_attr($k) . '" />' . $v . '</label> ';
                }
                $html .= '</select> ';
                break;

            case 'image':
                $image_thumb = '';
                if ($data) {
                    $image_thumb = wp_get_attachment_thumb_url($data);
                }
                $html .= '<img id="' . $option_name . '_preview" class="image_preview" src="' . $image_thumb . '" /><br/>' . "\n";
                $html .= '<input id="' . $option_name . '_button" type="button" data-uploader_title="' . __('Upload an image', 'plugin_textdomain') . '" data-uploader_button_text="' . __('Use image', 'plugin_textdomain') . '" class="image_upload_button button" value="' . __('Upload new image', 'plugin_textdomain') . '" />' . "\n";
                $html .= '<input id="' . $option_name . '_delete" type="button" class="image_delete_button button" value="' . __('Remove image', 'plugin_textdomain') . '" />' . "\n";
                $html .= '<input id="' . $option_name . '" class="image_data_field" type="hidden" name="' . $option_name . '" value="' . $data . '"/><br/>' . "\n";
                break;

            case 'file':
                $html .= '<input type="file" name="' . $option_name . '"/>';
                break;

            case 'color':
                ?>
                <div class="color-picker" style="position:relative;">
                    <input type="text" name="<?php esc_attr_e($option_name); ?>" class="color"
                           value="<?php esc_attr_e($data); ?>"/>

                    <div style="position:absolute;background:#FFF;z-index:99;border-radius:100%;"
                         class="colorpicker"></div>
                </div>
                <?php
                break;

        }

        switch ($field['type']) {

            case 'checkbox_multi':
            case 'radio':
            case 'select_multi':
                $html .= '<br/><span class="description">' . $field['description'] . '</span>';
                break;

            default:
                $html .= '<label for="' . esc_attr($field['id']) . '"><span class="description">' . $field['description'] . '</span></label>' . "\n";
                break;
        }

        echo $html;
    }

    /**
     * Validate individual settings field
     * @param  string $data Inputted value
     * @return string       Validated value
     */
    public function validate_field($data)
    {
        if ($data && strlen($data) > 0 && $data != '') {
            $data = urlencode(strtolower(str_replace(' ', '-', $data)));
        }
        return $data;
    }

    /**
     * Load settings page content
     * @return void
     */
    public function attalzr_settings_page()
    {
//		if(isset($_GET['settings-updated']) && $_GET['settings-updated'])
        if ($_POST) {
            attalzrPlugin::beginAnalyzingCSV();
        }

        (!$this->settings) ? $settings = $this->_settings_fields() : $settings = $this->settings;
        foreach ($settings as $page) {
            if ($page['slug'] == 'attalzr') {
                add_thickbox();
                // Build page HTML
                $html = '<div class="wrap" id="plugin_settings">' . "\n";
                $html .= '<div id="attalzr_' . $page['slug'] . '_settings">' . "\n";
                $html .= '<h2>' . __($page['title'], 'plugin_textdomain') . '</h2>' . "\n";
//                $html .= '<div>' . __($page['sections']['html-enclose']['description'], 'plugin_textdomain') . '</div>' . "\n";
                $html .= '<form id="attalzr_' . $page['slug'] . '_form" method="post" action="" enctype="multipart/form-data">' . "\n";
                $html .= $this->_display_hidden_fields();
                // Setup navigation
                //            $html .= '<ul id="settings-sections" class="subsubsub hide-if-no-js">' . "\n";
                //            $html .= '<li><a class="tab all current" href="#all">' . __('All', 'plugin_textdomain') . '</a></li>' . "\n";
                //            foreach ($page['sections'] as $section => $data) {
                //                $html .= '<li>| <a class="tab" id="' . $section . '_tab" href="#' . $section . '">' . $data['title'] . '</a></li>' . "\n";
                //            }

                $html .= '</ul>' . "\n";

                $html .= '<div class="clear"></div>' . "\n";
                // Get settings fields
                ob_start();
                ?>
                <div id="<?php echo $page['slug'] . '_container' ?>" class="settings_section_container">
                    <?php
                    settings_fields($page['slug']);
                    do_settings_sections($page['slug']);
                    //                    wp_editor('', 'test', $settings = array());
                    ?>
                </div>
                <?php

                $html .= ob_get_clean();

                $html .= '<p class="submit">' . "\n";
                $html .= $this->_get_submit_button();
                $html .= '</p>' . "\n";
                $html .= '</form>' . "\n";
                $html .= '<div id="attalzr_how_to_link"><a target="new" href="' . plugin_dir_url(__FILE__) . 'assets/example-input-output/how-to-spreadsheet-breakdown.pdf">Download Instructional PDF</a></div>';
//				$html .= '<div id="attalzr_input_ouput_example"><img src="' . plugin_dir_url(__FILE__) . 'assets/example-input-output/example-input-output-format-v100.jpg" /></div>';
                $html .= '</div>' . "\n";
                $html .= '</div>' . "\n";

                echo $html;
            }
        }
    }

    /**
     * Performs other actions involved with settings fields other than saving.
     */
    public function submit_settings_fields()
    {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {

        }
    }
}

