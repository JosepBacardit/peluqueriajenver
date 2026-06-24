// Minimal critical functionality: mobile menu, navbar scroll
const mobileMenuBtn = document.getElementById('mobile-menu-btn');
const mobileMenu = document.getElementById('mobile-menu');

if (mobileMenuBtn) {
  mobileMenuBtn.addEventListener('click', () => {
    mobileMenu?.classList.toggle('hidden');
  });
}

const mobileServicesToggle = document.getElementById('mobile-services-toggle');
const mobileServicesMenu = document.getElementById('mobile-services-menu');

if (mobileServicesToggle) {
  mobileServicesToggle.addEventListener('click', () => {
    mobileServicesMenu?.classList.toggle('hidden');
  });
}

if (mobileMenu) {
  mobileMenu.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', () => {
      mobileMenu.classList.add('hidden');
      mobileServicesMenu?.classList.add('hidden');
    });
  });
}

const navbar = document.getElementById('navbar');
window.addEventListener('scroll', () => {
  if (window.scrollY > 100) {
    navbar?.classList.add('shadow-lg', 'shadow-gold/20', 'bg-black/95');
    navbar?.classList.remove('bg-black/90');
  } else {
    navbar?.classList.add('bg-black/90');
    navbar?.classList.remove('shadow-lg', 'shadow-gold/20', 'bg-black/95');
  }
});
