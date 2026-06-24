// Detect WebP support and replace background images
document.addEventListener('DOMContentLoaded', function() {
  // Check WebP support
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d');
  canvas.width = canvas.height = 1;

  const supportsWebP = canvas.toDataURL('image/webp').indexOf('webp') === 5;

  if (supportsWebP) {
    // Replace background images
    const elementsWithBg = document.querySelectorAll('[style*="background-image"]');
    elementsWithBg.forEach(element => {
      const style = element.getAttribute('style');
      if (style && style.includes('.png')) {
        const newStyle = style.replace(/\.png/g, '.webp');
        element.setAttribute('style', newStyle);
      }
    });

    // Replace src attributes for img tags
    const images = document.querySelectorAll('img[src$=".png"]');
    images.forEach(img => {
      const newSrc = img.src.replace(/\.png$/, '.webp');
      img.srcset = newSrc;
    });
  }
});
