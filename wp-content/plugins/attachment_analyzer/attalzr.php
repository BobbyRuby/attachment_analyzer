<?php
/**
 * Created by PhpStorm.
 * User: Bobby
 * Date: 3/15/2015
 * Time: 3:48 PM
 */
include_once('class-poleAnalyzer.php');
include_once('class-attalzrSettings.php');

class attalzrPlugin
{
    private static $_instance;
    public $_incorrect_file; // string that holds error message for incorrect file
    public $_errors; // boolean true if errors
    private $_settings; // holds settings page data - class object that creates the initial display of the page for the analyzer

    function __construct()
    {
        $this->_settings = new attalzrSettings(__FILE__); // this class creates the page in wordpress and calls the other classes within settings_fields
        add_action('admin_menu', array($this, 'remove_menus'));
        add_action('wp_before_admin_bar_render', array($this, 'remove_admin_bar_links'));
        add_filter('login_redirect', create_function('$url,$query,$user', 'return home_url();'), 10, 3);

        // working on it
//		add_action( 'admin_notices', array( $this, 'working_admin_notice' ) );
    }

    public static function working_admin_notice()
    {
        ?>
        <div class="error">
            <p><?php _e('Currently CODING.  May not work as expected!', 'my-text-domain'); ?></p>
        </div>
    <?php
    }

    public static function remove_menus()
    {
        remove_menu_page('index.php');                  //Dashboard
        remove_menu_page('edit.php');                   //Posts
        remove_menu_page('upload.php');                 //Media
        remove_menu_page('edit.php?post_type=page');    //Pages
        remove_menu_page('edit-comments.php');          //Comments
        remove_menu_page('themes.php');                 //Appearance
        remove_menu_page('plugins.php');                //Plugins
        remove_menu_page('users.php');                  //Users
        remove_menu_page('tools.php');                  //Tools
        remove_menu_page('options-general.php');        //Settings
        remove_menu_page('profile.php');                //Profile
        remove_menu_page('wpcf7');                     //Contact Form 7
        remove_menu_page('revslider');                 //rev slider
    }

    public static function remove_admin_bar_links()
    {
        global $wp_admin_bar;
        $wp_admin_bar->remove_menu('wp-logo');          // Remove the WordPress logo
        $wp_admin_bar->remove_menu('about');            // Remove the about WordPress link
        $wp_admin_bar->remove_menu('wporg');            // Remove the WordPress.org link
        $wp_admin_bar->remove_menu('documentation');    // Remove the WordPress documentation link
        $wp_admin_bar->remove_menu('support-forums');   // Remove the support forums link
        $wp_admin_bar->remove_menu('feedback');         // Remove the feedback link
        $wp_admin_bar->remove_menu('site-name');        // Remove the site name menu
        $wp_admin_bar->remove_menu('view-site');        // Remove the view site link
        $wp_admin_bar->remove_menu('updates');          // Remove the updates link
        $wp_admin_bar->remove_menu('comments');         // Remove the comments link
        $wp_admin_bar->remove_menu('new-content');      // Remove the content link
        $wp_admin_bar->remove_menu('w3tc');             // If you use w3 total cache remove the performance link
//		$wp_admin_bar->remove_menu('my-account');       // Remove the user details tab
    }

    /**
     * @return attalzrPlugin
     */
    public static function getInstance()
    {
        if (null == self::$_instance) {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    /**
     * Another function for debugging
     * @param $debugItem
     * @param int $die
     * Usage agsgPlugin::rfd_debugger() or call from extended class
     */
    public static function rfd_debugger($debugItem, $die = 0)
    {
        echo '<pre>';
        print_r($debugItem);
        echo '</pre>';
        if ($die == 1) {
            die();
        }
    }

    /**
     * Handles reading of our csv file and returns an indexed array to work with
     * @param $csvFile
     *
     * @return array - Indexed array of csv ROWS.  Index 0 is always the column headers
     */
    public static function readCSV($csv_file)
    {
        $file_handle = fopen($csv_file, 'r');
        while (!feof($file_handle)) {
            $line_of_text[] = fgetcsv($file_handle, 1024);
        }
        fclose($file_handle);

        return $line_of_text;
    }

    /**
     *
     * Calls readCSV and organizes poles into an associative array where the pole can be accessed by its handle from CAD
     *
     * [0] => HANDLE
     * [1] => BLOCKNAME
     * [2] => REF
     * [3] => PLTG
     * [4] => OWNR
     * [5] => MP
     * [6] => ADRES
     * [7] => LOC
     * [8] => BLNK
     * [9] => LWSTPWR
     * [10] => TRFCCRCT
     * [11] => STLT
     * [12] => PWRFBR
     * [13] => UKNCM
     * [14] => UNKCM1
     * [15] => UNKCM2
     * [16] => CATV
     * [17] => CATV1
     * [18] => UNKCM3
     * [19] => UKNCM4
     * [20] => TELCO
     * [21] => TELCO1
     * [22] => TELCO2
     * [23] => TELCO3
     * [24] => UKN
     * [25] => UKN1
     * [26] => COMNTS
     * [27] => TYPE
     */
    public static function beginAnalyzingCSV()
    {
        if (count($_FILES) >= 1 && $_FILES['attalzr_file']['type'] === 'application/vnd.ms-excel') {
            $csv_raw = attalzrPlugin::readCSV(($_FILES['attalzr_file']['tmp_name']));
            $headings = $csv_raw[0];
            $headings[] = 'MR';
            $headings[] = 'PHOA';
            // grab all poles and put into pole rows
            // 0 is the headings key
            for ($i = 1; $i < count($csv_raw); $i++) {
                // get pole handle
                $handle = str_replace("'", "", $csv_raw[$i][0]);
                // grab all attachments and store as NAME (key) HEIGHT (value)
                // 0 was the handle
                for ($j = 0; $j < count($csv_raw[$i]); $j++) {
                    $pole[$headings[$j]] = $csv_raw[$i][$j];
                }
                $poles[$handle] = $pole; // store pole data by handle
            }
            // analyze each pole and store in associative array
            foreach ($poles as $pole) {
                $analyzed_pole = new pole_analyzer($pole, $_POST);
                $analyzed_poles[$analyzed_pole->pole_handle] = $analyzed_pole->pole;
                $analyzed_pole_objs[$analyzed_pole->pole_handle] = $analyzed_pole;
            }
            attalzrPlugin::outPutPoleData($analyzed_pole_objs);
            attalzrPlugin::createCSVfile($analyzed_poles, $headings);
        } else {
            echo 'No file input or file input was not a CSV.';
        }
    }

    public static function createCSVfile($analyzed_poles, $headings)
    {
        $file_name = plugin_dir_path(__FILE__) . 'assets/generated_csv_files/analyzed_poles.csv';
        $file_url = plugin_dir_url(__FILE__) . 'assets/generated_csv_files/analyzed_poles.csv';
        // create a file pointer
        $output = fopen($file_name, 'w');
        // output the column headings
        fputcsv($output, $headings);
        // loop through poles and output to CSV
        foreach ($analyzed_poles as $pole) {
            fputcsv($output, $pole);
        }
        echo "Your file has been analyzed! <a href='$file_url'>Download 'analyzed_poles.csv'</a>";
    }

    public static function outPutPoleData($analyzed_pole_objs)
    {
        // loop through poles and output data to screen
        foreach ($analyzed_pole_objs as $pole) {
            // attalzrPlugin::rfd_debugger($pole);
        }
    }
}