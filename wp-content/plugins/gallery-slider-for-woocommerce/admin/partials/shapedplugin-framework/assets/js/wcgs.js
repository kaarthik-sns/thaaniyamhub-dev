/**
 * -----------------------------------------------------------
 *
 * Shapedplugin Framework
 *
 * -----------------------------------------------------------
 *
 */
; (function ($, window, document, undefined) {
  'use strict';

  //
  // Constants
  //
  var WCGS = WCGS || {};

  WCGS.funcs = {};

  WCGS.vars = {
    onloaded: false,
    $body: $('body'),
    $window: $(window),
    $document: $(document),
    is_rtl: $('body').hasClass('rtl'),
    code_themes: [],
  };

  //
  // Helper Functions
  //
  WCGS.helper = {

    //
    // Generate UID
    //
    uid: function (prefix) {
      return (prefix || '') + Math.random().toString(36).substr(2, 9);
    },

    // Quote regular expression characters
    //
    preg_quote: function (str) {
      return (str + '').replace(/(\[|\-|\])/g, "\\$1");
    },

    //
    // Reneme input names
    //
    name_nested_replace: function ($selector, field_id) {

      var checks = [];
      var regex = new RegExp('(' + WCGS.helper.preg_quote(field_id) + ')\\[(\\d+)\\]', 'g');

      $selector.find(':radio').each(function () {
        if (this.checked || this.orginal_checked) {
          this.orginal_checked = true;
        }
      });

      $selector.each(function (index) {
        $(this).find(':input').each(function () {
          this.name = this.name.replace(regex, field_id + '[' + index + ']');
          if (this.orginal_checked) {
            this.checked = true;
          }
        });
      });

    },

    //
    // Debounce
    //
    debounce: function (callback, threshold, immediate) {
      var timeout;
      return function () {
        var context = this, args = arguments;
        var later = function () {
          timeout = null;
          if (!immediate) {
            callback.apply(context, args);
          }
        };
        var callNow = (immediate && !timeout);
        clearTimeout(timeout);
        timeout = setTimeout(later, threshold);
        if (callNow) {
          callback.apply(context, args);
        }
      };
    },

    //
    // Get a cookie
    //
    get_cookie: function (name) {

      var e, b, cookie = document.cookie, p = name + '=';

      if (!cookie) {
        return;
      }

      b = cookie.indexOf('; ' + p);

      if (b === -1) {
        b = cookie.indexOf(p);

        if (b !== 0) {
          return null;
        }
      } else {
        b += 2;
      }

      e = cookie.indexOf(';', b);

      if (e === -1) {
        e = cookie.length;
      }

      return decodeURIComponent(cookie.substring(b + p.length, e));

    },

    //
    // Set a cookie
    //
    set_cookie: function (name, value, expires, path, domain, secure) {

      var d = new Date();

      if (typeof (expires) === 'object' && expires.toGMTString) {
        expires = expires.toGMTString();
      } else if (parseInt(expires, 10)) {
        d.setTime(d.getTime() + (parseInt(expires, 10) * 1000));
        expires = d.toGMTString();
      } else {
        expires = '';
      }

      document.cookie = name + '=' + encodeURIComponent(value) +
        (expires ? '; expires=' + expires : '') +
        (path ? '; path=' + path : '') +
        (domain ? '; domain=' + domain : '') +
        (secure ? '; secure' : '');

    },

    //
    // Remove a cookie
    //
    remove_cookie: function (name, path, domain, secure) {
      WCGS.helper.set_cookie(name, '', -1000, path, domain, secure);
    },

  };

  //
  // Custom clone for textarea and select clone() bug
  //
  $.fn.wcgs_clone = function () {

    var base = $.fn.clone.apply(this, arguments),
      clone = this.find('select').add(this.filter('select')),
      cloned = base.find('select').add(base.filter('select'));

    for (var i = 0; i < clone.length; ++i) {
      for (var j = 0; j < clone[i].options.length; ++j) {

        if (clone[i].options[j].selected === true) {
          cloned[i].options[j].selected = true;
        }

      }
    }

    this.find(':radio').each(function () {
      this.orginal_checked = this.checked;
    });

    return base;

  };

  //
  // Expand All Options
  //
  $.fn.wcgs_expand_all = function () {
    return this.each(function () {
      $(this).on('click', function (e) {

        e.preventDefault();
        $('.wcgs-wrapper').toggleClass('wcgs-show-all');
        $('.wcgs-section').wcgs_reload_script();
        $(this).find('.fa').toggleClass('fa-indent').toggleClass('fa-outdent');

      });
    });
  };

  //
  // Options Navigation
  //
  $.fn.wcgs_nav_options = function () {
    return this.each(function () {

      var $nav = $(this),
        $links = $nav.find('a'),
        $hidden = $nav.closest('.wcgs').find('.wcgs-section-id'),
        $last_section;


      $(window).on('hashchange', function () {

        var hash = window.location.hash.match(new RegExp('tab=([^&]*)'));
        var slug = hash ? hash[1] : $links.first().attr('href').replace('#tab=', '');
        var $link = $('#wcgs-tab-link-' + slug);


        if ($link.length > 0) {
          $link.closest('.wcgs-tab-depth-0').addClass('wcgs-tab-active').siblings().removeClass('wcgs-tab-active');
          $links.removeClass('wcgs-section-active');
          $link.addClass('wcgs-section-active');

          if ($last_section !== undefined) {
            $last_section.hide();
          }

          var $section = $('#wcgs-section-' + slug);
          $section.css({ display: 'block' });
          $section.wcgs_reload_script();
          $('.wcgs-section').wcgs_reload_script();
          $hidden.val(slug);

          $last_section = $section;
          if ($('.toplevel_page_wpgs-settings').hasClass('wp-has-current-submenu')) {
            $('a[href="admin.php?page=wpgs-settings"]').parent().addClass('current wcgs-active').siblings().removeClass('current wcgs-active');
          }
        }
      }).trigger('hashchange');
    });
  };
  $(document).on('click', '.wcgs-tabbed-nav a', function (event) {
    var gallery_last_open_tab = $(this).attr('id');
    let hash = window.location.hash;

    // Update the hash without reloading.
    window.location.hash = hash;
    WCGS.helper.set_cookie('wcgs-gallery-last-open-tab', gallery_last_open_tab);
  });
  //
  // Metabox Tabs
  //
  $.fn.wcgs_nav_metabox = function () {
    return this.each(function () {

      var $nav = $(this),
        $links = $nav.find('a'),
        unique_id = $nav.data('unique'),
        post_id = $('#post_ID').val() || 'global',
        $last_section,
        $last_link;

      $links.on('click', function (e) {

        e.preventDefault();

        var $link = $(this),
          section_id = $link.data('section');

        if ($last_link !== undefined) {
          $last_link.removeClass('wcgs-section-active');
        }

        if ($last_section !== undefined) {
          $last_section.hide();
        }

        $link.addClass('wcgs-section-active');

        var $section = $('#wcgs-section-' + section_id);
        $section.css({ display: 'block' });
        $section.wcgs_reload_script();

        WCGS.helper.set_cookie('wcgs-last-metabox-tab-' + post_id + '-' + unique_id, section_id);

        $last_section = $section;
        $last_link = $link;

      });

      var get_cookie = WCGS.helper.get_cookie('wcgs-last-metabox-tab-' + post_id + '-' + unique_id);

      if (get_cookie) {
        $nav.find('a[data-section="' + get_cookie + '"]').trigger('click');
      } else {
        $links.first('a').trigger('click');
      }

    });
  };

  //
  // Search
  //
  $.fn.wcgs_search = function () {
    return this.each(function () {

      var $this = $(this),
        $input = $this.find('input');

      $input.on('change keyup', function () {

        var value = $(this).val(),
          $wrapper = $('.wcgs-wrapper'),
          $section = $wrapper.find('.wcgs-section'),
          $fields = $section.find('> .wcgs-field:not(.hidden)'),
          $titles = $fields.find('> .wcgs-title, .wcgs-search-tags');

        if (value.length > 3) {

          $fields.addClass('wcgs-hidden');
          $wrapper.addClass('wcgs-search-all');

          $titles.each(function () {

            var $title = $(this);

            if ($title.text().match(new RegExp('.*?' + value + '.*?', 'i'))) {

              var $field = $title.closest('.wcgs-field');

              $field.removeClass('wcgs-hidden');
              $field.parent().wcgs_reload_script();

            }

          });

        } else {

          $fields.removeClass('wcgs-hidden');
          $wrapper.removeClass('wcgs-search-all');

        }

      });

    });
  };

  //
  // Sticky Header
  //
  $.fn.wcgs_sticky = function () {
    return this.each(function () {

      var $this = $(this),
        $window = $(window),
        $inner = $this.find('.wcgs-header-inner'),
        padding = parseInt($inner.css('padding-left')) + parseInt($inner.css('padding-right')),
        offset = 32,
        scrollTop = 0,
        lastTop = 0,
        ticking = false,
        stickyUpdate = function () {

          var offsetTop = $this.offset().top,
            stickyTop = Math.max(offset, offsetTop - scrollTop),
            winWidth = Math.max(document.documentElement.clientWidth, window.innerWidth || 0);

          if (stickyTop <= offset && winWidth > 782) {
            $inner.css({ width: $this.outerWidth() - padding });
            $this.css({ height: $this.outerHeight() }).addClass('wcgs-sticky');
          } else {
            $inner.removeAttr('style');
            $this.removeAttr('style').removeClass('wcgs-sticky');
          }

        },
        requestTick = function () {

          if (!ticking) {
            requestAnimationFrame(function () {
              stickyUpdate();
              ticking = false;
            });
          }

          ticking = true;

        },
        onSticky = function () {

          scrollTop = $window.scrollTop();
          requestTick();

        };

      $window.on('scroll resize', onSticky);

      onSticky();

    });
  };

  //
  // Dependency System
  //
  $.fn.wcgs_dependency = function () {
    return this.each(function () {

      var $this = $(this),
        ruleset = $.wcgs_deps.createRuleset(),
        depends = [],
        is_global = false;

      $this.children('[data-controller]').each(function () {

        var $field = $(this),
          controllers = $field.data('controller').split('|'),
          conditions = $field.data('condition').split('|'),
          values = $field.data('value').toString().split('|'),
          rules = ruleset;

        if ($field.data('depend-global')) {
          is_global = true;
        }

        $.each(controllers, function (index, depend_id) {

          var value = values[index] || '',
            condition = conditions[index] || conditions[0];

          rules = rules.createRule('[data-depend-id="' + depend_id + '"]', condition, value);

          rules.include($field);

          depends.push(depend_id);

        });

      });

      if (depends.length) {

        if (is_global) {
          $.wcgs_deps.enable(WCGS.vars.$body, ruleset, depends);
        } else {
          $.wcgs_deps.enable($this, ruleset, depends);
        }

      }

    });
  };

  //
  // Field: code_editor
  //
  $.fn.wcgs_field_code_editor = function () {
    return this.each(function () {
      if (typeof wp === 'undefined' || typeof wp.codeEditor === 'undefined') {
        return;
      }

      var $this = $(this),
        $textarea = $this.find('textarea'),
        settings = $textarea.data('editor') || {};

      // Merge with WP defaults
      var editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
      editorSettings.codemirror = _.extend(
        {},
        editorSettings.codemirror,
        settings
      );

      // Initialize editor
      var editor = wp.codeEditor.initialize($textarea[0], editorSettings);
      // Sync changes back to textarea
      editor.codemirror.on('change', function () {
        $textarea.val(editor.codemirror.getValue()).trigger('change');
      });

      // Force refresh after initialization.
      setTimeout(function () {
        editor.codemirror.refresh();
      }, 100);
    });
  };


  //
  // Field: slider
  //
  $.fn.wcgs_field_slider = function () {
    return this.each(function () {

      var $this = $(this),
        $input = $this.find('input'),
        $slider = $this.find('.wcgs-slider-ui'),
        data = $input.data(),
        value = $input.val() || 0;

      if ($slider.hasClass('ui-slider')) {
        $slider.empty();
      }

      $slider.slider({
        range: 'min',
        value: value,
        min: data.min,
        max: data.max,
        step: data.step,
        slide: function (e, o) {
          $input.val(o.value).trigger('change');
        }
      });

      $input.on('keyup', function () {
        $slider.slider('value', $input.val());
      });

    });
  };


  //
  // Field: spinner
  //
  $.fn.wcgs_field_spinner = function () {
    return this.each(function () {

      var $this = $(this),
        $input = $this.find('input'),
        $inited = $this.find('.ui-spinner-button');

      if ($inited.length) {
        $inited.remove();
      }

      $input.spinner({
        max: $input.data('max') || 100,
        min: $input.data('min') || 0,
        step: $input.data('step') || 1,
        spin: function (event, ui) {
          $input.val(ui.value).trigger('change');
        }
      });


    });
  };

  //
  // Field: switcher
  //
  $.fn.wcgs_field_switcher = function () {
    return this.each(function () {

      var $switcher = $(this).find('.wcgs--switcher');

      $switcher.on('click', function () {

        var value = 0;
        var $input = $switcher.find('input');

        if ($switcher.hasClass('wcgs--active')) {
          $switcher.removeClass('wcgs--active');
        } else {
          value = 1;
          $switcher.addClass('wcgs--active');
        }

        $input.val(value).trigger('change');

      });

    });
  };


  //
  // Confirm
  //
  $.fn.wcgs_confirm = function () {
    return this.each(function () {
      $(this).on('click', function (e) {

        var confirm_text = $(this).data('confirm') || window.wcgs_vars.i18n.confirm;
        var confirm_answer = confirm(confirm_text);
        WCGS.vars.is_confirm = true;

        if (!confirm_answer) {
          e.preventDefault();
          WCGS.vars.is_confirm = false;
          return false;
        }

      });
    });
  };

  $.fn.serializeObject = function () {

    var obj = {};

    $.each(this.serializeArray(), function (i, o) {
      var n = o.name,
        v = o.value;

      obj[n] = obj[n] === undefined ? v
        : $.isArray(obj[n]) ? obj[n].concat(v)
          : [obj[n], v];
    });

    return obj;

  };

  //
  // Options Save
  //
  $.fn.wcgs_save = function () {
    return this.each(function () {

      var $this = $(this),
        $buttons = $('.wcgs-save'),
        $panel = $('.wcgs-options'),
        flooding = false,
        timeout;

      $this.on('click', function (e) {

        if (!flooding) {

          var $text = $this.data('save'),
            $value = $this.val();

          $buttons.attr('value', $text);

          if ($this.hasClass('wcgs-save-ajax')) {

            e.preventDefault();

            $panel.addClass('wcgs-saving');
            $buttons.prop('disabled', true);

            window.wp.ajax.post('wcgs_' + $panel.data('unique') + '_ajax_save', {
              data: $('#wcgs-form').serializeJSONWCGS(),
              nonce: $('#wcgs_options_nonce' + $panel.data('unique')).val()
            })
              .done(function (response) {

                clearTimeout(timeout);

                var $result_success = $('.wcgs-form-success');

                $result_success.empty().append(response.notice).slideDown('fast', function () {
                  timeout = setTimeout(function () {
                    $result_success.slideUp('fast');
                  }, 2000);
                });

                // clear errors
                $('.wcgs-error').remove();

                var $append_errors = $('.wcgs-form-error');

                $append_errors.empty().hide();

                if (Object.keys(response.errors).length) {

                  var error_icon = '<i class="wcgs-label-error wcgs-error">!</i>';

                  $.each(response.errors, function (key, error_message) {

                    var $field = $('[data-depend-id="' + key + '"]'),
                      $link = $('#wcgs-tab-link-' + ($field.closest('.wcgs-section').index() + 1)),
                      $tab = $link.closest('.wcgs-tab-depth-0');

                    $field.closest('.wcgs-fieldset').append('<p class="wcgs-text-error wcgs-error">' + error_message + '</p>');

                    if (!$link.find('.wcgs-error').length) {
                      $link.append(error_icon);
                    }

                    if (!$tab.find('.wcgs-arrow .wcgs-error').length) {
                      $tab.find('.wcgs-arrow').append(error_icon);
                    }

                    console.log(error_message);

                    $append_errors.append('<div>' + error_icon + ' ' + error_message + '</div>');

                  });

                  $append_errors.show();

                }

                $panel.removeClass('wcgs-saving');
                $buttons.prop('disabled', false).attr('value', $value);
                flooding = false;
              })
              .fail(function (response) {
                alert(response.error);
              });

          }

        }

        flooding = true;

      });

    });
  };


  //
  // Helper Checkbox Checker
  //
  $.fn.wcgs_checkbox = function () {
    return this.each(function () {

      var $this = $(this),
        $input = $this.find('.wcgs--input'),
        $checkbox = $this.find('.wcgs--checkbox');

      $checkbox.on('click', function () {
        $input.val(Number($checkbox.prop('checked'))).trigger('change');
      });

    });
  };

  //
  // Siblings
  //
  $.fn.wcgs_siblings = function () {
    return this.each(function () {

      var $this = $(this),
        $siblings = $this.find('.wcgs--sibling'),
        multiple = $this.data('multiple') || false;
      $siblings.on('click', function () {

        var $sibling = $(this);

        if (multiple) {
          if ($sibling.hasClass('wcgs--active')) {
            $sibling.removeClass('wcgs--active');
            $sibling.find('input').prop('checked', false).trigger('change');
          } else {
            $sibling.addClass('wcgs--active');
            $sibling.find('input').prop('checked', true).trigger('change');
          }
        } else {
          $this.find('input').prop('checked', false);
          $sibling.find('input').prop('checked', true).trigger('change');
          $sibling.addClass('wcgs--active').siblings().removeClass('wcgs--active');
        }
      });
    });
  };

  //
  // WP Color Picker
  //
  if (typeof Color === 'function') {

    Color.fn.toString = function () {

      if (this._alpha < 1) {
        return this.toCSS('rgba', this._alpha).replace(/\s+/g, '');
      }

      var hex = parseInt(this._color, 10).toString(16);

      if (this.error) { return ''; }

      if (hex.length < 6) {
        for (var i = 6 - hex.length - 1; i >= 0; i--) {
          hex = '0' + hex;
        }
      }

      return '#' + hex;

    };

  }

  WCGS.funcs.parse_color = function (color) {

    var value = color.replace(/\s+/g, ''),
      trans = (value.indexOf('rgba') !== -1) ? parseFloat(value.replace(/^.*,(.+)\)/, '$1') * 100) : 100,
      rgba = (trans < 100) ? true : false;

    return { value: value, transparent: trans, rgba: rgba };

  };

  $.fn.wcgs_color = function () {
    return this.each(function () {

      var $input = $(this),
        picker_color = WCGS.funcs.parse_color($input.val()),
        palette_color = window.wcgs_vars.color_palette.length ? window.wcgs_vars.color_palette : true,
        $container;

      // Destroy and Reinit
      if ($input.hasClass('wp-color-picker')) {
        $input.closest('.wp-picker-container').after($input).remove();
      }

      $input.wpColorPicker({
        palettes: palette_color,
        change: function (event, ui) {

          var ui_color_value = ui.color.toString();

          $container.removeClass('wcgs--transparent-active');
          $container.find('.wcgs--transparent-offset').css('background-color', ui_color_value);
          $input.val(ui_color_value).trigger('change');

        },
        create: function () {

          $container = $input.closest('.wp-picker-container');

          var a8cIris = $input.data('a8cIris'),
            $transparent_wrap = $('<div class="wcgs--transparent-wrap">' +
              '<div class="wcgs--transparent-slider"></div>' +
              '<div class="wcgs--transparent-offset"></div>' +
              '<div class="wcgs--transparent-text"></div>' +
              '<div class="wcgs--transparent-button button button-small">transparent</div>' +
              '</div>').appendTo($container.find('.wp-picker-holder')),
            $transparent_slider = $transparent_wrap.find('.wcgs--transparent-slider'),
            $transparent_text = $transparent_wrap.find('.wcgs--transparent-text'),
            $transparent_offset = $transparent_wrap.find('.wcgs--transparent-offset'),
            $transparent_button = $transparent_wrap.find('.wcgs--transparent-button');

          if ($input.val() === 'transparent') {
            $container.addClass('wcgs--transparent-active');
          }

          $transparent_button.on('click', function () {
            if ($input.val() !== 'transparent') {
              $input.val('transparent').trigger('change').removeClass('iris-error');
              $container.addClass('wcgs--transparent-active');
            } else {
              $input.val(a8cIris._color.toString()).trigger('change');
              $container.removeClass('wcgs--transparent-active');
            }
          });

          $transparent_slider.slider({
            value: picker_color.transparent,
            step: 1,
            min: 0,
            max: 100,
            slide: function (event, ui) {

              var slide_value = parseFloat(ui.value / 100);
              a8cIris._color._alpha = slide_value;
              $input.wpColorPicker('color', a8cIris._color.toString());
              $transparent_text.text((slide_value === 1 || slide_value === 0 ? '' : slide_value));

            },
            create: function () {

              var slide_value = parseFloat(picker_color.transparent / 100),
                text_value = slide_value < 1 ? slide_value : '';

              $transparent_text.text(text_value);
              $transparent_offset.css('background-color', picker_color.value);

              $container.on('click', '.wp-picker-clear', function () {

                a8cIris._color._alpha = 1;
                $transparent_text.text('');
                $transparent_slider.slider('option', 'value', 100);
                $container.removeClass('wcgs--transparent-active');
                $input.trigger('change');

              });

              $container.on('click', '.wp-picker-default', function () {

                var default_color = WCGS.funcs.parse_color($input.data('default-color')),
                  default_value = parseFloat(default_color.transparent / 100),
                  default_text = default_value < 1 ? default_value : '';

                a8cIris._color._alpha = default_value;
                $transparent_text.text(default_text);
                $transparent_slider.slider('option', 'value', default_color.transparent);

              });

              $container.on('click', '.wp-color-result', function () {
                $transparent_wrap.toggle();
              });

              $('body').on('click.wpcolorpicker', function () {
                $transparent_wrap.hide();
              });

            }
          });
        }
      });

    });
  };
  //
  // Field: group
  //
  $.fn.wcgs_field_group = function () {
    return this.each(function () {

      var $this = $(this),
        $fieldset = $this.children('.wcgs-fieldset'),
        $group = $fieldset.length ? $fieldset : $this,
        $wrapper = $group.children('.wcgs-cloneable-wrapper'),
        $hidden = $group.children('.wcgs-cloneable-hidden'),
        $max = $group.children('.wcgs-cloneable-max'),
        $min = $group.children('.wcgs-cloneable-min'),
        field_id = $wrapper.data('field-id'),
        is_number = Boolean(Number($wrapper.data('title-number'))),
        max = parseInt($wrapper.data('max')),
        min = parseInt($wrapper.data('min'));


      // clear accordion arrows if multi-instance
      if ($wrapper.hasClass('ui-accordion')) {
        $wrapper.find('.ui-accordion-header-icon').remove();
      }

      var update_title_numbers = function ($selector) {
        $selector.find('.wcgs-cloneable-title-number').each(function (index) {
          $(this).html(($(this).closest('.wcgs-cloneable-item').index() + 1) + '.');
        });
      };

      $wrapper.accordion({
        header: '> .wcgs-cloneable-item > .wcgs-cloneable-title',
        collapsible: true,
        active: false,
        animate: false,
        heightStyle: 'content',
        beforeActivate: function (event, ui) {
          var $panel = ui.newPanel;
          var $header = ui.newHeader;
          if ($panel.length && !$panel.data('opened')) {
            var $fields = $panel.children();
            var $first = $fields.first();
            var $title = $header.find('.wcgs-cloneable-value');
            $fields.on('change keyup', function (event) {
              setTimeout(function () {
                $fields.each(function () {
                  if ($(this).hasClass('wcgs_layout')) {
                    var $group_title = $(this).find('option:selected').html();
                    $title.text($group_title);
                  }
                });

              }, 300);
            });

          }
        },
        icons: {
          'header': 'wcgs-cloneable-header-icon sp_wgs-icon-angle-right',
          'activeHeader': 'wcgs-cloneable-header-icon sp_wgs-icon-angle-down'
        },
        activate: function (event, ui) {

          var $panel = ui.newPanel;
          var $header = ui.newHeader;

          if ($panel.length && !$panel.data('opened')) {
            var $fields = $panel.children();
            var $first = $fields.first();
            var $title = $header.find('.wcgs-cloneable-value');
            $fields.on('change keyup', function (event) {
              setTimeout(function () {
                $fields.each(function () {
                  if ($(this).hasClass('wcgs_layout')) {
                    var $group_title = $(this).find('option:selected').html();
                    $title.text($group_title);
                  }
                });
              }, 300);
            });
            $panel.wcgs_reload_script();
            $panel.data('opened', true);
            $panel.data('retry', false);
          } else if ($panel.data('retry')) {
            $panel.wcgs_reload_script_retry();
            $panel.data('retry', false);
          }
        }
      });

      $wrapper.sortable({
        axis: 'y',
        handle: '.wcgs-cloneable-title,.wcgs-cloneable-sort',
        helper: 'original',
        cursor: 'move',
        placeholder: 'widget-placeholder',
        start: function (event, ui) {
          $wrapper.accordion({ active: false });
          $wrapper.sortable('refreshPositions');
          ui.item.children('.wcgs-cloneable-content').data('retry', true);
        },
        update: function (event, ui) {

          WCGS.helper.name_nested_replace($wrapper.children('.wcgs-cloneable-item'), field_id);
          //  $wrapper.wcgs_customizer_refresh();

          if (is_number) {
            update_title_numbers($wrapper);
          }

        },
      });
      $group.children('.wcgs-cloneable-add').on('click', function (e) {

        e.preventDefault();

        var count = $wrapper.children('.wcgs-cloneable-item').length;

        $min.hide();
        if (max && (count + 1) > max) {
          //  $max.show();
          tb_show('', '#TB_inline?&width=440&height=225&inlineId=BuyProPopupContent');
          return;
        }

        var $cloned_item = $hidden.wcgs_clone(true);

        $cloned_item.removeClass('wcgs-cloneable-hidden');

        $cloned_item.find(':input[name!="_pseudo"]').each(function () {
          this.name = this.name.replace('___', '').replace(field_id + '[0]', field_id + '[' + count + ']');
        });

        $wrapper.append($cloned_item);
        $wrapper.accordion('refresh');
        $wrapper.accordion({ active: count });
        // $wrapper.wcgs_customizer_refresh();
        // $wrapper.wcgs_customizer_listen({ closest: true });

        if (is_number) {
          update_title_numbers($wrapper);
        }

      });

      var event_clone = function (e) {

        e.preventDefault();

        var count = $wrapper.children('.wcgs-cloneable-item').length;

        $min.hide();

        if (max && (count + 1) > max) {
          $max.show();
          return;
        }

        var $this = $(this),
          $parent = $this.parent().parent(),
          $cloned_helper = $parent.children('.wcgs-cloneable-helper').wcgs_clone(true),
          $cloned_title = $parent.children('.wcgs-cloneable-title').wcgs_clone(),
          $cloned_content = $parent.children('.wcgs-cloneable-content').wcgs_clone(),
          $cloned_item = $('<div class="wcgs-cloneable-item" />');

        $cloned_item.append($cloned_helper);
        $cloned_item.append($cloned_title);
        $cloned_item.append($cloned_content);

        $wrapper.children().eq($parent.index()).after($cloned_item);

        WCGS.helper.name_nested_replace($wrapper.children('.wcgs-cloneable-item'), field_id);

        $wrapper.accordion('refresh');
        //  $wrapper.wcgs_customizer_refresh();
        // $wrapper.wcgs_customizer_listen({ closest: true });

        if (is_number) {
          update_title_numbers($wrapper);
        }

      };

      $wrapper.children('.wcgs-cloneable-item').children('.wcgs-cloneable-helper').on('click', '.wcgs-cloneable-clone', event_clone);
      $group.children('.wcgs-cloneable-hidden').children('.wcgs-cloneable-helper').on('click', '.wcgs-cloneable-clone', event_clone);

      setTimeout(function () {
        $group.find('.wcgs-cloneable-item:not(.wcgs-cloneable-hidden)').each(function () {
          var $header = $(this).find('.wcgs-cloneable-title');
          var $title = $header.find('.wcgs-cloneable-value');
          // var $group_title = $(this).find('.wcgs--active .sp-carousel-type');
          var $group_title = $(this).find('.wcgs_layout option:selected').html();

          $title.text($group_title);
        });
      }, 100);
    });
  };
  //
  // Field: tabbed
  //
  $.fn.wcgs_field_tabbed = function () {
    return this.each(function () {

      var $this = $(this),
        $links = $this.find('.wcgs-tabbed-nav a'),
        $sections = $this.find('.wcgs-tabbed-section');
      $sections.eq(0).wcgs_reload_script();
      $links.on('click', function (e) {
        e.preventDefault();
        var $link = $(this),
          index = $link.index(),
          $section = $sections.eq(index);
        $link.addClass('wcgs-tabbed-active').siblings().removeClass('wcgs-tabbed-active');
        $section.wcgs_reload_script();
        $section.removeClass('hidden').siblings().addClass('hidden');

      });
    });
  };
  //
  // ChosenJS
  //
  $.fn.wcgs_chosen = function () {
    return this.each(function () {

      var $this = $(this),
        $inited = $this.parent().find('.chosen-container'),
        is_sortable = $this.hasClass('wcgs--sortable') || false,
        is_ajax = $this.hasClass('wcgs-chosen-ajax') || false,
        is_multiple = $this.attr('multiple') || false,
        set_width = is_multiple ? '100%' : 'auto',
        set_options = $.extend({
          allow_single_deselect: true,
          disable_search_threshold: 10,
          width: set_width,
          no_results_text: window.wcgs_vars.i18n.no_results_text,
        }, $this.data('chosen-settings'));

      if ($inited.length) {
        $inited.remove();
      }

      // Chosen ajax.
      if (is_ajax) {
        var set_ajax_options = $.extend({
          data: {
            type: 'post',
            nonce: '',
          },
          allow_single_deselect: true,
          disable_search_threshold: -1,
          width: '100%',
          min_length: 3,
          type_delay: 500,
          typing_text: window.wcgs_vars.i18n.typing_text,
          searching_text: window.wcgs_vars.i18n.searching_text,
          no_results_text: window.wcgs_vars.i18n.no_results_text,
        }, $this.data('chosen-settings'));

        $this.WCGSAjaxChosen(set_ajax_options);
      } else {
        $this.chosen(set_options);
      }
      // Chosen keep options order
      if (is_multiple) {


        var $hidden_select = $this.parent().find('.wcgs-hide-select');
        var $hidden_value = $hidden_select.val() || [];
        $this.on('change', function (obj, result) {
          if (result && result.selected) {
            if ($this.parents('.wcgs-field').hasClass('assign_product_terms') || $this.parents('.wcgs-field').hasClass('wcgs_specific_product')) {
              var selectedOptions = $(this).val();
              if (selectedOptions && selectedOptions.length > 1) {
                tb_show('', '#TB_inline?&width=440&height=225&inlineId=BuyProPopupContent');
                selectedOptions.pop();
                $(this).val(selectedOptions).trigger('chosen:updated');
                return;
              }
            };
            $hidden_select.append('<option value="' + result.selected + '" selected="selected">' + result.selected + '</option>');
          } else if (result && result.deselected) {
            $hidden_select.find('option[value="' + result.deselected + '"]').remove();
          }




          // Force customize refresh
          // if (window.wp.customize !== undefined && $hidden_select.children().length === 0 && $hidden_select.data('customize-setting-link')) {
          // 	window.wp.customize.control($hidden_select.data('customize-setting-link')).setting.set('');
          // }

          $hidden_select.trigger('change');
        });
        //  WCGS;
        // Chosen order abstract
        $this.WCGSChosenOrder($hidden_value, true);
      }

      // Chosen sortable
      if (is_sortable) {

        var $chosen_container = $this.parent().find('.chosen-container');
        var $chosen_choices = $chosen_container.find('.chosen-choices');

        $chosen_choices.on('mousedown', function (event) {
          if ($(event.target).is('span')) {
            event.stopPropagation();
          }
        });

        $chosen_choices.sortable({
          items: 'li:not(.search-field)',
          helper: 'orginal',
          cursor: 'move',
          placeholder: 'search-choice-placeholder',
          start: function (e, ui) {
            ui.placeholder.width(ui.item.innerWidth());
            ui.placeholder.height(ui.item.innerHeight());
          },
          update: function (e, ui) {

            var select_options = '';
            var chosen_object = $this.data('chosen');
            var $prev_select = $this.parent().find('.wcgs-hide-select');

            $chosen_choices.find('.search-choice-close').each(function () {
              var option_array_index = $(this).data('option-array-index');
              $.each(chosen_object.results_data, function (index, data) {
                if (data.array_index === option_array_index) {
                  select_options += '<option value="' + data.value + '" selected>' + data.value + '</option>';
                }
              });
            });
            $prev_select.children().remove();
            $prev_select.append(select_options);
            $prev_select.trigger('change');

          }
        });
      }
    });
  };

  //
  // Number (only allow numeric inputs)
  //
  $.fn.wcgs_number = function () {
    return this.each(function () {

      $(this).on('keypress', function (e) {

        if (e.keyCode !== 0 && e.keyCode !== 8 && e.keyCode !== 45 && e.keyCode !== 46 && (e.keyCode < 48 || e.keyCode > 57)) {
          return false;
        }

      });

    });
  };

  //
  // Help Tooltip
  //
  $.fn.wcgs_help = function () {
    return this.each(function () {

      var $this = $(this),
        $tooltip,
        offset_left;
      var $class = '';
      $this.on({
        mouseenter: function () {
          // this class add with the support tooltip.
          if ($this.find('.wcgs-support').length > 0) {
            $class = 'support-tooltip';
          }
          $tooltip = $('<div class="wcgs-tooltip ' + $class + '"></div>').html($this.find('.wcgs-help-text').html()).appendTo('body');
          offset_left = (WCGS.vars.is_rtl) ? $this.offset().left - $tooltip.outerWidth() : ($this.offset().left + 36);

          var $top = $this.offset().top - (($tooltip.outerHeight() / 2) - 14);
          // This block used for support tooltip.
          if ($this.find('.wcgs-support').length > 0) {
            $top = $this.offset().top + 46;
            offset_left = $this.offset().left - 249;
          }
          $tooltip.css({
            top: $top,
            left: offset_left,
            textAlign: 'left',
          });
        },
        mouseleave: function () {
          if ($tooltip !== undefined) {
            if (!$tooltip.is(':hover')) {
              $tooltip.remove();
            }
          }
        }
      });
      // Event delegation to handle tooltip removal when the cursor leaves the tooltip itself.
      $('body').on('mouseleave', '.wcgs-tooltip', function () {
        if ($tooltip !== undefined) {
          $tooltip.remove();
        }
      });
    });
  };

  //
  // Customize Refresh
  //
  $.fn.wcgs_customizer_refresh = function () {
    return this.each(function () {

      var $this = $(this),
        $complex = $this.closest('.wcgs-customize-complex');

      if ($complex.length) {

        var $input = $complex.find(':input'),
          $unique = $complex.data('unique-id'),
          $option = $complex.data('option-id'),
          obj = $input.serializeObjectWCGS(),
          data = (!$.isEmptyObject(obj)) ? obj[$unique][$option] : '',
          control = wp.customize.control($unique + '[' + $option + ']');

        // clear the value to force refresh.
        control.setting._value = null;

        control.setting.set(data);

      } else {

        $this.find(':input').first().trigger('change');

      }

      $(document).trigger('wcgs-customizer-refresh', $this);

    });
  };

  //
  // Customize Listen Form Elements
  //
  $.fn.wcgs_customizer_listen = function (options) {

    var settings = $.extend({
      closest: false,
    }, options);

    return this.each(function () {

      if (window.wp.customize === undefined) { return; }

      var $this = (settings.closest) ? $(this).closest('.wcgs-customize-complex') : $(this),
        $input = $this.find(':input'),
        unique_id = $this.data('unique-id'),
        option_id = $this.data('option-id');

      if (unique_id === undefined) { return; }

      $input.on('change keyup', WCGS.helper.debounce(function () {

        var obj = $this.find(':input').serializeObjectWCGS();

        if (!$.isEmptyObject(obj) && obj[unique_id]) {

          window.wp.customize.control(unique_id + '[' + option_id + ']').setting.set(obj[unique_id][option_id]);

        }

      }, 250));

    });
  };

  //
  // Customizer Listener for Reload JS
  //
  $(document).on('expanded', '.control-section', function () {

    var $this = $(this);

    if ($this.hasClass('open') && !$this.data('inited')) {

      var $fields = $this.find('.wcgs-customize-field');
      var $complex = $this.find('.wcgs-customize-complex');

      if ($fields.length) {
        $this.wcgs_dependency();
        $fields.wcgs_reload_script({ dependency: false });
        $complex.wcgs_customizer_listen();
      }

      $this.data('inited', true);

    }

  });

  //
  // Window on resize
  //
  WCGS.vars.$window.on('resize wcgs.resize', WCGS.helper.debounce(function (event) {

    var window_width = navigator.userAgent.indexOf('AppleWebKit/') > -1 ? WCGS.vars.$window.width() : window.innerWidth;

    if (window_width <= 782 && !WCGS.vars.onloaded) {
      $('.wcgs-section').wcgs_reload_script();
      WCGS.vars.onloaded = true;
    }

  }, 200)).trigger('wcgs.resize');

  //
  // Retry Plugins
  //
  $.fn.wcgs_reload_script_retry = function () {
    return this.each(function () {

      var $this = $(this);

    });
  };

  //
  // Reload Plugins
  //
  $.fn.wcgs_reload_script = function (options) {

    var settings = $.extend({
      dependency: true,
    }, options);

    return this.each(function () {

      var $this = $(this);

      // Avoid for conflicts
      if (!$this.data('inited')) {

        // Field plugins
        $this.children('.wcgs-field-code_editor').wcgs_field_code_editor();
        $this.children('.wcgs-field-slider').wcgs_field_slider();
        //  $this.children('.wcgs-field-spinner').wcgs_field_spinner();
        $this.children('.wcgs-field-switcher:not(.pro_switcher)').wcgs_field_switcher();
        $this.children('.wcgs-field-group').wcgs_field_group();
        // Field colors
        $this.children('.wcgs-field-border').find('.wcgs-color').wcgs_color();
        $this.children('.wcgs-field-color').find('.wcgs-color').wcgs_color();
        $this.children('.wcgs-field-color_group').find('.wcgs-color').wcgs_color();

        // Field allows only number
        $this.children('.wcgs-field-dimensions').find('.wcgs-number').wcgs_number();
        $this.children('.wcgs-field-dimensions_res').find('.wcgs-number').wcgs_number();
        $this.children('.wcgs-field-slider').find('.wcgs-number').wcgs_number();
        $this.children('.wcgs-field-spacing').find('.wcgs-number').wcgs_number();
        $this.children('.wcgs-field-spinner').find('.wcgs-number').wcgs_number();

        // Field chosenjs.
        $this.children('.wcgs-field-select').find('.wcgs-chosen').wcgs_chosen();

        // Field Checkbox.
        $this.children('.wcgs-field-checkbox').find('.wcgs-checkbox').wcgs_checkbox();

        // Field Siblings.
        $this.children('.wcgs-field-button_set').find('.wcgs-siblings').wcgs_siblings();
        $this.children('.wcgs-field-image_select').find('.wcgs-siblings').wcgs_siblings();
        $this.children('.wcgs-field-tabbed').wcgs_field_tabbed();
        // Help Tooptip.
        $this.children('.wcgs-field').find('.wcgs-help').wcgs_help();
        $('.wcgs-admin-header').find('.wcgs-support-area').wcgs_help()
        if (settings.dependency) {
          $this.wcgs_dependency();
        }

        $this.data('inited', true);

        $(document).trigger('wcgs-reload-script', $this);

      }

    });
  };
  $(".spwg_shortcode .wcgs-fieldset input").on('click', function (e) {
    $(this).select();
    document.execCommand("copy");
    $(".spwg_shortcode .wcgs-fieldset").append('<div style="display: none;color:green;" class="wcgs-alert">Copied!</div>');
    setTimeout(() => {
      $(".wcgs-alert").fadeIn(200);
    }, 100);
    setTimeout(() => {
      $(".wcgs-alert").fadeOut(200);
    }, 1000);
    setTimeout(() => {
      $(".wcgs-alert").remove();
    }, 1500);
  });
  //
  // Document ready and run scripts
  //
  $(document).ready(function () {

    $('.wcgs-save').wcgs_save();
    $('.wcgs-confirm').wcgs_confirm();
    $('.wcgs-nav-options').wcgs_nav_options();
    $('.wcgs-nav-metabox').wcgs_nav_metabox();
    $('.wcgs-expand-all').wcgs_expand_all();
    $('.wcgs-search').wcgs_search();
    $('.wcgs-sticky-header').wcgs_sticky();
    $('.wcgs-onload').wcgs_reload_script();
    // Automatically activate the custom nested tab in the gallery tab upon page reload.
    setTimeout(() => {
      var wcgs_open_tab_cookie = WCGS.helper.get_cookie('wcgs-gallery-last-open-tab');
      if (wcgs_open_tab_cookie !== null && wcgs_open_tab_cookie.length > 2) {
        $(document).find('#' + wcgs_open_tab_cookie).trigger('click')
        $('.wcgs-section').wcgs_reload_script();
      }
    }, 200);
    var checkedOptions = [];
    $('.wcgs-section input[name*="gallery_layout"]:checked').each(function () {
      checkedOptions.push($(this).val());
    });
    if (checkedOptions.includes('hide_thumb')) {
      $('#thumbnails-navigation').addClass('wcgs-hide');
    } else {
      $('#thumbnails-navigation').removeClass('wcgs-hide');
    }
    $('.gallery_layout').on("change", function () {
      setTimeout(function () {
        var checkedOptions = [];
        $('.wcgs-section input[name*="gallery_layout"]:checked').each(function () {
          checkedOptions.push($(this).val());
        });
        if (checkedOptions.includes('hide_thumb')) {
          $('#thumbnails-navigation').addClass('wcgs-hide');
        } else {
          $('#thumbnails-navigation').removeClass('wcgs-hide');
        }
      }, 100);
    });
  });

  /* Custom js */
  $("label:contains((Pro))").css({ 'pointer-events': 'none' });
  $("label:contains((Pro)) input, .pro_spinner input, .pro_checkbox input,.pro_dimensions option").attr('disabled', true);
  $("label:contains((Pro)) input, .pro_spinner input,.pro_dimensions option").css('opacity', '0.8');
  $("select option:contains((Pro))").attr('disabled', true).css('opacity', '0.8');
  $(".pro_only_slider .wcgs-slider-ui").slider({ disabled: true });
  $(".pro_only_slider input").attr({ 'disabled': true, 'value': 1.5 }).css('opacity', '0.8');
  // Event handler for changing the icon type
  var selectedValue = $('.zoom_type').find('input:checked').val();
  if (selectedValue == 'in_side') {
    $('.zoom_type').find('.wcgs-text-desc').css('opacity', 0);
  } else {
    $('.zoom_type').find('.wcgs-text-desc').css('opacity', 1)
    if (selectedValue == 'right_side') {
      $('.zoom_type').find('.wcgs-text-desc span').html('Right Side Zoom')
      $('.zoom_type').find('.wcgs-text-desc').css('opacity', 1);
    } else {
      $('.zoom_type').find('.wcgs-text-desc span').html('Magnific Zoom')
      $('.zoom_type').find('.wcgs-text-desc').css('opacity', 1);
    }
  }
  $('.zoom_type').on('change', function () {
    var _this = $(this);
    setTimeout(() => {
      var selectedValue = _this.find('input:checked').val();
      if (selectedValue == 'in_side') {
        _this.find('.wcgs-text-desc').css('opacity', 0)
      } else {
        if (selectedValue == 'right_side') {
          _this.find('.wcgs-text-desc span').html('Right Side Zoom')
          _this.find('.wcgs-text-desc').css('opacity', 1);
        } else {
          _this.find('.wcgs-text-desc span').html('Magnific Zoom')
          _this.find('.wcgs-text-desc').css('opacity', 1);
        }
      }
    }, 100);
  });

  var slider_height_type = $('.slider_height_type').find('input:checked').val();
  if (slider_height_type == 'adaptive' || slider_height_type == 'max_image') {
    $('.slider_height_type').find('.wcgs-text-desc').css('opacity', 0);
  } else {
    $('.slider_height_type').find('.wcgs-text-desc').css('opacity', 1);
  }

  $('.slider_height_type').on('change', function () {
    var _this = $(this);
    setTimeout(() => {
      var slider_height_type = _this.find('input:checked').val();
      if (slider_height_type == 'adaptive' || slider_height_type == 'max_image') {
        _this.find('.wcgs-text-desc').css('opacity', 0)
      } else {
        _this.find('.wcgs-text-desc').css('opacity', 1);
      }
    }, 100);
  });

  $(document).on('keyup change', '#wcgs-form', function (e) {
    e.preventDefault();
    var $button = $(this).find('.wcgs-save.wcgs-save-ajax');
    $button.css({ "background-color": "#00C263", "pointer-events": "initial" }).val('Save Settings');
  });
  $(document).on('click change', '#wcgs-form .wcgs-cloneable-helper, #wcgs-form .wcgs-cloneable-helper .sp_wgs-icon-clone, #wcgs-form .wcgs-cloneable-helper .sp_wgs-icon-drag-and-drop, .wcgs-cloneable-add', function (e) {
    e.preventDefault();
    var $button = $('#wcgs-form').find('.wcgs-save.wcgs-save-ajax');
    $button.css({ "background-color": "#00C263", "pointer-events": "initial" }).val('Save Settings');
  });
  // If no item added Add to assign layout, trigger to add one item.
  setTimeout(() => {
    if ($('#wcgs-section-assign_layout .wcgs-cloneable-wrapper .wcgs-cloneable-item').length == 0 && $('#wcgs-section-assign_layout .wcgs-cloneable-add').length > 0) {
      $('#wcgs-section-assign_layout .wcgs-cloneable-add').trigger('click');
    }
  }, 500);
  $(".wcgs-save").on('click', function (e) {
    e.preventDefault();
    $(this).css({ "background-color": "#C5C5C6", "pointer-events": "none", "padding-left": "38px" }).val('Changes Saved');
  })
  if ($('body').hasClass('post-type-wcgs_layouts')) {
    if ($('div').hasClass('wcgs-wrapper')) {
      $('.page-title-action').on('click', function (e) {
        e.preventDefault();
        var postCount = $('#the-list tr.type-wcgs_layouts').length;
        tb_show('', '#TB_inline?&width=440&height=225&inlineId=BuyProPopupContent');
        return;
      });
    }

    var postCount = $('#the-list tr').length;
    if (postCount >= 1 && $('#BuyProPopupContent').length) {
      $('.page-title-action').on('click', function (e) {
        e.preventDefault();
        var postCount = $('#the-list tr.type-wcgs_layouts').length;
        tb_show('', '#TB_inline?&width=440&height=225&inlineId=BuyProPopupContent');
        return;
      });
    }
  }
  var wcgs_offset = 0;
  var batchSize = 50; // Number of variations to migrate in each batch.
  var totalMigratedCount = 0;
  var wcgs_migration_from = '';
  var $wcgs_nonce = $('#wcgs_options_noncewcgs_settings').val();
  function runMigrationBatch() {
    $.post(ajaxurl, {
      action: 'wcgs_run_migration',
      offset: wcgs_offset,
      limit: batchSize,
      plugin: wcgs_migration_from,
      nonce: $wcgs_nonce,
    }, function (response) {
      if (response.success) {
        const batchMigrated = response.data.batch_migrated;
        totalMigratedCount += batchMigrated;
        if (response.data.continue) {
          wcgs_offset += batchSize;
          runMigrationBatch(); // Next batch.
        } else {
          if (totalMigratedCount > 0) {
            $('.' + wcgs_migration_from + ' .wcgs-text-desc').append(`<span>Migrated ${totalMigratedCount} variations successfully!</span>`);
          } else {
            $('.' + wcgs_migration_from + ' .wcgs-text-desc').append(`<span>No variation data found for migration.</span>`).addClass('wcgs-text-desc-no-data');
          }
          $('.' + wcgs_migration_from + ' .wcgs--button').prop('disabled', false).text('Migrate Now').css({ "background-color": "#2271b1", "pointer-events": "default" });
          setTimeout(() => {
            $('.' + wcgs_migration_from + ' .wcgs-text-desc').html('');
          }, 3000);
        }
      } else {
        $('#wcgs-migration-result').html('<span style="color:red;">Migration failed.</span>');
      }
    });
  }
  //.wcgs_migration_from select get selected data

  // wcgs_migration_from on change selected value
  // $('.wcgs_migration_from select').on('change', function () {
  //   wcgs_migration_from = $(this).find('option:selected').val();
  // });
  $('.wcgs_migration_button .wcgs--button').on('click', function (e) {
    e.preventDefault();
    wcgs_migration_from = $(this).find('input').data('depend-id');
    $(this).prop('disabled', true).text('Migrating...').css({ "background-color": "#245a85c9", "pointer-events": "none" });
    wcgs_offset = 0;
    totalMigratedCount = 0;
    runMigrationBatch();
  });


  // Gallery-Slider export.
  var $export_type = $('.wcgs_what_export').find('input:checked').val();
  $('.wcgs_what_export').on('change', function () {
    $export_type = $(this).find('input:checked').val();
  });
  // Check is valid JSON string.
  function isValidJSONString(str) {
    try {
      JSON.parse(str);
    } catch (e) {
      return false;
    }
    return true;
  }

  setTimeout(() => {
    $('.wcgs_export .wcgs-fieldset,.wcgs_import .wcgs-fieldset').each(function () {
      $(this).append('<p class="success-notice"></p>');
    });
  }, 600);

  $('.wcgs_export .wcgs--button').on('click', function (event) {
    event.preventDefault();
    var $layout_ids = $('.wcgs_post_ids select').val();
    var $ex_nonce = $('#wcgs_options_noncewcgs_settings').val();
    var selected_layout = $export_type === 'selected_layouts' ? $layout_ids : 'all_layouts';
    var data = {};
    console.log($export_type);
    if ($export_type === 'all_layouts' || $export_type === 'selected_layouts') {
      data = {
        action: 'wcgs_export_layouts',
        wcgs_ids: selected_layout,
        nonce: $ex_nonce,
      }
    } else if ($export_type === 'global_setting') {
      data = {
        action: 'wcgs_export_layouts',
        wcgs_ids: 'global_settings',
        nonce: $ex_nonce,
      }
    } else {
      $('.wcgs-field-button_set.wcgs_export .wcgs-fieldset p.success-notice').text('No layout selected.').show();
      setTimeout(function () {
        $('.wcgs-field-button_set.wcgs_export .wcgs-fieldset p.success-notice').hide().text('');
      }, 3000);
      return;
    }
    $.post(ajaxurl, data, function (resp) {
      if (resp) {
        // Convert JSON Array to string.
        if (isValidJSONString(resp)) {
          var json = JSON.stringify(JSON.parse(resp));
        } else {
          var json = JSON.stringify(resp);
        }
        // Convert JSON string to BLOB.
        json = [json];
        var blob = new Blob(json);
        var link = document.createElement('a');
        var wcgs_time = $.now();
        link.href = window.URL.createObjectURL(blob);
        link.download = "woo-gallery-export-" + wcgs_time + ".json";
        link.click();
        $('.wcgs-field-button_set.wcgs_export .wcgs-fieldset p.success-notice').text('Exported successfully!').show();
        setTimeout(function () {
          $('.wcgs-field-button_set.wcgs_export .wcgs-fieldset p.success-notice').hide().text('');
          $('.wcgs_post_ids select').val('').trigger('chosen:updated');
        }, 3000);
      }
    });
  });

  // Product Slider import.
  $('.wcgs_import button.import').on('click', function (event) {
    event.preventDefault();
    var $this = $(this);
    var wcgs_shortcodes = $('#import').prop('files')[0];
    if ($('#import').val() != '') {
      var $im_nonce = $('#wcgs_options_noncewcgs_settings').val();
      var reader = new FileReader();
      reader.readAsText(wcgs_shortcodes);
      reader.onload = function (event) {
        var jsonObj = JSON.stringify(event.target.result);
        $this.append('<span class="wcgs-page-loading-spinner"><i class="sp_wgs-icon-spin6" aria-hidden="true"></i></span>');
        $this.css('opacity', '0.7');
        $.ajax({
          url: ajaxurl,
          type: 'POST',
          data: {
            layout: jsonObj,
            action: 'wcgs_import_layouts',
            nonce: $im_nonce,
          },
          success: function (resp) {

            $('.wcgs-field-button_set.wcgs_import .wcgs-fieldset p.success-notice').html('Imported successfully!').show();
            $this.html('Import').css('opacity', '1');
            setTimeout(function () {
              $('.wcgs-field-button_set.wcgs_import .wcgs-fieldset p.success-notice').hide().text('');
              $('#import').val('');
              if (resp.data.import) {
                var cleanUrl = window.location.href.split('#')[0];
                window.location.href = cleanUrl;
              } else {
                window.location.replace($('#wcgs_shortcode_link_redirect').attr('href'));
              }
            }, 2000);
          }
        });
      }
    } else {
      $('.wcgs-field-button_set.wcgs_import .wcgs-fieldset p').text('No exported json file chosen.').show();
      setTimeout(function () {
        $('.wcgs-field-button_set.wcgs_import .wcgs-fieldset p').hide().text('');
      }, 3000);
    }
  });


  /**
   * Handles tracking and restoring the last valid (non-disabled) selection
   * for given input groups (settings + metabox).
   *
   * @param {string} baseName - The base input name without the prefix, e.g., "slider_height_type"
   */
  function handleLastValidInputSelection(baseName) {
    const nameSettings = `wcgs_settings[${baseName}]`;
    const nameMetabox = `wcgs_metabox[${baseName}]`;

    const $settingsInputs = $(`input[name="${nameSettings}"]`);
    const $metaboxInputs = $(`input[name="${nameMetabox}"]`);
    const $allInputs = $settingsInputs.add($metaboxInputs);
    const $submitTriggers = $('#publishing-action #publish, .wcgs-buttons .wcgs-save-ajax');

    let lastValidSettingsValue = $settingsInputs.filter(':checked').val();
    let lastValidMetaboxValue = $metaboxInputs.filter(':checked').val();

    function updateLastValidSelection($input) {
      if (!$input.is(':disabled')) {
        const value = $input.val();
        if ($input.is(`[name="${nameSettings}"]`)) {
          lastValidSettingsValue = value;
        }
        if ($input.is(`[name="${nameMetabox}"]`)) {
          lastValidMetaboxValue = value;
        }
      }
    }

    function revertInvalidSelection() {
      if ($settingsInputs.filter(':checked:disabled').length) {
        $settingsInputs.filter(`[value="${lastValidSettingsValue}"]`).prop('checked', true);
      }

      if ($metaboxInputs.filter(':checked:disabled').length) {
        $metaboxInputs.filter(`[value="${lastValidMetaboxValue}"]`).prop('checked', true);
      }
    }

    // Bind listeners
    $allInputs.on('change', function () {
      updateLastValidSelection($(this));
    });

    $submitTriggers.on('click', revertInvalidSelection);
  }

  handleLastValidInputSelection('video_icon');
  handleLastValidInputSelection('slider_height_type');
  handleLastValidInputSelection('pagination_type');
  // handleLastValidInputSelection('another_field'); // Add as needed

})(jQuery, window, document);
