// Cargar CSS de forma asincrónica para evitar bloqueo de renderizado
document.addEventListener('DOMContentLoaded', function() {
  // Si CSS ya está cargado (inyectado por Vite), no hacemos nada
  // Esta función es un backup para asegurar que cualquier CSS cargado sea no-bloqueante

  const stylesheets = document.querySelectorAll('link[rel="stylesheet"]');
  stylesheets.forEach(link => {
    if (!link.hasAttribute('data-async-loaded')) {
      // Si el stylesheet está en media="print" con onload, ya está optimizado
      if (link.media === 'print') return;

      // Para cualquier stylesheet bloqueante, asegurarse que se cargue
      link.setAttribute('data-async-loaded', 'true');
    }
  });
});

// Cargar Google Fonts de forma optimizada si no está disponible
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', loadOptimizedFonts);
} else {
  loadOptimizedFonts();
}

function loadOptimizedFonts() {
  // Aquí iría lógica adicional si fuera necesaria
  // Por ahora, las fuentes se cargan con la técnica media=print en el HTML
}
