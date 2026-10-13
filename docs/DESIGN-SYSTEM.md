# Backend Design System

This document is binding for all views under `resources/views/backend/`
as well as for plugin and theme views rendered in the backend.

The rule behind it in one sentence: **A view makes no design decisions of
its own.** It picks components and tokens — colours, spacing, radii and
states have already been decided.

---

## 1. Tokens instead of colour values

Defined in [`resources/css/tokens.css`](../resources/css/tokens.css).

There are two layers. The raw palette (`--color-ink-*`, `--color-brand-*`,
`--color-positive-*` …) is **never** used directly in views. Views use
only the semantic tokens, which describe the *role* a colour
plays:

| Token | Utility | Meaning |
|---|---|---|
| `--canvas` | `bg-canvas` | Page background |
| `--surface` | `bg-surface` | Cards, panels, header |
| `--surface-sunken` | `bg-surface-sunken` | Set-off areas (footers, group headers) |
| `--surface-hover` / `--surface-active` | `bg-surface-hover` | Hover and active state of rows |
| `--line` / `--line-strong` | `border-line` | Borders, dividers / field borders |
| `--fg` / `--fg-muted` / `--fg-subtle` | `text-fg`, `text-fg-muted` | Text, secondary text, hints |
| `--accent` | `bg-accent`, `text-accent` | Primary action, current state |
| `--positive` / `--caution` / `--critical` | `text-positive` … | Status meaning only |
| `--nav-*` | `bg-nav-bg` … | Navigation bar (dark in both themes) |

**Dark mode comes for free.** A new view needs not a single
`dark:` variant — the tokens are redefined under `.dark`. Anyone writing `dark:`
into the markup has most likely taken the wrong path.

Forbidden in views:

```blade
{{-- not like this --}}
<div class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow">

{{-- but like this --}}
<x-ui.card>
```

---

## 2. Components

Under `resources/views/components/ui/`. Each of them has exactly one job.

### Page scaffold

```blade
<x-ui.page :title="__('…')" :lead="__('…')">
    <x-slot:actions>
        <x-ui.button variant="primary" size="md" icon="plus" :href="…">New</x-ui.button>
    </x-slot:actions>

    <x-ui.card>…</x-ui.card>
</x-ui.page>
```

`x-ui.page` also renders the session messages (`x-ui.flash`). Flash markup
**never** belongs in a view.

### Buttons

Four weights, no more:

| Variant | Use |
|---|---|
| `primary` | the **one** main action of a page |
| `secondary` | equal-ranking secondary actions |
| `ghost` | actions in lists and toolbars |
| `danger` / `danger-solid` | destructive; filled only in the confirmation dialog |

**Icon or text?** In list rows icons only, with `:label`; in
page headers and toolbars text only. Across 78 rows,
"Edit Delete" as text would be a wall of words pushing the content columns aside.

**Unavailable actions are disabled, not hidden.** A missing
button shifts the columns of neighbouring rows and makes the list look
restless. `:disabled` plus `:disabled-reason` greys the button out and explains in the
tooltip why:

```blade
<x-ui.button variant="danger" icon="trash-2" :label="__('translation.delete')"
             :disabled="$role->is_system"
             :disabled-reason="__('translation.roles.system_not_deletable')" />
```

This applies to domain locks (system role, home page, protected plugin).
Missing **permissions** still hide the action completely — you should not
see what you may never do.

Without slot content the button becomes square and needs `:label` — this
produces the `aria-label` and the tooltip:

```blade
<x-ui.button variant="ghost" icon="pencil" :label="__('translation.edit')" :href="…" />
```

### Lists instead of tables

There are **no tables** in the backend, and no div imitations of them. Instead,
`x-ui.list`. The component responds via container query to its *own*
width, not the window's — the same list works unchanged on
a full page, in a narrow dashboard column and in the detail panel.

