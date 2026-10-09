"""Generate slots.v2.json: garis bantu wajah dan gigi untuk PoC kamera.

Jalankan dari folder poc/camera:  python3 tools/gen_slots.py
Ubah ukuran gigi / posisi di konstanta di bawah, lalu naikkan VERSION.
Semua gambar adalah outline generik buatan sendiri (bukan salinan aplikasi lain).
"""
import json
import math
import re
from pathlib import Path

VERSION = "2.0.0"
OUT = Path(__file__).resolve().parent.parent / "slots.v2.json"


def r(v):
    return round(v, 1)


# ---------------------------------------------------------------- mahkota gigi
# Bentuk lokal: px -0.5..0.5 (lebar), py 0 = gingiva, 1 = tepi insisal/oklusal.
def crown_local(kind):
    """Daftar segmen: ('M', p) / ('C', c1, c2, p) dalam koordinat lokal."""
    gl, gr = (-0.36, 0.16), (0.36, 0.16)
    seg = [("M", gl), ("C", (-0.34, -0.1), (0.34, -0.1), gr),
           ("C", (0.5, 0.36), (0.52, 0.72), (0.42, 0.92))]
    if kind == "inc":
        seg.append(("C", (0.3, 1.02), (-0.3, 1.02), (-0.42, 0.92)))
    elif kind == "can":
        seg += [("C", (0.3, 0.98), (0.12, 1.03), (0.0, 1.09)),
                ("C", (-0.12, 1.03), (-0.3, 0.98), (-0.42, 0.92))]
    else:
        n = 1 if kind == "pm" else 2
        xs = [0.42 - 0.84 * i / n for i in range(n + 1)]
        for i in range(n):
            xa, xb = xs[i], xs[i + 1]
            s = xa - xb
            end_y = 0.92 if i == n - 1 else 0.97
            seg.append(("C", (xa - 0.15 * s, 1.06), (xb + 0.15 * s, 1.06), (xb, end_y)))
    seg.append(("C", (-0.52, 0.72), (-0.5, 0.36), gl))
    return seg


def crown_path(cx, y0, w, h, kind, jaw="upper"):
    """jaw upper: gingiva di atas (y0). lower: tepi oklusal di atas (y0)."""
    def T(p):
        px, py = p
        y = y0 + py * h if jaw == "upper" else y0 + (1 - py) * h
        return f"{r(cx + px * w)} {r(y)}"
    out = []
    for s in crown_local(kind):
        if s[0] == "M":
            out.append(f"M{T(s[1])}")
        else:
            out.append(f"C{T(s[1])} {T(s[2])} {T(s[3])}")
    return "".join(out) + "Z"


def mirror_x(path, width):
    """Cerminkan path horizontal (untuk sisi kiri/kanan). Angka berpasangan x y."""
    out, idx = [], 0
    for tok in re.findall(r"[MCLZ]|-?\d+(?:\.\d+)?", path):
        if tok in "MCLZ":
            out.append(tok)
            continue
        v = float(tok)
        out.append(str(r(width - v)) if idx % 2 == 0 else str(r(v)))
        idx += 1
    s = ""
    for tok in out:
        s += tok if tok in "MCLZ" or (s and s[-1] in "MCLZ") else " " + tok
    return s


# ---------------------------------------------------------------- tampak depan
UPPER_FRONT = [  # per sisi dari midline: lebar, tinggi, jenis, offset gingiva
    (98, 128, "inc", 0), (76, 108, "inc", 8), (68, 118, "can", -2),
    (56, 96, "pm", 4), (48, 90, "pm", 8), (42, 84, "mol", 12),
]
LOWER_FRONT = [  # lebar, tinggi, jenis, y tepi insisal
    (60, 98, "inc", 196), (64, 100, "inc", 196), (64, 110, "can", 192),
    (56, 96, "pm", 186), (50, 90, "pm", 186), (46, 86, "mol", 184),
]


def front_rows(width=1000, top=90, n_upper=6, n_lower=6, lower_shift=0):
    mid = width / 2
    upper, lower = [], []
    x = 0
    for w, h, k, dz in UPPER_FRONT[:n_upper]:
        for side in (1, -1):
            upper.append(crown_path(mid + side * (x + w / 2), top + dz, w, h, k, "upper"))
        x += w
    x = 0
    for w, h, k, ytop in LOWER_FRONT[:n_lower]:
        for side in (1, -1):
            lower.append(crown_path(mid + side * (x + w / 2), ytop + lower_shift, w, h, k, "lower"))
        x += w
    return upper, lower


