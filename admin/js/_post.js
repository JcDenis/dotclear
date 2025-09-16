/*global $, dotclear */
'use strict';

dotclear.viewCommentContent = (line, _action = 'toggle', e = null) => {
  if ($(line).attr('id') === undefined) {
    return;
  }

  const commentId = $(line).attr('id').substring(1);
  const lineId = `ce${commentId}`;
  let tr = document.getElementById(lineId);

  // If meta key down or it's a spam then display content HTML code
  const clean = e.metaKey || $(line).hasClass('sts-junk');

  if (tr) {
    $(tr).toggle();
    $(line).toggleClass('expand');
  } else {
    // Get comment content
    dotclear.getCommentContent(
      commentId,
      (content) => {
        if (content) {
          // Content found
          tr = document.createElement('tr');
          tr.id = lineId;
          const td = document.createElement('td');
          td.colSpan = $(line).children('td').length;
          td.className = 'expand';
          tr.appendChild(td);
          $(td).append(content);
          $(line).addClass('expand');
          line.parentNode.insertBefore(tr, line.nextSibling);
          return;
        }
        // No content, content not found or server error
        $(line).removeClass('expand');
      },
      {
        ip: false,
        clean,
      },
    );
  }
};

dotclear.ready(() => {
  // DOM ready and content loaded

  // Add today button near publication date entry
  const dtField = document.querySelector('#post_dt');
  const dtTodayHelper = (event) => {
    event.preventDefault();
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    dtField.value = now.toISOString().slice(0, 16);
    dtField.focus();
    dtField.blur();
  };
  const dtTodayButtonTemplate = new DOMParser().parseFromString(
    `<button type="button" class="dt-today" title="${dotclear.msg.set_today}" aria-label="${dotclear.msg.set_today}"><span class="sr-only">${dotclear.msg.set_today}</span></button>`,
    'text/html',
  ).body.firstChild;
  const button = dtTodayButtonTemplate.cloneNode(true);
  dtField.after(button);
  dtField.classList.add('today_helper');
  button.addEventListener('click', dtTodayHelper);

  // Check if entry date or title change
  const post_options = dotclear.getData('post_options');
  const urlTitle = document.querySelector('#post_title');
  if (post_options?.entryurl_dt || urlTitle) {
    const urlEntry = document.querySelector('#post_url');
    const urlStatus = document.querySelector('#post_status');
    if (urlEntry && urlStatus?.value !== '1') {
      // Only not already published entry
      if (post_options?.entryurl_dt) {
        // Date field management
        const askResetDt = (event) => {
          if (event.target.value.slice(0, 10) !== dtValue && urlEntry.value !== '') {
            // Date has changed and entry URL is not empty, ask for reset
            if (window.confirm(dotclear.msg.dtchange_reseturl)) urlEntry.value = '';
          }
        };
        const dtValue = dtField.value.slice(0, 10); // Keep only date (ignoring time)
        dtField.addEventListener('blur', (event) => askResetDt(event));
        dtField.addEventListener('keypress', (/** @type {KeyboardEvent} */ event) => {
          if (event?.key === 'Enter') dtField.blur();
        });
      }
      // Title field management
      if (urlTitle) {
        const askResetTitle = (event) => {
          if (event.target.value !== titleValue && urlEntry.value !== '') {
            // Title has changed and entry URL is not empty, ask for reset
            if (window.confirm(dotclear.msg.titlechange_reseturl)) urlEntry.value = '';
          }
        };
        const titleValue = urlTitle.value;
        urlTitle.addEventListener('blur', (event) => askResetTitle(event));
        urlTitle.addEventListener('keypress', (/** @type {KeyboardEvent} */ event) => {
          if (event?.key === 'Enter') urlTitle.blur();
        });
      }
    }
  }

  // Post preview
  let preview_url = $('#post-preview').attr('href');
  if (preview_url) {
    // Make preview_url absolute
    const $a = document.createElement('a');
    $a.href = $('#post-preview').attr('href');
    preview_url = $a.href;
    const has_modal = $('#post-preview').hasClass('modal');

    // Check if admin and blog have same protocol (ie not mixed-content)
    if (has_modal && window.location.protocol === preview_url.substring(0, window.location.protocol.length)) {
      // Open preview in a modal iframe
      $('#post-preview').magnificPopup({
        type: 'iframe',
        iframe: {
          patterns: {
            dotclear_preview: {
              index: preview_url,
              src: preview_url,
            },
          },
        },
      });
    } else if (has_modal) {
      // If has not modal class, the preview is cope by direct link with target="blank" in HTML
      // Open preview on antother window
      $('#post-preview').on('click', function (e) {
        e.preventDefault();
        window.open($(this).attr('href'));
      });
    }
  }
  // Prevent history back if currently previewing Post (with magnificPopup
  history.pushState(null, null);
  window.addEventListener('popstate', () => {
    if (document.querySelector('.mfp-ready')) {
      // Prevent history back
      history.go(1);
      // Close current preview
      $.magnificPopup.close();
    }
  });

  // Tabs events
  document.getElementById('edit-entry')?.addEventListener('onetabload', () => {
    dotclear.hideLockable();

    // Confirm post deletion
    $('input[name="delete"]').on('click', () => window.confirm(dotclear.msg.confirm_delete_post));

    // Hide some fields
    $('#notes-area label').toggleWithLegend($('#notes-area').children().not('label'), {
      user_pref: 'dcx_post_notes',
      legend_click: true,
      hide: $('#post_notes').val() === '',
    });
    $('#post_lang').parent().children('label').toggleWithLegend($('#post_lang'), {
      user_pref: 'dcx_post_lang',
      legend_click: true,
    });
    $('#post_password')
      .parent()
      .children('label')
      .toggleWithLegend($('#post_password').parent().children().not('label'), {
        user_pref: 'dcx_post_password',
        legend_click: true,
        hide: $('#post_password').val() === '',
      });
    $('#post_status').parent().children('label').toggleWithLegend($('#post_status'), {
      user_pref: 'dcx_post_status',
      legend_click: true,
    });
    $('#post_dt').parent().children('label').toggleWithLegend($('#post_dt').parent().children().not('label'), {
      user_pref: 'dcx_post_dt',
      legend_click: true,
    });
    $('#label_format').toggleWithLegend($('#label_format').parent().children().not('#label_format'), {
      user_pref: 'dcx_post_format',
      legend_click: true,
    });
    $('#label_cat_id').toggleWithLegend($('#label_cat_id').parent().children().not('#label_cat_id'), {
      user_pref: 'dcx_cat_id',
      legend_click: true,
    });
    $('#create_cat').toggleWithLegend($('#create_cat').parent().children().not('#create_cat'), {
      // no cookie on new category as we don't use this every day
      legend_click: true,
    });
    $('#label_comment_tb').toggleWithLegend($('#label_comment_tb').parent().children().not('#label_comment_tb'), {
      user_pref: 'dcx_comment_tb',
      legend_click: true,
    });
    $('#post_url').parent().children('label').toggleWithLegend($('#post_url').parent().children().not('label'), {
      user_pref: 'post_url',
      legend_click: true,
    });
    // We load toolbar on excerpt only when it's ready
    $('#excerpt-area label').toggleWithLegend($('#excerpt-area').children().not('label'), {
      user_pref: 'dcx_post_excerpt',
      legend_click: true,
      hide: $('#post_excerpt').val() === '',
    });

    // Replace attachment remove links by a POST form submit
    $('a.attachment-remove').on('click', function () {
      this.href = '';
      const m_name = $(this).parents('ul').find('li:first>a').attr('title');
      if (window.confirm(dotclear.msg.confirm_remove_attachment.replace('%s', m_name))) {
        const f = $('#attachment-remove-hide').get(0);
        f.elements.media_id.value = this.id.substring(11);
        f.submit();
      }
      return false;
    });
  });

  document.getElementById('comments')?.addEventListener('onetabload', () => {
    dotclear.expandContent({
      line: document.querySelector('#form-comments .comments-list tr:not(.line)'),
      lines: document.querySelectorAll('#form-comments .comments-list tr.line'),
      callback: dotclear.viewCommentContent,
    });
    $('#form-comments .checkboxes-helpers').each(function () {
      dotclear.checkboxesHelpers(this);
    });

    dotclear.commentsActionsHelper();
  });

  document.getElementById('trackbacks')?.addEventListener('onetabload', () => {
    dotclear.expandContent({
      line: document.querySelector('#form-trackbacks .comments-list tr:not(.line)'),
      lines: document.querySelectorAll('#form-trackbacks .comments-list tr.line'),
      callback: dotclear.viewCommentContent,
    });
    $('#form-trackbacks .checkboxes-helpers').each(function () {
      dotclear.checkboxesHelpers(this);
    });

    dotclear.commentsActionsHelper();
  });

  document.getElementById('add-comment')?.addEventListener('onetabload', () => {
    dotclear.commentTb.draw('xhtml');
  });
});
