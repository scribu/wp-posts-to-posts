(function(){
  jQuery(function(){
    var setVal, clearVal;
    if (!jQuery('<input placeholder="1" />')[0].placeholder) {
      setVal = function(){
        var $this;
        $this = jQuery(this);
        if (!$this.val()) {
          $this.val($this.attr('placeholder'));
          $this.addClass('p2p-placeholder');
        }
      };
      clearVal = function(){
        var $this;
        $this = jQuery(this);
        if ($this.hasClass('p2p-placeholder')) {
          $this.val('');
          $this.removeClass('p2p-placeholder');
        }
      };
      jQuery('.p2p-search input[placeholder]').each(setVal).focus(clearVal).blur(setVal);
    }
    return jQuery('.p2p-box').each(function(){
      var $metabox, $connections, $spinner, ajax_request, row_ajax_request, clear_connections, delete_connection, create_connection, switch_to_tab, PostsTab, searchTab, listTab, $searchInput, $createButton, $createInput;
      $metabox = jQuery(this);
      $connections = $metabox.find('.p2p-connections');
      $spinner = jQuery('<img>', {
        'src': P2PAdmin.spinner,
        'class': 'p2p-spinner'
      });
      ajax_request = function(data, callback, method){
        method == null && (method = 'post');
        data.action = 'p2p_box';
        data.nonce = P2PAdmin.nonce;
        data.box_id = $metabox.data('box_id');
        data.post_type = jQuery('#post_type').val();
        return jQuery[method](ajaxurl, data, callback);
      };
      row_ajax_request = function($td, data, callback){
        $td.html($spinner.show());
        return ajax_request(data, callback);
      };
      clear_connections = function(ev){
        var $self, $td, data, _this = this;
        if (!confirm(P2PAdmin.deleteConfirmMessage)) {
          return false;
        }
        $self = jQuery(ev.target);
        $td = $self.closest('td');
        data = {
          subaction: 'clear_connections',
          post_id: jQuery('#post_ID').val()
        };
        row_ajax_request($td, data, function(response){
          $connections.hide().find('tbody').html('');
          return $td.html($self);
        });
        return false;
      };
      delete_connection = function(ev){
        var $self, $td, data, _this = this;
        $self = jQuery(ev.target);
        $td = $self.closest('td');
        data = {
          subaction: 'disconnect',
          p2p_id: $self.data('p2p_id')
        };
        row_ajax_request($td, data, function(response){
          $td.closest('tr').remove();
          if (!$connections.find('tbody tr').length) {
            return $connections.hide();
          }
        });
        return false;
      };
      create_connection = function(ev){
        var $self, $td, data, _this = this;
        $self = jQuery(ev.target);
        $td = $self.closest('td');
        data = {
          subaction: 'connect',
          from: jQuery('#post_ID').val(),
          to: $self.data('post_id')
        };
        row_ajax_request($td, data, function(response){
          var $table;
          $connections.show().find('tbody').append(response);
          if ($metabox.data('prevent_duplicates')) {
            $table = $td.closest('table');
            if (1 == $table.find('tbody tr').length) {
              return $table.remove();
            } else {
              return $td.closest('tr').remove();
            }
          } else {
            return $td.html($self);
          }
        });
        return false;
      };
      switch_to_tab = function(){
        var $tab;
        $tab = jQuery(this);
        $metabox.find('.wp-tab-bar li').removeClass('wp-tab-active');
        $tab.addClass('wp-tab-active');
        $metabox.find('.tabs-panel').hide().end().find($tab.data('ref')).show().find(':text').focus();
        return false;
      };
      $metabox.delegate('th.p2p-col-delete a', 'click', clear_connections).delegate('td.p2p-col-delete a', 'click', delete_connection).delegate('td.p2p-col-create a', 'click', create_connection).delegate('.wp-tab-bar li', 'click', switch_to_tab);
      if ($connections.find('th.p2p-col-order').length) {
        $connections.find('tbody').sortable({
          handle: 'td.p2p-col-order',
          helper: function(e, ui){
            ui.children().each(function(){
              var $this;
              $this = jQuery(this);
              return $this.width($this.width());
            });
            return ui;
          }
        });
      }
      PostsTab = (function(){
        PostsTab.displayName = 'PostsTab';
        var prototype = PostsTab.prototype, constructor = PostsTab;
        function PostsTab(selector){
          this.tab = $metabox.find(selector);
          this.init_pagination_data();
          this.tab.delegate('.p2p-prev, .p2p-next', 'click', __bind(this, this.change_page));
          this.data = {
            subaction: 'search',
            post_id: jQuery('#post_ID').val(),
            s: ''
          };
        }
        prototype.init_pagination_data = function(){
          this.current_page = this.tab.find('.p2p-current').text() || 1;
          return this.total_pages = this.tab.find('.p2p-total').text() || 1;
        };
        prototype.change_page = function(ev){
          var $navButton, new_page;
          $navButton = jQuery(ev.target);
          new_page = this.current_page;
          if ($navButton.hasClass('inactive')) {
            return false;
          }
          if ($navButton.hasClass('p2p-prev')) {
            new_page--;
          } else {
            new_page++;
          }
          this.find_posts(new_page);
          return false;
        };
        prototype.find_posts = function(new_page){
          this.data.paged = new_page
            ? new_page > this.total_pages ? this.current_page : new_page
            : this.current_page;
          $spinner.appendTo(this.tab.find('.p2p-navigation'));
          return ajax_request(this.data, __bind(this, this.update_rows), 'getJSON');
        };
        prototype.update_rows = function(response){
          $spinner.remove();
          this.tab.find('.p2p-results, .p2p-navigation, .p2p-notice').remove();
          if (!response.rows) {
            return this.tab.append(jQuery('<div class="p2p-notice">').html(response.msg));
          } else {
            this.tab.append(response.rows);
            return this.init_pagination_data();
          }
        };
        return PostsTab;
      }());
      searchTab = new PostsTab('.p2p-tab-search');
      listTab = new PostsTab('.p2p-tab-list');
      $searchInput = $metabox.find('.p2p-tab-search :text');
      $searchInput.keypress(function(ev){
        if (13 === ev.keyCode) {
          return false;
        }
      }).keyup(function(ev){
        var delayed;
        if (undefined !== delayed) {
          clearTimeout(delayed);
        }
        return delayed = setTimeout(function(){
          var searchStr;
          searchStr = $searchInput.val();
          if ('' == searchStr || searchStr === searchTab.data.s) {
            return;
          }
          searchTab.data.s = searchStr;
          $spinner.insertAfter($searchInput).show();
          return searchTab.find_posts(1);
        }, 400);
      });
      $createButton = $metabox.find('.p2p-tab-create-post .button');
      $createInput = $metabox.find('.p2p-tab-create-post :text');
      $createButton.click(function(){
        var $button, title, data;
        $button = jQuery(this);
        if ($button.hasClass('inactive')) {
          return false;
        }
        title = $createInput.val();
        if ('' === title) {
          $createInput.focus();
          return false;
        }
        $button.addClass('inactive');
        data = {
          subaction: 'create_post',
          from: jQuery('#post_ID').val(),
          post_title: title
        };
        ajax_request(data, function(response){
          $connections.show().find('tbody').append(response);
          $createInput.val('');
          return $button.removeClass('inactive');
        });
        return false;
      });
      return $createInput.keypress(function(ev){
        if (13 === ev.keyCode) {
          $createButton.click();
          return false;
        }
      });
    });
  });
  function __bind(me, fn){ return function(){ return fn.apply(me, arguments) } }
}).call(this);
