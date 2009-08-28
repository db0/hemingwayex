<?php
load_theme_textdomain('hemingwayex');
class HemingwayEx
	{
		var $raw_blocks;
		var $style;
		var $version;
		var $date;
				
		function get_style(){
			$this->style = get_option('hem_style');
		}
			
		function date_format($slashes = false){
			global $hemingwayEx_options;
			if ($slashes)
				return $hemingwayEx_options['international_dates'] == 1 ? 'd/m' : 'm/d'; 
			else
				return $hemingwayEx_options['international_dates'] == 1 ? 'd.m' : 'm.d'; 
		}
		
		// Excerpt cutting. I'd love to use the_excerpt_reloaded, but needless licensing prohibits me from doing so
		function excerpt(){
			echo $this->get_excerpt();
		}
			
		function get_excerpt(){
			global $post;
			global $hemingwayEx_options;
			
			//modified by Nalin. Added option to allow user to specify length of excerpt
			if (!is_null($hemingwayEx_options['excerpt_length']) 
					|| $hemingwayEx_options['excerpt_length'] != 0 ) {
				$max_length = $hemingwayEx_options['excerpt_length'];
			} else {
				$max_length = 75; // Maximum words.
			}
			
			// If they've manually put in an excerpt, let it go!
			if ($post->post_excerpt) return $post->post_excerpt;
			
			// Check to see if it's a password protected post
			if ($post->post_password) {
					if ($_COOKIE['wp-postpass_'.COOKIEHASH] != $post->post_password) {
							if(is_feed()) {
									return __('This is a protected post');
							} else {
									return  get_the_password_form();
							}
					}
			}
			
			if( strpos($post->post_content, '<!--more-->') ) { // There's a more link
				$temp_ex = explode('<!--more-->', $post->post_content, 2);
				$excerpt =  $temp_ex[0];
               		} else {
				$temp_ex = explode(' ', $post->post_content);  // Split up the spaces
				$length = count($temp_ex) < $max_length ? count($temp_ex) : $max_length;
				for ($i=0; $i<$length; $i++) $excerpt .= $temp_ex[$i] . ' ';
			}
			
			$excerpt = balanceTags($excerpt);
			$excerpt = apply_filters('the_excerpt', $excerpt);
			
			return $excerpt;	
		}
	}

$hemingwayEx = new HemingwayEx();
$hemingwayEx->version = "1.5 Final";
$hemingwayEx->date = "2008-09-03";
// Default Options. Used only when HemingwayEx is not installed or a newer version is available
$default_options = Array(
	'international_dates' => 0,
	'excerpt_length' => 75,
	'asc_comments' => 1,
	'page_comments' => 1,
	'slidebar_enabled' => 1,
	'bottombar_enabled' => 1,
	'post_navigation' => 0,
	'excerpt_enabled' => 1,
	'font_resizer' => 1,
	'paging_enabled' => 0,
);
if (!get_option('hem_version') || get_option('hem_version') < $hemingwayEx->version){	
	// HemingwayEx isn't installed, so we'll need to add options
	if (!get_option('hem_version') )
		add_option('hem_version', $hemingwayEx->version, 'Hemingway Version installed');
	else
		update_option('hem_version', $hemingwayEx->version);

	if (!get_option('hem_last_updated')) 
		add_option('hem_last_updated', '0000-00-00', 'Last date HemingwayEx was checked for an update');
	
	if (!get_option('hem_known_update')) 
		add_option('hem_known_update', '0000-00-00', 'Last known date when HemingwayEx update was released');
	if (!get_option('hem_style'))
		add_option('hem_style', '', 'Location of custom style sheet');
		
	if (!get_option('hem_options')) {
		add_option('hem_options', $default_options, 'Default options for HemingwayEx');
	}
	
	wp_cache_flush(); // I was having caching issues
}

// Stuff
add_action ('admin_menu', 'hemingway_menu');
$hem_loc = '../themes/' . basename(dirname($file));
$hemingwayEx_options = get_option('hem_options');
$hemingwayEx_last_updated = get_option('hem_last_updated');
$hemingwayEx->get_style();

// Ajax Stuff
function hemingwayEx_message($message) {
	echo "<div id=\"message\" class=\"updated fade\"><p>$message</p></div>\n";
}

