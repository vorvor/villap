(function (Drupal, once) {
  'use strict';

  const cleanups = new WeakMap();

  Drupal.behaviors.villaPromenadeNavigation = {
    attach(context) {
      once('villa-front-navigation', '.front-hero__navigation', context).forEach((navigation) => {
        let frame = null;
        const update = () => {
          frame = null;
          navigation.classList.toggle('is-scrolled', window.scrollY > 0);
        };
        const scroll = () => {
          if (frame === null) {
            frame = window.requestAnimationFrame(update);
          }
        };
        const measure = () => {
          document.documentElement.style.setProperty(
            '--villa-navigation-height',
            `${navigation.getBoundingClientRect().height}px`,
          );
        };
        const observer = new ResizeObserver(measure);
        observer.observe(navigation);
        window.addEventListener('scroll', scroll, { passive: true });
        measure();
        update();

        cleanups.set(navigation, () => {
          observer.disconnect();
          window.removeEventListener('scroll', scroll);
          if (frame !== null) {
            window.cancelAnimationFrame(frame);
          }
          navigation.classList.remove('is-scrolled');
          document.documentElement.style.removeProperty('--villa-navigation-height');
        });
      });
    },
    detach(context, settings, trigger) {
      if (trigger === 'unload') {
        once.remove('villa-front-navigation', '.front-hero__navigation', context).forEach((navigation) => {
          const cleanup = cleanups.get(navigation);
          if (cleanup) {
            cleanup();
            cleanups.delete(navigation);
          }
        });
      }
    },
  };

  Drupal.behaviors.villaPromenadeHero = {
    attach(context) {
      once('villa-front-hero', '.front-hero', context).forEach((hero) => {
        const enabled = window.matchMedia(
          '(hover: hover) and (pointer: fine) and (prefers-reduced-motion: no-preference)',
        );
        let frame = null;
        let pointerX = 0;
        let pointerY = 0;

        const reset = () => {
          if (frame !== null) {
            window.cancelAnimationFrame(frame);
            frame = null;
          }
          hero.style.setProperty('--hero-shift-x', '0px');
          hero.style.setProperty('--hero-shift-y', '0px');
          hero.style.setProperty('--hero-glow-opacity', '0');
        };

        const update = () => {
          frame = null;
          if (!enabled.matches) {
            reset();
            return;
          }
          const bounds = hero.getBoundingClientRect();
          if (!bounds.width || !bounds.height) {
            return;
          }
          const x = Math.max(0, Math.min(1, (pointerX - bounds.left) / bounds.width));
          const y = Math.max(0, Math.min(1, (pointerY - bounds.top) / bounds.height));
          hero.style.setProperty('--hero-shift-x', `${(x - 0.5) * 20}px`);
          hero.style.setProperty('--hero-shift-y', `${(y - 0.5) * 16}px`);
          hero.style.setProperty('--hero-glow-x', `${x * 100}%`);
          hero.style.setProperty('--hero-glow-y', `${y * 100}%`);
          hero.style.setProperty('--hero-glow-opacity', '1');
        };

        const move = (event) => {
          if (!enabled.matches || event.pointerType === 'touch') {
            return;
          }
          pointerX = event.clientX;
          pointerY = event.clientY;
          if (frame === null) {
            frame = window.requestAnimationFrame(update);
          }
        };

        hero.addEventListener('pointerenter', move, { passive: true });
        hero.addEventListener('pointermove', move, { passive: true });
        hero.addEventListener('pointerleave', reset);
        hero.addEventListener('pointercancel', reset);
        enabled.addEventListener('change', reset);
        window.addEventListener('blur', reset);

        cleanups.set(hero, () => {
          reset();
          hero.removeEventListener('pointerenter', move);
          hero.removeEventListener('pointermove', move);
          hero.removeEventListener('pointerleave', reset);
          hero.removeEventListener('pointercancel', reset);
          enabled.removeEventListener('change', reset);
          window.removeEventListener('blur', reset);
        });
      });
    },
    detach(context, settings, trigger) {
      if (trigger === 'unload') {
        once.remove('villa-front-hero', '.front-hero', context).forEach((hero) => {
          const cleanup = cleanups.get(hero);
          if (cleanup) {
            cleanup();
            cleanups.delete(hero);
          }
        });
      }
    },
  };
})(Drupal, once);
