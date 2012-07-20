jQuery ->

	# Placeholder support for IE
	if not jQuery('<input placeholder="1" />')[0].placeholder
		setVal = ->
			$this = jQuery(this)
			if not $this.val()
				$this.val($this.attr('placeholder'))
				$this.addClass('p2p-placeholder')

			undefined

		clearVal = ->
			$this = jQuery(this)

			if $this.hasClass('p2p-placeholder')
				$this.val('')
				$this.removeClass('p2p-placeholder')

			undefined

		jQuery('.p2p-search input[placeholder]')
			.each(setVal)
			.focus(clearVal)
			.blur(setVal)

	jQuery('.p2p-box').each ->
		$metabox = jQuery(this)
		$connections = $metabox.find('.p2p-connections')

		$spinner = jQuery('<img>', 'src': P2PAdmin.spinner, 'class': 'p2p-spinner')

		ajax_request = (data, callback, type = 'POST') ->
			jQuery.extend data,
				action: 'p2p_box'
				nonce: P2PAdmin.nonce
				p2p_type: $metabox.data('p2p_type')
				direction: $metabox.data('direction')
				from: jQuery('#post_ID').val()
				s: searchTab.params.s
				paged: searchTab.params.paged

			handler = (response) ->
				try
					response = jQuery.parseJSON response

					if response.error
						alert response.error
					else
						callback response
				catch e
					console?.error 'Malformed response', response

			jQuery.ajax {
				type: type
				url: ajaxurl
				data: data
				success: handler
			}


		class PostsTab
			constructor: (selector) ->
				@tab = $metabox.find(selector)

				@params = {
					subaction: 'search'
					s: ''
				}

				@init_pagination_data()

				@tab.delegate '.p2p-prev, .p2p-next', 'click', (ev) =>
					@change_page(ev.target)

			init_pagination_data: ->
				@params.paged = @tab.find('.p2p-current').data('num') || 1
				@total_pages = @tab.find('.p2p-total').data('num') || 1

			change_page: (button) ->
				$navButton = jQuery(button)
				new_page = @params.paged

				if $navButton.hasClass('inactive')
					return

				if $navButton.hasClass('p2p-prev')
					new_page--
				else
					new_page++

				$spinner.appendTo @tab.find('.p2p-navigation')

				@find_posts(new_page)

			find_posts: (new_page) ->
				if 0 < new_page <= @total_pages
					@params.paged = new_page

				ajax_request @params, (response) =>
					@update_rows response
				, 'GET'

			update_rows: (response) ->
				$spinner.remove()

				@tab.find('button, .p2p-results, .p2p-navigation, .p2p-notice').remove()

				@tab.append response.rows

				@init_pagination_data()

		searchTab = new PostsTab('.p2p-tab-search')


		row_ajax_request = ($td, data, callback) ->
			$td.find('.p2p-icon').css 'background-image', 'url(' + P2PAdmin.spinner + ')'

			ajax_request data, callback

		remove_row = ($td) ->
			$table = $td.closest('table')
			$td.closest('tr').remove()

			if not $table.find('tbody tr').length
				$table.hide()

		append_connection = (response) ->
			$connections.show()
				.find('tbody').append(response.row)

			if 'one' == $metabox.data('cardinality')
				$metabox.find('.p2p-create-connections').hide()

		refresh_candidates = (results) ->
			$metabox.find('.p2p-create-connections').show()

			searchTab.update_rows(results)


		clear_connections = (ev) ->
			ev.preventDefault()

			if not confirm(P2PAdmin.deleteConfirmMessage)
				return

			$td = jQuery(ev.target).closest('td')

			data = {
				subaction: 'clear_connections'
			}

			row_ajax_request $td, data, (response) =>
				$connections.hide().find('tbody').html('')

				refresh_candidates response

			null

		delete_connection = (ev) ->
			ev.preventDefault()

			$td = jQuery(ev.target).closest('td')

			data = {
				subaction: 'disconnect'
				p2p_id: $td.find('input').val()
			}

			row_ajax_request $td, data, (response) =>
				remove_row $td

				refresh_candidates response

			null

		create_connection = (ev) ->
			ev.preventDefault()

			$td = jQuery(ev.target).closest('td')

			data = {
				subaction: 'connect'
				to: $td.find('div').data('item-id')
			}

			row_ajax_request $td, data, (response) =>
				append_connection(response)

				if $metabox.data('duplicate_connections')
					$td.find('.p2p-icon').css('background-image', '')
				else
					remove_row $td

			null

		toggle_tabs = (ev) ->
			ev.preventDefault()

			$metabox.find('.p2p-create-connections-tabs').toggle()

			null

		switch_to_tab = (ev) ->
			ev.preventDefault()

			$tab = jQuery(this)

			# Set active tab
			$metabox.find('.wp-tab-bar li').removeClass('wp-tab-active')
			$tab.addClass('wp-tab-active')

			# Set active panel
			$metabox
				.find('.tabs-panel')
					.hide()
				.end()
				.find( $tab.data('ref') )
					.show()
					.find(':text').focus()

		$metabox
			.delegate('th.p2p-col-delete .p2p-icon', 'click', clear_connections)
			.delegate('td.p2p-col-delete .p2p-icon', 'click', delete_connection)
			.delegate('td.p2p-col-create div', 'click', create_connection)
			.delegate('.p2p-toggle-tabs', 'click', toggle_tabs)
			.delegate('.wp-tab-bar li', 'click', switch_to_tab)

		# Make sortable
		if $connections.find('th.p2p-col-order').length
			$connections.find('tbody').sortable {
				handle: 'td.p2p-col-order'
				helper: (e, ui) ->
					ui.children().each ->
						$this = jQuery(this)
						$this.width($this.width())
					return ui
			}

		# Search posts
		$searchInput = $metabox.find('.p2p-tab-search :text')

		$searchInput
			.keypress (ev) ->
				if ev.keyCode is 13 # RETURN
					ev.preventDefault()

				null

			.keyup (ev) ->
				if delayed isnt undefined
					clearTimeout(delayed)

				delayed = setTimeout ->
					searchStr = $searchInput.val()

					if searchStr is searchTab.params.s
						return

					searchTab.params.s = searchStr

					$spinner.insertAfter($searchInput).show()

					searchTab.find_posts(1)
				, 400

				null

		# Post creation
		$createButton = $metabox.find('.p2p-tab-create-post button')
		$createInput = $metabox.find('.p2p-tab-create-post :text')

		$createButton.click (ev) ->
			ev.preventDefault()

			$button = jQuery(this)

			if $button.hasClass('inactive')
				return

			title = $createInput.val()

			if title is ''
				$createInput.focus()
				return

			$button.addClass('inactive')

			data =
				subaction: 'create_post'
				post_title: title

			ajax_request data, (response) ->
				append_connection(response)

				$createInput.val('')

				$button.removeClass('inactive')

			null

		$createInput.keypress (ev) ->
			if 13 is ev.keyCode
				$createButton.click()

				ev.preventDefault()

			null
