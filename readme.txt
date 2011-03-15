=== Posts 2 Posts ===
Contributors: scribu
Donate link: http://scribu.net/paypal
Tags: cms, custom post types, relationships, many-to-many
Requires at least: 3.0
Tested up to: 3.1
Stable tag: 0.6

Create connections between posts

== Description ==

This plugin allows you to create many-to-many relationships between posts of all types.

To register a connection type, add this code in your theme's functions.php file:

`
function my_connection_types() {
	if ( !function_exists('p2p_register_connection_type') )
		return;

	p2p_register_connection_type( 'post', 'page' );
}
add_action('init', 'my_connection_types', 100);
`

Then, after creating a few connections, you can list the posts associated to a page, using WP_Query:

`
$connected = new WP_Query( array(
  'post_type' => 'post',
  'connected' => $some_page_id
) );

while( $connected->have_posts() ) $connected->the_post();
  echo '<li>';
  the_title();
  echo '</li>';
endwhile;

wp_reset_postdata();
`

<br>

Links: [API](http://plugins.trac.wordpress.org/browser/posts-to-posts/trunk/api.php) | [Plugin News](http://scribu.net/wordpress/posts-to-posts) | [Author's Site](http://scribu.net)

== Installation ==

You can either install it automatically from the WordPress admin, or do it manually:

1. Unzip the "Posts 2 Posts" archive and put the folder into your plugins folder (/wp-content/plugins/).
1. Activate the plugin from the Plugins menu.

== Frequently Asked Questions ==

= Error on activation: "Parse error: syntax error, unexpected..." =

Make sure your host is running PHP 5. The only foolproof way to do this is to add this line to wp-config.php:

`var_dump(PHP_VERSION);`
<br>

== Screenshots ==

1. The metabox on the post editing screen

== Changelog ==

= 0.7 =
* improved UI

= 0.6 =
* added p2p_each_connected()
* fixed p2p_is_connected()
* made p2p_get_connected() return p2p_ids even with `$direction = 'any'`
* made compatible with [Proper Network Activation](http://wordpress.org/extend/plugins/proper-network-activation)
* [more info](http://scribu.net/wordpress/posts-to-posts/version-0-6.html)

= 0.5.1 =
* fixed fatal error on Menus screen

= 0.5 =
* added 'connected_meta' var to WP_Query
* attach p2p_id to each post found via WP_Query
* 'connected_to' => 'any' etc.
* $data parameter can also be a meta_query
* metabox bugfixes
* fixed l10n loading
* [more info](http://scribu.net/wordpress/posts-to-posts/p2p-0-5.html)

= 0.4 =
* introduced 'connected_from', 'connected_to', 'connected' vars to WP_Query
* replaced $reciprocal with $data as the third argument
* p2p_register_connection_type() accepts an associative array as arguments
* removed p2p_list_connected()
* added p2p_delete_connection()
* [more info](http://scribu.net/wordpress/posts-to-posts/p2p-0-4.html)

= 0.3 =
* store connections using a taxonomy instead of postmeta
* [more info](http://scribu.net/wordpress/posts-to-posts/p2p-0-3.html)

= 0.2 =
* UI that supports multiple related posts. props [Patrik Bón](http://www.mrhead.sk/)
* added p2p_list_connected() template tag
* [more info](http://scribu.net/wordpress/posts-to-posts/p2p-0-2.html)

= 0.1 =
* initial release
* [more info](http://scribu.net/wordpress/posts-to-posts/p2p-0-1.html)