```blade
<x-ui.list>
    <x-ui.list-row :title="$page->title" icon="file-text" :href="route('backend.pages.edit', $page)">
        <x-slot:meta>
            <span>{{ $page->slug }}</span>
            <span>{{ $page->updated_at->diffForHumans() }}</span>
        </x-slot:meta>

        <x-slot:cols>
            <x-ui.list-col :label="__('translation.status')">…</x-ui.list-col>
            {{-- priority="low" hides the column before the list gets tight --}}
            <x-ui.list-col :label="__('translation.created_at')" priority="low">…</x-ui.list-col>
        </x-slot:cols>

        <x-slot:actions>…</x-slot:actions>

        {{-- optional: opens the detail panel instead of navigating --}}
        <x-slot:detail>
            <x-ui.facts>
                <x-ui.fact :label="__('translation.status')">…</x-ui.fact>
            </x-ui.facts>
            <div data-panel-actions>…</div>
        </x-slot:detail>
    </x-ui.list-row>
</x-ui.list>
```

Behaviour rules of the list:

- Separator dots between meta items are set by the CSS, not the markup.
- Actions appear on hover/focus; on touch devices they are always visible.
- A row is either a link (`:href`) **or** a panel trigger (`detail` slot).
- If the list contains `detail` slots, the layout must include `<x-ui.panel />` —
  the backend layout already does.
- With more than ~30 entries, an `x-ui.toolbar` with `data-filter-input`
  belongs above it (see Permissions).
- `x-ui.list-group` is a group heading inside the list that sticks while
  scrolling (`.ui-list-group`).

### Width

The page margin comes from `--spacing-gutter` and is the same for every view — a
view **never** sets its own `py-*`, `mx-auto` or `max-w-*` at the top
level. Every view spans the full available width, without exception; forms
too. Anyone who wants to change the width changes the layout, not a view.

### Two columns

Settings and edit pages with more than one card use two columns instead of a
long stack. There are two patterns:

- **Content + sidebar** — `ui-split` with `ui-split-main` and `ui-split-aside`:
  one column below 64 rem, above that a sidebar of 20–26 rem. Used where one
  side is the main content: users, profile, blog post form, Search settings
  (content types left, general options right), Mail Providers and Languages
  (settings left; provider choice and test mail, or the explanations, right).
- **Two equal, independent columns** — SEO and Security settings: the form is
  `grid grid-cols-[minmax(0,1fr)] items-start gap-4 xl:grid-cols-2`, each column
  a `flex flex-col gap-4` with its cards. Each column stacks on its own, so cards
  of unequal height leave no gaps; below `xl` it is one column.

### More components

