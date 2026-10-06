# REX 3D page

The **REX 3D** page template renders the original dark/red design from
`rex-3d/index.html`, with Uncode's header and footer. It ignores the saved
WordPress page-builder content.

Edit text, colors and layout in `rex-3d/index.html`; edit camera movement and
interaction in `rex-3d/app.js`. The 3D model is `rex-3d/model.glb`.

Select the **REX 3D** template on the WordPress Page. The optional custom field
`rex_3d_folder` selects another folder in the WordPress root; it defaults to
`rex-3d`. No builder import is required.

Commit and deploy the child theme and `rex-3d` files through cPanel, then clear
the site cache. Copy-based deployment leaves old files on the server, but the
template and `functions.php` no longer load the removed builder files.
