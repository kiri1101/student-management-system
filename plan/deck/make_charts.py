"""
SchuLyf deck — brand-styled figures & charts (matplotlib).
Follows the dataviz method: form by job, color by job, thin marks,
direct labels, recessive axes. Emerald brand palette; validated
categorical set (#047857/#1D4ED8/#B45309).
Outputs PNGs into plan/deck/charts/.
"""
import os
import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
import matplotlib.font_manager as fm
from matplotlib.patches import FancyBboxPatch, FancyArrowPatch, Rectangle
import numpy as np

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "charts")
os.makedirs(OUT, exist_ok=True)

# ── brand palette ─────────────────────────────────────────────────────
EM = {50:"#ECFDF5",100:"#D1FAE5",200:"#A7F3D0",300:"#6EE7B7",400:"#34D399",
      500:"#10B981",600:"#059669",700:"#047857",800:"#065F46",900:"#064E3B"}
INK, INK2, MUTED, GRID = "#0F172A", "#475569", "#94A3B8", "#E2E8F0"
CAT = ["#047857", "#1D4ED8", "#B45309"]            # validated categorical
GOOD, WARN, CRIT = "#059669", "#D97706", "#DC2626"  # reserved status
GRADE = {"A":"#047857","B":"#10B981","C":"#D97706","D":"#F59E0B","F":"#DC2626"}

# Segoe UI if present (Windows), else DejaVu Sans
_FAM = "DejaVu Sans"
for _p in [r"C:\Windows\Fonts\segoeui.ttf"]:
    if os.path.exists(_p):
        fm.fontManager.addfont(_p)
        fm.fontManager.addfont(r"C:\Windows\Fonts\segoeuib.ttf")
        _FAM = "Segoe UI"
        break

plt.rcParams.update({
    "figure.dpi": 200, "savefig.dpi": 200,
    "font.family": _FAM, "font.size": 13,
    "text.color": INK, "axes.labelcolor": INK2,
    "xtick.color": INK2, "ytick.color": INK2,
    "axes.edgecolor": MUTED, "axes.linewidth": 0.8,
    "axes.spines.top": False, "axes.spines.right": False,
    "figure.facecolor": "white", "axes.facecolor": "white",
    "savefig.facecolor": "white",
})


def _save(fig, name):
    fig.savefig(os.path.join(OUT, name), bbox_inches="tight", pad_inches=0.15,
                facecolor="white")
    plt.close(fig)
    print("wrote", name)


def _tile(ax, x, y, w, h, fc, ec, radius=0.02):
    ax.add_patch(FancyBboxPatch((x, y), w, h, mutation_aspect=1,
                 boxstyle=f"round,pad=0,rounding_size={radius}",
                 fc=fc, ec=ec, lw=1.4, clip_on=False))


# ── 1. Pain points (slide 2) — labelled infographic tiles ─────────────
def pain_points():
    fig, ax = plt.subplots(figsize=(11, 5.6))
    ax.set_xlim(0, 12); ax.set_ylim(0, 10); ax.axis("off")
    items = [
        ("Manual admission", "Paper forms carried to the office; slow, opaque, lossy."),
        ("Lost bank slips", "A misplaced deposit slip blocks receipt issuance entirely."),
        ("Forgeable receipts", "The paper school receipt can be tampered with or reused."),
        ("Payment disputes", "Students and accountants clash over who can see bank records."),
        ("Exam-access rows", "Standing at each deadline decides hall access — often contested."),
    ]
    n = len(items)
    gap, h = 0.35, (10 - (n + 1) * 0.35) / n
    for i, (title, desc) in enumerate(items):
        y = 10 - 0.35 - (i + 1) * h - i * gap
        _tile(ax, 0.2, y, 11.6, h, EM[50], EM[600], radius=0.08)
        ax.add_patch(FancyBboxPatch((0.2, y), 0.16, h, boxstyle="square,pad=0",
                     fc=EM[700], ec="none", clip_on=False))
        ax.text(0.75, y + h/2, f"{i+1:02d}", va="center", ha="left",
                fontsize=22, fontweight="bold", color=EM[300])
        ax.text(1.8, y + h*0.62, title, va="center", ha="left",
                fontsize=16, fontweight="bold", color=EM[800])
        ax.text(1.8, y + h*0.26, desc, va="center", ha="left",
                fontsize=12.5, color=INK2)
    _save(fig, "pain-points.png")


