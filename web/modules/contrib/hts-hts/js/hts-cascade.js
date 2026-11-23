/**
 * @file
 * Keeps HTS exposed filter child selectors in sync with parent selections.
 */
(function (Drupal, drupalSettings, once) {
  Drupal.behaviors.htsCascade = {
    attach(context) {
      const configs = (drupalSettings.hts && drupalSettings.hts.cascade) || {};

      once('hts-cascade', '.hts-container[data-hts-cascade]', context).forEach(
        (container) => {
          const key = container.getAttribute('data-hts-cascade');
          let config = configs[key];
          if (!config && container.dataset.htsConfig) {
            try {
              config = JSON.parse(container.dataset.htsConfig);
            } catch (e) {
              return;
            }
          }
          if (!config) {
            return;
          }

          const selects = Array.from(container.querySelectorAll('select'));
          const autocomplete = document.getElementById(config.autocomplete);
          const noneValue = config.noneValue || '_none';

          const setOptions = (select, options, selectedValue) => {
            select.innerHTML = '';
            options.forEach((option) => {
              const element = document.createElement('option');
              element.value = option.value;
              element.textContent = option.label;
              if (String(option.value) === String(selectedValue)) {
                element.selected = true;
              }
              select.appendChild(element);
            });
          };

          const optionsFor = (level, parentValue) => {
            const levelOptions = config.options[String(level)] || {};
            return (
              levelOptions[String(parentValue)] || levelOptions[noneValue] || []
            );
          };

          const updateAutocomplete = () => {
            if (!autocomplete) {
              return;
            }
            let selected = '';
            selects.forEach((select) => {
              if (select.value && select.value !== noneValue) {
                selected = select.value;
              }
            });
            autocomplete.value =
              selected && config.labels[selected]
                ? `${config.labels[selected]} (${selected})`
                : '';
          };

          const resetDescendants = (changedLevel) => {
            for (
              let level = changedLevel + 1;
              level < selects.length;
              level++
            ) {
              const parentValue = selects[level - 1].value;
              const hasParent = parentValue && parentValue !== noneValue;
              const options = hasParent
                ? optionsFor(level, parentValue)
                : optionsFor(level, noneValue);
              setOptions(selects[level], options, noneValue);
              selects[level].disabled = !hasParent;
            }
            updateAutocomplete();
          };

          selects.forEach((select, level) => {
            select.addEventListener('change', () => resetDescendants(level));
          });
        },
      );
    },
  };
})(Drupal, drupalSettings, once);