function hemingwayEx_update_version() {
	global $hemingwayEx;
	$known_update = get_option('hem_known_update');
	$found_update = "";//$known_update;
	
	// check for new versions if it's been a week
	if (strcmp(date("Y-m-d", time() - 7 * 24 * 60 * 60), get_option('hem_last_updated')) > 0) {
		// collects only publicly-available stats
		$stats = Array(
			'php'     => PHP_VERSION,
			'server'  => $_SERVER['SERVER_SOFTWARE'],
			'blog'    => 'Wordpress',
			'version' => get_bloginfo('version'),
			'url'     => get_bloginfo('wpurl'),
			'locale'  => WPLANG,
		);
		
		$args = array();
		foreach($stats as $key => $value) {
			$args[] = $key . '=' . urlencode($value);
		}
		$args = implode('&', $args);

		// load wp rss functions for update checking.
		if (!function_exists('parse_w3cdtf')) {
			require_once(ABSPATH . WPINC . '/rss-functions.php');
		}

		// note the updating and fetch potential updates
		update_option('hem_last_updated', date("Y-m-d"));
		$update = fetch_rss("http://nalinmakar.com/tag/hemingwayex/feed?$args");
		
		if ($update === False) {
			hemingwayEx_message(__('HemingwayEx tried to check for updates but failed. This might be the way PHP is set up, or just random network issues. Please <a href="http://nalinmakar.com/HemingwayEx">visit the HemingwayEx website</a> to update manually if needed.', 'hemingwayEx'));
			return;
		}

		// loop through feed, pulling out any updates
		foreach($update->items as $item) {
			$updates = Array();
			if (preg_match('|<!-- HemingwayEx:Update date="(\d{4}-\d{2}-\d{2})" version="(.*?)" -->|', $item['content']['encoded'], $updates)) {
				// if this is the newest update, save it
				if ($updates[1] > $found_update) {
					$found_update = $updates[1];
					$version = $updates[2];
				}
			}
		}
		
		// if an newer update was found, save it
		if (strcmp($found_update, $known_update) > 0)
			update_option('hem_known_update', $found_update);

		// if the best-known update is newer than this ver, tell user
		if (strcmp($found_update, $hemingwayEx->date) > 0)
			hemingwayEx_message(__('An update of HemingwayEx is available</a> as of ', 'hemingwayEx') . $found_update . __('. Download <a href="http://nalinmakar.com/HemingwayEx">HemingwayEx ', 'hemingwayEx') . $version . '</a>.');
	
	}
}

function hemingway_menu() {
	add_submenu_page('themes.php', 'HemingwayEx Options', 'HemingwayEx Options', 5, $hem_loc . 'functions.php', 'menu');
}

