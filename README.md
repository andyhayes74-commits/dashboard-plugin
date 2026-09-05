# Dashboard Plugin

A lightweight WordPress plugin that adds an Elementor widget for displaying live figures from a published Google Sheet.

The first use case is a public-facing impact statement such as:

> So far we have saved  
> **194 KG**  
> Of food from landfill

The top text, live value and bottom text are independently configurable and independently styled inside Elementor.

## Project status

Planning and specification stage.

The README is the working specification for the first build.

## Goal

Create a reusable Elementor widget that:

1. Retrieves a value from a public Google Sheet.
2. Displays the value between two predetermined text blocks.
3. Allows each text block and the value to be styled independently.
4. Uses server-side fetching and short-term caching.
5. Fails gracefully if the Google Sheet is temporarily unavailable.
6. Does not require Google credentials or expose credentials to site visitors.

## Expected output

Example:

```text
So far we have saved
194 KG
Of food from landfill
```

Recommended Google Sheet setup:

- Keep the detailed/master sheet private.
- Create a separate `WebsiteData` tab or separate output spreadsheet.
- Use formulas such as `IMPORTRANGE`, `QUERY` or `FILTER` to expose only the required non-sensitive result.
- Publish only the output data to the web.
- Store the numeric value as a number, for example `194`, rather than `194KG`.
- Add the unit in the Elementor widget as a suffix, for example ` KG`.

## Initial scope

### WordPress plugin

- Standard installable WordPress plugin.
- Main plugin directory: `dashboard-plugin`.
- No dependency on Bricks.
- Designed for Elementor.
- Compatible with Elementor Free where possible; do not require Elementor Pro-only APIs for the first version.
- No Google OAuth or service-account integration in the initial version.

### Google Sheets source

The initial version will use a published Google Sheet or published CSV/Google visualisation endpoint.

The plugin must:

- Fetch data server-side with WordPress HTTP functions such as `wp_safe_remote_get()`.
- Support a spreadsheet identifier or published sheet URL.
- Support worksheet/tab selection.
- Support A1-style cell references such as `B2`.
- Read a single value for each widget instance.
- Validate the response before displaying it.
- Avoid exposing the source URL unnecessarily in frontend JavaScript.
- Never require a Google API key for the public-sheet version.

The implementation should allow the source configuration to be stored globally in WordPress, with optional per-widget overrides if practical.

### Elementor widget

Widget name: **Dashboard Metric**

Elementor editor sections:

#### Content

- Before text
  - Default: `So far we have saved`
- Google Sheet source
  - Use global source
  - Optional source override
- Worksheet/tab
- Cell reference
  - Example: `B2`
- Value prefix
  - Example: `£`
- Value suffix
  - Example: ` KG`
- Number formatting
  - Decimal places
  - Thousands separator
  - Decimal separator
- After text
  - Default: `Of food from landfill`
- Fallback message
  - Example: `Data currently unavailable`
- Optional last-updated display

#### Style

Each of the three output areas must have separate Elementor controls:

1. Before text
2. Live value
3. After text

Each area should support, where Elementor provides the relevant control:

- Typography
- Font family
- Font size
- Font weight
- Text transform
- Font style
- Text decoration
- Text colour
- Alignment
- Line height
- Letter spacing
- Margin
- Padding

The overall widget should additionally support:

- Container width and alignment
- Background colour
- Border
- Border radius
- Box shadow
- Responsive controls
- Optional gap between the three lines

The widget must inherit Elementor's responsive behaviour rather than introducing a separate styling system.

## Recommended markup

The rendered output should use semantic, stable class names similar to:

```html
<div class="dashboard-metric">
  <div class="dashboard-metric__before">So far we have saved</div>
  <div class="dashboard-metric__value">
    <span class="dashboard-metric__number">194</span>
    <span class="dashboard-metric__suffix">KG</span>
  </div>
  <div class="dashboard-metric__after">Of food from landfill</div>
</div>
```

Text entered through Elementor must be escaped or sanitised appropriately. Do not output unsanitised user-controlled HTML.

## Data fetching and caching

The plugin must not request Google Sheets directly from the visitor's browser unless there is a clear reason to do so.

Recommended flow:

```text
Visitor loads page
        ↓
WordPress checks transient cache
        ↓
If cache is valid, use cached value
        ↓
If expired, WordPress requests the published Google Sheet
        ↓
Plugin validates and stores the result
        ↓
Elementor widget renders the value
```

Requirements:

- Default cache duration: 5 minutes.
- Cache duration configurable in plugin settings.
- Do not make a Google request on every page view.
- Use a short timeout.
- Keep the previous valid value available when a refresh fails, where practical.
- Never expose raw request errors to public visitors.
- Log useful errors only when WordPress debugging is enabled.

This is near-live data, not guaranteed instant real-time data. Google publishing and caching may introduce a delay.

## Plugin settings

