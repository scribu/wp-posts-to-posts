=== Posts 2 Posts ===
Contributors: scribu
Donate link: http://scribu.net/paypal
Tags: cms, custom post types, relationships, many-to-many
Requires at least: 3.0
Tested up to: 3.0
Stable tag: 0.1

Create connections between posts

== Description ==

This plugin allows you to create relationships between posts of different types. The relationships are stored in the postmeta table.

To register a connection type, write:

`
function my_connection_types() {
    p2p_register_connection_type('book', 'author');
}
add_action('init', 'my_connection_types', 100);
`
<br>

See [available functions](http://plugins.trac.wordpress.org/browser/posts-to-posts/trunk/api.php).


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

= 0.2 =
* added p2p_list_connected() template tag
* UI that supports multiple related posts. props [Patrik Bón](http://www.mrhead.sk/)
* [more info](http://scribu.net/wordpress/posts-to-posts/p2p-0-2.html)

= 0.1 =
* initial release
* [more info](http://scribu.net/wordpress/posts-to-posts/p2p-0-1.html)

