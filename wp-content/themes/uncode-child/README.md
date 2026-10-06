# Editable RD500N 3D page

The **REX 3D** template now uses Uncode's normal page renderer. Edit its page
with the Uncode/WPBakery builder: headings, paragraphs, tables, specifications,
links and contact information are ordinary Heading, Text Block and Button elements.

Under **REX 3D**, the **Viewer** element exposes loading text, the drag hint and
accessible model description. **Motor Controls** exposes button and status labels.
Each Row has a **REX 3D** tab for its camera view and navigation label. Anatomy
columns have a **Highlight 3D part** setting; their headings also label the hotspots.

Page Options and element fonts/colors are left at **Inherit**. Global fonts,
content skin, header, footer, width and accent come from Uncode Theme Options.
The small `rex-3d.css` stylesheet positions the canvas, hotspots and controls;
it does not override headings or the site's menu.

## First installation on another environment

Deploy the child theme and `rex-3d` files, then import the builder content on the
existing REX 3D **Page** (the WooCommerce product redirects to that page).
The local page is ID 179741; confirm the page ID on the other installation.
An empty page continues to use the original standalone layout until imported,
so deploying the code first does not leave the existing live page blank.

In the target site's WordPress admin, open **Pages**, edit the REX 3D page in the
backend editor and click **Import editable RD500N content** in the notice at
the top. This imports the builder content and resets that page's overrides to
Inherit. Then open the Uncode builder. The import button is only shown for an
empty page, and the action checks your page-editing permission and a nonce.

Alternatively, run this from the WordPress root in a terminal:

```sh
php wp-content/themes/uncode-child/tools/import-rd500n.php --page-id=179741
```

The importer makes a temporary backup of the old page and settings, leaves its
title/slug/status unchanged, and resets that page's Uncode overrides to Inherit.
It refuses to overwrite existing content unless `--replace` is explicitly supplied.
Alternatively, paste `content/rd500n.txt` into that page's Classic editor text view,
select the REX 3D template, switch to the builder and set Page Options to Inherit.

After installation, WordPress page content is the source of truth. Git deployments
copy the code but do not replace saved builder edits. Transfer subsequent page
content separately, or edit the page directly in the target environment.

The standalone `rex-3d/index.html` remains available; it no longer supplies the
WordPress page's text or typography.
