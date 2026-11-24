# Mega Builder — 7-Stage Architecture & Development Blueprint

> **Living Architecture Document** — Maintained step-by-step using the `/pipeline-mentor` 7-Stage Pipeline.

---

## Equipped Skills & Plugins

### Workspace Skills Downloaded from `agentic-awesome-skills` (`.agents/skills/`)
- **[php-pro](.agents/skills/php-pro/SKILL.md)**: Idiomatic PHP 8.1–8.4 OOP, strict typing, generators, and SPL structures for high-performance PrestaShop 8.0–9.2+ backend code.
- **[design-ux](.agents/skills/design-ux/SKILL.md)**: Interactive UI heuristic evaluation (Nielsen's 10 + Fitts's Law + progressive disclosure) for an intuitive drag-and-drop visual studio.
- **[deterministic-design](.agents/skills/deterministic-design/SKILL.md)**: Reproducible 3-viewport layout & spatial measurements (`1440px`, `768px`, `390px`) to prevent overflow and clipping.
- **[frontend-security-coder](.agents/skills/frontend-security-coder/SKILL.md)**: Client-side & DOM security patterns, XSS prevention, and HTML/CSS/SVG sanitization.

### Active Antigravity Plugins & Built-In Skills
- **`prestashop-qa-simulator`**: Full-suite Chrome DevTools QA simulator for PrestaShop Back-Office, 4 Sub-Studios, 15 Page Template Sections, 3-viewport responsive matrix, and non-destructive security fuzzing.
- **`modern-web-guidance`**: Modern HTML/CSS/JS best practices (Container Queries, CSS Grid/Flexbox, Popover/Dialog APIs, Core Web Vitals).
- **`generative_ui`**: Interactive visual prototypes and QA scorecards directly in chat.

---

## Stage 1: Problem & Requirements (LOCKED IN ✅)

### 1. Target Problem & Vision
Standard PrestaShop themes lock merchants into rigid Smarty/Twig template files. **Mega Builder** turns the entire PrestaShop storefront into a magnetic visual canvas where store owners can drag, drop, style, and publish full-site customizations without touching code.

### 2. Locked Scope Decisions
- **PrestaShop Version Window:** **PrestaShop 8.0 to 9.2+** (Modern PHP 8.1–8.4 & Symfony 4.4 / 6.4+ compatibility bridge).
- **Customization Coverage:**
  - **All 15 Storefront Page Template Sections:** `header`, `homepage`, `product`, `category`, `footer`, `cms`, `account`, `prices_drop`, `new_products`, `best_sales`, `search`, `checkout`, `manufacturers`, `page_404`, `custom`.
  - **4 Dedicated Component Sub-Studios:**
    1. **MegaMenu Builder** (multi-column department menus, promo cards, badges)
    2. **Sliders Builder** (hero carousels, autoplay, per-slide CTAs)
    3. **Cards Builder** (reusable custom product miniature cards & category cards)
    4. **Accordion Builder** (FAQ & product specification collapsible blocks)
  - **Dynamic Store Tags:** Placeholders (e.g., `{{mb:product_name}}`, `{{mb:product_price}}`, `{{mb:cart_count}}`) that automatically bind to live PrestaShop database entities at render time.
- **Workflow Accelerators (MVP):**
  - **Visual Drag-and-Drop Canvas + 1-Click Starter Templates** (No external AI API dependency in the MVP — 100% deterministic, zero API cost, instant offline/online reliability).

### 3. Core Merchant User Flows (MVP)
1. **Flow 1 — Browse & Launch from Templates Dashboard:** Merchant opens *PrestaShop Back-Office → Mega Builder*, filters by any of the 15 page sections (or opens a Sub-Studio), and clicks *"New Template"* or *"Start from 1-Click Blueprint"*.
2. **Flow 2 — Build & Style in the Visual Studio:** Merchant drags widgets or Sub-Studio components from the Left Library onto the Center Live Canvas, edits *Content / Style / Advanced* properties in the Right Inspector, and toggles between **Desktop (`1440px`)**, **Tablet (`768px`)**, and **Mobile (`390px`)** to fine-tune responsive overrides.
3. **Flow 3 — Configure Global Design System:** Merchant sets site-wide Brand Colors, Typography, Container Widths, and Studio UI Theme (Light/Dark) once in Global Settings so every template inherits consistent tokens.
4. **Flow 4 — Preview, Save Draft, or Publish Live:** Merchant clicks *"Preview"* to verify the page with real store products, saves a revision history snapshot (`Draft` or `Active`), and publishes to the storefront hook/route in one click.

