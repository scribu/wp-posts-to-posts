=== Posts 2 Posts ===
Contributors: scribu, ciobi
Tags: cms, custom post types, relationships, many-to-many
Requires at least: 3.2
Tested up to: 3.3
Stable tag: 0.9

Create connections between posts

== Description ==

This plugin allows you to create many-to-many relationships between posts of any type: post, page, custom etc.

A few example use cases:

* 'review' posts connected to 'product' posts
* manually curated lists of related posts

etc.

Links: [**Documentation**](http://github.com/scribu/wp-posts-to-posts/wiki) | [Plugin News](http://scribu.net/wordpress/posts-to-posts) | [Author's Site](http://scribu.net)

== Installation ==

See [Installing Plugins](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins).

After activating it, refer to the [Basic usage](https://github.com/scribu/wp-posts-to-posts/wiki/Basic-usage) tutorial.

Additional info can be found on the [wiki](http://github.com/scribu/wp-posts-to-posts/wiki).

== Frequently Asked Questions ==

= Error on activation: "Parse error: syntax error, unexpected..." =

Make sure your host is running PHP 5. The only foolproof way to do this is to add this line to wp-config.php:

`var_dump(PHP_VERSION);`
<br>

== Screenshots ==

1. Basic connection metabox
2. Advanced connection metabox

== Changelog ==

= 0.9.1 =
* add widget
* allow 'from' to be an array again

= 0.9 =
* introduce dropdown connection fields
* introduce 'sortable' arg to p2p_register_connection_type()
* introduce 'data' arg to p2p_register_connection_type()
* replace 'box' arg with hooks
* replace p2p_each_connected() with P2P_Post_Type->each_connected()
* allow using 'connected_meta' and 'connected_orderby' together
* fix some translations
* [more info](http://scribu.net/wordpress/posts-to-posts/p2p-0-9.html)

= 0.8 =
* added ability to create draft posts from the connection box. props Oren Kolker
* show post status in the connection box. props [Michael Fields](http://wordpress.mfields.org/)
* reduced number of queries by caching connection information
* revamped p2p_each_connected()
* introduced p2p_list_posts()
* introduced 'connected_orderby', 'connected_order' and 'connected_order_num' query vars
* [more info](http://scribu.net/wordpress/posts-to-posts/p2p-0-8.html)

= 0.7 =
* improved UI. props [Alex Ciobica](http://ciobi.ca/)
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

