jQuery(document).ready(function($) {
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
			$list.append( 
				$metabox.find('.connection-template').html()
					.replace( '%post_id%', $self.attr('name') )
					.replace( '%post_title%', $self.html() )
			);
		}

		update_input($metabox);

		return false;
	});

	var delayed = undefined;

	$('.p2p_search :text').keyup(function() {

		if ( delayed != undefined )
			clearTimeout(delayed);

		var $self = $(this);
			$metabox = $self.parents('.p2p_metabox'),
			$results = $metabox.find('.p2p_results'),
			post_type = $self.attr('name').replace('p2p_search_', ''),
			old_value = '',
			$spinner = $metabox.find('.waiting');

		var delayed = setTimeout(function() {
			if ( !$self.val().length ) {
				$results.html('');
				return;
			}

			if ( $self.val() == old_value )
				return;
			old_value = $self.val();

			$spinner.show();
			$.getJSON(ajaxurl, {action: 'p2p_search', q: $self.val(), post_type: post_type}, function(data) {
				$spinner.hide();

				$results.html('');

				$.each(data, function(id, title) {
					$results.append('<li><a href="#" name="' + id + '">' + title + '</a></li>');
				});
			});
		}, 400);
	});
});