# ---------------------------------------------------------------- tampak samping
UPPER_SIDE = [  # dari anterior: lebar, tinggi, jenis, overbite
    (52, 130, "inc", 18), (58, 112, "inc", 12), (72, 122, "can", 10),
    (70, 96, "pm", 0), (68, 92, "pm", 0), (104, 88, "mol", 0), (92, 82, "mol", 0),
]
LOWER_SIDE = [
    (30, 110, "inc"), (36, 112, "inc"), (58, 118, "can"),
    (64, 100, "pm"), (66, 96, "pm"), (110, 92, "mol"), (100, 88, "mol"),
]


def side_rows(anterior_right=True, width=1000, plane=240):
    total = sum(t[0] for t in UPPER_SIDE)
    xa = width / 2 + total / 2  # tepi anterior (kanan)
    upper, x = [], xa
    canine_center = None
    for i, (w, h, k, ob) in enumerate(UPPER_SIDE):
        cx = x - w / 2
        if i == 2:
            canine_center = cx
        bottom = plane + ob
        upper.append(crown_path(cx, bottom - h, w, h, k, "upper"))
        x -= w
    # Kelas I: batas kaninus bawah / premolar 1 bawah tepat di bawah tonjol kaninus atas
    lower = []
    anterior_w = sum(t[0] for t in LOWER_SIDE[:3])
    x = canine_center + anterior_w
    for w, h, k in LOWER_SIDE:
        cx = x - w / 2
        lower.append(crown_path(cx, plane - 6, w, h, k, "lower"))
        x -= w
    if not anterior_right:
        upper = [mirror_x(p, width) for p in upper]
        lower = [mirror_x(p, width) for p in lower]
    return upper, lower


# ---------------------------------------------------------------- tampak oklusal
UPPER_OCC = [(50, 0.55, 2.0), (40, 0.6, 2.0), (44, 0.95, 2.2), (42, 1.25, 2.6),
             (40, 1.3, 2.6), (60, 1.15, 3.0), (56, 1.1, 3.0)]
LOWER_OCC = [(36, 0.75, 2.0), (40, 0.75, 2.0), (42, 0.95, 2.2), (44, 1.1, 2.6),
             (46, 1.1, 2.6), (60, 1.0, 3.0), (56, 1.0, 3.0)]


def bezier(p0, p1, p2, p3, t):
    u = 1 - t
    return (u**3 * p0[0] + 3 * u * u * t * p1[0] + 3 * u * t * t * p2[0] + t**3 * p3[0],
            u**3 * p0[1] + 3 * u * u * t * p1[1] + 3 * u * t * t * p2[1] + t**3 * p3[1])


def superellipse(cx, cy, a, b, ang, n, steps=24):
    pts = []
    for i in range(steps):
        t = 2 * math.pi * i / steps
        c, s = math.cos(t), math.sin(t)
        x = a * math.copysign(abs(c) ** (2 / n), c)
        y = b * math.copysign(abs(s) ** (2 / n), s)
        xr = cx + x * math.cos(ang) - y * math.sin(ang)
        yr = cy + x * math.sin(ang) + y * math.cos(ang)
        pts.append((r(xr), r(yr)))
    return "M" + "L".join(f"{x} {y}" for x, y in pts) + "Z"


def occlusal_arch(teeth, ctrl, width=600, height=760, lower=False):
    p0, p1, p2, p3 = ctrl
    samples = [bezier(p0, p1, p2, p3, i / 2000) for i in range(2001)]
    acc = [0.0]
    for i in range(1, len(samples)):
        acc.append(acc[-1] + math.dist(samples[i - 1], samples[i]))
    L = acc[-1]
    k = (L - 6) / sum(t[0] for t in teeth)
    paths, s = [], 3.0
    for size, depth, n in teeth:
        sc = s + size * k / 2
        j = min(range(len(acc)), key=lambda i: abs(acc[i] - sc))
        j2 = min(j + 1, len(samples) - 1)
        j1 = max(j - 1, 0)
        ang = math.atan2(samples[j2][1] - samples[j1][1], samples[j2][0] - samples[j1][0])
        cx, cy = samples[j]
        a = size * k / 2 * 0.94
        b = a * depth
        for mx in (False, True):
            x = width - cx if mx else cx
            angle = math.pi - ang if mx else ang
            y = cy
            if lower:
                y = height - y
                angle = -angle
            paths.append(superellipse(x, y, a, b, angle, n))
        s += size * k
    return paths