| Component | Purpose |
|---|---|
| `x-ui.switch-row` | Yes/no setting as a row: text on the left, switch on the right |
| `x-ui.setting-field` | Field with a default value and a reset button |
| `x-ui.media-picker` | Image selection from the media library (one for every place); `multiple` for multi-select, result via the `media-picker:select` event |
| `x-ui.tile` | Tile for image overviews (media, gallery) — actions in their own bar, never over the image; grid via `.ui-tiles` |
| `x-ui.color` | Colour picker: colour swatch + hex value, linked in both directions |
| `ui-split` (class) | Content + sidebar (`ui-split-main`, `ui-split-aside`), see "Two columns" |
| `x-ui.option-card` | Choice as a card with icon, title, explanation (radio); disabled instead of hidden |
| `textarea[data-code-editor]` | Code editor with syntax highlighting (CodeMirror, loads only when needed) |
| `x-ui.toasts` | Pop-ups for session messages (in the layout, not in views) |
| `x-ui.locale-switch` / `x-ui.translation-note` / `x-ui.translation-source` / `x-ui.translation-status` | Language selection, notice and "Copy from …" in editors, translation status in lists (see "Editing translations") |
| `x-ui.dropzone` | Choose or drop a file |
| `x-ui.theme-toggle` | Light/dark, for signed-in users and guests |
| `x-ui.card` | Surface with border; `flush` for borderless lists |
| `x-ui.stat` | Key figure; the card is the link, no "View all" footer |
| `x-ui.badge` | Status; `tone` = `neutral\|accent\|positive\|caution\|critical` (default `neutral`) |
| `x-ui.note` | Notice/error box, same `tone` scale |
| `x-ui.facts` / `x-ui.fact` | Key values: label above the value, pairs fill the width in columns |
| `x-ui.empty` | Empty state — replaces italic grey text |
| `x-ui.field` + `x-ui.input/select/textarea` | Form field including label, help, error |
| `x-ui.icon` / `x-ui.nav-icon` | Icons (see below) |
| `x-ui.image-field` | Image field with preview, "Media library" and "Remove" (path in a hidden field); `dark` for logos on a dark background |
| `x-ui.avatar` | Profile picture or initials; the colour is tied to the person (`size` = `sm\|md\|lg`) |
| `ui-toolbar-sticky` (class) | Toolbar that stays at the top while scrolling (permission selection) |
| `ui-tab-muted` (class) | Tab without content: visible, but toned down |
| `ui-cards` (class) | Grid of similar cards (each from 26 rem, one column when narrow) — instead of five identical strips stacked (captcha keys) |
| `ui-palette` (classes) | Block palette of the builders: `ui-palette`, `-title`, `-label`, `-list`, `-item`, `-item-accent`, `-sep`; sticky from md, two columns when narrow |
| `x-ui.dropzone compact` | Single-line drop area (icon left, text right) — next to a preview |
| `ui-avatar-field` (class) | Profile picture + drop area + delete in one row (profile, users) |
| `ui-meta-flag` (class) | Highlighted meta item with icon (e.g. "Not in menu") instead of a second badge |
| `ui-log` (class) | Log view; entry header rows coloured by level (`ui-log-head ui-log-error` …) |
| `ui-editor-empty` (class) | Empty state over a workspace until something is loaded (theme editor) |
| `ui-kbd` (class) | Key cap for keyboard shortcuts |

**Tab bars** (`ui-tabs`) scroll sideways when they do not fit;
`ui/tabs-scroll.js` sets `data-overflow="start end"`, and the edge fades out
exactly where more tabs are. The active tab is scrolled into
view on load. No view has to do anything for this.

**On/off in lists** (activating plugins): an `x-ui.toggle` with
`data-autosubmit` in its own form in the status column — state and
control are the same element. No "Enabled" badge plus a
"Disable" button next to it.

**Delete as a bare icon** (`variant="danger"` without text) is neutral at
rest and only turns red under the pointer. With text (page header, dialog)
it stays red.

### Header bar

- **Quick search** (`ui/command.js`): button with `data-command-open`, Ctrl/⌘ + K
  opens it everywhere. Menu items appear instantly — the entries come from
  the rendered navigation, so permissions and plugin menu items are correct
  automatically. From two characters on it also queries the server
  (`data-command-search` = `backend.search`, `App\Services\AdminSearch`):
  settings, pages, media, users and whatever plugins add via an
  `AdminSearchProvider` — grouped and limited to what the user may open.
- **Notifications** as a dropdown (`ui-notify-*`): tinted icon chips,
  unread ones with a dot; on phones aligned to the window edge.
- **Account** with `x-ui.avatar`; the name gives way below 40 rem.

**Utilities beat components.** The component CSS lives in
`layer(components)`, Tailwind utilities above it. A `flex-1` in the markup can
therefore not be overridden from the component CSS. Whatever a component has to change
depending on its state belongs in its own class, not as a utility in the
markup.

### Sign-in pages

Log in, register, forgot/reset password, confirm, verify email
and two-factor use `x-guest-layout` (`backend/layouts/guest.blade.php`):
a single column with logo, form card and links below, a subtle glow in the
background, light and dark via the tokens (`components/auth.css`).
Slots: `title`, `text`, `footer`.

| Partial (`resources/views/auth/partials`) | Purpose |
|---|---|
| `password` | Password field with "Show password", optional link next to the label |
| `password-hints` | Password rules with live checking |
| `status` | Messages after an action (included in the layout) |

