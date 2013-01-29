(function() {
  var Candidate, CandidateCollection, CandidatesView, Connection, ConnectionCollection, ConnectionsView, CreatePostView, MetaboxView, remove_row;

  remove_row = function($td) {
    var $table;
    $table = $td.closest('table');
    $td.closest('tr').remove();
    if (!$table.find('tbody tr').length) {
      return $table.hide();
    }
  };

  Candidate = Backbone.Model;

  Connection = Backbone.Model;

  CandidateCollection = Backbone.Collection.extend({
    model: Candidate
  });

  ConnectionCollection = Backbone.Collection.extend({
    model: Connection
  });

  ConnectionsView = Backbone.View.extend({
    events: {
      'click th.p2p-col-delete .p2p-icon': 'clear',
      'click td.p2p-col-delete .p2p-icon': 'delete'
    },
    initialize: function(options) {
      this.ajax_request = options.ajax_request;
      this.maybe_make_sortable();
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
    row_ajax_request: function($td, data, callback) {
      $td.find('.p2p-icon').css('background-image', 'url(' + P2PAdmin.spinner + ')');
      return this.ajax_request(data, callback);
    },
    clear: function(ev) {
      var $td, data,
        _this = this;
      ev.preventDefault();
      if (!confirm(P2PAdmin.deleteConfirmMessage)) {
        return;
      }
      $td = jQuery(ev.target).closest('td');
      data = {
        subaction: 'clear_connections'
      };
      this.row_ajax_request($td, data, function(response) {
        _this.$el.hide().find('tbody').html('');
        return _this.collection.trigger('clear', response);
      });
      return null;
    },
    "delete": function(ev) {
      var $td, data,
        _this = this;
      ev.preventDefault();
      $td = jQuery(ev.target).closest('td');
      data = {
        subaction: 'disconnect',
        p2p_id: $td.find('input').val()
      };
      this.row_ajax_request($td, data, function(response) {
        remove_row($td);
        return _this.collection.trigger('delete', response);
      });
      return null;
    },
    appendConnection: function(response) {
      this.$el.show().find('tbody').append(response.row);
      return this.collection.trigger('append', response);
    },
    create: function($td) {
      var data,
        _this = this;
      data = {
        subaction: 'connect',
        to: $td.find('div').data('item-id')
      };
      this.row_ajax_request($td, data, function(response) {
        _this.appendConnection(response);
        return _this.collection.trigger('create', $td);
      });
      return null;
    }
  });

  CandidatesView = Backbone.View.extend({
    events: {
      'keypress :text': 'keypress',
      'keyup :text': 'keyup',
      'click .p2p-prev, .p2p-next': 'change_page',
      'click td.p2p-col-create div': 'promote'
    },
    initialize: function(options) {
      this.spinner = options.spinner;
      this.ajax_request = options.ajax_request;
      this.params = {
        subaction: 'search',
        s: ''
      };
      this.init_pagination_data();
      options.connections.on('create', this.on_connection_create, this);
      options.connections.on('append', this.on_connection_append, this);
      options.connections.on('delete', this.refresh_candidates, this);
      return options.connections.on('clear', this.refresh_candidates, this);
    },
    on_connection_create: function($td) {
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
      this.collection.trigger('promote', jQuery(ev.target).closest('td'));
      return false;
    },
    init_pagination_data: function() {
      this.params.paged = this.$('.p2p-current').data('num') || 1;
      return this.total_pages = this.$('.p2p-total').data('num') || 1;
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
        if (searchStr === _this.params.s) {
          return;
        }
        _this.spinner.insertAfter(_this.searchInput).show();
        _this.params.s = searchStr;
        return _this.find_posts(1);
      }, 400);
      return null;
    },
    change_page: function(button) {
      var $navButton, new_page;
      $navButton = jQuery(button);
      new_page = this.params.paged;
      if ($navButton.hasClass('inactive')) {
        return;
      }
      if ($navButton.hasClass('p2p-prev')) {
        new_page--;
      } else {
        new_page++;
      }
      this.spinner.appendTo(this.$('.p2p-navigation'));
      return this.find_posts(new_page);
    },
    find_posts: function(new_page) {
      var _this = this;
      if ((0 < new_page && new_page <= this.total_pages)) {
        this.params.paged = new_page;
      }
      return this.ajax_request(this.params, function(response) {
        return _this.update_rows(response);
      }, 'GET');
    },
    update_rows: function(response) {
      this.spinner.remove();
      this.$('button, .p2p-results, .p2p-navigation, .p2p-notice').remove();
      this.$el.append(response.rows);
      return this.init_pagination_data();
    },
    refresh_candidates: function(response) {
      this.$('.p2p-create-connections').show();
      return this.update_rows(response);
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
      return this.createInput = this.$(':text');
    },
    on_button_click: function(ev) {
      var data, title,
        _this = this;
      ev.preventDefault();
      if (this.createButton.hasClass('inactive')) {
        return;
      }
      title = this.createInput.val();
      if (title === '') {
        this.createInput.focus();
        return;
      }
      this.createButton.addClass('inactive');
      data = {
        subaction: 'create_post',
        post_title: title
      };
      this.ajax_request(data, function(response) {
        _this.options.connectionsView.appendConnection(response);
        _this.createInput.val('');
        return _this.createButton.removeClass('inactive');
      });
      return null;
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
    return jQuery('.p2p-box').each(function() {
      var ajax_request, candidates, candidatesView, connections, connectionsView, createPostView, metabox;
      metabox = new MetaboxView({
        el: jQuery(this)
      });
      ajax_request = function(data, callback, type) {
        var handler;
        if (type == null) {
          type = 'POST';
        }
        jQuery.extend(data, {
          action: 'p2p_box',
          nonce: P2PAdmin.nonce,
          p2p_type: metabox.$el.data('p2p_type'),
          direction: metabox.$el.data('direction'),
          from: jQuery('#post_ID').val(),
          s: candidatesView.params.s,
          paged: candidatesView.params.paged
        });
        handler = function(response) {
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
        };
        return jQuery.ajax({
          type: type,
          url: ajaxurl,
          data: data,
          success: handler
        });
      };
      candidates = new CandidateCollection;
      connections = new ConnectionCollection;
      connectionsView = new ConnectionsView({
        el: metabox.$('.p2p-connections'),
        collection: connections,
        candidates: candidates,
        ajax_request: ajax_request
      });
      candidatesView = new CandidatesView({
        el: metabox.$('.p2p-tab-search'),
        collection: candidates,
        connections: connections,
        spinner: metabox.spinner,
        cardinality: metabox.$el.data('cardinality'),
        duplicate_connections: metabox.$el.data('duplicate_connections'),
        ajax_request: ajax_request
      });
      return createPostView = new CreatePostView({
        el: metabox.$('.p2p-tab-create-post'),
        ajax_request: ajax_request,
        connectionsView: connectionsView
      });
    });
  });

}).call(this);
