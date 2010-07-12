jQuery(document).ready(function($) {
	var delayed = undefined;

	var update_input = function($metabox) {
		var ids = [];
		$metabox.find('.p2p_connected input:checked').each(function() {
			ids.push($(this).val());
		});
		$metabox.find('.p2p_connected_ids').val(ids.join(','));
	};

	$(document).delegate('.p2p_connected input', 'change', function() {
		update_input($(this).parents('.p2p_metabox'));
	});

	$('input.p2p_search').keyup(function() {

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

		$results.delegate('a', 'click', function() {
			var $list = $metabox.find('.p2p_connected');

			if ( !$list.find('input[value=' + $(this).attr('name') + ']').length != 0 ) {
				$list.append('<li><input type="checkbox" checked="checked" id="p2p_checkbox_' + $(this).attr('name') + '" value="' + $(this).attr('name') + '" autocomplete="off" /> <label for="p2p_checkbox_' + $(this).attr('name') + '">' + $(this).html() + '</label></li>');
			}

			update_input($metabox);

			return false;
		});
	});
});

