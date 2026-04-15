/**
 * Stats Counter Block — count-up animation
 */
(function () {
    function animate(el) {
        if (el.dataset.animated) return;
        el.dataset.animated = '1';

        var target   = parseFloat(el.dataset.target) || 0;
        var duration = parseInt(el.dataset.duration, 10) || 2000;
        var suffix   = el.dataset.suffix || '';
        var startTs  = null;

        function step(ts) {
            if (!startTs) startTs = ts;
            var progress = Math.min((ts - startTs) / duration, 1);
            // easeOutExpo
            var eased = progress >= 1 ? 1 : 1 - Math.pow(2, -10 * progress);
            el.textContent = Math.round(eased * target) + suffix;
            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                // Đảm bảo hiển thị đúng giá trị cuối
                el.textContent = target + suffix;
            }
        }

        el.textContent = '0' + suffix; // reset về 0 trước khi animate
        requestAnimationFrame(step);
    }

    function initBlock(block) {
        var trigger  = block.dataset.trigger || 'viewport';
        var counters = block.querySelectorAll('.block-stats-counter__number[data-target]');
        if (!counters.length) return;

        if (trigger === 'immediate' || !('IntersectionObserver' in window)) {
            counters.forEach(animate);
            return;
        }

        var io = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animate(entry.target);
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        counters.forEach(function (el) {
            // Nếu element đã trong viewport khi JS load → animate ngay
            var rect = el.getBoundingClientRect();
            if (rect.top < window.innerHeight && rect.bottom > 0) {
                animate(el);
            } else {
                io.observe(el);
            }
        });
    }

    function init() {
        document.querySelectorAll('.block-stats-counter').forEach(initBlock);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