Otherwise the normal building blocks apply: `x-ui.field` + `x-ui.input`, `x-ui.toggle`,
`x-ui.button` with `class="auth-submit"` for the main action.

The password rules come from `App\Support\PasswordPolicy` (security
settings) — the partial needs no parameters. `password` takes `id`
and `bag` (error bag) when there are several password fields on one page.

**Profile (`/profile`):** Users allowed into the backend see it in the backend layout; everyone
else in the account area `backend/layouts/account.blade.php` (sign-in surface,
wide, with log out). The website theme does not know the `x-ui` building blocks.

### Alias components

`x-btn`, `x-text-input`, `x-input-label` and `x-input-error` exist for
third-party plugins and only forward to the `x-ui.*` components — **new code
does not use them**. The repository itself does not use `x-btn`.

One exception: where a label stands without an associated field (e.g.
above a group of checkboxes), `x-input-label` is correct —
there is no `x-ui.field` for that.

---

### Colour and type

Calm, but not colourless. Colour sits in a few fixed places:

- **Navigation:** single colour (`--nav-bg`: light Inverys indigo, dark a deeper indigo), active entry as a `--nav-active` surface with a `--nav-indicator` accent edge
- **Content area:** colour shimmer at the top (`--canvas-glow`), scrolls along
- **Icon chips:** `.ui-chip` + `.ui-tint-{accent|violet|teal|positive|caution|critical|neutral}`
  in key figures (`x-ui.stat tone="…"`), card titles (`x-ui.card icon="…" tone="…"`)
  and option cards. `violet`/`teal` only differentiate, `positive`/`critical`
  carry meaning — do not use them for decoration.
- **Banner:** `.ui-hero` for the dashboard greeting, nowhere else

**Text colours:** `text-fg` for content, `text-fg-muted` for secondary text.
`text-fg-subtle` only for icons, placeholders and dividers — as a text colour
it does not reach the required contrast (4.5 : 1) in either mode.

Type in the backend is one step larger than the Tailwind default: `text-sm` = 15 px,
`text-xs` = 13 px (in `backend/app.css`, not in `tokens.css`, because the frontend
loads the tokens too).

---

### Narrow screens

Below 40rem, [`ui/compact.js`](../resources/js/backend/ui/compact.js) kicks in
automatically, without a view having to do anything:

- Page header with several actions: the main action stays, the rest moves
  into a "⋯" menu (the elements are moved, forms keep working)
- Filter bars (`x-ui.toolbar` with a form): search stays, the rest collapses
  behind a filter button; a number shows the active filters
- Tabs (`ui-tabs`) become a swipeable row, key figures (`ui-stats`) 2 × 2

Absolutely positioned elements need a reference point in the scroll area —
that is why `<main>` is `relative`. Otherwise invisible input fields extend
the whole page.

---

## 3. Icons

Lucide, shipped as SVG in `resources/icons/lucide/`, embedded via
`\App\Support\Icon`. They inherit text colour and font size (`1em`).

```blade
<x-ui.icon name="file-text" />
```

A new icon: add the name to [`copy-icons.cjs`](../copy-icons.cjs) and
run `npm run copy-icons`. The files are committed, so an
installation does not need npm.

Bootstrap Icons names (`bi-…`) are translated to Lucide via
[`resources/icons/aliases.json`](../resources/icons/aliases.json)
— only for plugin compatibility, not for new code. **The
Bootstrap font is not loaded in the backend**: an `<i class="bi …">`
is invisible.

Icons created only in the browser (page builder, form builder, page tree)
come from [`resources/js/backend/ui/icons.js`](../resources/js/backend/ui/icons.js)
— the same files, the same alias table:

```js
import { iconEl, iconHtml } from '../ui/icons.js';
button.append(iconEl('trash-2'));
```

In inline scripts and third-party plugins the same is available as `window.uiIcon(name, classes)`.
As a safety net, `icons.js` replaces every inserted `<i class="bi …">`
with the matching SVG — code that later toggles classes on the `<i>` then
has nothing to act on. Your own code therefore always uses `iconEl()`.