# ── 2. Objectives → modules map (slide 3) — bipartite ─────────────────
def objectives_modules():
    fig, ax = plt.subplots(figsize=(11, 6.2))
    ax.set_xlim(0, 12); ax.set_ylim(0, 10); ax.axis("off")
    pains = ["Admission", "Payments", "Receipts", "Access &\ndeferrals",
             "Course info", "Proof of\nstudy"]
    modules = ["Admissions", "Payments & receipts", "Exam gating",
               "Course management", "Notifications", "Admin & audit",
               "Transcripts"]
    links = {0:[0], 1:[1], 2:[1,5], 3:[2], 4:[3,4], 5:[6]}
    pl_y = np.linspace(9.2, 0.8, len(pains))
    mo_y = np.linspace(9.4, 0.6, len(modules))
    lx, rx = 2.6, 9.4
    for pi, mis in links.items():
        for mi in mis:
            ax.add_patch(FancyArrowPatch((lx+0.05, pl_y[pi]), (rx-0.05, mo_y[mi]),
                         arrowstyle="-", color=EM[300], lw=2, alpha=0.9))
    for i, p in enumerate(pains):
        _tile(ax, 0.3, pl_y[i]-0.42, 2.3, 0.84, "white", MUTED, radius=0.1)
        ax.text(1.45, pl_y[i], p, va="center", ha="center", fontsize=12.5,
                color=INK)
    for i, m in enumerate(modules):
        _tile(ax, 9.4, mo_y[i]-0.36, 2.5, 0.72, EM[50], EM[600], radius=0.1)
        ax.text(10.65, mo_y[i], m, va="center", ha="center", fontsize=12.5,
                fontweight="bold", color=EM[800])
    ax.text(1.45, 9.85, "Real-world pains", ha="center", fontsize=12.5,
            fontweight="bold", color=INK2)
    ax.text(10.65, 9.95, "SchuLyf modules", ha="center", fontsize=12.5,
            fontweight="bold", color=EM[700])
    _save(fig, "objectives-modules.png")


# ── 3. Phased delivery timeline (slide 4) — Gantt ─────────────────────
def phase_timeline():
    fig, ax = plt.subplots(figsize=(11, 5.4))
    rows = [  # (label, start, end, group)
        ("Roles & auth foundation", 0, 1, 0),
        ("Reference data & audit", 1, 2, 0),
        ("Profiles & login", 2, 3, 0),
        ("Admissions & SAO decisions", 3, 5, 1),
        ("Admin & user management", 5, 6, 1),
        ("Payments & receipts", 6, 7.2, 1),
        ("Exam gating & deferrals", 7, 8, 1),
        ("Course management", 7.6, 9, 1),
        ("Notifications", 8.6, 9.4, 1),
        ("Audit-driven hardening", 9, 10, 2),
        ("Documentation (28 ADRs)", 10, 11, 2),
        ("Academic transcripts (#71)", 11, 12, 2),
    ]
    labels = ["Foundation", "Core modules", "Feature hardening"]
    ypos, ylab = [], []
    for i, (lab, s, e, g) in enumerate(rows):
        y = len(rows) - 1 - i
        ypos.append(y); ylab.append(lab)
        ax.broken_barh([(s, e - s)], (y - 0.34, 0.68),
                       facecolors=CAT[g], edgecolor="white", linewidth=1.5)
    ax.set_yticks(ypos); ax.set_yticklabels(ylab, fontsize=11.5, color=INK)
    ax.tick_params(left=False)
    ax.set_ylim(-0.7, len(rows) - 0.3)
    ax.set_xlim(0, 12.4)
    ax.set_xlabel("Delivery timeline  →", color=INK2)
    ax.set_xticks([])
    ax.spines["left"].set_visible(False)
    handles = [Rectangle((0, 0), 1, 1, fc=CAT[i]) for i in range(3)]
    ax.legend(handles, labels, loc="lower left", frameon=False, fontsize=11.5,
              ncol=3, bbox_to_anchor=(0.0, 1.02))
    _save(fig, "phase-timeline.png")


