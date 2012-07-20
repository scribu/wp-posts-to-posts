(function() {

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
      var $connections, $createButton, $createInput, $metabox, $searchInput, $spinner, PostsTab, ajax_request, append_connection, clear_connections, create_connection, delete_connection, refresh_candidates, remove_row, row_ajax_request, searchTab, switch_to_tab, toggle_tabs;
      $metabox = jQuery(this);
      $connections = $metabox.find('.p2p-connections');
      $spinner = jQuery('<img>', {
        'src': P2PAdmin.spinner,
        'class': 'p2p-spinner'
      });
      ajax_request = function(data, callback, type) {
        var handler;
        if (type == null) {
          type = 'POST';
        }
        jQuery.extend(data, {
          action: 'p2p_box',
          nonce: P2PAdmin.nonce,
          p2p_type: $metabox.data('p2p_type'),
          direction: $metabox.data('direction'),
          from: jQuery('#post_ID').val(),
          s: searchTab.params.s,
          paged: searchTab.params.paged
        });
        handler = function(response) {
          try {
            response = jQuery.parseJSON(response);
            if (response.error) {
              return alert(response.error);
            } else {
              return callback(response);
            }
          } catch (e) {
            return typeof console !== "undefined" && console !== null ? console.error('Malformed response', response) : void 0;
          }
        };
        return jQuery.ajax({
          type: type,
          url: ajaxurl,
          data: data,
          success: handler
        });
      };
      PostsTab = (function() {

        function PostsTab(selector) {
          var _this = this;
          this.tab = $metabox.find(selector);
          this.params = {
            subaction: 'search',
            s: ''
          };
          this.init_pagination_data();
          this.tab.delegate('.p2p-prev, .p2p-next', 'click', function(ev) {
            return _this.change_page(ev.target);
          });
        }

        PostsTab.prototype.init_pagination_data = function() {
          this.params.paged = this.tab.find('.p2p-current').data('num') || 1;
          return this.total_pages = this.tab.find('.p2p-total').data('num') || 1;
        };

        PostsTab.prototype.change_page = function(button) {
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
          $spinner.appendTo(this.tab.find('.p2p-navigation'));
          return this.find_posts(new_page);
        };

        PostsTab.prototype.find_posts = function(new_page) {
          var _this = this;
          if ((0 < new_page && new_page <= this.total_pages)) {
            this.params.paged = new_page;
          }
          return ajax_request(this.params, function(response) {
            return _this.update_rows(response);
          }, 'GET');
        };

        PostsTab.prototype.update_rows = function(response) {
          $spinner.remove();
          this.tab.find('button, .p2p-results, .p2p-navigation, .p2p-notice').remove();
          this.tab.append(response.rows);
          return this.init_pagination_data();
        };

        return PostsTab;

      })();
      searchTab = new PostsTab('.p2p-tab-search');
      row_ajax_request = function($td, data, callback) {
        $td.find('.p2p-icon').css('background-image', 'url(' + P2PAdmin.spinner + ')');
        return ajax_request(data, callback);
      };
      remove_row = function($td) {
        var $table;
        $table = $td.closest('table');
        $td.closest('tr').remove();
        if (!$table.find('tbody tr').length) {
          return $table.hide();
        }
      };
      append_connection = function(response) {
        $connections.show().find('tbody').append(response.row);
        if ('one' === $metabox.data('cardinality')) {
          return $metabox.find('.p2p-create-connections').hide();
        }
      };
      refresh_candidates = function(results) {
        $metabox.find('.p2p-create-connections').show();
        return searchTab.update_rows(results);
      };
      clear_connections = function(ev) {
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
        row_ajax_request($td, data, function(response) {
          $connections.hide().find('tbody').html('');
          return refresh_candidates(response);
        });
        return null;
      };
      delete_connection = function(ev) {
        var $td, data,
          _this = this;
        ev.preventDefault();
        $td = jQuery(ev.target).closest('td');
        data = {
          subaction: 'disconnect',
          p2p_id: $td.find('input').val()
        };
        row_ajax_request($td, data, function(response) {
          remove_row($td);
          return refresh_candidates(response);
        });
        return null;
      };
      create_connection = function(ev) {
        var $td, data,
          _this = this;
        ev.preventDefault();
        $td = jQuery(ev.target).closest('td');
        data = {
          subaction: 'connect',
          to: $td.find('div').data('item-id')
        };
        row_ajax_request($td, data, function(response) {
          append_connection(response);
          if ($metabox.data('duplicate_connections')) {
            return $td.find('.p2p-icon').css('background-image', '');
          } else {
            return remove_row($td);
          }
        });
        return null;
      };
      toggle_tabs = function(ev) {
        ev.preventDefault();
        $metabox.find('.p2p-create-connections-tabs').toggle();
        return null;
      };
      switch_to_tab = function(ev) {
        var $tab;
        ev.preventDefault();
        $tab = jQuery(this);
        $metabox.find('.wp-tab-bar li').removeClass('wp-tab-active');
        $tab.addClass('wp-tab-active');
        return $metabox.find('.tabs-panel').hide().end().find($tab.data('ref')).show().find(':text').focus();
      };
      $metabox.delegate('th.p2p-col-delete .p2p-icon', 'click', clear_connections).delegate('td.p2p-col-delete .p2p-icon', 'click', delete_connection).delegate('td.p2p-col-create div', 'click', create_connection).delegate('.p2p-toggle-tabs', 'click', toggle_tabs).delegate('.wp-tab-bar li', 'click', switch_to_tab);
      if ($connections.find('th.p2p-col-order').length) {
        $connections.find('tbody').sortable({
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
      $searchInput = $metabox.find('.p2p-tab-search :text');
      $searchInput.keypress(function(ev) {
        if (ev.keyCode === 13) {
          ev.preventDefault();
        }
        return null;
      }).keyup(function(ev) {
        var delayed;
        if (delayed !== void 0) {
          clearTimeout(delayed);
        }
        delayed = setTimeout(function() {
          var searchStr;
          searchStr = $searchInput.val();
          if (searchStr === searchTab.params.s) {
            return;
          }
          searchTab.params.s = searchStr;
          $spinner.insertAfter($searchInput).show();
          return searchTab.find_posts(1);
        }, 400);
        return null;
      });
      $createButton = $metabox.find('.p2p-tab-create-post button');
      $createInput = $metabox.find('.p2p-tab-create-post :text');
      $createButton.click(function(ev) {
        var $button, data, title;
        ev.preventDefault();
        $button = jQuery(this);
        if ($button.hasClass('inactive')) {
          return;
        }
        title = $createInput.val();
        if (title === '') {
          $createInput.focus();
          return;
        }
        $button.addClass('inactive');
        data = {
          subaction: 'create_post',
          post_title: title
        };
        ajax_request(data, function(response) {
          append_connection(response);
          $createInput.val('');
          return $button.removeClass('inactive');
        });
        return null;
      });
      return $createInput.keypress(function(ev) {
        if (13 === ev.keyCode) {
          $createButton.click();
          ev.preventDefault();
        }
        return null;
      });
    });
  });

}).call(this);
