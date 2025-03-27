if (document.getElementById('page-mod-website-site') != null) {
var isMobile = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

document.addEventListener('DOMContentLoaded', function() {
    if (isMobile) {
      // 1. Force scroll properties
      const fixIframeScroll = function() {
        document.querySelectorAll('iframe').forEach(iframe => {
          iframe.style.touchAction = 'pan-y';
          iframe.style.webkitOverflowScrolling = 'touch';

          // 2. Ensure correct dimensions for iframe
          iframe.style.width = '100%';
          iframe.style.minHeight = '50vh';
        });
      };

    // 3. Apply immediately and every second in case of resizing
      fixIframeScroll();
      setInterval(fixIframeScroll, 1000);

    // 4. Handle touch events directly
      document.querySelectorAll('iframe').forEach(iframe => {
        iframe.addEventListener('touchstart', function() {
          this.style.pointerEvents = 'auto';
        });

        iframe.addEventListener('touchend', function() {
          this.style.pointerEvents = 'none';
        });
      });
      document.body.style.overflow = 'auto';
      document.documentElement.style.overflow = 'auto';
    }
  });
}