### 4. MVP vs. Phase 2+ Boundary
| Included in Day-1 MVP (Must-Have) | Reserved for Phase 2+ (Nice-to-Have Later) |
| :--- | :--- |
| PrestaShop 8.0–9.2+ compatibility adapter | Legacy PrestaShop 1.7.x PHP 7.4 polyfills |
| Visual Drag & Drop Studio + 3-Tab Inspector (Content, Style, Advanced) | Built-in AI Template & Image Generation (Gemini API Co-Pilot) |
| All 15 Page Sections + 4 Component Sub-Studios (MegaMenu, Slider, Card, Accordion) | A/B Split Testing & Conversion Heatmaps |
| 3-Tier Responsive Device Overrides (`1440px`, `768px`, `390px`) | Multi-user real-time collaborative editing (Google Docs style) |
| Dynamic Store Tags, Revision History (Undo/Restore), & 1-Click Starter Blueprints | Third-party marketplace for community widget packs |
| Built-in Media Manager (with SVG sanitization) & Global Design System (`DESIGN.md` import/export) | Custom GSAP scroll-timeline keyframe animation editor |

---

## Stage 2: System Architecture & Tech Stack (LOCKED IN ✅)

### 1. Locked Architectural Decisions
- **System Pattern:** **Modular Monolith PrestaShop Module** — Self-contained 1-click installable PrestaShop module organized internally into clean PSR-4 domain services (`src/`).
- **Backend Runtime & Compatibility Bridge:** **PHP 8.1–8.4 (PSR-4) + Version-Agnostic Controller & Hook Bridge** supporting PrestaShop 8.0 through PrestaShop 9.2+ (Symfony 4.4 to 6.4+).
- **Frontend Visual Studio (Back-Office):** **Vue 3 (Composition API) + TypeScript + Vite SPA** mounted inside PrestaShop Back-Office.
- **Storefront Output (Front-Office):** **Zero-Framework Server-Rendered Semantic HTML5 + Scoped CSS Variables + Lightweight Vanilla JS** for interactive widgets (MegaMenus, Sliders, Accordions).

---

## Stage 3: Data Modeling & Storage (LOCKED IN ✅)

### 1. Locked Storage & Schema Decisions
- **Database Paradigm & Engine:** **Hybrid Relational SQL + Structured JSON Document Columns** inside PrestaShop's built-in **MySQL / MariaDB** database (`utf8mb4`).
- **Database Access Layer:** **PrestaShop `ObjectModel` + Typed PSR-4 Repositories (`DbQuery`)** for 100% compatibility across PrestaShop 8.0 to 9.2+ without Doctrine annotation/attribute conflicts.
- **Core Schema (4 Tables + 2 Configuration Keys):**
  - `PREFIX_mb_template`: Unified table (`entity_kind`: `page_template` | `sub_component`) covering all 15 Page Sections and 4 Sub-Studios (`megamenu`, `slider`, `card`, `accordion`), with `hook_name`, `scope_rule`, `scope_targets`, `active` (`0` = Draft, `1` = Published), and `priority`.
  - `PREFIX_mb_template_lang`: Multilingual (`id_lang`) storage for `title`, `layout_json` (Vue Studio source widget tree), `compiled_html`, and `compiled_css`.
  - `PREFIX_mb_template_shop`: Multistore (`id_shop`) mapping and per-shop activation state.
  - `PREFIX_mb_revision`: Rolling history (last 25 revisions per template) storing `snapshot_json`, `id_employee`, and `label` for 1-click Undo/Restore.
  - `ps_configuration`: `MEGABUILDER_GLOBAL_SETTINGS` (Design tokens, typography, colors, container widths, page rules, `DESIGN.md`) and `MEGABUILDER_STUDIO_THEME` (Light/Dark mode, density, branding).
- **Storefront Caching Strategy:** **Write-Time Pre-Compilation (`compiled_html` + `compiled_css` + static CSS file cache)** when saving in Back-Office, paired with a `< 2 ms` fast-pass runtime resolver for Dynamic Store Tags (`{{mb:...}}`).

---

## Stage 4: API & Communication (LOCKED IN ✅)

