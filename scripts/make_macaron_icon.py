"""Generate the Macaron brand mark + all app/favicon assets deterministically.
Flat geometric 'M' with a truncated (receipt-cut) vertex. Exact brand hex, true
transparency, multi-size .ico. Re-run any time:  python scripts/make_macaron_icon.py
"""
import os, math
from PIL import Image, ImageDraw

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MUSTARD = (233, 184, 63, 255)     # #E9B83F
ESPRESSO = (23, 19, 16, 255)      # #171310
WHITE = (255, 255, 255, 255)
SS = 4

W = 150.0
LX = W / 2.0
RX = 1000.0 - W / 2.0
TOP = W / 2.0
BOT = 1000.0 - W / 2.0
CX, CV = 500.0, 648.0

def stroke(p, q, wd):
    px, py = p; qx, qy = q
    dx, dy = qx - px, qy - py
    L = math.hypot(dx, dy) or 1.0
    nx, ny = -dy / L, dx / L
    ox, oy = nx * wd / 2.0, ny * wd / 2.0
    return [(px + ox, py + oy), (qx + ox, qy + oy), (qx - ox, qy - oy), (px - ox, py - oy)]

def draw_mark(scale, color, punch=True):
    S = int(1000 * scale)
    im = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    for poly in [
        stroke((LX, TOP), (LX, BOT), W),
        stroke((RX, TOP), (RX, BOT), W),
        stroke((LX, TOP), (CX, CV), W),
        stroke((RX, TOP), (CX, CV), W),
    ]:
        d.polygon([(x * scale, y * scale) for x, y in poly], fill=color)
    if punch:
        cx = CX * scale
        d.rectangle([cx - 82 * scale, (CV + 34) * scale, cx + 82 * scale, 1000 * scale], fill=(0, 0, 0, 0))
    return im.resize((S // SS, S // SS), Image.LANCZOS)

def rounded_chip(size, bg, fill=0.60, mark=MUSTARD, punch=True):
    S = size * SS
    chip = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    ImageDraw.Draw(chip).rounded_rectangle([0, 0, S - 1, S - 1], radius=int(0.20 * S), fill=bg)
    ms = int(fill * S)
    chip.alpha_composite(draw_mark(ms / 1000.0, mark, punch).resize((ms, ms), Image.LANCZOS),
                         ((S - ms) // 2, (S - ms) // 2 - int(0.006 * S)))
    return chip.resize((size, size), Image.LANCZOS)

def fullbleed(size, bg, fill=0.62, mark=MUSTARD, punch=True):
    S = size * SS
    chip = Image.new("RGBA", (S, S), bg)
    ms = int(fill * S)
    chip.alpha_composite(draw_mark(ms / 1000.0, mark, punch).resize((ms, ms), Image.LANCZOS),
                         ((S - ms) // 2, (S - ms) // 2))
    return chip.resize((size, size), Image.LANCZOS)

def symbol(size, mark=MUSTARD, fill=0.86, punch=True):
    S = size * SS
    ms = int(fill * S)
    out = Image.new("RGBA", (S, S), (0, 0, 0, 0))
    out.alpha_composite(draw_mark(ms / 1000.0, mark, punch).resize((ms, ms), Image.LANCZOS),
                        ((S - ms) // 2, (S - ms) // 2))
    return out.resize((size, size), Image.LANCZOS)

def save(im, rel):
    p = os.path.join(ROOT, rel)
    os.makedirs(os.path.dirname(p), exist_ok=True)
    im.save(p)
    print("wrote", rel)

if __name__ == "__main__":
    # Electron window + Windows installer icon (electron-builder converts to .ico)
    save(rounded_chip(1024, ESPRESSO), "desktop/icon.png")
    # Web favicons
    save(rounded_chip(64, ESPRESSO), "public/favicon.png")                 # light tab: solid chip
    save(symbol(64, MUSTARD, fill=0.72), "public/favicon-dark.png")        # dark tab: mustard M on transparent
    save(fullbleed(180, ESPRESSO, fill=0.60), "public/apple-touch-icon.png")  # opaque, required
    # multi-size .ico (chip reads on any taskbar)
    rounded_chip(256, ESPRESSO).save(
        os.path.join(ROOT, "public/favicon.ico"),
        format="ICO", sizes=[(16, 16), (24, 24), (32, 32), (48, 48), (64, 64), (128, 128), (256, 256)])
    print("wrote public/favicon.ico")
    # portfolio brand sheet
    sheet = Image.new("RGBA", (1080, 720), (245, 243, 239, 255))
    dd = ImageDraw.Draw(sheet)
    tiles = [("PRIMARY", rounded_chip(1024, ESPRESSO).resize((300, 300), Image.LANCZOS), 20, 40),
             ("SYMBOL", symbol(512, MUSTARD).resize((220, 220), Image.LANCZOS), 360, 40),
             ("LIGHT-BG", rounded_chip(512, WHITE, mark=ESPRESSO).resize((220, 220), Image.LANCZOS), 610, 40),
             ("MONOCHROME", symbol(512, ESPRESSO).resize((220, 220), Image.LANCZOS), 360, 300),
             ("INVERTED", rounded_chip(512, MUSTARD, mark=ESPRESSO).resize((220, 220), Image.LANCZOS), 610, 300)]
    for label, im, x, y in tiles:
        sheet.alpha_composite(im, (x, y)); dd.text((x, y + im.height + 6), label, fill=(40, 36, 32, 255))
    xoff = 10
    for s in (16, 24, 32, 48, 64):
        im = rounded_chip(s, ESPRESSO); sheet.alpha_composite(im, (20 + xoff, 470 - s // 2))
        dd.text((24 + xoff, 505), str(s), fill=(40, 36, 32, 255)); xoff += s + 20
    dd.text((20, 380), "SIZE RAMP 16-64", fill=(40, 36, 32, 255))
    save(sheet, "docs/brand/macaron-icon-sheet.png")
    save(rounded_chip(1024, ESPRESSO), "docs/brand/macaron-icon-1024.png")