# ── 4. HMAC signature concept (slide 10) ──────────────────────────────
def hmac_signature():
    fig, ax = plt.subplots(figsize=(11, 5.0))
    ax.set_xlim(0, 12); ax.set_ylim(0, 8); ax.axis("off")

    def box(x, y, w, h, title, sub, fc, ec, tc, sc=INK2):
        _tile(ax, x, y, w, h, fc, ec, radius=0.12)
        ax.text(x + w/2, y + h*0.62, title, ha="center", va="center",
                fontsize=14, fontweight="bold", color=tc)
        if sub:
            ax.text(x + w/2, y + h*0.26, sub, ha="center", va="center",
                    fontsize=11, color=sc)

    def arrow(x1, y1, x2, y2, label=""):
        ax.add_patch(FancyArrowPatch((x1, y1), (x2, y2),
                     arrowstyle="-|>", mutation_scale=18, color=EM[700], lw=2))
        if label:
            ax.text((x1+x2)/2, (y1+y2)/2 + 0.35, label, ha="center",
                    fontsize=11, color=INK2, fontstyle="italic")

    # issue row
    box(0.3, 5.2, 3.1, 2.0, "Bound fields", "number | matricule |\namount | year",
        "white", MUTED, INK)
    box(4.6, 5.4, 2.6, 1.6, "HMAC-SHA256", "keyed by APP_KEY",
        EM[700], EM[800], "white", sc=EM[100])
    box(8.6, 5.2, 3.1, 2.0, "Signature", "stored on the\nimmutable receipt",
        EM[50], EM[600], EM[800])
    arrow(3.4, 6.2, 4.6, 6.2)
    arrow(7.2, 6.2, 8.6, 6.2)
    ax.text(6.0, 7.5, "AT ISSUE", ha="center", fontsize=12,
            fontweight="bold", color=EM[700])

    # verify row
    box(0.3, 1.2, 3.1, 2.0, "Stored fields", "re-read from the\nsame record",
        "white", MUTED, INK)
    box(4.6, 1.4, 2.6, 1.6, "Re-compute HMAC", "same key & payload",
        EM[600], EM[800], "white", sc=EM[100])
    box(8.6, 1.2, 3.1, 2.0, "hash_equals()", "constant-time\ncompare",
        EM[50], EM[600], EM[800])
    arrow(3.4, 2.2, 4.6, 2.2)
    arrow(7.2, 2.2, 8.6, 2.2)
    ax.text(6.0, 3.5, "AT VERIFY", ha="center", fontsize=12,
            fontweight="bold", color=EM[700])
    ax.text(10.15, 0.75, "match → authentic · drift → invalid", ha="center",
            fontsize=11, color=INK2, fontstyle="italic")
    _save(fig, "hmac-signature.png")


# ── 5. Security layers (slide 11) — defense in depth ──────────────────
def security_layers():
    fig, ax = plt.subplots(figsize=(10.5, 5.8))
    ax.set_xlim(0, 12); ax.set_ylim(0, 10); ax.axis("off")
    layers = [
        ("1", "Session authentication", "Fortify — login, verified email", EM[300], EM[900]),
        ("2", "Role middleware  (role:*)", "coarse gate on every route group", EM[400], EM[900]),
        ("3", "Per-resource ownership", "you only ever reach your own records", EM[600], "white"),
        ("4", "HMAC-signed, immutable records", "receipts & transcripts can't be forged or altered", EM[700], "white"),
        ("5", "Append-only audit log · no oracle", "every decision traceable; verify leaks nothing", EM[800], "white"),
    ]
    n = len(layers); band_h, gap, top = 1.45, 0.22, 9.4
    for i, (num, title, sub, fc, tc) in enumerate(layers):
        y = top - (i + 1) * band_h - i * gap
        _tile(ax, 0.9, y, 10.7, band_h, fc, "white", radius=0.1)
        ax.text(1.65, y + band_h/2, num, ha="center", va="center", fontsize=24,
                fontweight="bold", color=tc, alpha=0.85)
        ax.text(2.5, y + band_h*0.62, title, ha="left", va="center", fontsize=15,
                fontweight="bold", color=tc)
        ax.text(2.5, y + band_h*0.24, sub, ha="left", va="center", fontsize=11.5,
                color=tc, alpha=0.92)
    ax.annotate("", xy=(0.45, top - n*band_h - (n-1)*gap), xytext=(0.45, top),
                arrowprops=dict(arrowstyle="-|>", color=EM[700], lw=2.5))
    ax.text(6, 9.75, "A request must clear every layer", ha="center",
            fontsize=13.5, fontweight="bold", color=EM[700])
    _save(fig, "security-layers.png")


