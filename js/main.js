const header = document.getElementById('header');
const hero   = document.getElementById('hero');

function updateHeader() {
  if (window.scrollY > (hero ? hero.offsetHeight - 10 : 50)) {
    header.classList.remove('header--transparent');
    header.classList.add('header--scrolled');
  } else {
    header.classList.remove('header--scrolled');
    header.classList.add('header--transparent');
  }
}

window.addEventListener('scroll', updateHeader, { passive: true });
updateHeader();

// Services carousel dot indicators
const servicesCarousel = document.getElementById('servicesCarousel');
const servicesDots = document.querySelectorAll('.services__dot');
if (servicesCarousel && servicesDots.length) {
  servicesCarousel.addEventListener('scroll', () => {
    const cardWidth = servicesCarousel.querySelector('.services__card').offsetWidth + 16;
    const index = Math.min(Math.round(servicesCarousel.scrollLeft / cardWidth), servicesDots.length - 1);
    servicesDots.forEach((dot, i) => dot.classList.toggle('services__dot--active', i === index));
  }, { passive: true });
}

// Reviews carousel dot indicators
const reviewsCarousel = document.getElementById('reviewsCarousel');
const reviewsDots = document.querySelectorAll('.reviews__dot');
if (reviewsCarousel && reviewsDots.length) {
  reviewsCarousel.addEventListener('scroll', () => {
    const cardWidth = reviewsCarousel.querySelector('.reviews__card').offsetWidth + 16;
    const index = Math.min(Math.round(reviewsCarousel.scrollLeft / cardWidth), reviewsDots.length - 1);
    reviewsDots.forEach((dot, i) => dot.classList.toggle('reviews__dot--active', i === index));
  }, { passive: true });
}

// Contact form status handling
const contactForm = document.getElementById('contactForm');
if (contactForm) {
  // Check URL for status
  const urlParams = new URLSearchParams(window.location.search);
  const status = urlParams.get('status');
  const btn = contactForm.querySelector('button[type="submit"]');

  if (status === 'success') {
    btn.textContent = 'Elküldve! ✓';
    btn.style.background = '#1b5e20';
    // Clear status from URL after 3 seconds
    setTimeout(() => {
      window.history.replaceState({}, document.title, window.location.pathname + '#contact');
      btn.textContent = 'Ingyenes felmérést kérek';
      btn.style.background = '';
    }, 5000);
  } else if (status === 'error') {
    btn.textContent = 'Hiba történt! ✕';
    btn.style.background = '#b71c1c';
    setTimeout(() => btn.textContent = 'Ingyenes felmérést kérek', 5000);
  }
}

// Force scroll to top on load to prevent jumping to form
window.onload = () => {
  if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
  }
  window.scrollTo(0, 0);
};

// Handle broken images — show placeholder block
document.querySelectorAll('img').forEach(img => {
  img.addEventListener('error', function () {
    this.style.display = 'none';
    const parent = this.parentElement;
    if (parent) {
      parent.style.background = '#222';
      parent.style.minHeight = '120px';
    }
  });
});
