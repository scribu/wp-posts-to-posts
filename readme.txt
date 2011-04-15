=== Posts 2 Posts ===
Contributors: scribu
Donate link: http://scribu.net/paypal
Tags: cms, custom post types, relationships, many-to-many
Requires at least: 3.1
Tested up to: 3.1
Stable tag: 0.7

Create connections between posts

== Description ==

This plugin allows you to create many-to-many relationships between posts of all types.

You can use it to manually create lists of related posts.

Or, if you have a 'product' post type, you can make a by-directional connection to a 'review' post type.

The possibilities are endless.

Head out to the [Wiki](https://github.com/scribu/wp-posts-to-posts/wiki) for tutorials and other resources.

Links: [Plugin News](http://scribu.net/wordpress/posts-to-posts) | [Author's Site](http://scribu.net)

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

1. Simple connection metabox
2. Advanced connection metabox

== Changelog ==

= 0.8 =
* cache connection information to reduce number of queries

= 0.7 =
* improved UI
* added 'fields', 'context' and 'prevent_duplicates' args to p2p_register_connection_type()
* [more info](http://scribu.net/wordpress/posts-to-posts/p2p-0-7.html)

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

