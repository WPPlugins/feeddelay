<?php
/*
Plugin Name: FeedDelay
Plugin URI: http://www.naden.de/blog/feed-delay-wordpress-plugin
Description: Allows you to publishes posts delayed in your feed. You can set a different delay for each feed type (RSS1, RSS2, ATOM, RDF).
Version: 0.1
Author: Naden Badalgogtapeh
Author URI: http://www.naden.de/blog
*/

class FeedDelay {
  
  function FeedDelay() {
    $this->id         = 'feeddelay';
    $this->version    = '0.1';
    $this->name       = 'FeedDelay';
    $this->author_url = 'http://www.naden.de/blog/feed-delay-wordpress-plugin';
    
    $this->feed_types = array(
      'rss',
      'rss2',
      'atom',
      'rdf'
    );
    
    $locale = get_locale();

	  if(empty($locale)) {
		  $locale = 'en_US';
    }

    load_textdomain($this->id, dirname(__FILE__). '/'. $locale. '.mo');
    
    $this->loadOptions(); 
    
    if(is_admin()) {
      add_action('admin_menu', array(&$this, 'optionMenu'));
    }
    else {
      // remove_action( 'wp_head', 'feed_links_extra' ); // Display the links to the extra feeds such as category feeds
      // remove_action( 'wp_head', 'feed_links' ); // Display the links to the general feeds: Post and Comment Feed
      // remove_action( 'wp_head', 'rsd_link' ); // Display the link to the Really Simple Discovery service endpoint, EditURI link
      
      if(intval($this->options['remove_version']) == 1) {
        add_filter('the_generator', array(&$this, 'remove_version'));
      }

      foreach($this->feed_types as $feed_type) {
      
        if($feed_type == 'feed') {
          $feed_type = 'rss2';
        }
        
        if(intval($this->options['enabled_'. $feed_type]) == 0) {
          
          if($feed_type == 'rss2') {
            add_action('do_feed', array(&$this, 'remove_feed'), 1);
          }

          add_action('do_feed_'. $feed_type, array(&$this, 'remove_feed'), 1);
        }
        
      }
      
      add_filter('posts_where', array(&$this, 'alter_sql'));
    }
  }
  
  function optionMenu() {
    add_options_page($this->name, $this->name, 8, __FILE__, array(&$this, 'optionMenuPage'));
  }

  function optionMenuPage() {

    if(isset($_REQUEST[$this->id])) {

      $this->updateOptions($_REQUEST[$this->id]);
    
      echo '<div id="message" class="updated fade"><p><strong>' . __('Settings saved!', $this->id) . '</strong></p></div>'; 
    }
?>
<div class="wrap">

<h2><?=$this->name?> <?php _e('Settings', $this->id); ?> (<a href="<?=$this->author_url?>" target="_blank" title="<?php _e('Visit the plugin homepage.', $this->id); ?>">Plugin homepage</a>)</h2>

<form method="post" action="">
<table class="form-table">
<?php
foreach($this->feed_types as $feed_type) {
  printf('
  <tr valign="top"><th scope="row">%s-Feed %s</th><td><input type="text" name="%s[delay_%s]" value="%d" /> %s</td></tr>
  <tr valign="top"><th scope="row">%s-%s</th><td><input type="radio" name="%s[enabled_%s]" value="1" %s/>%s <input type="radio" name="%s[enabled_%s]" value="0" %s/>%s</td></tr>',
    ucfirst($feed_type),
    __('delay'),
    $this->id,
    $feed_type,
    $this->options['delay_'. $feed_type],
    __('minute(s)', $this->id),
    
    ucfirst($feed_type),
    __('Feed enabled?', $this->id),
    $this->id,
    $feed_type,
    intval($this->options['enabled_'. $feed_type]) == 1 ? 'checked="checked"' : '',
    __('yes', $this->id),
    
    $this->id,
    $feed_type,
    intval($this->options['enabled_'. $feed_type]) == 0 ? 'checked="checked"' : '',
    __('no', $this->id)
  );
}
?>
<tr valign="top">
  <th scope="row"><?php _e('Remove Wordpress version from feeds?', $this->id); ?></th>
  <?php
    printf('<td><input type="radio" name="%s[remove_version]" value="1" %s/>%s <input type="radio" name="%s[remove_version]" value="0" %s/>%s</td>', 
      $this->id,
      intval($this->options['remove_version']) == 1 ? 'checked="checked"' : '',
      __('yes', $this->id),
      $this->id,
      intval($this->options['remove_version']) == 0 ? 'checked="checked"' : '',
      __('no', $this->id)
    );
?>
</tr>
</table>

<p class="submit">
  <input type="submit" value="<?php _e('save settings', $this->id); ?>" name="submit" />
</p>

<p align="center"><?=$this->name?> v<?=$this->version?> - <a href="<?=$this->author_url?>" target="_blank" title="<?php _e('Visit the plugin homepage.', $this->id); ?>">Plugin homepage</a></p>

</form>

</div>
<?php
  }
  
  function remove_version() {
    return '';
  }
  
  function remove_feed() {
    wp_die(sprintf(__('This feed is not available. Please visit the <a href="%s">homepage</a>!', $this->id), get_bloginfo('url')));
  }
  
  function alter_sql($where) {
    if(is_feed()) {
  
      global $wp_query;
      
      $feed_type = $wp_query->query_vars['feed'];
      
      if($feed_type == 'feed') {
        $feed_type = 'rss2';
      }
      
      if(in_array($feed_type, $this->feed_types)) {
        
        global $wpdb;
      
        $where .= sprintf(" AND TIMESTAMPDIFF(MINUTE, %s.post_date_gmt, '%s') > %d ", $wpdb->posts, gmdate('Y-m-d H:i:s'), $this->options['delay_'. $feed_type]);
      } 
    }

    return $where;
  }

  function updateOption($name, $value) {
    $this->updateOptions(array($name => $value));
  }

  function updateOptions($options) {
    foreach($this->options as $k => $v) {
      if(array_key_exists($k, $options)) {
        $this->options[$k] = $options[ $k ];
      }
    }

		update_option($this->id, $this->options);
	}
	
  function loadOptions() {
    
    #delete_option($this->id);
    
    $this->options = get_option($this->id);

    if(!$this->options) {
      $this->options = array(
        'installed' => time(),
        'enabled_rss'  => 1,
        'enabled_rss2' => 1,
        'enabled_atom' => 1,
        'enabled_rdf' => 1,
        'delay_rss'  => 60,
        'delay_rss2' => 60,
        'delay_atom' => 60,
        'delay_rdf' => 60,
        'remove_version' => 0
			);

      add_option($this->id, $this->options, $this->name, 'yes');
    }
  } 
}

add_action('plugins_loaded', create_function('$FeedDelay_1203809k', 'global $FeedDelay; $FeedDelay = new FeedDelay();'));

?>