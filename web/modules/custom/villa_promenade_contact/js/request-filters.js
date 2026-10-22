/**
 * @file
 * Auto-apply name, email and event filters through the Views AJAX submit.
 */
(function (Drupal, once) {
  const forms = new WeakMap();
  const filterDrafts = new Map();

  Drupal.behaviors.villaRequestFilters = {
    attach(context) {
      once('villa-request-filters', 'form[data-request-filters]', context).forEach((form) => {
        let timer;
        const textInputs = Array.from(form.querySelectorAll('input[name="name"], input[name="email"]'));
        const rememberInputs = () => {
          filterDrafts.set(form.id, textInputs.map((input) => ({
            name: input.name,
            value: input.value,
            start: input.selectionStart,
            end: input.selectionEnd,
            focused: document.activeElement === input,
          })));
        };
        const applyFilters = () => {
          if (!form.isConnected) {
            return;
          }
          // Use the native exposed-filter submit, never the VBO action form.
          // Core handles AJAX, other filter values, paging and URL updates.
          const submit = form.querySelector('[data-drupal-selector^="edit-submit-villa-customer-requests"]');
          if (submit && submit.disabled) {
            timer = window.setTimeout(applyFilters, 100);
          }
          else if (submit) {
            rememberInputs();
            // Do not move typing focus to the Filter button after AJAX.
            submit.setAttribute('data-disable-refocus', 'true');
            submit.click();
          }
        };
        const schedule = (delay) => {
          window.clearTimeout(timer);
          timer = window.setTimeout(applyFilters, delay);
        };
        const onTextInput = (event) => {
          if (textInputs.includes(event.target)) {
            window.clearTimeout(timer);
            if (!event.isComposing) {
              schedule(350);
            }
          }
        };
        const onChange = (event) => {
          if (!event.target.matches('input[type="checkbox"]') ||
              !event.target.closest('[data-request-event-filter]')) {
            return;
          }
          schedule(150);
        };
        const drafts = filterDrafts.get(form.id);
        if (drafts) {
          // Keep characters typed while the preceding request was in flight.
          let changed = false;
          drafts.forEach((draft) => {
            const input = textInputs.find((field) => field.name === draft.name);
            if (!input) {
              return;
            }
            changed = changed || input.value !== draft.value;
            input.value = draft.value;
            if (draft.focused) {
              input.focus({ preventScroll: true });
              if (draft.start !== null && draft.end !== null) {
                input.setSelectionRange(draft.start, draft.end);
              }
            }
          });
          filterDrafts.delete(form.id);
          if (changed) {
            schedule(350);
          }
        }
        form.addEventListener('input', onTextInput);
        form.addEventListener('compositionend', onTextInput);
        form.addEventListener('change', onChange);
        forms.set(form, () => {
          if (filterDrafts.has(form.id)) {
            rememberInputs();
          }
          window.clearTimeout(timer);
          form.removeEventListener('input', onTextInput);
          form.removeEventListener('compositionend', onTextInput);
          form.removeEventListener('change', onChange);
        });
      });
    },

    detach(context, settings, trigger) {
      if (trigger === 'unload') {
        once.remove('villa-request-filters', 'form[data-request-filters]', context).forEach((form) => {
          const cleanup = forms.get(form);
          if (cleanup) {
            cleanup();
            forms.delete(form);
          }
        });
      }
    },
  };
})(Drupal, once);
