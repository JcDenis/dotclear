/*global $, dotclear */
'use strict';

Object.assign(dotclear.msg, dotclear.getData('ie_msg'));

dotclear.ready(() => {
  // DOM ready and content loaded

  if ($('*.error').length > 0) {
    return;
  }
  $('form[method=post]:has(input[type=hidden][name=autosubmit])').each(function () {
    $('input[type=submit]', this).remove();
    $(this).before(`<p style="font-size:1em;text-align:center">${dotclear.msg.please_wait}</p>`);
    $(this).trigger('submit');
  });
});
