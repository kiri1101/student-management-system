# SchuLyf — project presentation deck

A 15-slide academic-defense presentation for the SchuLyf Student Management
System. Built the same way as [`../report/`](../report): scripted and
reproducible, in the emerald brand.

**Deliverable:** [`build/SchuLyf-Presentation.pptx`](build/SchuLyf-Presentation.pptx)
(native, editable) — with [`build/SchuLyf-Presentation.pdf`](build/SchuLyf-Presentation.pdf)
for quick viewing.

## What's here

| Path | What |
|---|---|
| `build_deck.py` | Assembles the 15 slides (python-pptx) — the master layout, titles, footers, figure placement. |
| `make_charts.py` | Generates the 9 brand charts (matplotlib) → `charts/`. dataviz-validated emerald palette. |
| `uml/*.puml` | StarUML-style UML sources (PlantUML): class, use-case, crow's-foot ER, 3 sequences, activity, state machine, component/deployment. `_style.puml` is the shared emerald skin. |
| `uml/*.png` | Rendered diagrams (checked in so the deck builds without re-rendering). |
| `charts/*.png` | Rendered charts. |
| Screenshots | Reused from `../report/screenshots/`. |

## Rebuild

```bash
# 1. charts
python make_charts.py

# 2. UML diagrams (needs Java; PlantUML jar is not versioned — fetch once)
curl -L -o tools/plantuml.jar \
  https://github.com/plantuml/plantuml/releases/download/v1.2025.4/plantuml-1.2025.4.jar
cd uml && java -jar ../tools/plantuml.jar -tpng *.puml && cd ..
#   (Smetana layout is built in, so graphviz/dot is NOT required.)

# 3. assemble the .pptx
python build_deck.py
```

`build_deck.py` requires `python-pptx` and `Pillow`; `make_charts.py` requires
`matplotlib`.

## Cover

Slide 1 carries `«placeholders»` (presenter, institution, department/course,
supervisor, date) — edit them in PowerPoint or in `build_deck.py`
(`title_slide()`), then re-run `python build_deck.py`.
