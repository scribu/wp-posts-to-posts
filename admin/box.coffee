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


	remove_row = ($td) ->
		$table = $td.closest('table')
		$td.closest('tr').remove()

		if not $table.find('tbody tr').length
			$table.hide()

	events = _.clone(Backbone.Events)


	ConnectionsView = Backbone.View.extend {

		events: {
			'click th.p2p-col-delete .p2p-icon': 'clear'
			'click td.p2p-col-delete .p2p-icon': 'delete'
		}

		initialize: (options) ->
			@ajax_request = options.ajax_request

			@maybe_make_sortable()

			events.on('candidate:promote', @create, this)

		maybe_make_sortable: ->
			if @$('th.p2p-col-order').length
				@$('tbody').sortable {
					handle: 'td.p2p-col-order'
					helper: (e, ui) ->
						ui.children().each ->
							$this = jQuery(this)
							$this.width($this.width())
						return ui
				}

		# appends a row to the connections table
		append: (response) ->
			@$el.show()
				.find('tbody').append(response.row)

			events.trigger('connection:append', response)

		row_ajax_request: ($td, data, callback) ->
			$td.find('.p2p-icon').css 'background-image', 'url(' + P2PAdmin.spinner + ')'

			@ajax_request data, callback

		clear: (ev) ->
			ev.preventDefault()

			if not confirm(P2PAdmin.deleteConfirmMessage)
				return

			$td = jQuery(ev.target).closest('td')

			data = {
				subaction: 'clear_connections'
			}

			@row_ajax_request $td, data, (response) =>
				@$el.hide().find('tbody').html('')

				events.trigger('connection:clear', response)

			null

		delete: (ev) ->
			ev.preventDefault()

			$td = jQuery(ev.target).closest('td')

			data = {
				subaction: 'disconnect'
				p2p_id: $td.find('input').val()
			}

			@row_ajax_request $td, data, (response) =>
				remove_row $td

				events.trigger('connection:delete', response)

			null

		create: ($td) ->
			data = {
				subaction: 'connect'
				to: $td.find('div').data('item-id')
			}

			@row_ajax_request $td, data, (response) =>
				@append(response)

				events.trigger('connection:create', $td)

			null

	}


	MetaboxView = Backbone.View.extend {

		events: {
			'click .p2p-toggle-tabs': 'toggle_tabs'
			'click .wp-tab-bar li': 'switch_to_tab'
		}

		initialize: (options) ->
			@spinner = jQuery('<img>', 'src': P2PAdmin.spinner, 'class': 'p2p-spinner')

		toggle_tabs: (ev) ->
			ev.preventDefault()

			@.$('.p2p-create-connections-tabs').toggle()

			null

		switch_to_tab: (ev) ->
			ev.preventDefault()

			$tab = jQuery(ev.currentTarget)

			# Set active tab
			@.$('.wp-tab-bar li').removeClass('wp-tab-active')
			$tab.addClass('wp-tab-active')

			# Set active panel
			@.$el
				.find('.tabs-panel')
					.hide()
				.end()
				.find( $tab.data('ref') )
					.show()
					.find(':text').focus()
	}


	CandidatesView = Backbone.View.extend {

		events: {
			'keypress :text': 'keypress'
			'keyup :text': 'keyup'
			'click .p2p-prev, .p2p-next': 'change_page'
			'click td.p2p-col-create div': 'promote'
		}

		initialize: (options) ->
			@spinner = options.spinner
			@ajax_request = options.ajax_request

			@params = {
				subaction: 'search'
				s: ''
			}

			@init_pagination_data()

			events.on('connection:create', @on_connection_create, this)
			events.on('connection:append', @on_connection_append, this)
			events.on('connection:delete', @refresh_candidates, this)
			events.on('connection:clear', @refresh_candidates, this)

		on_connection_create: ($td) ->
			if @options.duplicate_connections
				$td.find('.p2p-icon').css('background-image', '')
			else
				remove_row $td

		on_connection_append: (response) ->
			if 'one' == @options.cardinality
				@$('.p2p-create-connections').hide()

		promote: (ev) ->
			console.log ev

			events.trigger('candidate:promote', jQuery(ev.target).closest('td'))

			false

		init_pagination_data: ->
			@params.paged = @$('.p2p-current').data('num') || 1
			@total_pages = @$('.p2p-total').data('num') || 1

		keypress: (ev) ->
			if ev.keyCode is 13 # RETURN
				ev.preventDefault()

			null

		keyup: (ev) ->
			if delayed isnt undefined
				clearTimeout(delayed)

			$searchInput = jQuery(ev.target)

			delayed = setTimeout =>
				searchStr = $searchInput.val()

				if searchStr is @params.s
					return

				@spinner.insertAfter(@searchInput).show()

				@params.s = searchStr

				@find_posts(1)
			, 400

			null

		change_page: (button) ->
			$navButton = jQuery(button)
			new_page = @params.paged

			if $navButton.hasClass('inactive')
				return

			if $navButton.hasClass('p2p-prev')
				new_page--
			else
				new_page++

			@spinner.appendTo @$('.p2p-navigation')

			@find_posts(new_page)

		find_posts: (new_page) ->
			if 0 < new_page <= @total_pages
				@params.paged = new_page

			@ajax_request @params, (response) =>
				@update_rows response
			, 'GET'

		update_rows: (response) ->
			@spinner.remove()

			@$('button, .p2p-results, .p2p-navigation, .p2p-notice').remove()

			@$el.append response.rows

			@init_pagination_data()
	
		refresh_candidates: (response) ->
			@$('.p2p-create-connections').show()

			@update_rows response
	}


	CreatePostView = Backbone.View.extend {

		events: {
			'click button': 'on_button_click'
			'keypress :text': 'on_input_keypress'
		}

		initialize: (options) ->
			@ajax_request = options.ajax_request
			@connections = options.connections

			@createButton = @$('button')
			@createInput = @$(':text')

		on_button_click: (ev) ->
			ev.preventDefault()

			if @createButton.hasClass('inactive')
				return

			title = @createInput.val()

			if title is ''
				@createInput.focus()
				return

			@createButton.addClass('inactive')

			data =
				subaction: 'create_post'
				post_title: title

			@ajax_request data, (response) =>
				@connections.append(response)

				@createInput.val('')

				@createButton.removeClass('inactive')

			null

		on_input_keypress: (ev) ->
			if 13 is ev.keyCode
				@createButton.click()

				ev.preventDefault()

			null
	}


	jQuery('.p2p-box').each ->
		metabox = new MetaboxView {
			el: jQuery(this)
		}

		# TODO: fix circular dependency between candidatesView and ajax_request
		ajax_request = (data, callback, type = 'POST') ->
			jQuery.extend data,
				action: 'p2p_box'
				nonce: P2PAdmin.nonce
				p2p_type: metabox.$el.data('p2p_type')
				direction: metabox.$el.data('direction')
				from: jQuery('#post_ID').val()
				s: candidatesView.params.s
				paged: candidatesView.params.paged

			handler = (response) ->
				try
					response = jQuery.parseJSON response
				catch e
					console?.error 'Malformed response', response
					return

				if response.error
					alert response.error
				else
					callback response

			jQuery.ajax {
				type: type
				url: ajaxurl
				data: data
				success: handler
			}

		connections = new ConnectionsView {
			el: metabox.$('.p2p-connections')
			ajax_request: ajax_request
		}

		candidatesView = new CandidatesView {
			el: metabox.$('.p2p-tab-search')
			spinner: metabox.spinner
			ajax_request: ajax_request
			cardinality: metabox.$el.data('cardinality')
			duplicate_connections: metabox.$el.data('duplicate_connections')
		}

		createPostView = new CreatePostView {
			el: metabox.$('.p2p-tab-create-post')
			metabox: metabox
			ajax_request: ajax_request
			connections: connections
		}
