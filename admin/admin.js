jQuery(document).ready(function($) {
// TODO: add spinner

	var update_input = function($metabox) {
		$metabox.find('.p2p_connected .howto').remove();

		var ids = [];
		$metabox.find('.p2p_connected input:checked').each(function() {
			ids.push($(this).val());
		});
		$metabox.find('.p2p_connected_ids').val(ids.join(','));
	};

	$('.p2p_connected').delegate('input', 'change', function() {
		update_input($(this).parents('.p2p_metabox'));
	});

	$('.p2p_results').delegate('a', 'click', function() {
		var $self = $(this);
			$metabox = $self.parents('.p2p_metabox'),
			$list = $metabox.find('.p2p_connected');

		if ( !$list.find('input[value=' + $self.attr('name') + ']').length ) {
			$list
				.append($('<li>')
					.append($('<input>').attr({
						'type': 'checkbox',
						'checked': 'checked',
						'id': 'p2p_checkbox_' + $self.attr('name'),
						'value': $self.attr('name'),
						'autocomplete': 'off'
					}))
					.append($('<label>').attr({
						'for': 'p2p_checkbox_' + $self.attr('name')
					}).html($self.html()))
				);
		}

		update_input($metabox);

		return false;
	});

	var delayed = undefined;

	$('.p2p_search').keyup(function() {

		if ( delayed != undefined )
			clearTimeout(delayed);

		var $self = $(this);
			$metabox = $self.parents('.p2p_metabox'),
			$results = $metabox.find('.p2p_results'),
			post_type = $self.attr('name').split('_')[2],
			old_value = '';

		var delayed = setTimeout(function() {
			if ( !$self.val().length ) {
				$results.html('');
				return;
			}

			if ( $self.val() == old_value )
				return;
			old_value = $self.val();

			$.getJSON(ajaxurl, {action: 'p2p_search', q: $self.val(), post_type: post_type}, function(data) {
				$results.html('');

				$.each(data, function(id, title) {
					$results.append('<li><a href="#" name="' + id + '">' + title + '</a></li>');
				});
			});
		}, 400);
	});
});

