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

$('.p2p-add-new').each(function() {
	var $metabox = $(this).closest('.inside'),
		$connections = $metabox.find('.p2p-connections'),
		$addNew = $metabox.find('.p2p-add-new'),
		base_data = {
			box_id: $addNew.attr('data-box_id'),
			direction: $addNew.attr('data-direction')
		};

	// Save the wp-spinner
	var $spinner = $('#publishing-action .ajax-loading')
		.clone()
		.removeAttr('id')
		.removeClass('ajax-loading')
		.addClass('waiting');

	function show_spinner() {
		$metabox.find('.p2p-recent').after( $spinner.show() );
	}

	function hide_spinner() {
		$spinner.hide();
	}

	// Init actions
	$metabox.closest('.postbox')
		.addClass('p2p');
	
	// Delete all connections
	$metabox.delegate('th.p2p-col-delete a', 'click', function() {
		var confirmation = confirm(P2PAdmin_I18n.deleteConfirmMessage);
		if (confirmation) {
			var $self = $(this),
				data = $.extend( base_data, {
					action: 'p2p_connections',
					subaction: 'clear_connections',
					post_id: $('#post_ID').val()
				} );
			
			show_spinner();

			$.post(ajaxurl, data, function(response) {
				$connections
					.hide()
					.find('tbody').html('');
				hide_spinner();
			});
		}			
		return false;
		
	});

	// Delete connection
	$metabox.delegate('td.p2p-col-delete a', 'click', function() {
		var $self = $(this),
			$row = $self.parents('tr'),
			data = $.extend( base_data, {
				action: 'p2p_connections',
				subaction: 'disconnect',
				p2p_id: $self.attr('data-p2p_id')
			} );

		show_spinner();

		$.post(ajaxurl, data, function(response) {
			$row.remove();

			if ( !$connections.find('tbody tr').length )
				$connections.hide();

			hide_spinner();
		});

		return false;
	});

	// Create connection
	$metabox.delegate('td.p2p-col-add a', 'click', function() {
		var $self = $(this),
			$row = $self.closest('tr'),
			data = $.extend( base_data, {
				action: 'p2p_connections',
				subaction: 'connect',
				from: $('#post_ID').val(),
				to: $self.attr('data-post_id')
			} );

		show_spinner();

		$.post(ajaxurl, data, function(response) {
//			if ( '-1' == response )
//				return;
			$connections
				.show()
				.find('tbody').append(response);

			if ( $addNew.attr('data-prevent_duplicates') ) {
				$row.remove();
			}

			hide_spinner();
		});

		return false;
	});

	// Pagination
	var	current_page = 1,
		total_pages = 0;

	function update_nav() {
		if ( total_pages <= 1 ) {
			$metabox.find('.p2p-prev, .p2p-next').hide();
		} else {
			if ( 1 === current_page ) {
				$metabox.find('.p2p-prev').hide();
				$metabox.find('.p2p-next').css('margin-left', '29px' );
			} else {
				$metabox.find('.p2p-prev').show();
				$metabox.find('.p2p-next').css('margin-left', '0' );
			}

			if ( total_pages === current_page ) {
				$metabox.find('.p2p-next').hide();
			} else {
				$metabox.find('.p2p-next').show();
			}
		}
	}

	function find_posts(new_page) {
		new_page = new_page || current_page;

		var data = $.extend( base_data, {
			action: 'p2p_search',
			s: $metabox.find('.p2p-search :text').val(),
			paged: new_page,
			post_id: $('#post_ID').val()
		} );

		show_spinner();

		$.getJSON(ajaxurl, data, function(data) {
			current_page = new_page;
			total_pages = data.pages;

			update_nav();

			hide_spinner();

			$metabox.find('.p2p-results tbody').html(data.rows);
		});
	}

	// Delegate recent
	$metabox.delegate('.p2p-recent', 'click', function() {
		$metabox.find('.p2p-search :text')
			.val('')
			.blur();	// so that placeholder is shown again in IE

		find_posts(1);

		return false;
	});

	// Search posts
	var delayed, old_value = '';

	$metabox.find('.p2p-search :text')
		.keypress(function (ev) {
			if ( 13 === ev.keyCode )
				return false;
		})

		.keyup(function (ev) {
			if ( undefined !== delayed ) {
				clearTimeout(delayed);
			}

			var $self = $(this),
				$results = $metabox.find('.p2p-results tbody'),

			delayed = setTimeout(function() {
				if ( $self.val() === old_value ) {
					return;
				}
				old_value = $self.val();

				find_posts(1);
			}, 400);
		});

	// Pagination
	$metabox.delegate('.p2p-prev, .p2p-next', 'click', function() {
		var $self = $(this),
			new_page = current_page;

		if ( $self.hasClass('p2p-prev') )
			new_page--;
		else
			new_page++;

		find_posts(new_page);
	});
});
});
