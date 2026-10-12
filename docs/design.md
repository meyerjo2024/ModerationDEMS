# Design system

The interface follows the *DJI-style* design spec supplied with the project (a flat, photography-light showroom look) in the official **CPUT colours**.

| Idea in the spec | How it is applied here |
|---|---|
| White canvas, pale grey bands (`#f7f9fa`, `#f5f5f5`) | Page `--canvas`, panels `--canvas-soft`, dashboard hero and login side panel `--fog` |
| One action colour, every action a pill | **CPUT Dark Blue `#006699`** for primary pills, active states and links; Mid Blue `#0099CC` for secondary highlights; Sea Blue `#66CCCC` on dark surfaces |
| Secondary actions are 1px ink outline pills | `btn-secondary`; the tinted companion (“Add to Cart” style) is `btn-brand` |
| Open Sans, tight tracking (−0.02em body, −0.03em headings), weights 400/500/600 | Self-hosted via `@fontsource-variable/open-sans`; set in `resources/css/app.css` |
| Ink at stepped opacities (`#262626`, `#595959`, `#8c8c8c`) | `--ink`, `--ink-2`, `--ink-3` |
| Flat surfaces: 1px rules, 8px corners, no shadow; shadows only for floating things | `card`, `card-soft`; `shadow-lift` (0 8px 16px at 10%) only on menus, drawers and dialogs |
| White 64px navigation bar with a hairline | `glass-header` / `app-layout` |
| Orange rank labels | Not used — the CPUT palette has no orange; status colours (amber, red, green) stay semantic |

All tokens live at the top of `resources/css/app.css` (the `@theme` block and `:root`), so the palette can be changed in one place. A dark theme follows the system setting.
