=== Posts 2 Posts ===

Contributors: scribu, ciobi  
Tags: connections, custom post types, relationships, many-to-many, users  
Requires at least: 3.9  
Tested up to: 4.3  
Stable tag: 1.6.5  
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  

Efficient many-to-many connections between posts, pages, custom post types, users.

== Description ==

This plugin allows you to create many-to-many relationships between posts of any type: post, page, custom etc. A few example use cases:

* manually curated lists of related posts
* post series
* products connected to retailers
* etc.

Additionally, you can create many-to-many relationships between posts and users. So, you could also implement:

* favorite posts of users
* multiple authors per post
* etc.

= Support & Maintenance =

I, scribu, will not be offering support (either free or paid) for this plugin anymore.

If you want to help maintain the plugin, fork it [on github](https://github.com/scribu/wp-posts-to-posts) and open pull requests.

Links: [**Documentation**](http://github.com/scribu/wp-posts-to-posts/wiki) | [Plugin News](http://scribu.net/wordpress/posts-to-posts) | [Author's Site](http://scribu.net)

== Installation ==

See [Installing Plugins](http://codex.wordpress.org/Managing_Plugins#Installing_Plugins).

After activating it, refer to the [Basic usage](https://github.com/scribu/wp-posts-to-posts/wiki/Basic-usage) tutorial.
 
Additional info can be found on the [wiki](http://github.com/scribu/wp-posts-to-posts/wiki).

== Frequently Asked Questions ==

= The waiting icon keeps spinning forever. =

[Check for JavaScript errors](http://codex.wordpress.org/Using_Your_Browser_to_Diagnose_JavaScript_Errors). If it's an AJAX request, check its output.

== Screenshots ==

1. Basic connection metabox
2. Advanced connection metabox
3. Admin column
4. Widget
5. Connection Types screen

== Changelog ==

= 1.6.5 =
* fixed error when Mustache is already loaded. props ApatheticG
* fixed WP_User_Query warning. props PatelUtkarsh
* added Chinese translation. props iwillhappy1314

= 1.6.4 =
* added Danish translation. props phh
* updated Swedish translation. props EyesX
* fixed issue with multiple `parse_query` calls. props hezachenary
* added `p2p_post_admin_column_link` and `p2p_user_admin_column_link` filters. props PareshRadadiya

= 1.6.3 =
* added Serbian translation. props Borisa Djuraskovic
* fixed spinner in admin box. props yamablam
* fixed JavaScript error related to Backbone. props ericandrewlewis
* made 'p2p_connected_title' filter work for users too. props MZAWeb
* added support for 'dropdown_title' labels. props GaryJones
* made `get_related()` consider all connected items

= 1.6.2 =
* fixed URL query handling. props ntns
* store `WP_Error` instance instead of calling `trigger_error()`. props MZAWeb
* fixed warning when used with Multilingual Press. props dimadin
* introduced `p2p_connected_title` filter. props petitphp

= 1.6.1 =
* fixed user column handling. props versusbassz
* fixed PHP strict standards warnings. props meloniq
* added Estonian translation. props RistoNiinemets
* added Finnish translation. props danielck

= 1.6 =
* introduced `p2p_candidate_title` filter
* introduced JavaScript API
* added Japanese translation
* various refactorings

= 1.5.2 =
* fixed get_prev() and get_next()
* introduced get_adjacent_items()
* fixed admin column titles
* made admin column titles show up before the post date. props luk3thomas
* added 'help' key to 'from_labels' and 'to_labels' arrays. props tareq1988

= 1.5.1 =
* fix fatal error on activation. props benmay

= 1.5 =
* added [admin dropdowns](https://github.com/scribu/wp-posts-to-posts/wiki/Admin-dropdown-display)
* fixed SQL error related to user connections
* fixed 'labels' handling and added 'column_title' subkey
* refactor metabox JavaScript using Backbone.js
* lazy-load connection candidates, for faster page loads
* lazy-load PHP classes using `spl_register_autoload()`

= 1.4.3 =
* various bug fixes
* added 'inline' mode for shortcodes
* replaced 'trash' icon with 'minus' icon
* pass direction to 'default_cb'

= 1.4.2 =
* fixed each_connected() returning wrapped objects
* fixed issue with user queries and get_current_screen()
* fixed "Delete all connections" button
* fixed bugs with reciprocal and non-reciprocal indeterminate connection types
* added Dutch translation

= 1.4.1 =
* fixed errors in admin box
* fixed each_connected()

= 1.4 =
* added 'p2p_init' hook
* replaced 'View All' button with '+ Create connections' toggle
* improved usability of connection candidate UI
* fixed issues related to auto-drafts
* show columns on the admin user list screen
* [more info](http://scribu.net/wordpress/posts-to-posts/p2p-1-4.html)

= 1.3.1 =
* sanitize connection fields values on save, preventing security exploits
* improved connection field default value handling
* added 'default_cb' as an optional key when defining connection fields
* fixed parameter order for 'p2p_admin_box_show' filter
* pass the current post ID to the 'p2p_new_post_args' filter

= 1.3 =
* allow passing entire objects to get_connected(), connect() etc.
* made get_related() work with posts-to-users connections
* made each_connected() work with simple array of posts
* introduced [p2p_connected] and [p2p_related] shortcodes
* allow 'default' parameter in 'fields' array
* [more info](http://scribu.net/wordpress/posts-to-posts/p2p-1-3.html)

= 1.2 =
* added Tools -> Connection Types admin screen
* fixed migration script
* made p2p_get_connections() accept arrays of ids
* added 'separator' parameter to p2p_list_posts()
* made P2P_Directed_Type->connect() return WP_Error instances instead of just false
* when a user is deleted, delete all the associated connections
* fixed conflict with bbPress Topics for Posts plugin
* [more info](http://scribu.net/wordpress/posts-to-posts/p2p-1-2.html)

= 1.1.6 =
* convert "View All" tab into button
* refresh candidate list after deleting a connection
* fix cardinality check
* introduce 'p2p_connection_type_args' filter
* make 'connected_type' accept an array of connection type names
* inadvertently remove support for queries without 'connected_type' parameter

= 1.1.5 =
* added P2P_Connection_Type->replace() method
* added 'self_connections' flag to p2p_register_connection_type()
* made P2P_Connection_Type->each_connected() work for posts-to-users connections
* made admin list table columns work for posts-to-users connections
* fixed 'from_labels' and 'to_labels' parameters
* fixed search being limited only to post titles

= 1.1.4 =
* show attachment thumbnail instead of title
* merged 'from_object' into 'from' and 'to_object' into 'to'
* made posts-to-users queries respect 'to_query_vars' args
* added $prop_name parameter to P2P_Type::each_connected()
* fixed connection field name conflict

= 1.1.3 =
* fixed regression related to posts-to-users direction
* fixed admin columns overwriting each other
* fixed incorrect direction in admin column links
* added notices when connection type is not properly defined

= 1.1.2 =
* fixed fields not being saved for posts-to-users connections
* fixed missing "New Post" tab in admin box
* fixed notice when deleting post

= 1.1.1 =
* fixed faulty scbFramework loading
* simplified syntax for defining posts-to-users connection types

= 1.1 =
* add p2p_type column to the wp_p2p table
* new low-level api: p2p_create_connection(), p2p_get_connections(), p2p_delete_connections(), p2p_connection_exists()
* support posts-to-users and users-to-posts connection types in the admin
* add 'from_labels' and 'to_labels' args to p2p_register_connection_type()
* [more info](http://scribu.net/wordpress/posts-to-posts/p2p-1-1.html)

= 1.0.1 =
* don't show metabox at all if user doesn't have the required capability
* fix checkbox handling when there are no other input fields
* improve metabox styling
* rename 'show_ui' to 'admin_box'
* add 'admin_column' parameter

= 1.0 =
* widget can now list related posts
* add P2P_Connection_Type::get_related() method
* add 'can_create_post' arg to p2p_register_connection_type()
* two-box mode for `'reciprocal' => false`
* more options for 'show_ui'
* allow checkboxes, radio buttons and textareas as connection fields
* allow drag & drop ordering in both directions
* added get_previous(), get_next() and get_adjacent() methods to P2P_Connection_Type
* [more info](http://scribu.net/wordpress/posts-to-posts/p2p-1-0.html)

= 0.9.5 =
* add '{from|to}_query_vars' args to p2p_register_connection_type()
* add 'cardinality' arg to p2p_register_connection_type()
* add 'id' arg and p2p_type() function
* introduce p2p_split_posts()
* remove p2p_connect(), p2p_disconnect() and p2p_get_connected()
* [more info](http://scribu.net/wordpress/posts-to-posts/p2p-0-9-5.html)

= 0.9.2 =
* fix incorrect storage when creating a connection from the other end
* respect 'reciprocal' => false when 'from' == 'to'
* pass pagination numbers through number_format_i18n()

= 0.9.1 =
* fix bug with each_connected()
* add widget
* allow 'from' and 'to' to be arrays again
* improve RTL support

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
