/*global dotclear */
'use strict';

dotclear.ready(() => {
  // DOM ready

  const href = document.getElementById('href');
  const liok = document.getElementById('link-insert-ok');

  // Enable submit button only if mandotory field is not empty
  if (liok && href) {
    liok.setAttribute('disabled', true);
    liok.classList.add('disabled');
    href?.addEventListener('input', function () {
      liok.setAttribute('disabled', this.value == '');
      liok.classList.toggle('disabled', this.value == '');
    });

    // Set focus on #href input
    href.focus();
  }

  // Deal with enter key on link insert popup form : every form element will be filtered but Cancel button
  dotclear.enterKeyInForm('#link-insert-form', '#link-insert-ok', '#link-insert-cancel');
});
