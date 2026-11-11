(function (Drupal, once) {
  'use strict';

  const cleanups = new WeakMap();

  Drupal.behaviors.villaImageTextGallery = {
    attach(context) {
      once('villa-image-text-gallery', '.image-text-block--gallery', context).forEach((gallery) => {
        const slides = Array.from(gallery.querySelectorAll('.image-text-block__image, .image-text-block__gallery-item'));
        if (slides.length < 2) {
          return;
        }

        const controller = new AbortController();
        const mobile = window.matchMedia('(max-width: 63.99rem)');
        const pager = document.createElement('div');
        pager.className = 'image-text-block__pager';
        pager.setAttribute('role', 'group');
        pager.setAttribute('aria-label', Drupal.t('Choose a photo'));
        const status = document.createElement('span');
        status.className = 'visually-hidden';
        status.setAttribute('aria-live', 'polite');
        status.setAttribute('aria-atomic', 'true');
        let current = 0;
        const dots = slides.map((slide, index) => {
          const dot = document.createElement('button');
          dot.type = 'button';
          dot.className = 'image-text-block__dot';
          dot.setAttribute('aria-label', Drupal.t('Show photo @number of @total', {
            '@number': index + 1,
            '@total': slides.length,
          }));
          dot.addEventListener('click', () => show(index), { signal: controller.signal });
          pager.append(dot);
          return dot;
        });

        const show = (index) => {
          current = (index + slides.length) % slides.length;
          slides.forEach((slide, position) => {
            slide.hidden = mobile.matches && position !== current;
            dots[position].setAttribute('aria-current', position === current ? 'true' : 'false');
          });
          status.textContent = mobile.matches ? Drupal.t('Photo @number of @total', {
            '@number': current + 1,
            '@total': slides.length,
          }) : '';
        };

        pager.addEventListener('keydown', (event) => {
          let next;
          if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            next = current + (event.key === 'ArrowLeft' ? -1 : 1);
          }
          else if (event.key === 'Home' || event.key === 'End') {
            next = event.key === 'Home' ? 0 : slides.length - 1;
          }
          else {
            return;
          }
          event.preventDefault();
          show(next);
          dots[current].focus();
        }, { signal: controller.signal });

        const updateLayout = () => {
          show(current);
          status.hidden = !mobile.matches;
        };
        mobile.addEventListener('change', updateLayout, { signal: controller.signal });
        updateLayout();
        gallery.querySelector('.image-text-block__inner').append(pager, status);
        cleanups.set(gallery, () => {
          controller.abort();
          pager.remove();
          status.remove();
          slides.forEach((slide) => { slide.hidden = false; });
        });
      });
    },
    detach(context, settings, trigger) {
      if (trigger === 'unload') {
        once.remove('villa-image-text-gallery', '.image-text-block--gallery', context).forEach((gallery) => {
          if (cleanups.has(gallery)) {
            cleanups.get(gallery)();
            cleanups.delete(gallery);
          }
        });
      }
    },
  };
})(Drupal, once);
