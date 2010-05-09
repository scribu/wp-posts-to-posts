=== Posts 2 Posts ===
Contributors: scribu
Donate link: http://scribu.net/paypal
Tags: custom post types, relationships, graph, many-to-many
Requires at least: 3.0
Tested up to: 3.0
Stable tag: trunk

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

Refer to /api.php for available functions.


== Installation ==

You can either install it automatically from the WordPress admin, or do it manually:

1. Unzip the "Posts 2 Posts" archive and put the folder into your plugins folder (/wp-content/plugins/).
1. Activate the plugin from the Plugins menu.

== Frequently Asked Questions ==

= Error on activation: "Parse error: syntax error, unexpected..." =

Make sure your host is running PHP 5. The only foolproof way to do this is to add this line to wp-config.php:

`var_dump(PHP_VERSION);`
<br>

= 0.1 =
* initial release
* [more info](http://scribu.net/wordpress/posts-to-posts/p2p-0-1.html)

