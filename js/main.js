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

// Contact form dummy submit
const contactForm = document.getElementById('contactForm');
if (contactForm) {
  contactForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const btn = contactForm.querySelector('.contact__btn');
    btn.textContent = 'Elküldve! ✓';
    btn.style.background = '#1b5e20';
    contactForm.reset();
    setTimeout(() => {
      btn.textContent = 'Ingyenes Ajánlatot Kérek';
      btn.style.background = '';
    }, 3000);
  });
}

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