# ── 6. Payment standing thresholds (slide 12) — step ──────────────────
def standing_thresholds():
    fig, ax = plt.subplots(figsize=(10.5, 5.4))
    deadlines = ["Oct", "Dec", "Feb", "Apr", "Jun"]
    x = np.arange(len(deadlines))
    required = np.array([150, 300, 450, 600, 750])   # k XAF thresholds
    paid = np.array([150, 300, 300, 500, 750])        # student cumulative
    ax.step(np.append(x, x[-1]+0.5)-0.25, np.append(required, required[-1]),
            where="post", color=MUTED, lw=2, ls="--", label="Required threshold")
    ax.plot(x, paid, color=EM[700], lw=2.5, marker="o", ms=8,
            mfc=EM[700], mec="white", label="Validated paid")
    for xi, r, p in zip(x, required, paid):
        ok = p >= r
        ax.scatter([xi], [p], s=120, color=(GOOD if ok else CRIT),
                   zorder=5, edgecolor="white", lw=1.5)
    ax.fill_between(x, paid, required, where=(paid >= required),
                    color=EM[100], alpha=0.7, step=None)
    ax.fill_between(x, paid, required, where=(paid < required),
                    color="#FEE2E2", alpha=0.9)
    ax.set_xticks(x); ax.set_xticklabels(deadlines)
    ax.set_ylabel("Cumulative tuition (’000 XAF)", color=INK2)
    ax.set_ylim(0, 850)
    ax.grid(axis="y", color=GRID, lw=0.8)
    ax.set_axisbelow(True)
    ax.legend(loc="upper left", frameon=False, fontsize=12)
    ax.annotate("At risk — below threshold\n(deferral required)",
                xy=(2, 375), xytext=(2.2, 130), fontsize=11, color=CRIT,
                arrowprops=dict(arrowstyle="->", color=CRIT))
    _save(fig, "standing-thresholds.png")


# ── 7. Transcript GPA → CGPA (slide 13) ───────────────────────────────
def transcript_gpa():
    fig, ax = plt.subplots(figsize=(10.5, 5.6))
    # demo data: (course, credits, grade, points)
    courses = [("CSC201", 4, "A", 4), ("CSC202", 3, "B", 3), ("CSC203", 3, "C", 2),
               ("CSC204", 4, "A", 4), ("CSC205", 3, "F", 0)]
    xs = np.arange(len(courses))
    pts = [c[3] for c in courses]
    cols = [GRADE[c[2]] for c in courses]
    bars = ax.bar(xs, pts, width=0.62, color=cols, edgecolor="white", lw=1.5)
    for b, (code, cr, g, p) in zip(bars, courses):
        ax.text(b.get_x()+b.get_width()/2, p+0.08, f"{g}", ha="center",
                va="bottom", fontsize=13, fontweight="bold", color=GRADE[g])
        ax.text(b.get_x()+b.get_width()/2, -0.32, f"{code}\n{cr} cr",
                ha="center", va="top", fontsize=10.5, color=INK2)
    # semester + cumulative reference
    sem1, sem2, cgpa = 3.10, 2.29, 2.76
    ax.plot([-0.4, 2.4], [sem1, sem1], color=CAT[1], lw=2, ls="--")
    ax.text(2.45, sem1, f" Sem 1 GPA {sem1:.2f}", va="center", color=CAT[1],
            fontsize=11.5, fontweight="bold")
    ax.plot([2.6, 4.4], [sem2, sem2], color=CAT[1], lw=2, ls="--")
    ax.text(3.5, sem2+0.12, f"Sem 2 GPA {sem2:.2f}", va="bottom", ha="center",
            color=CAT[1], fontsize=11.5, fontweight="bold")
    ax.axhline(cgpa, color=EM[900], lw=2)
    ax.text(4.45, cgpa, f" CGPA {cgpa:.2f}", va="center", color=EM[900],
            fontsize=12.5, fontweight="bold")
    ax.axvline(2.5, color=GRID, lw=1)
    ax.text(0.75, 4.35, "Semester 1", ha="center", color=INK2, fontsize=12)
    ax.text(3.5, 4.35, "Semester 2", ha="center", color=INK2, fontsize=12)
    ax.set_ylim(0, 4.6); ax.set_xlim(-0.7, 5.6)
    ax.set_ylabel("Grade points (4.0 scale)", color=INK2)
    ax.set_xticks([])
    ax.grid(axis="y", color=GRID, lw=0.8); ax.set_axisbelow(True)
    _save(fig, "transcript-gpa.png")


