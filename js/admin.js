jQuery(document).ready(function($) {
	var delayed = undefined;
	$('.p2p_metabox input.p2p_search').keyup(function() {
		if(delayed != undefined) {
			clearTimeout(delayed);
		}
		
		var $this = this;
			delayed = setTimeout(function() {
				var post_type = $($this).attr('name').split('_')[2];
				var this_metabox = $($this).parents('.p2p_metabox');
				var results = this_metabox.find('.results');
				
				if($($this).val().length == 0) {
					results.html('');
					return;
				}		
				$.post(ajaxurl, {action: 'p2p_search', q: $($this).val(), post_type: post_type}, function(data) {
					
					results.html('');
		
					if(data.length == 0) {
						return;
					}
					
					var lines = data.split("\n");
					var line;
					for(var i in lines) {
						line = lines[i].split('|');
						results.append('<li><a href="#" name="' + line[0] + '">' + line[1] + '</a></li>');
					}
					$('a', results).click(function() {
						checkboxes = this_metabox.find('.checkboxes');
						checkbox = checkboxes.find('input[value=' + $(this).attr('name') + ']');
						if(checkbox.length != 0) {
					
						} else {
							checkboxes.append('<input type="checkbox" checked="checked" id="p2p_checkbox_' + $(this).attr('name') + '" value="' + $(this).attr('name') + '" /> <label for="p2p_checkbox_' + $(this).attr('name') + '">' + $(this).html() + '</label><br />');
						}
		
						update_input(this_metabox);
						update_event_handlers();
						return false;
					});
				});
			}, 400);
	});
	
	function update_event_handlers() {
		$('.p2p_metabox .checkboxes input').change(function() {
			update_input($(this).parents('.p2p_metabox'));
		});
	}
	update_event_handlers();
	
	function update_input(metabox) {
		metabox.find('.p2p_connected_ids').val('');
		var ids = new Array();
		metabox.find('.checkboxes input:checked').each(function() {
			ids.push($(this).val());
		});
		metabox.find('.p2p_connected_ids').val(ids.join(','));
	}
});
