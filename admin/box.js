jQuery(document).ready(function($) {

// Placeholder support for IE
if (!jQuery('<input placeholder="1" />')[0].placeholder) {
	jQuery('.p2p-search input[placeholder]').each(function(){
		var $this = $(this);
		if (!$this.val()) {
			$this.val($this.attr('placeholder'));
			$this.addClass('p2p-placeholder');
		}
	}).focus(function(e){
		var $this = $(this);
		if ($this.hasClass('p2p-placeholder')) {
			$this.val('');
			$this.removeClass('p2p-placeholder');
		}
	}).blur(function(e){
		var $this = $(this);
		if (!$this.val()) {
			$this.addClass('p2p-placeholder');
			$this.val($this.attr('placeholder'));
		}
	});
}

$('.p2p-box').each(function() {
	var
		$metabox = $(this),
		$connections = $metabox.find('.p2p-connections'),
		$spinner = $('<img>', {'src': P2PAdmin.spinner});

	function get_base_data() {
		return {
			action: 'p2p_box',
			nonce: P2PAdmin.nonce,
			box_id: $metabox.attr('data-box_id'),
			post_type: $('#post_type').val()
		};
	}

	// Make sortable
	if ( $connections.find('th.p2p-col-order').length ) {
		$connections.find('tbody').sortable({
			handle: 'td.p2p-col-order',
			helper: function(e, ui) {
				ui.children().each(function() {
					$(this).width($(this).width());
				});
				return ui;
			}
		});
	}

	// Delete all connections
	$metabox.delegate('th.p2p-col-delete a', 'click', function() {
		if ( !confirm(P2PAdmin.deleteConfirmMessage) )
			return false;

		var
			$self = $(this),
			$td = $self.closest('td'),
			data = $.extend( get_base_data(), {
				subaction: 'clear_connections',
				post_id: $('#post_ID').val()
			} );

		$td.html( $spinner.show() );

		$.post(ajaxurl, data, function(response) {
			$connections.hide()
				.find('tbody').html('');

			$td.html($self);
		});

		return false;
	});

	// Delete connection
	$metabox.delegate('td.p2p-col-delete a', 'click', function() {
		var
			$self = $(this),
			$td = $self.closest('td'),
			data = $.extend( get_base_data(), {
				subaction: 'disconnect',
				p2p_id: $self.attr('data-p2p_id')
			} );

		$td.html( $spinner.show() );

		$.post(ajaxurl, data, function(response) {
			$td.closest('tr').remove();

			if ( !$connections.find('tbody tr').length )
				$connections.hide();
		});

		return false;
	});

	// Create connection
	$metabox.delegate('td.p2p-col-create a', 'click', function() {
		var
			$self = $(this),
			$td = $self.closest('td'),
			data = $.extend( get_base_data(), {
				subaction: 'connect',
				from: $('#post_ID').val(),
				to: $self.attr('data-post_id')
			} );

		$td.html( $spinner.show() );

		$.post(ajaxurl, data, function(response) {
			$connections.show()
				.find('tbody').append(response);

			if ( $metabox.attr('data-prevent_duplicates') ) {
				var $table = $td.closest('table');

				if ( 1 == $table.find('tbody tr').length )
					$table.remove();
				else
					$td.closest('tr').remove();
			} else {
				$td.html( $self );
			}
		});

		return false;
	});

	// Tabs
	$metabox.delegate('.wp-tab-bar li', 'click', function() {
		var $tab = $(this);

		// Set active tab
		$metabox.find('.wp-tab-bar li').removeClass('wp-tab-active');
		$tab.addClass('wp-tab-active');

		// Set active panel
		$metabox.find('.tabs-panel').hide();
		$metabox.find( $tab.attr('data-ref') )
			.show()
			.find(':text').focus();

		return false;
	});

	function PostsTab(selector) {
		this.tab = $metabox.find(selector);

		this.init_pagination_data();

		this.tab.delegate('.p2p-prev, .p2p-next', 'click', $.proxy(this, 'change_page'));

		this.data = $.extend( get_base_data(), {
			subaction: 'search',
			post_id: $('#post_ID').val(),
			s: ''
		} );
	}

	$.extend(PostsTab.prototype, {

		init_pagination_data: function() {
		      this.current_page = this.tab.find('.p2p-current').text() || 1;
		      this.total_pages = this.tab.find('.p2p-total').text() || 1;
		},

		change_page: function(ev) {
			var
				$navButton = $(ev.target),
				new_page = this.current_page;

			if ( $navButton.hasClass('inactive') )
				return false;

			if ( $navButton.hasClass('p2p-prev') )
				new_page--;
			else
				new_page++;

			this.find_posts(new_page);

			return false;
		},

		find_posts: function (new_page) {
			this.data.paged = new_page ? ( new_page > this.total_pages ? this.current_page : new_page ) : this.current_page;

			$spinner.appendTo( this.tab.find('.p2p-navigation') );
			$.getJSON(ajaxurl, this.data, $.proxy(this, 'update_rows'));
		},

		update_rows: function(response) {
			$spinner.remove();

			this.tab.find('.p2p-results, .p2p-navigation, .p2p-notice').remove();

			if ( 'undefined' === typeof response.rows ) {
				this.tab.append( $('<div class="p2p-notice">').html(response.msg) );
			} else {
				this.tab.append(response.rows);

				this.init_pagination_data();
			}
		}
	});

	var searchTab = new PostsTab('.p2p-tab-search');
	var recentTab = new PostsTab('.p2p-tab-recent');

	// Search posts
	var delayed, $searchInput = $metabox.find('.p2p-tab-search :text');

	$searchInput
		.keypress(function (ev) {
			if ( 13 === ev.keyCode )
				return false;
		})
		.keyup(function (ev) {
			if ( undefined !== delayed ) {
				clearTimeout(delayed);
			}

			delayed = setTimeout(function () {
				var searchStr = $searchInput.val();

				if ( '' == searchStr || searchStr === searchTab.data.s ) {
					return;
				}

				searchTab.data.s = searchStr;

				$spinner.insertAfter($searchInput).show();

				searchTab.find_posts(1);
			}, 400);
		});

	// Post creation
	var
		$createButton = $metabox.find('.p2p-tab-create-post .button'),
		$createInput = $metabox.find('.p2p-tab-create-post :text');

	$createButton.click(function () {
		var $button = $(this);

		if ( $button.hasClass('inactive') )
			return false;

		var title = $createInput.val();

		if ( '' === title ) {
			$createInput.focus();
			return false;
		}

		$button.addClass('inactive');

		var data = $.extend( get_base_data(), {
			subaction: 'create_post',
			from: $('#post_ID').val(),
			post_title: title
		});

		$.post(ajaxurl, data, function (response) {
			$connections.show()
				.find('tbody').append(response);

			$createInput.val('');

			$button.removeClass('inactive');
		});

		return false;
	});

	$createInput.keypress(function (ev) {
		if ( 13 === ev.keyCode ) {
			$createButton.click();

			return false;
		}
	});
});
});

