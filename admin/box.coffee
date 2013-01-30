row_wait = ($td) ->
	$td.find('.p2p-icon').css 'background-image', 'url(' + P2PAdmin.spinner + ')'

remove_row = ($td) ->
	$table = $td.closest('table')
	$td.closest('tr').remove()

	if not $table.find('tbody tr').length
		$table.hide()

get_mustache_template = (name) ->
	jQuery('#p2p-template-' + name).html()

# Controller that handles the pagination state
Candidates = Backbone.Model.extend {

	sync: (method, model) ->
		params = _.extend {}, model.attributes, {
			subaction: 'search'
		}

		@ajax_request params, (response) =>
			@total_pages = response.navigation['total-pages-raw']

			model.trigger('sync', response)
	
	validate: (attrs) ->
		if 0 < attrs['paged'] <= @total_pages
			return null

		return 'invalid page'
}

Connections = Backbone.Model.extend {

	createItemAndConnect: (title) ->
		data = {
			subaction: 'create_post'
			post_title: title
		}

		@ajax_request data, (response) => @trigger 'create:from_new_item', response

	create: ($td) ->
		data = {
			subaction: 'connect'
			to: $td.find('div').data('item-id')
		}

		@ajax_request data, (response) => @trigger 'create', response, $td

	delete: ($td) ->
		data = {
			subaction: 'disconnect'
			p2p_id: $td.find('input').val()
		}

		@ajax_request data, (response) => @trigger 'delete', response, $td

	clear: ->
		data = {
			subaction: 'clear_connections'
		}

		@ajax_request data, (response) => @trigger 'clear', response
}


ConnectionsView = Backbone.View.extend {

	events: {
		'click th.p2p-col-delete .p2p-icon': 'clear'
		'click td.p2p-col-delete .p2p-icon': 'delete'
	}

	initialize: (options) ->
		@ajax_request = options.ajax_request

		@maybe_make_sortable()

		@collection.on('create', @afterCreate, this)
		@collection.on('create:from_new_item', @afterCreate, this)
		@collection.on('delete', @afterDelete, this)
		@collection.on('clear', @afterClear, this)

		options.candidates.on('promote', @create, this)

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

	clear: (ev) ->
		ev.preventDefault()

		if not confirm(P2PAdmin.deleteConfirmMessage)
			return

		$td = jQuery(ev.target).closest('td')

		row_wait $td

		@collection.clear()

	afterClear: ->
		@$el.hide().find('tbody').html('')

	delete: (ev) ->
		ev.preventDefault()

		$td = jQuery(ev.target).closest('td')

		row_wait $td

		@collection.delete $td

		null

	afterDelete: (response, $td) ->
		remove_row $td

	create: ($td) ->
		@collection.create $td

		null

	afterCreate: (response) ->
		@$el.show()
			.find('tbody').append(response.row)

		@collection.trigger('append', response)
}

CandidatesView = Backbone.View.extend {

	template: Mustache.compile get_mustache_template('tab-list')

	events: {
		'keypress :text': 'keypress'
		'keyup :text': 'keyup'
		'click .p2p-prev, .p2p-next': 'change_page'
		'click td.p2p-col-create div': 'promote'
	}

	initialize: (options) ->
		@spinner = options.spinner

		options.connections.on('create', @on_connection_create, this)
		options.connections.on('append', @on_connection_append, this)
		options.connections.on('delete', @refresh_candidates, this)
		options.connections.on('clear', @refresh_candidates, this)

		@collection.on('sync', @refresh_candidates, this)

		@collection.on('error', @handle_invalid, this)    # Backbone 0.9.2
		@collection.on('invalid', @handle_invalid, this)

	on_connection_create: (response, $td) ->
		if @options.duplicate_connections
			$td.find('.p2p-icon').css('background-image', '')
		else
			remove_row $td

	on_connection_append: (response) ->
		if 'one' == @options.cardinality
			@$('.p2p-create-connections').hide()

	promote: (ev) ->
		$td = jQuery(ev.target).closest('td')

		row_wait $td

		@collection.trigger 'promote', $td

		false

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

			if searchStr is @collection.get 's'
				return

			@spinner.insertAfter(@searchInput).show()

			@collection.save {
				's': searchStr,
				'paged': 1
			}
		, 400

		null

	change_page: (ev) ->
		$navButton = jQuery(ev.currentTarget)
		new_page = @collection.get 'paged'

		if $navButton.hasClass('p2p-prev')
			new_page--
		else
			new_page++

		@spinner.appendTo @$('.p2p-navigation')

		@collection.save('paged', new_page)

	refresh_candidates: (response) ->
		@$('.p2p-create-connections').show()

		@spinner.remove()

		@$('button, .p2p-results, .p2p-navigation, .p2p-notice').remove()

		@$el.append @template(response)
	
	handle_invalid: ->
		@spinner.remove()
}


CreatePostView = Backbone.View.extend {

	events: {
		'click button': 'on_button_click'
		'keypress :text': 'on_input_keypress'
	}

	initialize: (options) ->
		@ajax_request = options.ajax_request

		@createButton = @$('button')
		@createInput = @$(':text')

		@collection.on('create:from_new_item', @afterItemCreated, this)

	on_button_click: (ev) ->
		ev.preventDefault()

		if @createButton.hasClass('inactive')
			return false

		title = @createInput.val()

		if title is ''
			@createInput.focus()
			return

		@createButton.addClass('inactive')

		@collection.createItemAndConnect title

		null

	afterItemCreated: ->
		@createInput.val('')

		@createButton.removeClass('inactive')

	on_input_keypress: (ev) ->
		if 13 is ev.keyCode
			@createButton.click()

			ev.preventDefault()

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

	Mustache.compilePartial 'table-row', get_mustache_template('table-row')

	jQuery('.p2p-box').each ->
		metabox = new MetaboxView {
			el: jQuery(this)
		}

		candidates = new Candidates {
			's': '',
			'paged': 1
		}
		candidates.total_pages = metabox.$('.p2p-total').data('num') || 1 # TODO
		candidates.ajax_request = ajax_request

		ctype = {
			p2p_type: metabox.$el.data('p2p_type')
			direction: metabox.$el.data('direction')
			from: jQuery('#post_ID').val()
		}

		# TODO: fix circular dependency between candidates and ajax_request
		ajax_request = (options, callback) ->
			params = _.extend {}, options, candidates.attributes, ctype, {
				action: 'p2p_box'
				nonce: P2PAdmin.nonce
			}

			jQuery.post ajaxurl, params, (response) ->
				try
					response = jQuery.parseJSON response
				catch e
					console?.error 'Malformed response', response
					return

				if response.error
					alert response.error
				else
					callback response

		connections = new Connections
		connections.ajax_request = ajax_request

		connectionsView = new ConnectionsView {
			el: metabox.$('.p2p-connections')
			collection: connections
			candidates
		}

		candidatesView = new CandidatesView {
			el: metabox.$('.p2p-tab-search')
			collection: candidates
			connections
			spinner: metabox.spinner
			cardinality: metabox.$el.data('cardinality')
			duplicate_connections: metabox.$el.data('duplicate_connections')
		}

		createPostView = new CreatePostView {
			el: metabox.$('.p2p-tab-create-post')
			collection: connections
		}