function menu() {
	global $hem_loc, $hemingwayEx, $message, $hemingwayEx_options;
	
	if ($_POST['custom_styles']){
		update_option('hem_style', $_POST['custom_styles']);
		wp_cache_flush();
		$message  = __('Styles updated!','hemingwayex');
	}
	
	if ($_POST['reset'] == 1){
		delete_option('hem_style');
		delete_option('hem_blocks');
		delete_option('hem_version');
		delete_option('hem_options');
		delete_option('hem_known_update');
		delete_option('hem_last_updated');
		$message = __('Settings removed.','hemingwayex');
	}
	
	if ($_POST['misc_options']){
		$hemingwayEx_options['international_dates'] = $_POST['international_dates'];
		$hemingwayEx_options['excerpt_length'] = $_POST['excerpt_length'];
		$hemingwayEx_options['asc_comments'] = $_POST['asc_comments'];
		$hemingwayEx_options['page_comments'] = $_POST['page_comments'];
		$hemingwayEx_options['slidebar_enabled'] = $_POST['slidebar_enabled'];
		$hemingwayEx_options['bottombar_enabled'] = $_POST['bottombar_enabled'];
		$hemingwayEx_options['excerpt_enabled'] = $_POST['excerpt_enabled'];
		$hemingwayEx_options['post_navigation'] = $_POST['post_navigation'];
		$hemingwayEx_options['font_resizer'] = $_POST['font_resizer'];
		$hemingwayEx_options['paging_enabled'] = $_POST['paging_enabled'];
		update_option('hem_options', $hemingwayEx_options);
		
		wp_cache_flush();
		$message  = __('Options updated!','hemingwayex');
	}
?>
<?php if($message) : 
	hemingwayEx_message($message);
	endif; ?>
<div id="dropmessage" class="updated" style="display:none;"></div>
<?php if (get_option('hem_version')) : ?>
<?php hemingwayEx_update_version(); ?>
<?php 
	// getting the hemingway options again. For some reason they disappear.
	$hemingwayEx_options = get_option('hem_options');
	$hemingwayEx_last_updated = get_option('hem_last_updated');
	$hemingwayEx_known_update = get_option('hem_known_update');
?>
<div class="wrap" style="position:relative;">
<h2><?php _e('HemingwayEx Options'); ?></h2>
<h3><?php _e('Custom Styles','hemingwayex') ?></h3>
<p><?php _e('Select a style from the dropdown below to customize HemingwayEx with a special style.','hemingwayex') ?></p>
<form name="dofollow" action="" method="post">
  <input type="hidden" name="page_options" value="'dofollow_timeout'" />
	<select name="custom_styles">
	<option value="none"<?php if ($hemingwayEx->style == 'none') echo ' selected="selected"'; ?>><?php _e('No Custom Style','hemingwayex') ?></option>
	<?php
	$scheme_dir = @ dir(ABSPATH . '/wp-content/themes/' . get_template() . '/styles');
	if ($scheme_dir) {
		while(($file = $scheme_dir->read()) !== false) {
				if (!preg_match('|^\.+$|', $file) && preg_match('|\.css$|', $file)) 
				$scheme_files[] = $file;
			}
		}
		if ($scheme_dir || $scheme_files) {
			foreach($scheme_files as $scheme_file) {
			if ($scheme_file == $hemingwayEx->style){
				$selected = ' selected="selected"';
			}else{
				$selected = "";
			}
			echo '<option value="' . $scheme_file . '"' . $selected . '>' . $scheme_file . '</option>';
		}
	} 
	?>
	</select>
	<input type="submit" value="Save" />
</form>
<h3><?php _e('Miscellaneous Options','hemingwayex') ?></h3>
<form name="dofollow" action="" method="post">
<input type="hidden" name="misc_options" value="1" />
<h4><?php _e('Sidebars','hemingwayex') ?></h4>
<p>
	<label><input type="checkbox" value="1" name="slidebar_enabled" <?php if ($hemingwayEx_options['slidebar_enabled'] == 1) echo "checked=\"checked\""; ?> /> <?php _e('Enable Slidebar','hemingwayex') ?>&trade;</label>
</p>
<p>
	<label><input type="checkbox" value="1" name="bottombar_enabled" <?php if ($hemingwayEx_options['bottombar_enabled'] == 1) echo "checked=\"checked\""; ?> /> <?php _e('Enable Bottombar','hemingwayex') ?>&trade;</label>
</p>
<h4><?php _e('Excerpt','hemingwayex') ?></h4>
<p>
	<label><input type="checkbox" value="1" name="excerpt_enabled" <?php if ($hemingwayEx_options['excerpt_enabled'] == 1) echo "checked=\"checked\""; ?> /> <?php _e('Enable Excerpt on homepage','hemingwayex') ?></label>
</p>
<p><?php _e("Enter the length of excerpt in number of words. If length is not specified or is set to 0, it will default to 75 words. Also, this will only be used if an excerpt isn't already defined for the post.",'hemingwayex') ?></p>
<p><input type="text" name="excerpt_length" value="<?php echo $hemingwayEx_options['excerpt_length']; ?>" size="3" /></p>
<h4><?php _e('Comments','hemingwayex') ?></h4>
<p>
	<label><input type="checkbox" value="1" name="asc_comments" <?php if ($hemingwayEx_options['asc_comments'] == 1) echo "checked=\"checked\""; ?> /> <?php _e('Show comments in ascending order','hemingwayex') ?></label>
</p>
<p>
	<label><input type="checkbox" value="1" name="page_comments" <?php if ($hemingwayEx_options['page_comments'] == 1) echo "checked=\"checked\""; ?> /> <?php _e('Display comments section on static pages','hemingwayex') ?></label>
</p>
<h4><?php _e('Misc Options','hemingwayex') ?></h4>
<p>
	<label><input type="checkbox" value="1" name="international_dates" <?php if ($hemingwayEx_options['international_dates'] == 1) echo "checked=\"checked\""; ?> /> <?php _e('Use international dates? (day/month/year)','hemingwayex') ?></label>
</p>
<p>
	<label><input type="checkbox" value="1" name="post_navigation" <?php if ($hemingwayEx_options['post_navigation'] == 1) echo "checked=\"checked\""; ?> /> <?php _e('Enabled previous/next post navigation','hemingwayex') ?></label>
</p>
<p>
	<label><input type="checkbox" value="1" name="paging_enabled" <?php if ($hemingwayEx_options['paging_enabled'] == 1) echo "checked=\"checked\""; ?> /> <?php _e('Enabled paging on homepage','hemingwayex') ?></label>
</p>
<p>
	<label><input type="checkbox" value="1" name="font_resizer" <?php if ($hemingwayEx_options['font_resizer'] == 1) echo "checked=\"checked\""; ?> /> <?php _e('Display font resize script in header','hemingwayex') ?></label>
</p>
<p>
	<br/><input type="submit" value="Save my options" />
</p>
</form>
<h3><?php _e('Updates'); ?></h3>
<p>HemingwayEx checks for new versions when you bring up this page. (At most once per week.)</p>
<p>This copy of HemingwayEx is version <b><?php echo $hemingwayEx->version; ?></b> released on <b><?php echo $hemingwayEx->date; ?></b>. 
<?php if(strcmp($hemingwayEx_known_update, $hemingwayEx->date) > 0) { ?>
There is an update available as of <?php echo $hemingwayEx_known_update ?>. <?php _e('Download','hemingwayEx') ?> <a href="http://nalinmakar.com/HemingwayEx">HemingwayEx</a>.
<?php } else { ?>
You have the latest version installed.
<?php } ?>
</p><p>Last checked on <b><?php echo $hemingwayEx_last_updated; ?></b>.</p>
<h3><?php _e('Donations','hemingwayex') ?></h3>
<p></p>
<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="image" src="https://www.paypal.com/en_US/i/btn/x-click-but04.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
<p>Do you like this theme? I work on HemingwayEx because I enjoy doing it, but it does take up a lot of my time. If you liked this theme, how about buying me a beer/coffee ? :). Thank you.<p>
<input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7-----
">
</form>
<h3><?php _e('Reset / Uninstall','hemingwayex') ?></h3>
<form action="" method="post" onsubmit="return confirm('Are you sure you want to reset all of your settings?')">
<input type="hidden" name="reset" value="1" />
<p><?php _e('If you would like to reset or uninstall HemingwayEx, push this button. It will erase all of your preferences.','hemingwayex') ?> <input type="submit" value="<?php _e('Reset / Uninstall','hemingwayex') ?>" /></p>
</form>
<?php else: // else for 'if (get_option('hem_version'))' ?>
<div class="wrap" style="position:relative;">
<p><?php _e('Thank you for using HemingwayEx! There are two reasons you might be seeing this','hemingwayex') ?>:</p>
<ol>
	<li><?php _e("You've just installed HemingwayEx for the first time: If this is the case, simply reload this page or click on HemingwayEx Options again and you'll be on your way!",'hemingwayex') ?></li>
	<li><?php _e("You've just uninstalled HemingwayEx or reset your options. If you'd like to keep using HemingwayEx, reload this page or click on HemingwayEx Options again.",'hemingwayex') ?></li>
