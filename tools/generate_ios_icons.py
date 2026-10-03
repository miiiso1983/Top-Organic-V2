#!/usr/bin/env python3
"""Generate iOS app icon set from a source image."""
import json
import os
from pathlib import Path
from PIL import Image

SOURCE = Path(__file__).parent / 'app_icon_source.png'
ICONSET = Path('/Users/mustafaaljaf/restaurant-system/flutter_app/ios/Runner/Assets.xcassets/AppIcon.appiconset')
CONTENTS = ICONSET / 'Contents.json'

# Base point size -> scales -> pixel size and filename pattern
ICONS = [
    (20,  [2, 3],     'iphone', 'Icon-App-20x20'),
    (29,  [1, 2, 3],  'iphone', 'Icon-App-29x29'),
    (40,  [2, 3],     'iphone', 'Icon-App-40x40'),
    (57,  [1, 2],     'iphone', 'Icon-App-57x57'),
    (60,  [2, 3],     'iphone', 'Icon-App-60x60'),
    (20,  [1, 2],     'ipad',   'Icon-App-20x20'),
    (29,  [1, 2],     'ipad',   'Icon-App-29x29'),
    (40,  [1, 2],     'ipad',   'Icon-App-40x40'),
    (50,  [1, 2],     'ipad',   'Icon-App-50x50'),
    (72,  [1, 2],     'ipad',   'Icon-App-72x72'),
    (76,  [1, 2],     'ipad',   'Icon-App-76x76'),
    (83.5, [2],       'ipad',   'Icon-App-83.5x83.5'),
    (1024, [1],        'ios-marketing', 'Icon-App-1024x1024'),
]

def prepare_source(src: Image.Image) -> Image.Image:
    # Square crop centered
    w, h = src.size
    if w != h:
        min_dim = min(w, h)
        left = (w - min_dim) // 2
        top = (h - min_dim) // 2
        src = src.crop((left, top, left + min_dim, top + min_dim))
    # App Store icons must not have transparency; add white background
    if src.mode == 'RGBA':
        bg = Image.new('RGBA', src.size, (255, 255, 255, 255))
        src = Image.alpha_composite(bg, src)
    return src.convert('RGB')

def resize(src: Image.Image, size: int) -> Image.Image:
    return src.resize((size, size), Image.Resampling.LANCZOS)

def main():
    if not SOURCE.exists():
        print(f'Source image not found: {SOURCE}')
        return 1

    src = prepare_source(Image.open(SOURCE))

    # Remove existing icon PNGs (keep Contents.json)
    for f in ICONSET.glob('*.png'):
        f.unlink()

    contents = {'images': [], 'info': {'version': 1, 'author': 'xcode'}}
    for point_size, scales, idiom, name in ICONS:
        for scale in scales:
            px = int(point_size * scale)
            filename = f'{name}@{scale}x.png'
            img = resize(src, px)
            img.save(ICONSET / filename, 'PNG')
            contents['images'].append({
                'size': f'{point_size}x{point_size}' if point_size != 83.5 else '83.5x83.5',
                'idiom': idiom,
                'filename': filename,
                'scale': f'{scale}x',
            })

    with open(CONTENTS, 'w') as f:
        json.dump(contents, f, indent=2)

    print(f'Generated {len(contents["images"])} icons in {ICONSET}')
    return 0

if __name__ == '__main__':
    raise SystemExit(main())