Every icon carries `ui-icon` (`display: inline-block`). Without it, Tailwind's
Preflight turns an SVG into a block, and an icon in a heading slides above the
text.

---

## 4. Navigation

Structure, permissions and active state come from
[`App\Support\Navigation`](../app/Support/Navigation.php); the view only renders.
A new menu item is added there, not in Blade.

There are two active states, and they look different:

- `data-current` — exactly this page (surface + accent edge)
- `data-within` — the section you are in (only brighter text)

A parent item **never** becomes `current` while one of its children is, so
parent and child item are never highlighted at the same time.

---

## 5. Permission groups

`\App\Support\PermissionGroup::of('manage-search-settings')` returns `settings`
(a plain `explode('-', $name)[1]` would yield "search" — and for
`api-access` the group "access"). New permissions follow the pattern
`{verb}-{subject}`; if the subject fits an existing group, it belongs
in the `ALIASES` table.

**Permissions checked by the code are locked.** The core permissions (`Permission::CORE`)
and everything a plugin creates via `PermissionManager::createPermission()`
carry `is_system` — in the UI they can be neither renamed nor deleted (buttons
disabled with a reason, the controller rejects). Likewise, system roles keep
their name, and your own account stays active and keeps the admin role.
Display names of the bundled roles: `$role->label` (`translation.roles.names.*`).

---

## 6. Checklist for a backend view

- [ ] `<x-ui.page>` as root, title and actions in the slots
- [ ] no own `py-*`, `mx-auto` or `max-w-*` at the top level
- [ ] no `bg-white`, `bg-gray-*`, `text-gray-*`, `dark:*` in the markup
- [ ] no hand-copied flash markup
- [ ] lists via `x-ui.list`, no `role="table"` construction
- [ ] icons via `x-ui.icon` or `iconEl()` in JS, no `<i class="bi …">`
- [ ] no own toast/message implementation — `window.uiToast`
- [ ] controller reads switches with `$request->boolean()`
- [ ] empty state via `x-ui.empty`
- [ ] form fields via `x-ui.field`
- [ ] exactly one `primary` action per page
- [ ] checked in both themes — by switching, not by reading

### Applies to plugins too — but only in the backend

Plugin views in the backend follow the same rules; they inherit the layout and
must use the tokens, otherwise they stick out in dark mode.

**The boundary is sharp and important:** the tokens are only defined in the
backend stylesheet. Anyone writing `bg-surface` or `text-fg` in a
frontend view creates a class there that does not exist — the
element loses its styling on the public website without
any test failing.

Do not touch:

- `plugins/*/views/frontend/**`
- Public output of a plugin (cookie banner, captcha fields,
  form fields, frontend blocks)
- `themes/**`

A bulk replacement run over "all views" is therefore always wrong. It must
be limited to `resources/views/backend/**` and the plugins' admin views.

A plugin registers its menu item with `order` so that it sits in the right
place instead of being appended at the end:

```php
$this->extensions()->registerSidebarItem([
    'route' => 'backend.activity-log.index',
    'icon' => 'activity',      // Lucide name, not bi-…
    'section' => 'system',
    'order' => 25,             // right after "Logs" (20)
]);
```

### Form width

`ui-grid` instead of a fixed column count: a wrapping flex row, each field
grows from 18 rem (`ui-grid-dense`: 13 rem). The row always fills
completely — a single field takes the full width, two share it,
a third one that no longer fits gets the next row to itself. This way
no empty columns appear. A field that should always stand alone
gets `ui-grid-full`.

A **single** `x-ui.switch-row` stands without `ui-choices` — the grid is
meant for lists; a single switch inside it becomes half width and its
hint wraps tightly.

### Form helpers (attributes)