### 1. Locked API & Validation Decisions
- **API Communication Style:** **PrestaShop Action-Routed JSON REST/RPC Controller** (`AdminMegaBuilderAjaxController` in Back-Office + `MegaBuilderPreviewModuleFrontController` on Storefront), accessed via a typed TypeScript `apiClient.ts` wrapper.
- **Canvas & Preview Protocol:** **60 FPS Instant Reactive Vue 3 Canvas** for drag-and-drop and style sliders + **On-Demand PHP Storefront Preview (`previewTemplate`)** for 100% accurate PHP compilation and live Dynamic Tag resolution.
- **Standard Data Envelope:** Unified `{ ok, code, message, data, errors }` JSON response contract with standard HTTP status codes (`200`, `400`, `403`, `404`, `422`).
- **Dual-Layer Input Validation:** TypeScript schema guards & idempotency keys on the client + strict PSR-4 PHP `PayloadValidator` enforcing section allowlists, clamping numeric boundaries (`-99999`, `NaN`, `1e308`), and rejecting malformed JSON before database writes.

---

## Stage 5: Authentication & Security (LOCKED IN ✅)

### 1. Locked Security & Authorization Decisions
- **Authentication & CSRF Protection:** **PrestaShop Native Employee Session + Per-Action `X-MB-CSRF-Token` Header Verification** (immediately returning HTTP `403 Forbidden` on missing or mutated tokens) + **15-Minute HMAC-SHA256 Signed Preview Tokens** for viewing unpublished Draft (`active = 0`) templates on the storefront.
- **Role-Based Access Control (RBAC):** Mapped directly to PrestaShop Employee Profile permissions (`VIEW`, `ADD`, `EDIT`, `DELETE`).
- **5-Shield Sanitization & Security Hygiene:**
  1. **CSRF Shield:** Strict token check on every mutating `POST` action.
  2. **HTML/XSS Shield:** Client-side **DOMPurify** + Server-side **`HtmlSanitizer`** stripping `<script>`, `javascript:` URLs, and inline `on*` event attributes (`onload`, `onerror`, etc.).
  3. **CSS Breakout Shield:** Server-side **`CssSanitizer`** stripping `</style>`, `<script>`, and unsafe CSS expressions.
  4. **Media & SVG Shield:** Server-side **`SvgSanitizer`** stripping embedded scripts/`<foreignObject>`/`on*` handlers from uploaded `.svg` files, paired with strict MIME and path-traversal checks.
  5. **Framing & Secret Shield:** `X-Frame-Options: SAMEORIGIN` / `CSP: frame-ancestors 'self'` on preview routes and zero hardcoded store URLs or credentials.

---

## Stage 6: UI/UX & State Management (LOCKED IN ✅ — Imperative Responsiveness, Full 77-Widget Library & Zero-Friction Workflow)

### 1. Imperative 3-Tier Responsive Engine (`1440px` Desktop / `768px` Tablet / `390px` Mobile)
- **Device-Badge Property Inheritance:** Every layout, spacing, typography, and alignment control in the Inspector has a `🖥️ Desktop | 📱 Tablet | 📲 Mobile` badge. Changes in Tablet or Mobile only override that breakpoint down the CSS cascade without breaking Desktop.
- **Mobile-First Structural Controls:**
  - **Column Stacking & Reverse Column Order on Mobile/Tablet** (so alternating Image/Text rows on desktop stack cleanly as Image-top / Text-bottom on mobile).
  - **Per-Device Visibility Toggles** (*Hide on Desktop*, *Hide on Tablet*, *Hide on Mobile*).
  - **Fluid `clamp()` Typography & Zero-Overflow Containers** (`max-width: 100%`, automatic grid column collapse, touch targets `>= 44×44px`).
- **Studio UI Self-Responsiveness:** The Back-Office Visual Studio itself adapts across `1440×900`, `768×1024`, and `390×844` in both **Light** and **Dark** modes (auto-collapsing Left/Right panels into slide-over drawers on smaller screens).

