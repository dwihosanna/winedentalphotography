"""Generate slots.v2.json: garis bantu wajah dan gigi untuk PoC kamera.

Jalankan dari folder poc/camera:  python3 tools/gen_slots.py
Ubah ukuran gigi / posisi di konstanta di bawah, lalu naikkan VERSION.
Semua gambar adalah outline generik buatan sendiri (bukan salinan aplikasi lain).
"""
import json
import math
import re
from pathlib import Path

VERSION = "2.1.0"
OUT = Path(__file__).resolve().parent.parent / "slots.v2.json"


def r(v):
    return round(v, 1)


def poly(pts):
    return "M" + "L".join(f"{r(x)} {r(y)}" for x, y in pts) + "Z"


def spow(v, n):
    return math.copysign(abs(v) ** (2 / n), v)


# ---------------------------------------------------------------- mahkota gigi (tampak depan/samping)
# Bentuk = superellips dengan eksponen berbeda untuk sisi gingiva dan sisi insisal/oklusal:
# n besar = sudut lebih persegi, n = 2 = elips. taper = leher gingiva lebih sempit dari tepi insisal.
CROWN = {  # n_gingiva, n_insisal, taper, cusp (tonjol di tengah tepi insisal, pecahan tinggi)
    "inc": (2.6, 5.0, 0.10, 0.0),
    "lat": (2.5, 4.0, 0.12, 0.0),
    "can": (2.4, 2.8, 0.10, 0.07),
    "pm": (2.4, 3.0, 0.08, 0.03),
    "mol": (2.8, 3.6, 0.05, 0.0),
    "linc": (2.4, 6.0, 0.22, 0.0),  # insisivus bawah: tepi rata, leher sempit
}


def crown_path(cx, y0, w, h, kind, jaw="upper", tilt=0.0, steps=48):
    """Outline satu mahkota. upper: gingiva di y0 (atas). lower: tepi insisal di y0 (atas).

    tilt (derajat) memiringkan sumbu gigi; positif = sisi gingiva condong ke kanan layar.
    """
    ng, ni, taper, cusp = CROWN[kind]
    a, b = w / 2, h / 2
    cy = y0 + b
    rad = math.radians(tilt)
    pts = []
    for i in range(steps):
        t = 2 * math.pi * i / steps
        c, s = math.cos(t), math.sin(t)
        gingival = s < 0  # setengah atas pada rahang atas
        x = a * spow(c, ng if gingival else ni)
        y = b * spow(s, ng if gingival else ni)
        k = (y + b) / (2 * b)  # 0 = gingiva, 1 = insisal
        x *= 1 - taper * (1 - k)
        if not gingival and cusp:
            y += cusp * h * max(0.0, 1 - abs(x) / a) ** 2
        if jaw == "lower":
            y = -y
        # miring: sisi gingiva bergeser, tepi insisal tetap (rotasi di sekitar tepi insisal)
        pivot = b if jaw == "upper" else -b
        dx, dy = x, y - pivot
        xr = dx * math.cos(rad) - dy * math.sin(rad)
        yr = dx * math.sin(rad) + dy * math.cos(rad) + pivot
        pts.append((cx + xr, cy + yr))
    return poly(pts)


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
# Satuan bebas (skala digambar ulang ke frame). Gigi posterior menyempit karena perspektif lengkung.
UPPER_FRONT = [  # per sisi dari midline: lebar, tinggi, jenis, y gingiva, tilt
    (132, 175, "inc", 245, 0), (92, 152, "lat", 258, 2), (72, 160, "can", 250, 4),
    (54, 128, "pm", 280, 4), (44, 112, "pm", 292, 3), (38, 100, "mol", 302, 2),
]
LOWER_FRONT = [  # lebar, tinggi, jenis, y tepi insisal, tilt
    (84, 125, "linc", 382, 0), (80, 132, "linc", 380, 2), (74, 135, "can", 378, 4),
    (58, 112, "pm", 384, 4), (48, 100, "pm", 388, 3), (42, 95, "mol", 390, 2),
]


