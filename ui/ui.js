jQuery(document).ready(function($) {

$('.p2p-add-new').each(function() {
	var $metabox = $(this).parents('.inside'),
		$connections = $metabox.find('.p2p-connections'),
		$addNew = $metabox.find('.p2p-add-new'),
		base_data = {
			box_id: $addNew.attr('data-box_id'),
			direction: $addNew.attr('data-direction')
		},
		$spinner = $metabox.find('.waiting');

	// Delete all connections
	$metabox.delegate('th.p2p-col-delete a', 'click', function() {
		var $self = $(this),
			data = $.extend( base_data, {
				action: 'p2p_connections',
				subaction: 'clear_connections',
				post_id: $('#post_ID').val(),
			} );

		$spinner.show();

		$.post(ajaxurl, data, function(response) {
			$connections
				.hide()
				.find('tbody').html('');
			$spinner.hide();
		});

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

		$spinner.show();

		$.post(ajaxurl, data, function(response) {
			$row.remove();

			if ( !$connections.find('tbody tr').length )
				$connections.hide();

			$spinner.hide();
		});

		return false;
	});

	// Create connection
	$metabox.delegate('td.p2p-col-add a', 'click', function() {
		var $self = $(this),
			$row = $self.parents('tr'),
			data = $.extend( base_data, {
				action: 'p2p_connections',
				subaction: 'connect',
				from: $('#post_ID').val(),
				to: $self.attr('data-post_id')
			} );

		$spinner.show();

		$.post(ajaxurl, data, function(response) {
//			if ( '-1' == response )
//				return;
			$connections
				.show()
				.find('tbody').append(response);

			if ( $addNew.attr('data-prevent_duplicates') ) {
				$row.remove();
			}

			$spinner.hide();
		});

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
				$metabox = $self.parents('.inside'),
				$results = $metabox.find('.p2p-results tbody'),
				$spinner = $metabox.find('.waiting');

			delayed = setTimeout(function() {
				if ( !$self.val().length ) {
					$results.html('');
					return;
				}

				if ( $self.val() === old_value ) {
					return;
				}
				old_value = $self.val();

				$spinner.show();

				var data = $.extend( base_data, {
					action: 'p2p_search',
					q: $self.val(),
					post_id: $('#post_ID').val(),
				} );

				$.get(ajaxurl, data, function(data) {
					$spinner.hide();

					$results.html(data);
				});
			}, 400);
		});
});
});
