<?php
/* Site Stats Finder (site-stats-finder.php) v.0.1.1 */
require_once ( EAMM_DIR . 'inc/eamm-defines.php' );
require_once ( EAMM_DIR . 'inc/eamm-shortcodes.php' );

class easy_admin_menu {
	protected $pluginloc;
	protected $pluginPage = false;
    protected $logfile = array(
        'LOGFILE' => array('2022-10-12 00:00:00'=>'Activated')
    );
    protected $options = array(
	    'switches' => array(
			'Show Dashboard Info'=>0,
			'Simplify the Admin Menu'=>0
	    ),
	    'items' => array(
		    'upload.php|#|'=>['n'=>'Media / Uploads', 'hide'=>0],
		    'edit-comments.php|#|'=>['n'=>'Comments Menu', 'hide'=>1],
		    'edit.php?post_type=project|#|'=>['n'=>'Project Menu', 'hide'=>1],
		    'themes.php|#|'=>['n'=>'Themes Menu', 'hide'=>0],
		    'plugins.php|#|'=>['n'=>'Plugins Menu', 'hide'=>0],
		    'users.php|#|'=>['n'=>'Users Menu', 'hide'=>1],
	    ),
	    'url' => ''
    );

	/**
	 * Initialise the plugin class
	 * @param string $loc the full directory and filename for the plugin
	 */
	public function __construct($loc) {
		$this->pluginloc = strlen($loc)? $loc: __FILE__;
		//check if the active page is the plugin
		$this->pluginPage = (sanitize_text_field($_GET['page'])=='eamm_menu_page');
		$basename = plugin_basename($this->pluginloc);
		$options = get_option('eamm_options');
		$this->options = is_array($options)? array_merge($this->options,$options): $this->options;
        $this->options['url'] = home_url();
		if (is_admin()){
			add_action( 'admin_enqueue_scripts', array($this, 'eamm_enqueue_admin') );
			add_action( 'admin_init',array($this, 'eamm_register_settings') );
			add_action( 'admin_menu', array($this, 'eamm_admin_menu'), 11 );
			add_filter( 'plugin_action_links_'.$basename, array($this, 'eamm_settings_link') );
			if (isset($this->options['switches']['Show Dashboard Info']) && $this->options['switches']['Show Dashboard Info']){
				add_action( 'wp_dashboard_setup', array($this, 'eamm_add_dashboard') );
			}
			if (!$this->pluginPage) {
				add_action( 'admin_menu', array($this, 'eamm_hide_menus'), 999 );
			} else {
				add_action( 'admin_menu', array($this, 'eamm_update_option'), 999 );
			}
			//manage the stored variable and option values when registering or deactivating
			register_activation_hook( $loc, array($this, 'eamm_load_options' ) );
			register_deactivation_hook( $loc, array($this, 'eamm_unset_options' ) );
			register_uninstall_hook ( $loc, array($this, 'eamm_uninstall') );
		} else {
			add_action('wp_enqueue_scripts', array($this, 'eamm_enqueue_main'));
		}
		//Load a function that runs after all plugins are registered to ensure so that all plugin filters and actions are defined 
		//add_action('plugins_loaded', array($this, 'eamm_late_loader'));
		//set the ajax hooks to run when the license is to be updated
		//add_action( 'wp_ajax_nopriv_my_js_action', array($this, 'my_js_action'));
		//add_action( 'wp_ajax_my_js_action', array($this, 'my_js_action'));
		//create a scheduled cron hook
	}

	// -------------------- Add styles and scripts --------------------
	/**
	 * @param $hook - the admin_enqueue_scripts action provides the $hook_suffix for the current admin page.
	 * This is used to load the scripts only for the admin pages associated with the plugin
	 * HOOK: "toplevel_page_leads5050-code"
	 */
	function eamm_enqueue_main($hook){
		wp_enqueue_style('eamm-main', plugins_url('css/eamm.css', __FILE__));
		wp_enqueue_script('eamm-main', plugins_url(('js/eamm.js?x='.rand(5,300)), __FILE__), array('jquery'), '1.0', true);
		//set any local variables for the script
        wp_localize_script('eamm-main', 'eamm_varz', array(
            'ajax_url' => admin_url('admin-ajax.php'),
			'page_id' => get_the_ID(),
            'hash'=>base64_encode(home_url()),
            'nonce'=>wp_create_nonce('eamm-nonce'),
        ));
	}