</ol>
<?php endif; ?>
</div>
<?php
}
//register sidebars if, use of widgets is enabled and widgets are supported.
if (function_exists('register_sidebar')) {
    if ($hemingwayEx_options['slidebar_enabled'] == 1){
		 register_sidebar(array(
			'name'=>'Slidebar Left',
				'before_widget' => '<li class="widget">',
			'after_widget' => '</li>',
			'before_title' => '<h2>',
			'after_title' => '</h2>',
		 ));
		register_sidebar(array(
			'name'=>'Slidebar Center',
				'before_widget' => '<li class="widget">',
			'after_widget' => '</li>',
			'before_title' => '<h2>',
			'after_title' => '</h2>',
		 ));
		register_sidebar(array(
			'name'=>'Slidebar Right',
				'before_widget' => '<li class="widget">',
			'after_widget' => '</li>',
			'before_title' => '<h2>',
			'after_title' => '</h2>',
		 ));
    }
    if ($hemingwayEx_options['bottombar_enabled'] == 1){
		register_sidebar(array(
			'name'=>'Bottombar Left',
				'before_widget' => '<li class="widget">',
			'after_widget' => '</li>',
			'before_title' => '<h2>',
			'after_title' => '</h2>',
		 ));
		register_sidebar(array(
			'name'=>'Bottombar Center',
				'before_widget' => '<li class="widget">',
			'after_widget' => '</li>',
			'before_title' => '<h2>',
			'after_title' => '</h2>',
		 ));
		register_sidebar(array(
			'name'=>'Bottombar Right',
				'before_widget' => '<li class="widget">',
			'after_widget' => '</li>',
			'before_title' => '<h2>',
			'after_title' => '</h2>',
		 ));
    }
}
?>