### 2. Full 7-Category Widget & Template Library (Inspired by Creative Elements — 65+ Widgets)
1. **Basic & Layout Widgets (11):** Flex/Grid Container, Inner Section / Columns, Heading, Text Editor, Image, Video, Button, Divider, Spacer, Icon, Google Maps.
2. **General Content Widgets (15):** Accordion, Toggle, Tabs, Alert, Counter, Progress Bar, Icon Box, Icon List, Image Box, Image Carousel, Image Gallery, Social Icons, Star Rating, Testimonial, Custom HTML / Shortcode.
3. **Premium & Conversion Widgets (14):** Creative Hero Slider, Animated Headline, Call To Action (CTA), Countdown Timer, Flip Box, Image Hotspot ("Shop the Look" interactive pins), Contact Form, Email Subscription, Testimonial Carousel, Articles (CMS/Brand/Supplier Grid), **PrestaShop Module Widget** (embed any third-party PrestaShop module/hook inline), **Template / Sub-Component Widget** (embed saved templates or Sub-Studio components), Trust & Review Badges, Lightbox Trigger.
4. **Site, Header & Footer Widgets (10):** Shop Logo, Shop Title, Nav Menu / MegaMenu, AJAX Live Search Bar, Shopping Cart (Mini-Cart Drawer/Dropdown), User Account Menu, Language Selector, Currency Selector, Breadcrumb, Page Title.
5. **Catalog Widgets (6):** Product Grid, Product Carousel, Single Product Box, Category Grid, Category List, Category Tree Links.
6. **Product Detail Page Widgets (10):** Product Name, Product Price & Discount Badge, Product Image Zoom Gallery, Add to Cart (Quantity + Variant Swatches), Short & Full Description, Stock / Availability Badge, Product Variations, Rating & Reviews Block, Product Meta (SKU/ISBN/Brand), Social Share Buttons.
7. **Category Listing & Customer Account Widgets (11):**
   - *Category/Listing:* Faceted Search Filters, Active Filters Bar, Category Description, Additional Description, Sort Order Dropdown, Pagination, Listing Info Counter.
   - *Customer/Account:* Login Form, Register Form, Password Recovery Form, Account / Back Navigation Links.
8. **Template Library & Reuse System:**
   - **Pre-Built Starter Blueprints & Blocks** for all 15 Page Sections & 4 Sub-Studios.
   - **My Saved Templates:** Insert as an **Independent Copy** (editable inline) OR as a **Global Live Shortcode/Sub-Component** (edit once, updates everywhere).
   - **1-Click `.json` / `.zip` Import & Export** + **`DESIGN.md` Design System Import/Export**.

### 3. Zero-Friction PrestaShop Onboarding & Workflow Accelerators
- **Zero-Config 1-Click Install:** Auto-registers all hooks, detects existing theme colors/fonts as initial global tokens, and leaves the live storefront untouched (all starter templates initialize safely as `Draft`) until the merchant clicks **Publish**.
- **Direct "✏️ Edit with Mega Builder" Entry Points Everywhere:**
  - Injected action button inside native PrestaShop Back-Office edit forms (*Products*, *Categories*, *CMS Pages*).
  - **Front-Office Floating Admin Bar** shown to logged-in store admins while browsing the storefront, with 1-click links to edit the current page's **Header**, **Page Body**, or **Footer** directly.
- **Built-In Navigator (`Ctrl+L`) & Quick Finder (`Ctrl+K` / `Ctrl+I`):**
  - **Navigator Tree:** Hierarchical outline (`Container → Column → Widget`) with drag-to-reorder, visibility toggle, and layer renaming.
  - **Quick Finder Command Palette:** Instant search to jump between any template, product, category, or global setting without leaving the Studio.
- **Keyboard Shortcuts Cheat Sheet (`Ctrl+?`):** Built-in modal showing all productivity shortcuts (`Ctrl+Z` Undo, `Ctrl+Y` Redo, `Ctrl+S` Save, `Ctrl+P` Preview, `Ctrl+M` Responsive Mode).

---

## Stage 7: Testing, Deployment & DevOps (LOCKED IN ✅)

### 1. Locked Testing, Packaging & Deployment Decisions
- **Distribution Model:** **Pre-Compiled Self-Contained `.zip` Archive (`dist/megabuilder.zip`)** containing compiled `views/dist/` assets, PSR-4 PHP classes, and automated `index.php` directory guards—ready for 1-click upload in any PrestaShop 8.0–9.2+ Back-Office or automated SSH deployment.
- **3-Tier Automated Verification:**
  1. **Tier 1 (Unit & Type Safety):** `npm run typecheck` (`vue-tsc`) + automated tests for all 77 Widget Schemas, Undo/Redo Store, `PayloadValidator`, `HtmlSanitizer`, `CssSanitizer`, `SvgSanitizer`, and `LayoutCompiler`.
  2. **Tier 2 (Package Integrity):** `scripts/verify-module.mjs` checking `php -l` syntax, `index.php` security guards in all subdirectories, and `.zip` structure.
  3. **Tier 3 (Live E2E & QA Simulator):** Live deployment via SSH to the configured target server + Chrome verification across Desktop (`1440×900`), Tablet (`768×1024`), and Mobile (`390×844`) in Light and Dark modes.
- **Secret Hygiene & Data Safety:**
  - All environment/server targets stored strictly in gitignored `.agents/qa-targets.local.json` (never committed to repo files).
  - Rolling 25-revision database history (`PREFIX_mb_revision`), 1-click `.json`/`.zip` backup export/import, and non-destructive module reset/upgrade hooks.