	function eamm_enqueue_admin(){
        wp_enqueue_style('eamm-admin-css', plugins_url('css/eamm-admin.css', __FILE__));
        wp_enqueue_script('eamm-admin-js', plugins_url(('js/eamm-admin.js?x='.rand(5,300)), __FILE__), array('jquery'), '1.0', true);
	}

	/**
	 * Late loading function for actions that runs after all plugins are loaded
	 */
	function eamm_late_loader(){

	}

	function eamm_update_option(){
		$this->options['items'] = update_toggle_list($this->options['items']);
		update_option('eamm_options', $this->options);
	}

	// -------------------- Options and Variables - Admin Settings Form Definition --------------------

	function eamm_admin_menu() {
		add_menu_page( 'Easy Admin Menu Manager', 'Easy Admin Menu', 'manage_options', 'eamm_menu_page',
			array($this,'eamm_options_page'), plugins_url('/img/eamm-y.png',__FILE__),9999);
		add_management_page('Easy Admin Menu Manager', 'Easy Admin Menu', 'manage_options',
			'eamm_menu_page', array($this, 'eamm_options_page'));
	}

	/**
	 * @param $links - When the 'plugin_action_links_(plugin file name)' filter is called, it is passed one parameter:
	 * namely the links to show on the plugins overview page in an array
	 * https://codex.wordpress.org/Plugin_API/Filter_Reference/plugin_action_links_(plugin_file_name)
	 *
	 * @return mixed
	 */
	function eamm_settings_link($links) {
		$url = get_admin_url().'admin.php?page=eamm_menu_page';
		$settings_link = '<a href="'.$url.'">' . __("Settings") . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	function eamm_register_settings() {
		register_setting('eamm_group', 'eamm_options', array($this, 'eamm_validate'));
	}

	/**
	 * Validate and transform the values submitted to the options form. All inputs are switches and values are
	 * forced to 0 (off) or 1 (on) for the storage and display.
	 * @param array $input - options results from the form submission
	 * @return array|false - validated and transformed options results
	 */
	function eamm_validate($input){
        $output = array();
		foreach ( $this->options['switches'] as $type => $state ) {
			$output['switches'][$type] = (isset($input['switches'][$type]) && $input['switches'][$type])? 1: 0;
		}
		foreach ( $this->options['items'] as $att => $arr ) {
			$output['items'][$att] = $arr;
			$output['items'][$att]['hide'] = (isset($input['items'][$att]['hide']) && $input['items'][$att]['hide'])? 1: 0;
		}
		return $output;
	}

	function eamm_options_page() {
		$options = $this->options;
		if(current_user_can('manage_options')) {
			echo '<div class="wrap">';
				echo '<h2>Option Settings ['.esc_html(get_admin_page_title()).']</h2>';
				echo '<div id="eamm-main-form">';
				settings_errors();
					echo '<form action="options.php" method="post">';
						settings_fields('eamm_group'); //This line must be inside the form tags!!
						echo '<table class="form-table">';
							echo '<tr class="eamm-input-hdr"><th colspan="2">GENERAL SETTINGS</th></tr>';
							echo '<tr><th>Name</th><th>State</th></tr>';
							foreach ($options['switches'] as $name => $toggle) {
								echo '<tr><td style="width:30%;">'.esc_html($name).'</td><td>';
								echo '<label class="eamm_switch">';
								$disable = (in_array($name,array('External')))? 'disabled="disabled"': '';
								echo '<input name="eamm_options[switches]['.esc_html($name).']" value="1" type="checkbox" '.($toggle? "checked": "").' '.esc_attr($disable).'>';
								echo '<span class="eamm_slider"></span>';
								echo '</label>';
								echo '</td></tr>';
							}
						echo '</table>';
						echo '<table class="form-table">';
							echo '<tr class="eamm-input-hdr"><th colspan="2">MENU SWITCHES</th></tr>';
							echo '<tr><th>Name</th><th>Hide</th></tr>';
							foreach ($options['items'] as $att => $arr) {
								echo '<tr><td style="width:30%;">'.esc_html($arr['n']).'</td><td>';
								echo '<label class="eamm_switch">';
								echo '<input name="eamm_options[items]['.esc_html($att).'][hide]" value="1" type="checkbox" '.($arr['hide']==1? "checked": "").'>';
								echo '<span class="eamm_slider"></span>';
								echo '</label>';
								echo '</td></tr>';
							}
						echo '</table>';
						submit_button();
					echo '</form>';
				echo '</div>';
			echo '</div>';

		} else {
			wp_die(__('You do not have sufficient permissions to access this page.'));
		}
	}

	// -------------------- Dashboard Widget --------------------
	public function eamm_add_dashboard(){
		wp_add_dashboard_widget ('eamm_dashboard_widget', 'Easy Admin Menu Manager',array($this, 'eamm_dashboard_widget'));
	}

	/**
	 * Include a notice that Easy Admin Menu Manager is active on the site and link this to the menu item.
	 * @return void
	 */
	public function eamm_dashboard_widget(){
		$logo = plugin_dir_url( __FILE__ ).'img/easy-notice.png';
		$url = get_admin_url().'admin.php?page=eamm_menu_page';
		echo '<div><a href="'.esc_url($url).'" title="Go To Easy Admin Menu"><img src="'.esc_url($logo).'"/></a></div>';
	}
	// -------------------- Clean Dashboard --------------------
	/**
	 * Remove the menu items that are installed and marked to be hidden
	 * @return void
	 */
	function eamm_hide_menus(){
		$options = get_option('eamm_options');
		$items = (is_array($options['items']) && count($options['items']))? $options['items']: [];
		if (isset($options['switches']['Simplify the Admin Menu']) && $options['switches']['Simplify the Admin Menu']){
			if ( $items ){
				foreach ( $items as $att=>$arr ) {
					$mnu = explode('|#|',$att);
					if ( is_array($arr) && isset($mnu[0]) && isset($mnu[1]) && $arr['hide']==1 ) {
						if ( strlen($mnu[1]) ) {
							remove_submenu_page( $mnu[0], $mnu[1]);
						} else {
							remove_menu_page($mnu[0]);
						}
					}
				}
			}
		}
	}
	// -------------------- Actions --------------------
	// -------------------- AJAX call function --------------------
	// -------------------- Set up the CRON jobs --------------------

	// -------------------- Define actions to be taken when installing and uninstalling the Plugin --------------------
	function eamm_load_options() {
		//$value = serialize($this->options);
		add_option('eamm_options', $this->options);
		add_option('eamm_log', $this->logfile);
	}

	function eamm_unset_options() {
        delete_option('eamm_options');
        delete_option('eamm_log');
		//Unschedule any CRON jobs
	}

	function eamm_uninstall() {
		delete_option('eamm_options');
		delete_option('eamm_log');
		//Unschedule any CRON jobs
	}
}

/**
 * Check that option values are upp to date when the options page is loaded
 * @param $toggles
 * @param $incSubmenu
 *
 * @return array
 */
function update_toggle_list($toggles, $incSubmenu=false): array {
	global $menu, $submenu;
	$menuList = [];
	//get the full menu list and set the default toggle to off
	foreach ( $menu as $i => $itm ) {
		if ( isset($itm[0]) && strlen($itm[0]) && isset($itm[2]) && $itm[2]!='eamm_menu_page' && $itm[2]!='index.php' ){
			$m = sanitize_text_field($itm[2]);
			$menuList[($m.'|#|')] = ['n'=>sanitize_text_field($itm[0]), 'hide'=>0];
			if ($incSubmenu){
				if ( isset($submenu[$itm[2]]) && is_array($submenu[$itm[2]]) ){
					foreach ( $submenu[$itm[2]] as $j => $sub ) {
						if ( isset($sub[0]) && strlen($sub[0]) && isset($sub[2]) ){
							$s = sanitize_text_field($sub[2]);
							if ( $m!=$s ) {
								$menuList[($m.'|#|'.$s)] = ['n'=>sanitize_text_field($sub[0]), 'hide'=>0];
							}
						}
					}
				}
			}
		}
	}
	//update the toggles based on the original toggle list ($toggles)
	if (is_array($toggles)){
		foreach ( $toggles as $att => $tgl ) {
			if (isset($menuList[$att])){
				$menuList[$att]['hide'] = $tgl['hide'];
			}
		}
	}
	return $menuList;
}