| Attribute | Effect |
|---|---|
| `data-requires="field"` / `"field=value"` / `"!field"` / `"!field=value"` | Greys out dependent fields while the main switch is off (`inert` + `ui-inactive`, **not** `disabled` — the values are still submitted). Disabled instead of hidden. `value` also works with option cards (`x-ui.option-card`, the selected option is compared). |
| `data-slug-field` | Address field: cleans up input (umlauts → ae/oe/ue); follows the title only when creating |
| `data-count-max="160"` | Character counter below the field, red when exceeded |
| `data-copy="#id"` | Button copies the target's text to the clipboard (with a pop-up) |
| `data-has-error` | On a tab (`ui-tab`): red dot for validation errors in that tab; the view sets it and opens the tab with errors itself (page editor) |
| `data-confirm="…"` | On a form: asks before submitting, in the admin-styled dialog (`window.uiConfirm()`) instead of the browser's box |

Save button in the page header: form with `id`, button with `form="…"`. That
keeps the main action visible on long forms without scrolling to the end.

Input fields in cards are always 100 % wide (base rule in
`base.css`); only filters in `ui-toolbar` stay content-width.

### Messages

Success, status and warning messages from the session appear as a pop-up
at the bottom right and disappear after five seconds (paused while the pointer
rests on them). `x-ui.toasts` in the layout handles this — a view writes
nothing for it. Messages without a page change: `window.uiToast(text, 'positive'|'caution'|'critical')`.
Breeze-style keys (`->with('status', 'profile-updated')`) are translated by the
component via `translation.flash.<key>`; without an entry nothing appears —
never the raw key.

**Validation errors are not pop-ups.** They stay in the page flow via `x-ui.flash`,
because you need to read them while correcting.

### Yes/no settings

For on/off there is `x-ui.toggle`, not a select with two options. The
component also submits the off state via a hidden field,
so that server-side `required|in:on,off` rules apply.

**There are no checkboxes in the backend** — not even in
selection lists. Single values and lists use the same building block:

```blade
{{-- single value --}}
<x-ui.switch-row name="is_active" :label="…" :checked="…" on-value="1" off-value="0" />

{{-- selection list: no off field, value = ID, grid via ui-choices --}}
<div class="ui-choices">
    <x-ui.switch-row name="roles[]" multiple :bleed="false" :on-value="$role->id" … />
</div>
```

The whole row is the label; a click on the text toggles. `ui-choices`
fills columns with `auto-fill` so a single entry is not stretched across the
full width. Switches created in JavaScript use the same
markup (`label.ui-toggle > input.ui-toggle-input + span.ui-toggle-track`).

Consequence for the controller: `$request->has('field')` is therefore **always** true.
Read it with `$request->boolean('field')`.

Three states (use default / on / off) remain a select.

### No directives in the component tag

```blade
{{-- wrong: the tag is not compiled and ends up raw in the HTML --}}
<x-ui.toggle name="a" @if($x) data-field="a" @endif />

{{-- right: a null attribute is left out of the attribute list --}}
<x-ui.toggle name="a" :data-field="$x ? 'a' : null" />
```

This produces no error and no wrong status code — the element is simply
missing. `check-widths.py` reports such pages as `RAW`.

### Editing translations

With the multilingual plugin, every editor for translatable content gets
a language selector. All editors behave the same:

- `x-ui.locale-switch` in the page actions; the selection is in the address
  (`?lang=de`), and saving uses the hidden `_lang` field. Without the plugin
  or with only one language, the component does not appear.
- `x-ui.translation-note` at the top of the form says which version is being edited.
- Translatable fields show the translation's value, or, when empty, the original as a
  placeholder. Empty means "show the original" — not "show empty".
- Fields that apply to all languages (status, parent, images, colours) are
  **disabled, not hidden** (`$i18n->shared`) — you should see that they
  exist. Whatever can only be changed in the original and carries no meaning
  (revisions, image upload, custom CSS) is omitted.
- The block editor gets the original's blocks as `defaultBlocks`;
  "Copy original" copies them as a starting point.

The values come from `\App\Support\EditLocale::form($model)` (`value()`,
`placeholder()`, `shared`); the controller saves via
`Translations::saveFromRequest()` and leaves the original untouched.

