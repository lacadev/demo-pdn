import AOS from 'aos';
import 'aos/dist/aos.css';

const initAos = () => {
  AOS.init({
    duration: 700,
    easing: 'ease-out-cubic',
    once: true,
    offset: 80,
    disable: () => window.matchMedia('(prefers-reduced-motion: reduce)').matches,
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAos);
} else {
  initAos();
}
