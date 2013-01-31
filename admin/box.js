(function() {
  var Candidates, CandidatesView, Connections, ConnectionsView, CreatePostView, MetaboxView, get_mustache_template, remove_row, row_wait;

  row_wait = function($td) {
    return $td.find('.p2p-icon').css('background-image', 'url(' + P2PAdmin.spinner + ')');
  };

  remove_row = function($td) {
    var $table;
    $table = $td.closest('table');
    $td.closest('tr').remove();
    if (!$table.find('tbody tr').length) {
      return $table.hide();
    }
  };

  get_mustache_template = function(name) {
    return jQuery('#p2p-template-' + name).html();
  };

  Candidates = Backbone.Model.extend({
    sync: function(method) {
      var params,
        _this = this;
      params = _.extend({}, model.attributes, {
        subaction: 'search'
      });
      return this.ajax_request(params, function(response) {
        _this.total_pages = response.navigation['total-pages-raw'];
        return _this.trigger('sync', response);
      });
    },
    validate: function(attrs) {
      var _ref;
      if ((0 < (_ref = attrs['paged']) && _ref <= this.total_pages)) {
        return null;
      }
      return 'invalid page';
    }
  });

  Connections = Backbone.Model.extend({
    createItemAndConnect: function(title) {
      var data,
        _this = this;
      data = {
        subaction: 'create_post',
        post_title: title
      };
      return this.ajax_request(data, function(response) {
        return _this.trigger('create:from_new_item', response);
      });
    },
    create: function($td) {
      var data,
        _this = this;
      data = {
        subaction: 'connect',
        to: $td.find('div').data('item-id')
      };
      return this.ajax_request(data, function(response) {
        return _this.trigger('create', response, $td);
      });
    },
    "delete": function($td) {
      var data,
        _this = this;
      data = {
        subaction: 'disconnect',
        p2p_id: $td.find('input').val()
      };
      return this.ajax_request(data, function(response) {
        return _this.trigger('delete', response, $td);
      });
    },
    clear: function() {
      var data,
        _this = this;
      data = {
        subaction: 'clear_connections'
      };
      return this.ajax_request(data, function(response) {
        return _this.trigger('clear', response);
      });
    }
  });

  ConnectionsView = Backbone.View.extend({
    events: {
      'click th.p2p-col-delete .p2p-icon': 'clear',
      'click td.p2p-col-delete .p2p-icon': 'delete'
    },
    initialize: function(options) {
      this.ajax_request = options.ajax_request;
      this.maybe_make_sortable();
      this.collection.on('create', this.afterCreate, this);
      this.collection.on('create:from_new_item', this.afterCreate, this);
      this.collection.on('delete', this.afterDelete, this);
      this.collection.on('clear', this.afterClear, this);
      return options.candidates.on('promote', this.create, this);
    },
    maybe_make_sortable: function() {
      if (this.$('th.p2p-col-order').length) {
        return this.$('tbody').sortable({
          handle: 'td.p2p-col-order',
          helper: function(e, ui) {
            ui.children().each(function() {
              var $this;
              $this = jQuery(this);
              return $this.width($this.width());
            });
            return ui;
          }
        });
      }
    },
    clear: function(ev) {
      var $td;
      ev.preventDefault();
      if (!confirm(P2PAdmin.deleteConfirmMessage)) {
        return;
      }
      $td = jQuery(ev.target).closest('td');
      row_wait($td);
      return this.collection.clear();
    },
    afterClear: function() {
      return this.$el.hide().find('tbody').html('');
    },
    "delete": function(ev) {
      var $td;
      ev.preventDefault();
      $td = jQuery(ev.target).closest('td');
      row_wait($td);
      this.collection["delete"]($td);
      return null;
    },
    afterDelete: function(response, $td) {
      return remove_row($td);
    },
    create: function($td) {
      this.collection.create($td);
      return null;
    },
    afterCreate: function(response) {
      this.$el.show().find('tbody').append(response.row);
      return this.collection.trigger('append', response);
    }
  });

  CandidatesView = Backbone.View.extend({
    template: Mustache.compile(get_mustache_template('tab-list')),
    events: {
      'keypress :text': 'keypress',
      'keyup :text': 'keyup',
      'click .p2p-prev, .p2p-next': 'change_page',
      'click td.p2p-col-create div': 'promote'
    },
    initialize: function(options) {
      this.spinner = options.spinner;
      options.connections.on('create', this.on_connection_create, this);
      options.connections.on('append', this.on_connection_append, this);
      options.connections.on('delete', this.refresh_candidates, this);
      options.connections.on('clear', this.refresh_candidates, this);
      this.collection.on('sync', this.refresh_candidates, this);
      this.collection.on('error', this.handle_invalid, this);
      return this.collection.on('invalid', this.handle_invalid, this);
    },
    on_connection_create: function(response, $td) {
      if (this.options.duplicate_connections) {
        return $td.find('.p2p-icon').css('background-image', '');
      } else {
        return remove_row($td);
      }
    },
    on_connection_append: function(response) {
      if ('one' === this.options.cardinality) {
        return this.$('.p2p-create-connections').hide();
      }
    },
    promote: function(ev) {
      var $td;
      $td = jQuery(ev.target).closest('td');
      row_wait($td);
      this.collection.trigger('promote', $td);
      return false;
    },
    keypress: function(ev) {
      if (ev.keyCode === 13) {
        ev.preventDefault();
      }
      return null;
    },
    keyup: function(ev) {
      var $searchInput, delayed,
        _this = this;
      if (delayed !== void 0) {
        clearTimeout(delayed);
      }
      $searchInput = jQuery(ev.target);
      delayed = setTimeout(function() {
        var searchStr;
        searchStr = $searchInput.val();
        if (searchStr === _this.collection.get('s')) {
          return;
        }
        _this.spinner.insertAfter(_this.searchInput).show();
        return _this.collection.save({
          's': searchStr,
          'paged': 1
        });
      }, 400);
      return null;
    },
    change_page: function(ev) {
      var $navButton, new_page;
      $navButton = jQuery(ev.currentTarget);
      new_page = this.collection.get('paged');
      if ($navButton.hasClass('p2p-prev')) {
        new_page--;
      } else {
        new_page++;
      }
      this.spinner.appendTo(this.$('.p2p-navigation'));
      return this.collection.save('paged', new_page);
    },
    refresh_candidates: function(response) {
      this.$('.p2p-create-connections').show();
      this.spinner.remove();
      this.$('button, .p2p-results, .p2p-navigation, .p2p-notice').remove();
      return this.$el.append(this.template(response));
    },
    handle_invalid: function() {
      return this.spinner.remove();
    }
  });

  CreatePostView = Backbone.View.extend({
    events: {
      'click button': 'on_button_click',
      'keypress :text': 'on_input_keypress'
    },
    initialize: function(options) {
      this.ajax_request = options.ajax_request;
      this.createButton = this.$('button');
      this.createInput = this.$(':text');
      return this.collection.on('create:from_new_item', this.afterItemCreated, this);
    },
    on_button_click: function(ev) {
      var title;
      ev.preventDefault();
      if (this.createButton.hasClass('inactive')) {
        return false;
      }
      title = this.createInput.val();
      if (title === '') {
        this.createInput.focus();
        return;
      }
      this.createButton.addClass('inactive');
      this.collection.createItemAndConnect(title);
      return null;
    },
    afterItemCreated: function() {
      this.createInput.val('');
      return this.createButton.removeClass('inactive');
    },
    on_input_keypress: function(ev) {
      if (13 === ev.keyCode) {
        this.createButton.click();
        ev.preventDefault();
      }
      return null;
    }
  });

  MetaboxView = Backbone.View.extend({
    events: {
      'click .p2p-toggle-tabs': 'toggle_tabs',
      'click .wp-tab-bar li': 'switch_to_tab'
    },
    initialize: function(options) {
      return this.spinner = jQuery('<img>', {
        'src': P2PAdmin.spinner,
        'class': 'p2p-spinner'
      });
    },
    toggle_tabs: function(ev) {
      ev.preventDefault();
      this.$('.p2p-create-connections-tabs').toggle();
      return null;
    },
    switch_to_tab: function(ev) {
      var $tab;
      ev.preventDefault();
      $tab = jQuery(ev.currentTarget);
      this.$('.wp-tab-bar li').removeClass('wp-tab-active');
      $tab.addClass('wp-tab-active');
      return this.$el.find('.tabs-panel').hide().end().find($tab.data('ref')).show().find(':text').focus();
    }
  });

  jQuery(function() {
    var clearVal, setVal;
    if (!jQuery('<input placeholder="1" />')[0].placeholder) {
      setVal = function() {
        var $this;
        $this = jQuery(this);
        if (!$this.val()) {
          $this.val($this.attr('placeholder'));
          $this.addClass('p2p-placeholder');
        }
        return void 0;
      };
      clearVal = function() {
        var $this;
        $this = jQuery(this);
        if ($this.hasClass('p2p-placeholder')) {
          $this.val('');
          $this.removeClass('p2p-placeholder');
        }
        return void 0;
      };
      jQuery('.p2p-search input[placeholder]').each(setVal).focus(clearVal).blur(setVal);
    }
    Mustache.compilePartial('table-row', get_mustache_template('table-row'));
    return jQuery('.p2p-box').each(function() {
      var ajax_request, candidates, candidatesView, connections, connectionsView, createPostView, ctype, metabox;
      metabox = new MetaboxView({
        el: jQuery(this)
      });
      candidates = new Candidates({
        's': '',
        'paged': 1
      });
      candidates.total_pages = metabox.$('.p2p-total').data('num') || 1;
      ctype = {
        p2p_type: metabox.$el.data('p2p_type'),
        direction: metabox.$el.data('direction'),
        from: jQuery('#post_ID').val()
      };
      ajax_request = function(options, callback) {
        var params;
        params = _.extend({}, options, candidates.attributes, ctype, {
          action: 'p2p_box',
          nonce: P2PAdmin.nonce
        });
        return jQuery.post(ajaxurl, params, function(response) {
          try {
            response = jQuery.parseJSON(response);
          } catch (e) {
            if (typeof console !== "undefined" && console !== null) {
              console.error('Malformed response', response);
            }
            return;
          }
          if (response.error) {
            return alert(response.error);
          } else {
            return callback(response);
          }
        });
      };
      candidates.ajax_request = ajax_request;
      connections = new Connections;
      connections.ajax_request = ajax_request;
      connectionsView = new ConnectionsView({
        el: metabox.$('.p2p-connections'),
        collection: connections,
        candidates: candidates
      });
      candidatesView = new CandidatesView({
        el: metabox.$('.p2p-tab-search'),
        collection: candidates,
        connections: connections,
        spinner: metabox.spinner,
        cardinality: metabox.$el.data('cardinality'),
        duplicate_connections: metabox.$el.data('duplicate_connections')
      });
      return createPostView = new CreatePostView({
        el: metabox.$('.p2p-tab-create-post'),
        collection: connections
      });
    });
  });

}).call(this);
