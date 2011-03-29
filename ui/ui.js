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
			$this.removeClass('p2p-placeholder')
		}
	}).blur(function(e){
		var $this = $(this);
		if (!$this.val()) {
			$this.addClass('p2p-placeholder');
			$this.val($this.attr('placeholder'));
		}
	});
}

// Save the wp-spinner
var $spinner = $('#publishing-action .ajax-loading')
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
		};

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
	$metabox.delegate('.p2p-recent', 'click', function() {
		var $self = $(this),
			$results = $metabox.find('.p2p-results tbody');

		$metabox.find('.p2p-search :text')
			.val('')
			.blur();	// so that placeholder is shown again in IE

		$self.after( $spinner );

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