# ── 8. Test-count growth (slide 14) ───────────────────────────────────
def test_growth():
    fig, ax = plt.subplots(figsize=(10.5, 5.0))
    labels = ["Roles", "Login", "Reference", "Profiles", "SAO\ndecisions",
              "Admin", "Audit\nhardening", "Feature\nbacklog", "Transcripts"]
    vals = [90, 104, 207, 246, 327, 359, 440, 682, 707]
    x = np.arange(len(vals))
    ax.plot(x, vals, color=EM[700], lw=2.5, marker="o", ms=8, mfc=EM[700],
            mec="white", zorder=3)
    ax.fill_between(x, vals, 0, color=EM[100], alpha=0.6)
    for xi, v in [(0, 90), (6, 440), (8, 707)]:
        ax.annotate(f"{v}", (xi, v), textcoords="offset points",
                    xytext=(0, 12), ha="center", fontsize=13,
                    fontweight="bold", color=EM[800])
    ax.set_xticks(x); ax.set_xticklabels(labels, fontsize=10.5)
    ax.set_ylabel("Automated tests (Pest)", color=INK2)
    ax.set_ylim(0, 780)
    ax.grid(axis="y", color=GRID, lw=0.8); ax.set_axisbelow(True)
    _save(fig, "test-growth.png")


# ── 9. Test pyramid + CI (slide 15) ───────────────────────────────────
def ci_testpyramid():
    fig, ax = plt.subplots(figsize=(10.5, 5.6))
    ax.set_xlim(0, 12); ax.set_ylim(0, 10); ax.axis("off")
    # proper pyramid: widest base (Unit) at the bottom, narrowest (Browser) on top
    tiers = [(7.6, "Unit — pure logic", EM[300], EM[900]),
             (5.6, "Feature — integration & HTTP", EM[600], "white"),
             (3.9, "Browser · smoke", EM[800], "white")]
    yb, hh, gap = 0.9, 1.45, 0.08
    for i, (w, lab, fc, tc) in enumerate(tiers):
        y = yb + i * (hh + gap)
        ax.add_patch(FancyBboxPatch((6 - w/2, y), w, hh,
                     boxstyle="round,pad=0,rounding_size=0.06",
                     fc=fc, ec="white", lw=2))
        ax.text(6, y + hh/2, lab, ha="center", va="center", fontsize=13,
                fontweight="bold", color=tc)
    ax.text(6, yb + 3*(hh+gap) + 0.35, "707 tests — the quality gate",
            ha="center", fontsize=14.5, fontweight="bold", color=EM[800])
    # CI checks row (top)
    checks = ["ci (8.4)", "ci (8.5)", "quality", "browser"]
    for i, c in enumerate(checks):
        x = 0.5 + i * 2.95
        _tile(ax, x, 8.5, 2.7, 1.0, EM[50], EM[600], radius=0.12)
        ax.text(x + 0.5, 9.0, r"$\checkmark$", ha="center", va="center",
                fontsize=20, color=GOOD)
        ax.text(x + 1.6, 9.0, c, ha="center", va="center", fontsize=12,
                color=EM[800], fontweight="bold")
    ax.text(0.5, 7.8, "Every PR — 4 required checks, green before merge",
            ha="left", fontsize=11.5, color=INK2)
    _save(fig, "ci-testpyramid.png")


if __name__ == "__main__":
    pain_points()
    objectives_modules()
    phase_timeline()
    hmac_signature()
    security_layers()
    standing_thresholds()
    transcript_gpa()
    test_growth()
    ci_testpyramid()
    print("all charts done ->", OUT)
