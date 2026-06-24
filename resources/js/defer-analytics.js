// Cargar Google Analytics después de que la página esté completamente renderizada
// Esto evita reflows forzados durante el renderizado inicial

window.addEventListener('load', function() {
  // Google Analytics
  const script = document.createElement('script');
  script.async = true;
  script.src = 'https://www.googletagmanager.com/gtag/js?id=G-EX4HPXH0WV';
  document.head.appendChild(script);

  // Inicializar gtag después de que se cargue
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-EX4HPXH0WV');
});