Add a WordPress settings page under a suitable admin menu, for example:

**Settings → Dashboard Plugin**

Initial settings:

- Published Google Sheet URL or spreadsheet ID
- Default worksheet/tab
- Default cache duration
- Enable/disable debug logging
- Test connection button
- Display connection status
- Clear cached values button

The settings page must:

- Require an appropriate administrator capability.
- Use WordPress settings APIs and nonces.
- Sanitize and validate all saved values.
- Avoid storing unnecessary credentials.
- Clearly warn that published Google Sheet data is publicly accessible.

## Error handling

If the source cannot be read:

- Do not display PHP warnings or raw API responses.
- Show the configured fallback message.
- Keep the last valid value where possible.
- Add an admin/debug notice identifying the problem.
- Handle:
  - Invalid source URL
  - Missing worksheet
  - Invalid cell reference
  - Empty cell
  - Malformed CSV/JSON
  - HTTP error
  - Timeout
  - Non-numeric data when numeric formatting is selected

## Accessibility

The widget should:

- Use readable semantic markup.
- Preserve sufficient colour contrast.
- Not rely on colour alone.
- Work with keyboard navigation where interactive controls are later added.
- Avoid unnecessary ARIA attributes.
- Allow the site owner to provide meaningful text.
- Avoid animation by default.

## Security

The first version uses a deliberately public data source.

The plugin must:

- Never request or store a private Google service-account key in the initial version.
- Never expose WordPress admin settings through the frontend.
- Escape output.
- Sanitize Elementor text fields and settings.
- Validate remote URLs.
- Use WordPress HTTP APIs.
- Use nonces and capability checks for admin actions.
- Avoid arbitrary remote URL fetching if it creates an SSRF risk; restrict or validate the supported Google URL formats.

Only publish non-sensitive summary values. The public output sheet must not contain names, addresses, personal data, private financial information or formulas that reveal confidential information.

## Suggested file structure

```text
dashboard-plugin/
├── dashboard-plugin.php
├── readme.md
├── includes/
│   ├── class-dashboard-plugin-settings.php
│   ├── class-dashboard-plugin-sheets-client.php
│   ├── class-dashboard-plugin-cache.php
│   └── class-dashboard-plugin-elementor.php
├── widgets/
│   └── class-dashboard-metric-widget.php
├── assets/
│   ├── css/
│   │   └── dashboard-plugin.css
│   └── js/
│       └── dashboard-plugin.js
├── languages/
└── tests/
```

The structure may be simplified during implementation if a smaller, clean plugin is more appropriate.

## Build stages

### Stage 1 — Plugin foundation

- Add plugin header and constants.
- Add activation/deactivation safety checks.
- Check that Elementor is installed and active.
- Show a clear admin notice if Elementor is unavailable.
- Register the plugin settings page.

### Stage 2 — Google Sheets client

- Implement public-sheet request handling.
- Implement worksheet and cell selection.
- Add response parsing.
- Add validation and error handling.
- Add transient caching.
- Add a connection test in settings.

### Stage 3 — Elementor widget

- Register the `Dashboard Metric` widget.
- Add before text, value and after text controls.
- Add prefix/suffix and number formatting.
- Add independent Elementor typography/style controls.
- Add responsive support.
- Add fallback output.

### Stage 4 — Testing and packaging

Test with:

- A numeric value.
- A value with decimals.
- A value with thousands separators.
- A prefix and suffix.
- An empty cell.
- A missing worksheet.
- An invalid cell reference.
- A temporarily unavailable source.
- A changed Google Sheet value.
- Elementor responsive controls.
- Elementor editor preview and published frontend.
- Elementor Free, if supported.
- WordPress debug mode enabled.

Package the final result as an installable ZIP with no development files that are not required for production.

## Acceptance criteria

The first release is complete when:

- The plugin installs and activates without PHP errors.
- Elementor recognises the **Dashboard Metric** widget.
- The widget can display a value from the selected Google Sheet cell.
- The output can contain editable text above and below the value.
- The three sections can be styled independently in Elementor.
- The value can have a prefix or suffix.
- Numeric formatting works as configured.
- Data is cached and does not trigger a Google request on every page view.
- A failed request produces a controlled fallback message.
- No Google credentials are exposed.
- The plugin does not depend on Bricks.
- The widget works in an Elementor page preview and on the published page.
- The README and code document the required Google Sheet setup.

## Future enhancements

Not part of the initial build, but the design should not prevent:

- Multiple data points in one widget.
- A reusable named metric library.
- Private Google Sheets through a service account or OAuth.
- Charts and historical values.
- Percentage change indicators.
- Automatic refresh through AJAX.
- A last-updated timestamp.
- REST API output.
- Shortcode support.
- Gutenberg block support.
- Importing values from other public data sources.

## Naming

Working plugin name: **Dashboard Plugin**

Working widget name: **Dashboard Metric**

Repository: `dashboard-plugin`
