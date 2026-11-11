from pathlib import Path
import json
from PIL import Image, ImageDraw, ImageFont

root = Path(__file__).resolve().parent
source = root / 'comparison-captures'
parts = root / 'comparison-sections'
parts.mkdir(exist_ok=True)
metadata = {name: json.loads((source / f'{name}.json').read_text()) for name in ('desktop', 'mobile')}
assert [b['label'] for b in metadata['desktop']['blocks']] == [b['label'] for b in metadata['mobile']['blocks']]
images = {}
for name, data in metadata.items():
    image = Image.new('RGB', (data['width'], data['height']))
    covered = 0
    for tile in data['tiles']:
        shot = Image.open(source / tile['file']).convert('RGB')
        assert shot.width == data['width']
        assert tile['y'] <= covered, 'Gap between tiles'
        image.paste(shot, (0, tile['y']))
        covered = max(covered, tile['y'] + shot.height)
    assert covered >= data['height']
    images[name] = image

fontfile = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
font = ImageFont.truetype(fontfile, 21)
small = ImageFont.truetype(fontfile, 16)
big = ImageFont.truetype(fontfile, 29)
rows = []
for i, (desktop, mobile) in enumerate(zip(metadata['desktop']['blocks'], metadata['mobile']['blocks']), 1):
    pair = []
    for name, block in [('desktop', desktop), ('mobile', mobile)]:
        crop = images[name].crop((0, block['y'], images[name].width, block['end']))
        assert crop.height > 0
        crop.save(parts / f'{i:02d}-{name}.png')
        if name == 'desktop':
            crop = crop.resize((720, round(crop.height / 2)), Image.Resampling.LANCZOS)
        pair.append(crop)
    rows.append((desktop['label'], pair))

margin, column, gap, padding, labelheight, rowgap = 24, 752, 24, 16, 88, 24
width = margin * 2 + column * 2 + gap
height = 126 + sum(labelheight + max(p.height for p in pair) + padding * 2 + rowgap for _, pair in rows)
canvas = Image.new('RGB', (width, height), '#dfe5ec')
draw = ImageDraw.Draw(canvas)
draw.text((margin, 22), 'Villa Promenade | Section-by-section responsive preview', font=big, fill='#17212d')
draw.text((margin, 68), 'Desktop rendered at 1440 px (50% scale). Mobile rendered at 390 px (100% scale).', font=small, fill='#42526a')
y = 126
positions = []
for i, (label, pair) in enumerate(rows, 1):
    rowheight = labelheight + max(p.height for p in pair) + padding * 2
    positions.append((y, rowheight))
    for j, (name, crop) in enumerate(zip(('DESKTOP', 'MOBILE'), pair)):
        x = margin + j * (column + gap)
        draw.rectangle((x, y, x + column - 1, y + rowheight - 1), fill='white', outline='#b9c4d1', width=1)
        draw.rectangle((x, y, x + column - 1, y + labelheight - 1), fill='#17212d')
        draw.text((x + padding, y + 12), f'{i:02d}  {label}', font=font, fill='white')
        draw.text((x + padding, y + 48), name + (' / 1440 px' if j == 0 else ' / 390 px'), font=small, fill='#b9cbdc')
        canvas.paste(crop, (x + (column - crop.width) // 2, y + labelheight + padding))
    y += rowheight + rowgap
assert y == height
output = root / 'homepage-desktop-mobile-aligned.png'
canvas.save(output)
with Image.open(output) as check:
    check.verify()
# Produce a normal-sized detail for visual verification of formerly offset rows.
top = positions[8][0]
bottom = positions[9][0] + positions[9][1]
canvas.crop((0, top, width, bottom)).save(root / 'comparison-sections-check.png')
# A browser preview preserves legibility without opening an extremely tall bitmap.
import html
cards = []
for i, (label, _) in enumerate(rows, 1):
    cells = ''.join(f'<article><h2>{i:02d} {html.escape(label)}</h2><p>{name.upper()} / {metadata[name]["width"]} px</p><img src="comparison-sections/{i:02d}-{name}.png" class="{name}"></article>' for name in ('desktop', 'mobile'))
    cards.append(f'<section>{cells}</section>')
(root / 'homepage-comparison.html').write_text('<!doctype html><html><meta charset="utf-8"><title>Responsive section comparison</title><style>body{margin:24px;background:#dfe5ec;font:16px system-ui;color:#17212d;min-width:1100px}header{position:sticky;top:0;background:#dfe5ec;padding:8px;z-index:1}section{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin:24px 0;align-items:stretch}article{background:white;border:1px solid #b9c4d1;padding:16px;min-width:0}h2{font-size:20px;margin:0}p{font-size:13px;color:#42526a}img{display:block;height:auto;margin:0 auto;max-width:100%}.desktop{width:720px}.mobile{width:390px}</style><header>DESKTOP (left) / MOBILE (right) — identical section in every row</header>' + ''.join(cards) + '</html>')
print(json.dumps({'image': str(output), 'size': [width, height], 'matched_sections': len(rows), 'html': str(root / 'homepage-comparison.html'), 'bytes': output.stat().st_size}))
