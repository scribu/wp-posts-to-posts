jQuery(document).ready(function($) {
// Save the wp-spinner
$spinner = $('#publishing-action .ajax-loading')
	.clone()
	.removeAttr('id')
	.removeClass('ajax-loading')
	.addClass('waiting');
		
$('.p2p-add-new').each(function() {
	var $metabox = $(this).closest('.inside'),
		$connections = $metabox.find('.p2p-connections'),
		$addNew = $metabox.find('.p2p-add-new'),
		base_data = {
			box_id: $addNew.attr('data-box_id'),
			direction: $addNew.attr('data-direction')
		},
		$deleteConfirm = $metabox.find('.p2p-delete-confirm'),
		deleteConfirmMessage = $deleteConfirm.html();
	// Init actions	
	$deleteConfirm.remove();
	$metabox.closest('.postbox')
		.addClass('p2p');
	
	// Delete all connections
	$metabox.delegate('th.p2p-col-delete a', 'click', function() {
		var confirmation = confirm(deleteConfirmMessage);
			if (confirmation) {
			var $self = $(this),
				data = $.extend( base_data, {
					action: 'p2p_connections',
					subaction: 'clear_connections',
					post_id: $('#post_ID').val(),
				} );
			
			$spinner.prependTo($metabox.find('.p2p-footer'));
			
			$.post(ajaxurl, data, function(response) {
				$connections
					.hide()
					.find('tbody').html('');
				$spinner.remove();
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

		$spinner.prependTo($metabox.find('.p2p-footer'));

		$.post(ajaxurl, data, function(response) {
			$row.remove();

			if ( !$connections.find('tbody tr').length )
				$connections.hide();

			$spinner.remove();
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

		$spinner.prependTo($metabox.find('.p2p-footer'));

		$.post(ajaxurl, data, function(response) {
//			if ( '-1' == response )
//				return;
			$connections
				.show()
				.find('tbody').append(response);

			if ( $addNew.attr('data-prevent_duplicates') ) {
				$row.remove();
			}

			$spinner.remove();
		});

		return false;
	});
	
	// Delegate recent
	$metabox.delegate('a.p2p-recent', 'click', function() {
	var $self = $(this),
		$metabox = $self.parents('.inside'),
		$results = $metabox.find('.p2p-results tbody'),
		$input = $metabox.find('.p2p-search :text');

		$input
			.val('')
			.closest('.p2p-search')
				.find('.p2p-hint').removeClass('hidden');

		$spinner.prependTo($metabox.find('.p2p-footer'));
		
		var data = $.extend( base_data, {
			action: 'p2p_recent',
			post_id: $('#post_ID').val(),
		} );
			
		$.get(ajaxurl, data, function(data) {
			$spinner.remove();

			$results.html(data);
		});
		
		return false;
	});

	// Search posts
	var delayed, old_value = '';

	$metabox.find('.p2p-search :text')
		.focus(function() {
			$(this)
				.closest('.p2p-search')
					.find('.p2p-hint').addClass('hidden');
		})
		.blur(function() {
			if ($(this).val().length < 1)
				$(this)
					.closest('.p2p-search')
						.find('.p2p-hint').removeClass('hidden');
		})
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

			delayed = setTimeout(function() {
				if ( !$self.val().length ) {
					$results.html('');
					return;
				}

				if ( $self.val() === old_value ) {
					return;
				}
				old_value = $self.val();

				$spinner.appendTo($metabox.find('.p2p-search p'));

				var data = $.extend( base_data, {
					action: 'p2p_search',
					q: $self.val(),
					post_id: $('#post_ID').val(),
				} );

				$.get(ajaxurl, data, function(data) {
					$spinner.remove();
				
					$results.html(data);
				});
			}, 400);
		});
});
});
