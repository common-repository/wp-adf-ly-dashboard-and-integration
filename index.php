<?php
/**
 * Plugin Name: WP Adf.ly Dashboard and Integration
 * Plugin URI: https://wordpress-plugins.luongovincenzo.it/#wp-adf-ly-dashboard-integration
 * Description: This plugin allows you to configure Full Page Script, Website Entry Script, Pop-Ads tools and Dashboard widget for stats
 * Version: 1.3.7
 * Author: Vincenzo Luongo
 * Author URI: https://www.luongovincenzo.it/
 * License: GPLv2 or later
 * Text Domain: wp-adf-ly-dashboard-and-integration
 */
if (!defined('ABSPATH')) {
    exit;
}

define("ADFLY_DASHBOARD_INTEGRATION_PLUGIN_DIR", plugin_dir_path(__FILE__));
define("ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX", 'wp_adf_ly_dashboard_integration_option');
define("ADFLY_DASHBOARD_INTEGRATION_PLUGIN_SETTINGS_GROUP", 'wp-adf-ly-dashboard-integration-settings-group');

class WPAdflyDashboardIntegration {

    protected $pluginDetails;
    protected $pluginOptions = [];

    function __construct() {

        if (!function_exists('get_plugin_data')) {
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }

        $this->pluginDetails = get_plugin_data(__FILE__);
        $this->pluginOptions = [
            'enabled' => get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_enabled'),
            'enabled_stats' => get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_enabled_stats'),
            'enabled_amp' => get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_enabled_amp'),
            'id' => trim(get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_id')) ?: '-1',
            'popads_enabled' => get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_popads_enabled'),
            'type' => trim(get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_type')) ?: 'int',
            'domain' => trim(get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_domain')) ?: 'adf.ly',
            'custom_domain' => trim(get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_custom_domain')) ?: '',
            'nofollow' => get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_nofollow'),
            'website_entry_enabled' => get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_website_entry_enabled'),
            'protocol' => trim(get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_protocol')) ?: 'http',
            'include_exclude_domains_choose' => get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_include_exclude_domains_choose') ?: 'exclude',
            'include_exclude_domains_value' => trim(get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_include_exclude_domains_value')),
            'widget_filter_month' => get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_widget_month_filter'),
        ];

        add_action('wp_dashboard_setup', [$this, 'dashboard_widget']);

        add_action('wp_head', [$this, 'gen_script']);
        add_action('admin_menu', [$this, 'create_admin_menu']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_plugin_actions']);

        add_action('admin_enqueue_scripts', [$this, 'widget_dashboard_ajax_script']);
        add_action('wp_ajax_wp_adfly_update_month_filter', [$this, 'wp_adfly_update_month_filter_action']);

        /*
         * Support for AMPforWP - ampforwp.com
         */

        if (in_array('accelerated-mobile-pages/accelerated-moblie-pages.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            if ($this->pluginOptions['enabled_amp']) {
                add_action('amp_post_template_head', [$this, 'gen_script']);
            }
        }
    }

    public function wp_adfly_update_month_filter_action() {

        $filter = $this->validFilter(@$_POST['filter_month']);

        update_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_widget_month_filter', $filter);

        wp_die();
    }

    private function validFilter($timestamp) {
        if (!empty($timestamp) && ((string) (int) $timestamp === $timestamp) && ($timestamp <= PHP_INT_MAX) && ($timestamp >= ~PHP_INT_MAX) && is_numeric($timestamp)) {

            return $timestamp;
        } else {
            return null;
        }
    }

    public function widget_dashboard_ajax_script($hook) {

        if ('index.php' != $hook) {
            // Only applies to dashboard panel
            return;
        }


        wp_enqueue_style('adf-ly-dashboard-widget-admin-theme', plugins_url('/css/style.css', __FILE__), $this->pluginDetails['Version']);

        wp_enqueue_script('chartjs', plugins_url('/js/chartjs.js', __FILE__), ['jquery'], $this->pluginDetails['Version']);

        wp_enqueue_script('ajax-script', plugins_url('/js/main.js', __FILE__), ['jquery'], $this->pluginDetails['Version']);

        wp_localize_script('ajax-script', 'ajax_object', ['ajax_url' => admin_url('admin-ajax.php'), 'filter_month' => null]);
    }

    public function add_plugin_actions($links) {
        $links[] = '<a href="' . esc_url(get_admin_url(null, 'options-general.php?page=wp-adf-ly-dashboard-and-integration%2Findex.php')) . '">Settings</a>';
        $links[] = '<a href="https://wordpress-plugins.luongovincenzo.it/#donate" target="_blank">Donate</a>';
        return $links;
    }

    private function includeExcludeDomainScript($options) {
        $script = 'var ';
        if ($options['include_exclude_domains_choose'] == 'include') {
            $script .= 'domains = [';
        } else if ($options['include_exclude_domains_choose'] == 'exclude') {
            $script .= 'exclude_domains = [';
        }
        if (trim($options['include_exclude_domains_value'])) {
            $script .= implode(', ', array_map(function ($x) {
                        return json_encode(trim($x));
                    }, explode(',', trim($options['include_exclude_domains_value']))));
        }

        $script .= '];';
        return $script;
    }

    public function gen_script() {
        if (get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_enabled')) {
            $options = $this->pluginOptions;

            print '<!--wp-adf-ly-dashboard-integration-->
                <script type="text/javascript">
                    var adfly_id = ' . json_encode($options['id']) . ';
                    var adfly_advert = ' . json_encode($options['type']) . ';
                    var adfly_domain = ' . json_encode($options['custom_domain'] ?: $options['domain']) . ';
                    ' . ($options['nofollow'] ? 'var adfly_nofollow = true;' : '') . '
                    var adfly_protocol = ' . json_encode($options['protocol']) . ';
                    ' . $this->includeExcludeDomainScript($options) . ' 
                    
                    ' . ($options['website_entry_enabled'] ? 'var frequency_cap = 5;' : '') . ' 
                    ' . ($options['website_entry_enabled'] ? 'var frequency_delay = 5;' : '') . ' 
                    ' . ($options['website_entry_enabled'] ? 'var init_delay = 3;' : '') . ' 
                    
                    ' . ($options['popads_enabled'] ? 'var popunder = true;' : '') . ' 
                </script>
                <script defer src="https://cdn.adf.ly/js/link-converter.js"></script>
                ' . ($options['website_entry_enabled'] ? '<script defer src="https://cdn.adf.ly/js/entry.js"></script>' : '') . ' 
                <!-- [END] wp-adf-ly-dashboard-integration -->
            ';
        } else {
            return false;
        }
    }

    public function create_admin_menu() {
        add_options_page('AdFly Integration Settings', 'AdFly Integration Settings', 'administrator', __FILE__, [$this, 'viewAdminSettingsPage']);
        add_action('admin_init', [$this, '_registerOptions']);
    }

    function optionIdValidate($value) {
        if (!preg_match("/^([0-9])+$/", str_replace(" ", "", trim($value)))) {
            add_settings_error('adfly_plugins_option_id', 'adfly_plugins_option_id', 'User ID is required and must be a number.', 'error');
            return false;
        } else {
            return $value;
        }
    }

    private function domainNameValidate($value) {
        return preg_match('/^(?!\-)(?:[a-zA-Z\d\-]{0,62}[a-zA-Z\d]\.){1,126}(?!\d+)[a-zA-Z\d]{1,63}$/', $value);
    }

    private function includeExcludeDomainsValueValidate($value) {
        $arr = array_filter(array_map(function ($x) {
                    return trim($x);
                }, explode(',', trim($value))), function ($x) {
                    return $x ? true : false;
                });
        if (count($arr)) {
            array_map(function ($x) {
                if (!$this->domainNameValidate($x)) {
                    //add_settings_error('adfly_plugins_option_id', 'adfly_plugins_option_include_exclude_domains_value', $x . ' is not valid domain name.', 'error');
                }
            }, $arr);
        } else {
            add_settings_error(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_id', [$this, 'includeExcludeDomainsValueValidate'], 'You must specify at least one domain name to include/exclude.', 'error');
        }

        return implode(',', $arr);
    }

    private function custom_domain_validate($value) {
        if (($value = trim($value)) && !$this->domainNameValidate($value)) {
            add_settings_error(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_id', ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_custom_domain', $value . ' is not valid domain name.', 'error');
            return false;
        }

        return $value;
    }

    public function _registerOptions() {
        register_setting(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_SETTINGS_GROUP, ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_enabled');
        register_setting(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_SETTINGS_GROUP, ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_id', [$this, 'optionIdValidate']);
        register_setting(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_SETTINGS_GROUP, ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_enabled_stats');
        register_setting(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_SETTINGS_GROUP, ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_enabled_amp');
        register_setting(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_SETTINGS_GROUP, ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_public_api_key');
        register_setting(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_SETTINGS_GROUP, ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_secret_api_key');
        register_setting(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_SETTINGS_GROUP, ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_popads_enabled');
        register_setting(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_SETTINGS_GROUP, ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_type');
        register_setting(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_SETTINGS_GROUP, ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_domain');
        register_setting(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_SETTINGS_GROUP, ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_custom_domain', [$this, 'domainNameValidate']);
        register_setting(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_SETTINGS_GROUP, ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_nofollow');
        register_setting(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_SETTINGS_GROUP, ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_website_entry_enabled');
        register_setting(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_SETTINGS_GROUP, ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_protocol');
        register_setting(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_SETTINGS_GROUP, ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_include_exclude_domains_choose');
        register_setting(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_SETTINGS_GROUP, ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_include_exclude_domains_value', [$this, 'includeExcludeDomainsValueValidate']);
    }

    public function dashboard_widget() {
        wp_add_dashboard_widget('adf_ly_dashboard_widget', 'Earnings Dashboard for Adf.ly', [$this, 'adf_ly_dashboard_widget']);
    }

    public function viewAdminSettingsPage() {
        ?>

        <style>
            .left_adfly_bar {
                width:200px;
            }
            #domains_demo_list {
                display: none;
                width: 64%;
            }
        </style>
        <div class="wrap">
            <h2>WP Adf.ly Integration Settings</h2>

            <form method="post" action="options.php">
                <?php settings_fields(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_SETTINGS_GROUP); ?>
                <?php do_settings_sections(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_SETTINGS_GROUP); ?>
                <table class="form-table">
                    <tbody>
                        <tr valign="top">
                            <td scope="row" class="left_adfly_bar">Integration Enabled</td>
                            <td><input type="checkbox" <?php echo get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_enabled') ? 'checked="checked"' : '' ?> value="1" name="<?php print ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX; ?>_enabled" /></td>
                        </tr>



                        <tr valign="top">
                            <td scope="row" class="left_adfly_bar">Enable AMPforWP Integration</td>
                            <td><input type="checkbox" <?php echo get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_enabled_amp') ? 'checked="checked"' : '' ?> value="1" name="<?php print ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX; ?>_enabled_amp" /></td>
                        </tr>

                        <tr valign="top">
                            <td scope="row" class="left_adfly_bar">AdFly User ID</td>
                            <td>
                                <input type="text" name="<?php print ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX; ?>_id" value="<?php echo htmlspecialchars(get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_id'), ENT_QUOTES) ?>" required />
                                <p class="description">
                                    Simply visit <a href="https://login.adf.ly/publisher/tools#tools-api" target="_blank">API Documentation</a> page.
                                    Read <strong>Your User ID</strong> number
                                </p>
                            </td>
                        </tr>

                        <tr valign="top">
                            <td scope="row" class="left_adfly_bar">Widget Stats Enabled</td>
                            <td><input type="checkbox" <?php echo get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_enabled_stats') ? 'checked="checked"' : '' ?> value="1" name="<?php print ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX; ?>_enabled_stats" /></td>
                        </tr>
                        <tr valign="top">
                            <td scope="row" class="left_adfly_bar">Public API Key</td>
                            <td>
                                <input type="text" name="<?php print ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX; ?>_public_api_key" value="<?php echo htmlspecialchars(get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_public_api_key'), ENT_QUOTES) ?>" required />
                                <p class="description">
                                    Simply visit <a href="https://login.adf.ly/publisher/tools#tools-api" target="_blank">API Documentation</a> page.
                                    Read <strong>Your Public API Key</strong> number
                                </p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <td scope="row" class="left_adfly_bar">Secret API Key</td>
                            <td>
                                <input type="text" name="<?php print ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX; ?>_secret_api_key" value="<?php echo htmlspecialchars(get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_secret_api_key'), ENT_QUOTES) ?>" required />
                                <p class="description">
                                    Simply visit <a href="https://login.adf.ly/publisher/tools#tools-api" target="_blank">API Documentation</a> page.
                                    Read <strong>Your Secret API Key</strong> number
                                </p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <td scope="row" class="left_adfly_bar">Ad Type</td>
                            <td>
                                <select name="<?php print ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX; ?>_type">
                                    <option value="int" <?php echo get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_type') == 'int' ? 'selected="selected"' : '' ?>>Interstitial</option>
                                    <option value="banner" <?php echo get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_type') == 'banner' ? 'selected="selected"' : '' ?>>Banner</option>
                                </select>
                            </td>
                        </tr>
                        <tr valign="top">
                            <td scope="row" class="left_adfly_bar">AdFly Domain</td>
                            <td>
                                <select name="<?php print ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX; ?>_domain">
                                    <option value="adf.ly" <?php echo get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_domain') == 'adf.ly' ? 'selected="selected"' : '' ?>>adf.ly</option>
                                    <option value="j.gs" <?php echo get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_domain') == 'j.gs' ? 'selected="selected"' : '' ?>>j.gs</option>
                                    <option value="q.gs" <?php echo get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_domain') == 'q.gs' ? 'selected="selected"' : '' ?>>q.gs</option>
                                </select>
                                &nbsp;or specify custom domain&nbsp;
                                <input type="text" name="<?php print ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX; ?>_custom_domain" value="<?php echo htmlspecialchars(get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_custom_domain'), ENT_QUOTES) ?>" />
                                <p class="description">Custom domain can be used only with HTTP protocol.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <td scope="row" class="left_adfly_bar">Include/Exclude Domains</td>
                            <td>
                                <div>
                                    <label>
                                        <input type="radio" name="<?php print ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX; ?>_include_exclude_domains_choose" value="include" <?php echo!get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_include_exclude_domains_choose') || get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_include_exclude_domains_choose') == 'include' ? 'checked="checked"' : '' ?> />
                                        Include
                                    </label>
                                    <label>
                                        <input type="radio" name="<?php print ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX; ?>_include_exclude_domains_choose" value="exclude" <?php echo get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_include_exclude_domains_choose') == 'exclude' ? 'checked="checked"' : '' ?> />
                                        Exclude
                                    </label>
                                </div>
                                <div>
                                    <textarea rows="4" style="width: 64%;" name="<?php print ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX; ?>_include_exclude_domains_value"><?php echo htmlspecialchars(trim(get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_include_exclude_domains_value')), ENT_QUOTES) ?></textarea>
                                    <p class="description">
                                        Comma-separated list of domains. you can view a list of demo domains <a href="javascript:jQuery('#domains_demo_list').toggle();">here</a>
                                        <br />
                                        <textarea rows="4" id="domains_demo_list" readonly=""><?php include('domains'); ?></textarea>
                                    </p>

                                </div>
                            </td>
                        </tr>
                        <tr valign="top">
                            <td scope="row" class="left_adfly_bar">No Follow</td>
                            <td>
                                <input type="checkbox" <?php echo get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_nofollow') ? 'checked="checked"' : '' ?> value="1" name="<?php print ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX; ?>_nofollow" />
                                <p class="description">Check this option if you wish links to stop outbound page equity.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <td scope="row" class="left_adfly_bar">Protocol</td>
                            <td>
                                <select name="<?php print ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX; ?>_protocol">
                                    <option value="https" <?php echo get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_protocol') == 'https' ? 'selected="selected"' : '' ?>>https</option>
                                    <option value="http" <?php echo get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_protocol') == 'http' ? 'selected="selected"' : '' ?>>http</option>
                                </select>
                            </td>
                        </tr>
                        <tr valign="top">
                            <td scope="row" class="left_adfly_bar">Pop Ads Enabled</td>
                            <td>
                                <input type="checkbox" <?php echo get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_popads_enabled') ? 'checked="checked"' : '' ?> value="1" name="<?php print ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX; ?>_popads_enabled" />
                                <p class="description">Apart from making money with your AdFly short links, you can also try a new additional method of advertising on AdFly - Pop Ads.</p>
                                <p class="description">These popups can generate you extra revenue if you already use our 'Full Page Script' or 'Website Entry Script' on your website.</p>
                            </td>
                        </tr>
                        <tr valign="top">
                            <td scope="row" class="left_adfly_bar">Website Entry Script Enabled</td>
                            <td>
                                <input type="checkbox" <?php echo get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_website_entry_enabled') ? 'checked="checked"' : '' ?> value="1" name="<?php print ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX; ?>_website_entry_enabled" />
                                <p class="description">Check this option if you wish to earn money when a visitor simply enters your site.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" class="button-primary" value="<?php _e('Update Settings') ?>" />
                </p>
            </form>
        </div>
        <?php
    }

    public function adf_ly_dashboard_widget() {
        require_once ADFLY_DASHBOARD_INTEGRATION_PLUGIN_DIR . 'libs/class-api-client.php';

        $pluginSettings = [
            'enabled_stats' => get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_enabled_stats'),
            'user_id' => get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_id'),
            'public_api_key' => get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_public_api_key'),
            'secret_api_key' => get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_secret_api_key'),
            'widget_filter_month' => get_option(ADFLY_DASHBOARD_INTEGRATION_PLUGIN_OPTIONS_PREFIX . '_widget_month_filter'),
        ];

        if (empty($pluginSettings['enabled_stats'])) {
            print '<h3>Plugin not active, please enter into <a href="' . esc_url(get_admin_url(null, 'options-general.php?page=wp-adf-ly-dashboard-and-integration%2Findex.php')) . '">Setting page</a> and enable it';
            return;
        }

        if (empty($pluginSettings['user_id']) || empty($pluginSettings['public_api_key']) || empty($pluginSettings['secret_api_key'])) {
            print "<h3>ID or Public API Key or Secret API Key not setted, please enter into settings page for edit credentials";
            return;
        }

        $adflyAPIClient = new WPAdflyDashboardIntegrationAPIClient($pluginSettings['user_id'], $pluginSettings['public_api_key'], $pluginSettings['secret_api_key']);

        $dateFilter = null;

        if ($pluginSettings['widget_filter_month']) {
            $dateFilter = date('Y-m-d', $pluginSettings['widget_filter_month']);
        }

        $stats = $adflyAPIClient->getPublisherStats($dateFilter);
        $pushadStats = $adflyAPIClient->getPushadStats($dateFilter);
        $popAdStats = $adflyAPIClient->getPopAdStats($dateFilter);

        $labels_X = [];
        $values_Y = [];

        foreach ($stats['data']['data'] as $stat) {
            $labels_X[] = $stat['day'];
            $values_Y['earnings'][] = $stat['earnings'];
            $values_Y['uniques'][] = $stat['uniques'];
            $values_Y['views'][] = $stat['views'];
            $values_Y['cpm'][] = $stat['cpm'];
        }

        foreach ($pushadStats['data']['data'] as $stat) {
            $values_Y['pushAd'][] = $stat['amount'];
        }

        foreach ($popAdStats['data']['data'] as $stat) {
            $values_Y['popAd'][] = $stat['amount'];
        }
        ?>

        <div id="container-box">

            <div style="height: 300px;" id="containerChartjs">
                <canvas id="adflyStatsCanvas"></canvas>
            </div>

            <table style="width:100%;">
                <tr>
                                   <td style="width:30%;">Filter Month:</td>
                                   <td style="width:70%; font-weight: bold;">
                        <select id="adf_ly_dashboard_widget_filter_month" >
        <?php
        for ($i = 0; $i <= 12; $i++) {

            $selectValue = date('F Y', strtotime("-$i month"));

            $selectedDom = '';
            if (
                    (!$dateFilter && date('Y-m', strtotime($selectValue)) == date('Y-m')) ||
                    ($dateFilter && date('Y-m', strtotime($dateFilter)) == date('Y-m', strtotime($selectValue)) )
            ) {
                $selectedDom = ' selected ';
            }

            print '<option value="' . strtotime($selectValue) . '" ' . $selectedDom . ' >' . $selectValue . '</option>' . PHP_EOL;
        }
        ?>
                        </select>
                    </td>
                </tr>
            </table>

            <table>
                <tr>
                    <td>
                        <div class="small-box"><h3>Ads Tot. Visitors</h3><p><?php print number_format($stats['data']['visitors'], 0, '.', '.'); ?></p></div>
                        <div class="small-box"><h3>PushAd Tot. Visitors</h3><p><?php print number_format($pushadStats['data']['totalUsers'], 0, '.', '.'); ?></p></div>
                        <div class="small-box"><h3>AVG CPM</h3><p><?php print number_format($stats['data']['avgCpm'], 4); ?></p></div>

                        <div class="small-box"><h3>Tot. Ads Earned</h3><p><?php print number_format($stats['data']['earned'], 2, '.', '.'); ?> $</p></div>
                        <div class="small-box"><h3>Tot. Push Ad Earned</h3><p><?php print number_format($pushadStats['data']['totalAmount'], 2, '.', '.'); ?> $</p></div>
                        <div class="small-box"><h3>Tot. Pop Ad Earned</h3><p><?php print number_format($popAdStats['data']['totalAmount'], 2, '.', '.'); ?> $</p></div>

                        <div class="small-box small-md-6"><h3>Grand Total Visitors</h3><p><?php print number_format(($stats['data']['visitors'] + $pushadStats['data']['totalUsers']), 0, '.', '.'); ?></p></div>
                        <div class="small-box small-md-6"><h3>Grand Earnings</h3><p><?php print number_format(($stats['data']['earned'] + $pushadStats['data']['totalAmount'] + $popAdStats['data']['totalAmount']), 2, '.', ','); ?> $</p></div>
                    </td>
                </tr>
            </table>
        </div>

        <script>

            var ADFLY_LABELS_X = [<?php print implode(",", $labels_X); ?>];

            var ADFLY_COLORS = [];
            ADFLY_COLORS['earnings'] = 'rgba(105,204,79,1)';
            ADFLY_COLORS['uniques'] = 'rgba(163,63,84, 1)';
            ADFLY_COLORS['views'] = 'rgba(103,46,223, 1)';
            ADFLY_COLORS['cpm'] = 'rgba(53,70,94, 1)';
            ADFLY_COLORS['pushAd'] = 'rgba(77,200,240, 1)';

            var adflyChartconfig = {
                type: 'line',
                data: {
                    labels: ADFLY_LABELS_X,
                    datasets: [
        <?php foreach ($values_Y as $key => $value) { ?>
                            {
                                label: '<?php print strtoupper($key); ?>',
                                backgroundColor: ADFLY_COLORS['<?php print $key; ?>'],
                                borderColor: ADFLY_COLORS['<?php print $key; ?>'],
                                data: [<?php print implode(",", $values_Y[$key]); ?>],
                                fill: false,
                                hidden: <?php print ($key == 'earnings' || $key == 'cpm' || $key == 'pushAd' || $key == 'popAd') ? 'false' : 'true'; ?>,
                            },
        <?php } ?>
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    title: {
                        display: false,
                        text: 'adf.ly Stats'
                    },
                    tooltips: {
                        mode: 'index',
                        intersect: false,
                    },
                    hover: {
                        mode: 'nearest',
                        intersect: true
                    },
                    scales: {
                        xAxes: [{
                                display: true,
                                scaleLabel: {
                                    display: true,
                                    labelString: 'Days of <?php print $stats['data']['month']; ?>'
                                }
                            }],
                        yAxes: [{
                                display: true,
                                scaleLabel: {
                                    display: false,
                                    labelString: 'Values'
                                }
                            }]
                    }
                }
            };

            document.addEventListener("DOMContentLoaded", function () {
                var adflyCtx = document.getElementById('adflyStatsCanvas').getContext('2d');
                var adflyCanvasChart = new Chart(adflyCtx, adflyChartconfig);
            });

        </script>
        <?php
    }

}

new WPAdflyDashboardIntegration();
?>