Lists of translatable entries show the status with
`<x-ui.translation-status :model="$page" />` in the meta area of the row:
filled codes are translated, outlined ones are still missing. The component loads once per
model type per request and only appears with the multilingual plugin.

### Texts

Every visible text goes through the language files — including `aria-label`,
`title`, placeholders and texts generated by JavaScript.

- Backend scripts get texts from the layout (`ui-i18n`, group
  `translation.script`): `import { t } from './ui/i18n.js'` or
  `window.uiT('key', 'Fallback')`. Inline scripts receive them via `@json`.
- Labels from `plugin.json`/`theme.json` are English and are output with
  `__()` — they are translated in the language pack (`de.json`).
- Dates only via `format_date()`, `format_datetime()`,
  `format_date_long()`; never `->format('d.m.Y')`.
- Permissions show `PermissionGroup::permissionLabel()`, with the technical
  name in small print next to it.

---

## 7. Checks

```bash
php artisan view:cache && bash bin/lint-views.sh   # compiles every Blade file, reports syntax errors
bash bin/lint-inline-js.sh     # checks every inline script block with node --check
python3 bin/audit-views.py     # rule violations in the markup (--strict: exit code 1)
python3 bin/check-widths.py    # measures in the delivered HTML whether the content runs full width
node bin/check-browser.cjs     # real browser, desktop + phone (see below); also npm run check:browser
php bin/check-translations.php         # every key in English and in every language pack
python3 bin/check-hardcoded-text.py    # visible text without __()
python3 bin/check-german.py            # no German outside translation files (CI and release gate)
```

For texts created only at runtime (menu items, widgets, plugin names)
there is also `CMS_LOG_MISSING_TRANSLATIONS=true` in `.env`: when
clicking through in another language, every untranslated text ends up in
`storage/logs/missing-translations.log`.

All of them are mandatory after a larger change, and each one catches something
the others do not see:

- A broken Blade expression otherwise only shows up when someone opens exactly that
  one page.
- A torn JS string literal is invisible to `php -l`. The script block
  aborts in the browser and takes everything in the same block down with it — that is how
  the theme switcher once broke even though the page was delivered cleanly.
- **A 200 status code says nothing about whether a page looks right.**
  Views can render without errors and still sit at half width.
  That is exactly what `check-widths.py` is for: it measures the rendered HTML, not the
  source. Its page list comes from `php artisan route:list` — a hand-maintained
  list misses exactly the pages nobody thinks of.
  It also reports raw `<x-…>` tags in the delivered HTML.
- `check-browser.cjs` opens every page in Chromium (Playwright), on desktop
  and at 390 px width. It reports JavaScript errors, pages that scroll
  as a whole, content overflowing sideways, invisible Bootstrap icons and
  raw `<x-…>` tags — as well as clipped fields and buttons (outside on the
  right, even when an `overflow: hidden` silently hides them), text that
  runs out of buttons, badges and tabs, and raw translation keys
  and JS leftovers ("undefined") in text, `title`, `aria-label` and placeholders.
  Without signing in, it additionally checks the sign-in pages, home page, search and the
  404 page, also under `/de/…` if that language exists (`GUEST_LOCALES`,
  default `de`). Page paths as arguments check single pages; `SHOTS=<dir>` adds
  screenshots.
- All page checkers (`lint-inline-js.sh`, `check-widths.py`,
  `check-browser.cjs`) get their page list from `php artisan route:list` and
  need a logged-in session in `$COOKIE_JAR` (default `/tmp/inverys-cookies.txt`;
  `BASE_URL` for another address). A hand-maintained list once hid a broken
  form builder because it did not know the plugin pages.
- None of the tools sees what something looks like, though. After
  changes to icons, builder scripts or base CSS rules, a look
  in the browser is part of the job — in both themes. That is how a dropdown arrow in every
  text field and the invisible builder icons were caught.

Quick self-test:

```bash
grep -nE "dark:|bg-white|bg-gray-|text-gray-|role=\"table\"|<i class=\"bi " resources/views/backend/…
```

If this returns hits, the view does not follow the design system yet.
