import json
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parent
metadata = json.loads((ROOT / 'homepage-comparison-sections.json').read_text())
images = {name: Image.open(ROOT / f'homepage-{name}-full.png').convert('RGB') for name in ('desktop', 'mobile')}
desktop, mobile = metadata['desktop']['blocks'], metadata['mobile']['blocks']
for blocks in (desktop, mobile):
    # Keep the mobile administrative toolbar above the hero in its first crop.
    blocks[0]['y'] = 0
assert len(desktop) == len(mobile)
assert [b['label'] for b in desktop] == [b['label'] for b in mobile]
for name in images:
    assert images[name].size == (metadata[name]['width'], metadata[name]['height'])
    blocks = metadata[name]['blocks']
    assert blocks[0]['y'] == 0
    assert all(b['end'] > b['y'] for b in blocks)
    assert sum(b['end'] - b['y'] for b in blocks) == images[name].height

font_path = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
font = ImageFont.truetype(font_path, 22)
small = ImageFont.truetype(font_path, 17)
title = ImageFont.truetype(font_path, 32)
margin, gap, header, label_h, row_gap = 32, 48, 142, 48, 32
left_x = margin
right_x = margin + images['desktop'].width + gap
width = right_x + images['mobile'].width + margin
height = header + sum(label_h + max(d['end'] - d['y'], m['end'] - m['y']) + row_gap for d, m in zip(desktop, mobile))
canvas = Image.new('RGB', (width, height), '#e6e9ed')
draw = ImageDraw.Draw(canvas)
draw.text((margin, 20), 'Villa Promenade | Aligned responsive comparison', font=title, fill='#17212d')
draw.text((margin, 66), 'Matching sections start on the same line. Blank areas are comparison spacing, not page layout.', font=small, fill='#42526a')
draw.text((left_x, 103), 'DESKTOP / 1440 px', font=font, fill='#17212d')
draw.text((right_x, 103), 'MOBILE / 390 px', font=font, fill='#17212d')
y = header
for index, (d, m) in enumerate(zip(desktop, mobile), 1):
    label = f'{index:02d}  {d["label"]}'
    draw.rectangle((margin, y, width - margin, y + label_h - 8), fill='#17212d')
    draw.text((margin + 12, y + 7), label, font=font, fill='white')
    y += label_h
    row_h = max(d['end'] - d['y'], m['end'] - m['y'])
    for name, block, x in [('desktop', d, left_x), ('mobile', m, right_x)]:
        image = images[name]
        crop = image.crop((0, block['y'], image.width, block['end']))
        draw.rectangle((x, y, x + image.width - 1, y + row_h - 1), fill='#f8fafc')
        canvas.paste(crop, (x, y))
        if crop.height < row_h - 35:
            draw.text((x + 12, y + crop.height + 12), 'Alignment spacing', font=small, fill='#8b95a4')
    y += row_h + row_gap
assert y == height
output = ROOT / 'homepage-desktop-mobile-aligned.png'
canvas.save(output, optimize=True)
with Image.open(output) as saved:
    saved.verify()
preview = canvas.copy()
preview.thumbnail((700, 12000))
preview.save(ROOT / 'homepage-desktop-mobile-aligned-preview.jpg', quality=85)
print(json.dumps({'output': str(output), 'dimensions': [width, height], 'sections': len(desktop), 'bytes': output.stat().st_size, 'source_pixels_preserved': True}))
