(function (Drupal, once) {
  'use strict';

  const selector = '.gallery-sections, .about-team';
  const cleanups = new WeakMap();
  const properties = ['--botanical-texture-y', '--botanical-left-y', '--botanical-right-y'];

  Drupal.behaviors.villaLeafParallax = {
    attach(context) {
      once('villa-leaf-parallax', selector, context).forEach((section) => {
        const layers = document.createElement('div');
        layers.className = 'botanical-layers';
        layers.setAttribute('aria-hidden', 'true');
        ['texture', 'left', 'right'].forEach((name) => {
          const layer = document.createElement('div');
          layer.className = `botanical-layers__${name}`;
          layers.append(layer);
        });
        section.prepend(layers);
        section.classList.add('botanical-section');

        const controller = new AbortController();
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let visible = false;
        let frame = 0;

        const update = () => {
          frame = 0;
          if (reducedMotion.matches) {
            properties.forEach((property) => section.style.removeProperty(property));
            return;
          }
          if (!visible) {
            return;
          }
          const bounds = section.getBoundingClientRect();
          const viewport = document.documentElement.clientHeight;
          const progress = Math.max(-1, Math.min(1,
            (viewport / 2 - bounds.top - bounds.height / 2) / ((viewport + bounds.height) / 2)));
          const strength = window.innerWidth < 768 ? 0.45 : 1;
          // Counter-drift the texture against faster leaves for stronger depth.
          [-60, 320, 230].forEach((distance, index) => {
            section.style.setProperty(properties[index], `${progress * distance * strength}px`);
          });
        };
        const schedule = () => {
          if (reducedMotion.matches) {
            properties.forEach((property) => section.style.removeProperty(property));
            return;
          }
          if (!frame && visible) {
            frame = window.requestAnimationFrame(update);
          }
        };
        const observer = new IntersectionObserver(([entry]) => {
          visible = entry.isIntersecting;
          schedule();
        }, { rootMargin: '100px' });
        observer.observe(section);
        const resizeObserver = new ResizeObserver(schedule);
        resizeObserver.observe(section);
        window.addEventListener('scroll', schedule, { passive: true, signal: controller.signal });
        window.addEventListener('resize', schedule, { passive: true, signal: controller.signal });
        reducedMotion.addEventListener('change', schedule, { signal: controller.signal });

        cleanups.set(section, () => {
          controller.abort();
          observer.disconnect();
          resizeObserver.disconnect();
          window.cancelAnimationFrame(frame);
          properties.forEach((property) => section.style.removeProperty(property));
          section.classList.remove('botanical-section');
          layers.remove();
        });
      });
    },
    detach(context, settings, trigger) {
      if (trigger === 'unload') {
        once.remove('villa-leaf-parallax', selector, context).forEach((section) => {
          cleanups.get(section)?.();
          cleanups.delete(section);
        });
      }
    },
  };
})(Drupal, once);
