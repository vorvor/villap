(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.villaPromenadeGallery = {
    attach(context) {
      once('villa-photo-gallery', '.photo-gallery', context).forEach((gallery) => {
        if (typeof HTMLDialogElement === 'undefined' || !HTMLDialogElement.prototype.showModal) {
          return;
        }
        const links = Array.from(gallery.querySelectorAll('.photo-gallery__item'));
        if (!links.length) {
          return;
        }
        const dialog = document.createElement('dialog');
        dialog.className = 'photo-gallery-dialog';
        dialog.setAttribute('aria-label', Drupal.t('Photo gallery'));
        const image = document.createElement('img');
        image.className = 'photo-gallery-dialog__image';
        const caption = document.createElement('p');
        caption.className = 'photo-gallery-dialog__caption';
        caption.setAttribute('aria-live', 'polite');
        const button = (className, label, text) => {
          const element = document.createElement('button');
          element.type = 'button';
          element.className = `photo-gallery-dialog__button ${className}`;
          element.setAttribute('aria-label', label);
          element.textContent = text;
          return element;
        };
        const close = button('photo-gallery-dialog__close', Drupal.t('Close gallery'), '×');
        const previous = button('photo-gallery-dialog__previous', Drupal.t('Previous photo'), '‹');
        const next = button('photo-gallery-dialog__next', Drupal.t('Next photo'), '›');
        dialog.append(image, caption, close, previous, next);
        gallery.append(dialog);
        let index = 0;
        let opener;
        const show = (position) => {
          index = (position + links.length) % links.length;
          const thumbnail = links[index].querySelector('img');
          image.src = links[index].href;
          image.alt = thumbnail ? thumbnail.alt : '';
          caption.textContent = `${index + 1} / ${links.length}${image.alt ? ` — ${image.alt}` : ''}`;
        };
        links.forEach((link, position) => {
          link.addEventListener('click', (event) => {
            if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
              return;
            }
            event.preventDefault();
            opener = link;
            show(position);
            dialog.showModal();
            close.focus();
          });
        });
        close.addEventListener('click', () => dialog.close());
        previous.addEventListener('click', () => show(index - 1));
        next.addEventListener('click', () => show(index + 1));
        dialog.addEventListener('keydown', (event) => {
          if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            show(index + (event.key === 'ArrowLeft' ? -1 : 1));
          }
        });
        dialog.addEventListener('click', (event) => {
          if (event.target === dialog) {
            const bounds = dialog.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) {
              dialog.close();
            }
          }
        });
        dialog.addEventListener('close', () => {
          image.removeAttribute('src');
          if (opener && opener.isConnected) {
            opener.focus();
          }
        });
      });
    },
  };
})(Drupal, once);