def front_rows(width=1200, n_upper=6, n_lower=6, lower_shift=0, upper_shift=0):
    mid = width / 2
    upper, lower = [], []
    x = 0
    for w, h, k, y0, tilt in UPPER_FRONT[:n_upper]:
        for side in (1, -1):
            upper.append(crown_path(mid + side * (x + w / 2), y0 + upper_shift, w, h, k, "upper", side * tilt))
        x += w
    x = 0
    for w, h, k, y0, tilt in LOWER_FRONT[:n_lower]:
        for side in (1, -1):
            lower.append(crown_path(mid + side * (x + w / 2), y0 + lower_shift, w, h, k, "lower", side * tilt))
        x += w
    return upper, lower


# ---------------------------------------------------------------- tampak samping
UPPER_SIDE = [  # dari anterior: lebar, tinggi, jenis, overbite
    (46, 150, "inc", 22), (56, 132, "lat", 14), (72, 140, "can", 10),
    (68, 108, "pm", 0), (66, 102, "pm", 0), (104, 96, "mol", 0), (94, 90, "mol", 0),
]
LOWER_SIDE = [
    (28, 120, "linc"), (34, 124, "linc"), (58, 128, "can"),
    (64, 108, "pm"), (66, 104, "pm"), (110, 98, "mol"), (100, 94, "mol"),
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
        upper.append(crown_path(cx, bottom - h, w, h, k, "upper", -3 if i < 3 else 0))
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
# Lengkung U (mm): titik tengah mahkota setengah rahang, dari midline sampai distal M2
# (x ke lateral, y ke posterior). Lebar interkaninus atas ~33 mm, intermolar M1 ~51 mm, kedalaman ~44 mm;
# bawah ~26 / ~45 / ~42 mm. Anterior membulat, posterior hampir lurus dan sedikit melebar (bukan V).
UPPER_ARCH = [(0, 0), (4.3, 1.0), (11.0, 4.0), (16.5, 8.5), (20.0, 14.5), (22.5, 21.0), (25.5, 29.0), (28.0, 39.0), (29.0, 44.0)]
LOWER_ARCH = [(0, 0), (2.7, 0.5), (8.0, 2.5), (13.0, 6.0), (16.8, 11.5), (19.5, 18.0), (22.5, 26.5), (25.0, 37.0), (26.0, 42.0)]
# Ukuran mahkota rata-rata (mm): mesiodistal, bukolingual, n superellips, taper labial->lingual
UPPER_OCC = [(8.6, 7.0, 2.6, 0.35), (6.6, 6.2, 2.6, 0.35), (7.6, 8.0, 2.4, 0.25),
             (7.0, 9.2, 2.8, 0.08), (6.6, 9.0, 2.8, 0.08), (10.2, 11.3, 3.4, 0.06), (9.2, 11.0, 3.4, 0.06)]
LOWER_OCC = [(5.3, 5.9, 2.6, 0.30), (5.9, 6.2, 2.6, 0.30), (6.9, 7.6, 2.4, 0.22),
             (7.0, 7.8, 2.6, 0.10), (7.1, 8.2, 2.8, 0.08), (11.2, 10.6, 3.4, 0.05), (10.5, 10.2, 3.4, 0.05)]
OCC_GAP = 0.97  # ruang kecil antar gigi pada gambar


def catmull_samples(points, per_seg=200):
    """Kurva halus melalui titik. Titik semu di kiri midline membuat garis singgung midline horizontal."""
    pts = [(-points[1][0], points[1][1])] + points
    pts = pts + [(2 * pts[-1][0] - pts[-2][0], 2 * pts[-1][1] - pts[-2][1])]
    out = []
    for i in range(1, len(pts) - 2):
        p0, p1, p2, p3 = pts[i - 1], pts[i], pts[i + 1], pts[i + 2]
        for j in range(per_seg):
            t = j / per_seg
            t2, t3 = t * t, t * t * t
            out.append(tuple(0.5 * (2 * p1[k] + (-p0[k] + p2[k]) * t + (2 * p0[k] - 5 * p1[k] + 4 * p2[k] - p3[k]) * t2
                                    + (-p0[k] + 3 * p1[k] - 3 * p2[k] + p3[k]) * t3) for k in (0, 1)))
    out.append(points[-1])
    return out


def occlusal_tooth(cx, cy, ang, md, bl, n, taper, steps=40):
    """ang = arah garis singgung lengkung (ke distal). Sisi luar (bukal/labial) di kiri garis singgung."""
    a, b = md / 2 * OCC_GAP, bl / 2
    ca, sa = math.cos(ang), math.sin(ang)
    pts = []
    for i in range(steps):
        t = 2 * math.pi * i / steps
        u = a * spow(math.cos(t), n)
        v = b * spow(math.sin(t), n)  # v < 0 = labial/bukal, v > 0 = lingual/palatal
        u *= 1 - taper * (v / b + 1) / 2
        # arah labial/bukal = (sa, -ca): tegak lurus garis singgung, ke luar lengkung (sisi kanan)
        pts.append((cx + u * ca - v * sa, cy + u * sa + v * ca))
    return poly(pts)


def occlusal_arch(arch, teeth, lower=False):
    samples = catmull_samples([(x * 10, y * 10) for x, y in arch])  # 0,1 mm per satuan
    acc = [0.0]
    for p, q in zip(samples, samples[1:]):
        acc.append(acc[-1] + math.dist(p, q))
    k = acc[-1] / sum(t[0] for t in teeth)  # satuan gambar per mm
    paths, s, j = [], 0.0, 0
    for md, bl, n, taper in teeth:
        sc = s + md * k / 2
        while j < len(acc) - 1 and acc[j] < sc:
            j += 1
        p, q = samples[max(j - 1, 0)], samples[min(j + 1, len(samples) - 1)]
        ang = math.atan2(q[1] - p[1], q[0] - p[0])
        cx, cy = samples[j]
        tooth = occlusal_tooth(cx, cy, ang, md * k, bl * k, n, taper)  # sisi kanan gambar
        paths += [tooth, mirror_x(tooth, 0)]
        s += md * k
    if lower:  # gigi seri bawah di bawah layar
        paths = [flip_y(p) for p in paths]
    return paths


def flip_y(path):
    out, idx = [], 0
    for tok in re.findall(r"[MCLZ]|-?\d+(?:\.\d+)?", path):
        if tok in "MCLZ":
            out.append(tok)
            continue
        v = float(tok)
        out.append(str(r(-v)) if idx % 2 else str(r(v)))
        idx += 1
    s = ""
    for tok in out:
        s += tok if tok in "MCLZ" or (s and s[-1] in "MCLZ") else " " + tok
    return s


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


def art(layers, landscape, portrait):
    """Garis bantu mengikuti rotasi HP: app memilih box sesuai bentuk frame saat itu."""
    return {"t": "art", "boxLandscape": list(landscape), "boxPortrait": list(portrait),
            "viewBox": fit_view_box(layers), "layers": layers}


# Box (x, y, w, h) dalam pecahan frame. Gambar diskalakan proporsional (meet) ke dalam box.
WIDE_L, WIDE_P = (0.12, 0.2, 0.76, 0.6), (0.06, 0.25, 0.88, 0.5)   # gigi tampak depan/samping
TALL_L, TALL_P = (0.25, 0.08, 0.5, 0.84), (0.12, 0.16, 0.76, 0.68)  # lengkung oklusal
HEAD_L, HEAD_P = (0.25, 0.1, 0.5, 0.8), (0.1, 0.16, 0.8, 0.7)


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
    au, _ = front_rows(n_upper=4, n_lower=0)
    _, al = front_rows(n_upper=0, n_lower=4)
    ru, rl = side_rows(anterior_right=True)
    lu, ll = side_rows(anterior_right=False)
    occ_up = occlusal_arch(UPPER_ARCH, UPPER_OCC)
    occ_lo = occlusal_arch(LOWER_ARCH, LOWER_OCC, lower=True)

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

    wide = lambda layers: art(layers, WIDE_L, WIDE_P)
    tall = lambda layers: art(layers, TALL_L, TALL_P)

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
         "overlay": [corners(), art([{"id": "head", "paths": [catmull_rom(PROFILE_RIGHT)]}], HEAD_L, HEAD_P)]},
        {"code": "IO-01", "label": "Intraoral kiri", "orientation": "landscape", "required": True,
         "level": True, "mirror": {"axis": "horizontal", "default": False},
         "checklist": ["Dua retraktor", "Oklusi maksimal", "Gigi depan di sisi kiri layar"],
         "overlay": [corners(), wide(teeth_layers(lu, ll))]},
        {"code": "IO-02", "label": "Intraoral depan", "orientation": "landscape", "required": True,
         "level": True, "mirror": None,
         "checklist": ["Dua retraktor", "Oklusi maksimal", "Midline di tengah"],
         "overlay": [corners(), wide(teeth_layers(fu, fl))]},
        {"code": "IO-03", "label": "Intraoral kanan", "orientation": "landscape", "required": True,
         "level": True, "mirror": {"axis": "horizontal", "default": False},
         "checklist": ["Dua retraktor", "Oklusi maksimal", "Gigi depan di sisi kanan layar"],
         "overlay": [corners(), wide(teeth_layers(ru, rl))]},
        {"code": "IO-04", "label": "Oklusal atas", "orientation": "portrait", "required": True,
         "level": False, "mirror": {"axis": "vertical", "default": True},
         "checklist": ["Cermin oklusal dihangatkan", "Mulut terbuka maksimal", "Gigi seri di atas layar"],
         "overlay": [corners(), tall([{"id": "arch", "paths": occ_up}])]},
        {"code": "IO-05", "label": "Oklusal bawah", "orientation": "portrait", "required": True,
         "level": False, "mirror": {"axis": "vertical", "default": True},
         "checklist": ["Cermin oklusal dihangatkan", "Lidah ditarik ke belakang", "Gigi seri di bawah layar"],
         "overlay": [corners(), tall([{"id": "arch", "paths": occ_lo}])]},
        {"code": "IO-06", "label": "Anterior atas", "orientation": "landscape", "required": False,
         "level": True, "mirror": None,
         "checklist": ["Mulut sedikit terbuka", "Tepi insisal atas terlihat jelas"],
         "overlay": [corners(), wide(teeth_layers(au, []))]},
        {"code": "IO-07", "label": "Anterior bawah", "orientation": "landscape", "required": False,
         "level": True, "mirror": None,
         "checklist": ["Mulut sedikit terbuka", "Tepi insisal bawah terlihat jelas"],
         "overlay": [corners(), wide(teeth_layers([], al))]},
        {"code": "FREE", "label": "Bebas", "orientation": "any", "required": False,
         "level": False, "mirror": {"axis": "horizontal", "default": False},
         "checklist": ["Tanpa garis bantu"],
         "overlay": [corners()]},
    ]
    return {
        "version": VERSION,
        "note": "Dibuat oleh tools/gen_slots.py. Koordinat shape biasa = pecahan frame (0..1); "
                "'art' digambar di viewBox sendiri dan diskalakan ke boxLandscape/boxPortrait sesuai rotasi HP. "
                "'orientation' = orientasi yang disarankan. Garis bantu adalah titik awal, wajib dikoreksi dokter.",
        "slots": slots,
    }


if __name__ == "__main__":
    data = build()
    OUT.write_text(json.dumps(data, ensure_ascii=False, separators=(",", ":")))
    print(f"{OUT.name}: {len(data['slots'])} slot, {OUT.stat().st_size / 1024:.1f} KB")
