jQuery(document).ready(function($) {

$('.p2p-add-new').each(function() {
	var $metabox = $(this).parents('.inside'),
		$addNew = $metabox.find('.p2p-add-new'),
		base_data = {
			box_id: $addNew.attr('data-box-id'),
			reversed: + $addNew.attr('data-reversed')
		},
		$spinner = $metabox.find('.waiting');

	$metabox.delegate('.p2p-col-delete a', 'click', function() {
		var $row = $(this).parents('tr'),
			data = $.extend( base_data, {
				action: 'p2p_connections',
				subaction: 'disconnect',
				p2p_id: $row.attr('data-p2p-id')
			} );

		$spinner.show();

		$.post(ajaxurl, data, function(response) {
			$spinner.hide();
			$row.slideUp();
		});

		return false;
	});

	$metabox.delegate('.p2p-results a', 'click', function() {
		var $self = $(this),
			data = $.extend( base_data, {
				action: 'p2p_connections',
				subaction: 'connect',
				from: $('#post_ID').val(),
				to: $self.attr('name')
			} );

		$spinner.show();

		$.post(ajaxurl, data, function(response) {
			$spinner.hide();
//			if ( '-1' == response )
//				return;
			$metabox.find('.p2p-connections tbody').append(response);
			$self.parents('li').remove();
		});

		return false;
	});

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
				$results = $metabox.find('.p2p-results'),
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

				$.getJSON(ajaxurl, data, function(data) {
					$spinner.hide();

					$results.html('');

					$.each(data, function(id, title) {
						$results.append('<li><a href="#" name="' + id + '">' + title + '</a></li>');
					});
				});
			}, 400);
		});
});
});
