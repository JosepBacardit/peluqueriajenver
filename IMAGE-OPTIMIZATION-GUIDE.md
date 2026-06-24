# Image Optimization Guide - Peluquería Jenver

## 📊 Current Status
- **PageSpeed Score (Mobile):** 63 → Target: 85+
- **LCP (Largest Contentful Paint):** 15.4s → Target: <2.5s
- **Main Issue:** Large unoptimized images

---

## 🚀 Optimization Implemented

### 1. **Lazy Loading**
- ✅ Added `loading="lazy"` to all images
- ✅ Added `decoding="async"` for non-blocking render
- ✅ Gallery images reduced from 800px to 600px width

### 2. **Component for Optimized Images**
**File:** `resources/views/components/optimized-image.blade.php`

**Usage:**
```blade
<x-optimized-image
    src="images/hero.png"
    alt="Hero image"
    lazy
    preload
    class="w-full h-full"
    width="800"
    height="600"
/>
```

**Features:**
- Automatic WebP conversion
- Fallback to original format
- Lazy loading by default
- Optional preload for hero images
- Async decoding

### 3. **Server-Side Optimization**
- Caching middleware (already implemented)
- GZIP compression (already implemented)
- 1-year cache for images (already implemented)

---

## 🔧 How to Optimize Your Images

### Method 1: Automatic Script
```bash
# Make script executable
chmod +x optimize-images.sh

# Run optimization (requires ImageMagick)
./optimize-images.sh
```

**Requirements:**
- Linux/macOS: ImageMagick (`brew install imagemagick`)
- Windows: Download from https://imagemagick.org

### Method 2: Online Tools
Use free online WebP converters:
- https://convertio.co/png-webp/
- https://ilovepdf.com/image-to-webp
- https://tinypng.com/

### Method 3: Manual with ImageMagick
```bash
# Convert PNG to WebP (quality 80)
convert image.png -quality 80 image.webp

# Convert JPG to WebP
convert image.jpg -quality 80 image.webp

# Batch convert
for file in *.png; do convert "$file" -quality 80 "${file%.png}.webp"; done
```

---

## 📁 Images to Optimize

**High Priority (Hero Images):**
- `public/images/color-y-mechas-profesionales.png` → 63px hero
- `public/images/corte-y-tratamientos.png` → 63px hero
- `public/images/peinado-boda.png` → 63px hero
- `public/images/belleza-y-estética.png` → 63px hero
- `public/images/tu-pelo-en-buenas-manos.png` → Hero (home)

**Medium Priority (Content Images):**
- `public/images/diagnostico-gratuito-personalizado.png` → 63px (home)
- `public/images/logo-jenver.png` → Multiple uses

**Lower Priority (Already Optimized):**
- Gallery images (using Unsplash WebP with smaller dimensions)

---

## 📈 Expected Results After Optimization

### Image Compression Benefits
| Image | Current | After WebP | Savings |
|-------|---------|-----------|---------|
| hero.png (500KB) | 500KB | 50KB | **90%** |
| diagnostic.png (300KB) | 300KB | 30KB | **90%** |
| color-service.png (400KB) | 400KB | 40KB | **90%** |

### PageSpeed Impact
| Metric | Current | Expected |
|--------|---------|----------|
| **Performance Score** | 63 | 80-85 |
| **LCP** | 15.4s | <3s |
| **FCP** | 4.0s | <2s |
| **Overall Load Time** | ~5s | ~2s |

---

## ✅ Implementation Checklist

### Images Already Optimized:
- [x] Gallery images (Unsplash, WebP format)
- [x] Lazy loading added to all images
- [x] Decoding="async" for non-blocking render
- [x] Component for future images

### Images Pending Optimization:
- [ ] `color-y-mechas-profesionales.png` → Convert to WebP
- [ ] `corte-y-tratamientos.png` → Convert to WebP
- [ ] `peinado-boda.png` → Convert to WebP
- [ ] `belleza-y-estética.png` → Convert to WebP
- [ ] `tu-pelo-en-buenas-manos.png` → Convert to WebP
- [ ] `diagnostico-gratuito-personalizado.png` → Convert to WebP
- [ ] `logo-jenver.png` → Keep PNG (vector/transparent logo)

### Post-Conversion:
- [ ] Update blade files to use optimized-image component
- [ ] Test in PageSpeed Insights
- [ ] Verify no visual quality loss
- [ ] Remove old PNG/JPG files (optional, keep as backup)

---

## 🎯 Update Blade Files to Use Optimized Images

### Current Implementation:
```blade
<img src="/images/hero.png" alt="Hero" loading="lazy">
```

### Updated Implementation:
```blade
<x-optimized-image
    src="images/hero.png"
    alt="Hero"
    preload
    lazy
    class="w-full h-full"
/>
```

### Files to Update:
1. `resources/views/pages/home.blade.php`
2. `resources/views/pages/services/color_mechas.blade.php`
3. `resources/views/pages/services/corte_tratamientos.blade.php`
4. `resources/views/pages/services/peinado_eventos.blade.php`
5. `resources/views/pages/services/belleza_estetica.blade.php`
6. `resources/views/layouts/app.blade.php` (logo)

---

## 🔍 Quality Control

After converting to WebP:
1. Visually compare original vs converted image
2. Ensure no quality loss at normal viewing distance
3. Test on mobile and desktop
4. Verify color accuracy for photography

### Quality Settings:
- **Photos & Complex Images:** Quality 75-85
- **Screenshots & Detailed Images:** Quality 85-90
- **Icons & Logos:** Quality 90-95

---

## 📚 Additional Resources

- WebP Format Guide: https://developers.google.com/speed/webp
- ImageMagick Documentation: https://imagemagick.org/
- Google PageSpeed Tips: https://web.dev/performance/
- Lazy Loading Guide: https://web.dev/lazy-loading/

---

## 🚀 Next Steps

1. **Convert your images to WebP** (using script or online tools)
2. **Test in PageSpeed Insights** to verify improvements
3. **Update blade files** to use optimized-image component
4. **Monitor performance metrics** over 2-4 weeks
5. **Gather user feedback** on image quality and load times

**Expected timeline to 85+ score:** 1-2 weeks after WebP conversion
