const CAROUSEL = '[data-initiatives-carousel]';
const TRACK = '[data-initiatives-carousel-track]';

function updateControls(carousel) {
  const track = carousel.querySelector(TRACK);
  const controls = carousel.querySelector('[data-initiatives-carousel-controls]');
  if (!track || !controls) {
    return;
  }
  const maxScroll = track.scrollWidth - track.clientWidth;
  controls.hidden = maxScroll <= 1;
  carousel.querySelector('[data-initiatives-carousel-prev]').disabled = track.scrollLeft <= 1;
  carousel.querySelector('[data-initiatives-carousel-next]').disabled = track.scrollLeft >= maxScroll - 1;
}

function updateAll() {
  document.querySelectorAll(CAROUSEL).forEach(updateControls);
}

document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-initiatives-carousel-prev], [data-initiatives-carousel-next]');
  if (!button) {
    return;
  }
  const track = button.closest(CAROUSEL)?.querySelector(TRACK);
  if (!track) {
    return;
  }
  const direction = button.hasAttribute('data-initiatives-carousel-next') ? 1 : -1;
  track.scrollBy({ left: direction * track.clientWidth, behavior: 'smooth' });
});

document.addEventListener('scroll', (event) => {
  if (event.target instanceof Element && event.target.matches(TRACK)) {
    updateControls(event.target.closest(CAROUSEL));
  }
}, true);

window.addEventListener('resize', updateAll);
window.addEventListener('load', updateAll);
document.addEventListener('DOMContentLoaded', () => setTimeout(updateAll, 0));
