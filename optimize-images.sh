#!/bin/bash

# Image Optimization Script for Peluquería Jenver
# Converts PNG/JPG to WebP and compresses

IMAGES_DIR="public/images"
QUALITY=80

echo "🖼️  Starting image optimization..."
echo "Directory: $IMAGES_DIR"
echo "Target Quality: $QUALITY"
echo ""

# Check if ImageMagick is installed
if ! command -v convert &> /dev/null; then
    echo "❌ ImageMagick not found. Install with:"
    echo "   Ubuntu/Debian: sudo apt-get install imagemagick"
    echo "   macOS: brew install imagemagick"
    echo "   Windows: Download from https://imagemagick.org/script/download.php"
    exit 1
fi

# Convert PNG to WebP
echo "📦 Converting PNG to WebP..."
for file in "$IMAGES_DIR"/*.png; do
    if [ -f "$file" ]; then
        filename=$(basename "$file" .png)
        output="$IMAGES_DIR/$filename.webp"

        echo "   Converting: $filename.png → $filename.webp"
        convert "$file" -quality $QUALITY "$output"

        # Show size reduction
        original_size=$(du -h "$file" | cut -f1)
        new_size=$(du -h "$output" | cut -f1)
        echo "   Size: $original_size → $new_size"
    fi
done

# Convert JPG to WebP
echo ""
echo "📦 Converting JPG to WebP..."
for file in "$IMAGES_DIR"/*.jpg "$IMAGES_DIR"/*.jpeg; do
    if [ -f "$file" ]; then
        filename=$(basename "$file" .jpg)
        filename=$(basename "$filename" .jpeg)
        output="$IMAGES_DIR/$filename.webp"

        echo "   Converting: $filename → $filename.webp"
        convert "$file" -quality $QUALITY "$output"

        # Show size reduction
        original_size=$(du -h "$file" | cut -f1)
        new_size=$(du -h "$output" | cut -f1)
        echo "   Size: $original_size → $new_size"
    fi
done

echo ""
echo "✅ Image optimization complete!"
echo ""
echo "📝 Next steps:"
echo "1. Update image references in your blade files to use the optimized-image component"
echo "2. Example: <x-optimized-image src=\"images/hero.png\" alt=\"Hero\" lazy preload />"
echo "3. Keep original PNG/JPG files as backup"
echo "4. Test on PageSpeed Insights again"