# ---------------------------------------------------------------- siluet profil
def catmull_rom(points, closed=False):
    pts = points
    d = f"M{r(pts[0][0])} {r(pts[0][1])}"
    for i in range(len(pts) - 1):
        p0 = pts[i - 1] if i > 0 else pts[i]
        p1, p2 = pts[i], pts[i + 1]
        p3 = pts[i + 2] if i + 2 < len(pts) else p2
        c1 = (p1[0] + (p2[0] - p0[0]) / 6, p1[1] + (p2[1] - p0[1]) / 6)
        c2 = (p2[0] - (p3[0] - p1[0]) / 6, p2[1] - (p3[1] - p1[1]) / 6)
        d += f"C{r(c1[0])} {r(c1[1])} {r(c2[0])} {r(c2[1])} {r(p2[0])} {r(p2[1])}"
    return d


PROFILE_RIGHT = [  # menghadap ke kanan; viewBox 0 0 300 400
    (104, 398), (96, 350), (78, 300), (64, 240), (62, 180), (80, 112), (122, 72),
    (172, 60), (214, 76), (238, 112), (246, 158), (246, 184), (240, 200),
    (252, 222), (266, 246), (248, 258), (254, 274), (246, 284),
    (251, 295), (240, 308), (247, 326), (240, 340), (222, 348),
    (196, 350), (186, 362), (184, 398),
]


# ---------------------------------------------------------------- slot
def corners(box=(0.08, 0.1, 0.84, 0.8)):
    return {"t": "corners", "box": list(box)}


def fit_view_box(layers, pad=0.03):
    nums = [float(n) for L in layers for d in L["paths"] for n in re.findall(r"-?\d+(?:\.\d+)?", d)]
    xs, ys = nums[0::2], nums[1::2]
    x0, x1, y0, y1 = min(xs), max(xs), min(ys), max(ys)
    px, py = (x1 - x0) * pad, (y1 - y0) * pad
    return f"{r(x0 - px)} {r(y0 - py)} {r(x1 - x0 + 2 * px)} {r(y1 - y0 + 2 * py)}"


def art(_view_box, layers, box=(0.1, 0.12, 0.8, 0.76)):
    return {"t": "art", "box": list(box), "viewBox": fit_view_box(layers), "layers": layers}


def face_front_lines():
    return [
        {"t": "ellipse", "cx": 0.5, "cy": 0.42, "rx": 0.22, "ry": 0.22},
        {"t": "line", "x1": 0.5, "y1": 0.1, "x2": 0.5, "y2": 0.82, "dash": True, "label": "garis tengah wajah", "lx": 0.52, "ly": 0.12},
        {"t": "line", "x1": 0.26, "y1": 0.38, "x2": 0.74, "y2": 0.38, "label": "garis pupil, harus datar", "lx": 0.26, "ly": 0.36},
        {"t": "line", "x1": 0.32, "y1": 0.2, "x2": 0.68, "y2": 0.2, "dash": True, "label": "garis rambut", "lx": 0.7, "ly": 0.21},
        {"t": "line", "x1": 0.36, "y1": 0.64, "x2": 0.64, "y2": 0.64, "dash": True, "label": "dagu", "lx": 0.66, "ly": 0.65},
        {"t": "polyline", "points": [[0.06, 1], [0.2, 0.86], [0.38, 0.8]]},
        {"t": "polyline", "points": [[0.62, 0.8], [0.8, 0.86], [0.94, 1]]},
    ]


