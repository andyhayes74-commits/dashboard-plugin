# Dashboard Plugin v2.3

Dashboard Plugin v2.3 is a standalone WordPress shortcode plugin for creating multiple live dashboard metrics from published Google Sheets.

It does not load Elementor classes or widgets. Elementor can still be used as the page builder by placing each generated shortcode in a normal Elementor **Shortcode** widget.

## Branch and version

- Branch: `v2.3`
- Plugin version: `2.3.0`
- Settings page: **Settings → Dashboard Plugin**

## What changed

- Settings now has one tab per dashboard.
- Each tab has its own Google Sheet URL, worksheet, cell, text, formatting, cache duration, and CSS class.
- Each dashboard receives a stable unique shortcode, for example `[dashboard_food_saved]`.
- New dashboards can be added or deleted from the settings page.
- Existing v2 single-dashboard settings are migrated into the first dashboard tab.
- The compatibility shortcode `[dashboard_metric]` remains available.
- Each dashboard tab includes a preview using the last saved settings and live cached Sheet value.
- Each dashboard can use an optional override figure instead of the Google Sheet value.
- A dashboard can be tested with an override figure before a Google Sheet URL is added.

## Updating the existing plugin

1. Back up the WordPress files and database first.
2. In WordPress, go to **Plugins → Add New Plugin → Upload Plugin**.
3. Upload the stable update package named `dashboard-plugin-main-v2.3.zip`.
4. WordPress should identify it as the installed Dashboard Plugin and show the current and uploaded versions.
5. Click **Replace current with uploaded**. This updates the existing plugin; it does not create a second copy, and the saved dashboard settings remain in WordPress.
6. Return to **Plugins** and confirm Dashboard Plugin is active, then open **Settings → Dashboard Plugin**.

The package contains the stable top-level folder `dashboard-plugin-main`, matching the installation created from the main-branch ZIP. Do not use the raw `v2.3` branch archive for updating: its branch-specific folder name makes WordPress treat it as a separate plugin.

If WordPress offers only a normal new installation or says that the destination folder already exists, cancel the upload. Do not activate a second Dashboard Plugin copy.

Download: `https://github.com/andyhayes74-commits/dashboard-plugin/raw/refs/heads/v2.3/dashboard-plugin-main-v2.3.zip`

## Creating dashboards

1. Open **Settings → Dashboard Plugin**.
2. Configure the first dashboard tab.
3. Click **+ Add dashboard** to create another tab.
4. Give it a name, configure its Google Sheet and cell, and save it.
5. Copy the shortcode shown in that tab.

Set the shortcode name in a tab to `dashboard_food_saved`. The plugin stores the shortcode separately from the dashboard display name, so renaming the dashboard does not change the shortcode. Duplicate names are automatically adjusted.

## Example

If the shortcode name is set to `dashboard_food_saved`, the plugin displays:

```text
[dashboard_food_saved]
```

With the default settings this produces:

```text
So far we have saved
194 KG
Of food from landfill
```

You can place the shortcode in Elementor's **Shortcode** widget, a WordPress Shortcode block, or another page builder's shortcode element.

## Compatibility shortcode

The original generic shortcode still works and uses the first dashboard tab:

```text
[dashboard_metric]
```

To select a particular dashboard with the generic shortcode, use its dashboard ID:

```text
[dashboard_metric dashboard="food_saved"]
```

The generated dashboard shortcode is preferred because it is clearer and does not depend on remembering the dashboard ID.

## Per-instance overrides

Generated shortcodes use the settings saved in their tab. You can override individual values when needed:

```text
[dashboard_food_saved cell="C5" suffix=" kg" class="large-impact-metric"]
```

Supported attributes are:

| Attribute | Purpose |
| --- | --- |
| `source_url` | Override the published Google Sheet URL. |
| `sheet` | Override the worksheet/tab name. |
| `cell` | Override the cell, such as `B2` or `C5`. |
| `before` | Override the text above the value. |
| `after` | Override the text below the value. |
| `prefix` | Text immediately before the value. |
| `suffix` | Text immediately after the value. |
| `override` | Override the Google Sheet value; leave blank to use the sheet. |
| `decimals` | Decimal places; `-1` preserves the source value. |
| `thousands` | Thousands separator. |
| `decimal` | Decimal separator. |
| `fallback` | Text shown when the sheet cannot be read. |
| `class` | Additional CSS class for this output. |

## Google Sheet setup

Each dashboard may use a different published sheet or a different tab/cell in the same spreadsheet. The source must be publicly published as CSV. Do not publish confidential information.

In Google Sheets:

1. Open the output spreadsheet.
2. Choose **File → Share → Publish to web**.
3. Select the required tab and choose **Comma-separated values (.csv)**.
4. Publish it and paste the spreadsheet URL into the relevant dashboard tab.

A small public output sheet populated from a private working sheet is suitable when the public sheet contains only the final figures needed by the website.

## Styling

Every dashboard uses these classes:

- `.hayfam-dashboard-metric`
- `.hayfam-dashboard-metric__before`
- `.hayfam-dashboard-metric__value`
- `.hayfam-dashboard-metric__after`

Use the tab's optional CSS class to target one dashboard separately:

```css
.large-impact-metric {
  text-align: center;
}

.large-impact-metric .hayfam-dashboard-metric__value {
  color: #2f855a;
  font-size: 4rem;
  font-weight: 700;
  line-height: 1;
}
```

## Technical notes

- Requires WordPress 6.4+ and PHP 7.4+.
- Values are cached per source, sheet, and cell.
- Each dashboard has its own cache duration, defaulting to five minutes.
- The plugin does not request Google credentials.
- The plugin has no Elementor runtime dependency.
