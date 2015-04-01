(function() {
  var Candidate, Candidates, CandidatesView, Connection, Connections, ConnectionsView, CreatePostView, ENTER_KEY, MetaboxView, get_mustache_template, remove_row, row_wait;

  ENTER_KEY = 13;

  row_wait = function($td) {
    return $td.find('.p2p-icon').css('background-image', 'url(' + P2PAdminL10n.spinner + ')');
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

  // Class for representing a single connection candidate
  Candidate = Backbone.Model.extend({});

  // Class for representing a single connection
  Connection = Backbone.Model.extend({});

  // Class for holding search parameters; not really a model
  Candidates = Backbone.Model.extend({
    
    // (Re)perform a search with the current parameters
    sync: function() {
      var params, _this = this;
      params = {
        subaction: 'search'
      };
      return this.ajax_request(params, function(response) {
        var _ref = response.navigation;
        _this.total_pages = (_ref ? _ref['total-pages-raw'] : void 0) || 1;
        _this.trigger('sync', response);
      });
    },

    // Validation function, called by Backbone when parameters are changed
    validate: function(attrs) {
      var _ref = attrs.paged;
      if (0 < _ref && _ref <= this.total_pages) {
        return null;
      }
      return 'invalid page';
    }
  });

  // Class for holding a list of connections
  Connections = Backbone.Collection.extend({
    model: Connection,

    // Create both a candidate item and a connection
    createItemAndConnect: function(title) {
      var data, _this = this;
      data = {
        subaction: 'create_post',
        post_title: title
      };
      return this.ajax_request(data, function(response) {
        _this.trigger('create', response);
      });
    },

    // Create a connection from a candidate
    create: function(candidate) {
      var data, _this = this;
      data = {
        subaction: 'connect',
        to: candidate.get('id')
      };
      return this.ajax_request(data, function(response) {
        _this.trigger('create', response);
      });
    },

    // Delete a connection
    "delete": function(connection) {
      var data, _this = this;
      data = {
        subaction: 'disconnect',
        p2p_id: connection.get('id')
      };
      return this.ajax_request(data, function(response) {
        _this.trigger('delete', response, connection);
      });
    },

    // Delete all connections
    clear: function() {
      var data, _this = this;
      data = {
        subaction: 'clear_connections'
      };
      return this.ajax_request(data, function(response) {
        _this.trigger('clear', response);
      });
    }
  });

  // View responsible for the connection list
  ConnectionsView = Backbone.View.extend({

    events: {
      'click th.p2p-col-delete .p2p-icon': 'clear',
      'click td.p2p-col-delete .p2p-icon': 'delete'
    },

    initialize: function(options) {
      this.options = options;
      this.maybe_make_sortable();
      this.collection.on('create', this.afterCreate, this);
      this.collection.on('clear', this.afterClear, this);
    },

    maybe_make_sortable: function() {
      if (this.$('th.p2p-col-order').length) {
        this.$('tbody').sortable({
          handle: 'td.p2p-col-order',
          helper: function(e, ui) {
            ui.children().each(function() {
              var $this;
              $this = jQuery(this);
              $this.width($this.width());
            });
            return ui;
          }
        });
      }
    },

    clear: function(ev) {
      var $td;
      ev.preventDefault();
      if (!confirm(P2PAdminL10n.deleteConfirmMessage)) {
        return;
      }
      $td = jQuery(ev.target).closest('td');
      row_wait($td);
      this.collection.clear();
    },

    afterClear: function() {
      this.$el.hide().find('tbody').html('');
    },

    "delete": function(ev) {
      var $td, req;
      ev.preventDefault();
      $td = jQuery(ev.target).closest('td');
      row_wait($td);
      req = this.collection["delete"](new Connection({
        id: $td.find('input').val()
      }));
      req.done(function() {
        remove_row($td);
      });
    },

    afterCreate: function(response) {
      this.$el.show().find('tbody').append(response.row);
      this.collection.trigger('append', response);
    }
  });

  // View responsible for the candidate list
  CandidatesView = Backbone.View.extend({

    template: Mustache.compile(get_mustache_template('tab-list')),

    events: {
      'keypress :text': 'handleReturn',
      'keyup :text': 'handleSearch',
      'click .p2p-prev, .p2p-next': 'changePage',
      'click td.p2p-col-create div': 'promote'
    },

    initialize: function(options) {
      this.options = options;
      this.spinner = options.spinner;
      options.connections.on('delete', this.afterCandidatesRefreshed, this);
      options.connections.on('clear', this.afterCandidatesRefreshed, this);
      this.collection.on('sync', this.afterCandidatesRefreshed, this);
      this.collection.on('error', this.afterInvalid, this);
      this.collection.on('invalid', this.afterInvalid, this);
    },

    promote: function(ev) {
      var $td, req, _this = this;
      ev.preventDefault();
      $td = jQuery(ev.target).closest('td');
      row_wait($td);
      var candidate = new Candidate({
        id: $td.find('div').data('item-id')
      });
      req = this.options.connections.create(candidate);
      req.done(function() {
        if (_this.options.duplicate_connections) {
          $td.find('.p2p-icon').css('background-image', '');
        } else {
          remove_row($td);
        }
      });
    },

    handleReturn: function(ev) {
      if (ev.keyCode === ENTER_KEY) {
        ev.preventDefault();
      }
    },

    handleSearch: function(ev) {
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
        _this.spinner.insertAfter($searchInput).show();
        _this.collection.save({
          's': searchStr,
          'paged': 1
        });
      }, 400);
    },

    changePage: function(ev) {
      var $navButton, new_page;
      $navButton = jQuery(ev.currentTarget);
      new_page = this.collection.get('paged');
      if ($navButton.hasClass('p2p-prev')) {
        new_page--;
      } else {
        new_page++;
      }
      this.spinner.appendTo(this.$('.p2p-navigation'));
      this.collection.save('paged', new_page);
    },

    afterCandidatesRefreshed: function(response) {
      this.spinner.remove();
      this.$('button, .p2p-results, .p2p-navigation, .p2p-notice').remove();
      if ('string' !== typeof response) {
        response = this.template(response);
      }
      this.$el.append(response);
    },
    afterInvalid: function() {
      this.spinner.remove();
    }
  });

  // View responsible for the post creation UI
  CreatePostView = Backbone.View.extend({

    events: {
      'click button': 'createItem',
      'keypress :text': 'handleReturn'
    },

    initialize: function(options) {
      this.options = options;
      this.createButton = this.$('button');
      this.createInput = this.$(':text');
    },

    handleReturn: function(ev) {
      if (ev.keyCode === ENTER_KEY) {
        this.createButton.click();
        ev.preventDefault();
      }
    },

    createItem: function(ev) {
      var req, title, _this = this;
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
      req = this.collection.createItemAndConnect(title);
      req.done(function() {
        _this.createInput.val('');
        _this.createButton.removeClass('inactive');
      });
    }
  });

  // View responsible for the entire metabox
  MetaboxView = Backbone.View.extend({

    events: {
      'click .p2p-toggle-tabs': 'toggleTabs',
      'click .wp-tab-bar li': 'setActiveTab'
    },

    initialize: function(options) {
      this.options = options;
      this.spinner = options.spinner;
      this.initializedCandidates = false;
      options.connections.on('append', this.afterConnectionAppended, this);
      options.connections.on('clear', this.afterConnectionDeleted, this);
      options.connections.on('delete', this.afterConnectionDeleted, this);
    },

    toggleTabs: function(ev) {
      var $tabs;
      ev.preventDefault();
      $tabs = this.$('.p2p-create-connections-tabs');
      $tabs.toggle();
      if (!this.initializedCandidates && $tabs.is(':visible')) {
        this.options.candidates.sync();
        this.initializedCandidates = true;
      }
    },

    setActiveTab: function(ev) {
      var $tab;
      ev.preventDefault();
      $tab = jQuery(ev.currentTarget);
      this.$('.wp-tab-bar li').removeClass('wp-tab-active');
      $tab.addClass('wp-tab-active');
      this.$el.find('.tabs-panel').hide().end().find($tab.data('ref')).show().find(':text').focus();
    },

    afterConnectionAppended: function(response) {
      if ('one' === this.options.cardinality) {
        this.$('.p2p-create-connections').hide();
      }
    },

    afterConnectionDeleted: function(response) {
      if ('one' === this.options.cardinality) {
        this.$('.p2p-create-connections').show();
      }
    }
  });

  window.P2PAdmin = {
    Candidate: Candidate,
    Connection: Connection,
    boxes: {}
  };

  jQuery(function() {
    // Polyfill for browsers that don't support the placeholder attribute
    if (!jQuery('<input placeholder="1" />')[0].placeholder) {
      function setVal() {
        var $this;
        $this = jQuery(this);
        if (!$this.val()) {
          $this.val($this.attr('placeholder'));
          $this.addClass('p2p-placeholder');
        }
      };

      function clearVal() {
        var $this;
        $this = jQuery(this);
        if ($this.hasClass('p2p-placeholder')) {
          $this.val('');
          $this.removeClass('p2p-placeholder');
        }
      };
      jQuery('.p2p-search input[placeholder]').each(setVal).focus(clearVal).blur(setVal);
    }

    Mustache.compilePartial('table-row', get_mustache_template('table-row'));

    jQuery('.p2p-box').each(function() {
      var $metabox, $spinner, candidates, candidatesView, connections, connectionsView, createPostView, ctype, metaboxView;

      $metabox = jQuery(this);

      $spinner = jQuery('<img>', {
        'src': P2PAdminL10n.spinner,
        'class': 'p2p-spinner'
      });

      candidates = new Candidates({
        's': '',
        'paged': 1
      });
      candidates.total_pages = $metabox.find('.p2p-total').data('num') || 1;

      ctype = {
        p2p_type: $metabox.data('p2p_type'),
        direction: $metabox.data('direction'),
        from: jQuery('#post_ID').val()
      };

      // All ajax requests should be done through this function
      function ajax_request(options, callback) {
        var params = _.extend({}, options, candidates.attributes, ctype, {
          action: 'p2p_box',
          nonce: P2PAdminL10n.nonce
        });

        return jQuery.post(ajaxurl, params, function(response) {
          var e;
          try {
            response = jQuery.parseJSON(response);
          } catch (_error) {
            e = _error;
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
      }

      candidates.ajax_request = ajax_request;

      connections = new Connections();
      connections.ajax_request = ajax_request;

      connectionsView = new ConnectionsView({
        el: $metabox.find('.p2p-connections'),
        collection: connections,
        candidates: candidates
      });

      candidatesView = new CandidatesView({
        el: $metabox.find('.p2p-tab-search'),
        collection: candidates,
        connections: connections,
        spinner: $spinner,
        duplicate_connections: $metabox.data('duplicate_connections')
      });

      createPostView = new CreatePostView({
        el: $metabox.find('.p2p-tab-create-post'),
        collection: connections
      });

      metaboxView = new MetaboxView({
        el: $metabox,
        spinner: $spinner,
        cardinality: $metabox.data('cardinality'),
        candidates: candidates,
        connections: connections
      });

      P2PAdmin.boxes[ctype.p2p_type] = {
        candidates: candidates,
        connections: connections
      };
    });
  });
}());