def build():
    fu, fl = front_rows()
    au, _ = front_rows(top=110, n_upper=4, n_lower=0)
    _, al = front_rows(n_upper=0, n_lower=4, lower_shift=-60)
    ru, rl = side_rows(anterior_right=True)
    lu, ll = side_rows(anterior_right=False)
    occ_up = occlusal_arch(UPPER_OCC, ((300, 120), (195, 118), (112, 330), (110, 640)))
    occ_lo = occlusal_arch(LOWER_OCC, ((300, 120), (200, 118), (120, 340), (118, 640)), lower=True)
    teeth_box = (0.12, 0.2, 0.76, 0.6)

    def teeth_layers(upper, lower):
        layers = []
        if upper:
            layers.append({"id": "upper", "paths": upper})
        if lower:
            layer = {"id": "lower", "paths": lower}
            if upper:
                layer["maskBy"] = "upper"  # gigi bawah tersembunyi di belakang gigi atas
            layers.append(layer)
        return layers

    slots = [
        {"code": "EO-01", "label": "Wajah depan, rest", "orientation": "portrait", "required": True,
         "level": True, "mirror": None,
         "checklist": ["Latar putih", "Kacamata dilepas", "Telinga terlihat", "Bibir rileks", "Mata lurus ke kamera"],
         "overlay": [corners()] + face_front_lines()},
        {"code": "EO-02", "label": "Wajah depan, senyum", "orientation": "portrait", "required": True,
         "level": True, "mirror": None,
         "checklist": ["Latar putih", "Senyum sosial alami", "Telinga terlihat", "Mata lurus ke kamera"],
         "overlay": [corners()] + face_front_lines()},
        {"code": "EO-03", "label": "Profil kanan (standar ABO)", "orientation": "portrait", "required": True,
         "level": True, "mirror": None,
         "checklist": ["Latar putih", "Wajah menghadap ke kanan layar", "Telinga terlihat", "Bahu tegak lurus kamera"],
         "overlay": [corners(), art("0 0 300 400", [{"id": "head", "paths": [catmull_rom(PROFILE_RIGHT)]}],
                                    box=(0.1, 0.16, 0.8, 0.7))]},
        {"code": "IO-01", "label": "Intraoral kiri", "orientation": "landscape", "required": True,
         "level": True, "mirror": {"axis": "horizontal", "default": False},
         "checklist": ["Dua retraktor", "Oklusi maksimal", "Gigi depan di sisi kiri layar"],
         "overlay": [corners(), art("0 0 1000 480", teeth_layers(lu, ll), box=teeth_box)]},
        {"code": "IO-02", "label": "Intraoral depan", "orientation": "landscape", "required": True,
         "level": True, "mirror": None,
         "checklist": ["Dua retraktor", "Oklusi maksimal", "Midline di tengah"],
         "overlay": [corners(), art("0 0 1000 460", teeth_layers(fu, fl), box=teeth_box)]},
        {"code": "IO-03", "label": "Intraoral kanan", "orientation": "landscape", "required": True,
         "level": True, "mirror": {"axis": "horizontal", "default": False},
         "checklist": ["Dua retraktor", "Oklusi maksimal", "Gigi depan di sisi kanan layar"],
         "overlay": [corners(), art("0 0 1000 480", teeth_layers(ru, rl), box=teeth_box)]},
        {"code": "IO-04", "label": "Oklusal atas", "orientation": "portrait", "required": True,
         "level": False, "mirror": {"axis": "vertical", "default": True},
         "checklist": ["Cermin oklusal dihangatkan", "Mulut terbuka maksimal", "Gigi seri di atas layar"],
         "overlay": [corners(), art("0 0 600 760", [{"id": "arch", "paths": occ_up}], box=(0.14, 0.18, 0.72, 0.64))]},
        {"code": "IO-05", "label": "Oklusal bawah", "orientation": "portrait", "required": True,
         "level": False, "mirror": {"axis": "vertical", "default": True},
         "checklist": ["Cermin oklusal dihangatkan", "Lidah ditarik ke belakang", "Gigi seri di bawah layar"],
         "overlay": [corners(), art("0 0 600 760", [{"id": "arch", "paths": occ_lo}], box=(0.14, 0.18, 0.72, 0.64))]},
        {"code": "IO-06", "label": "Anterior atas", "orientation": "landscape", "required": False,
         "level": True, "mirror": None,
         "checklist": ["Mulut sedikit terbuka", "Tepi insisal atas terlihat jelas"],
         "overlay": [corners(), art("0 0 1000 400", teeth_layers(au, []), box=teeth_box)]},
        {"code": "IO-07", "label": "Anterior bawah", "orientation": "landscape", "required": False,
         "level": True, "mirror": None,
         "checklist": ["Mulut sedikit terbuka", "Tepi insisal bawah terlihat jelas"],
         "overlay": [corners(), art("0 0 1000 400", teeth_layers([], al), box=teeth_box)]},
        {"code": "FREE", "label": "Bebas", "orientation": "any", "required": False,
         "level": False, "mirror": {"axis": "horizontal", "default": False},
         "checklist": ["Tanpa garis bantu"],
         "overlay": [corners()]},
    ]
    return {
        "version": VERSION,
        "note": "Dibuat oleh tools/gen_slots.py. Koordinat shape biasa = pecahan frame (0..1); "
                "'art' digambar di viewBox sendiri dan diskalakan ke 'box'. Garis bantu adalah titik awal, wajib dikoreksi dokter.",
        "slots": slots,
    }


if __name__ == "__main__":
    data = build()
    OUT.write_text(json.dumps(data, ensure_ascii=False, separators=(",", ":")))
    print(f"{OUT.name}: {len(data['slots'])} slot, {OUT.stat().st_size / 1024:.1f} KB")
