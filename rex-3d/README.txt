RD500N Coconut Deshelling Machine - scroll-driven 3D product page
==================================================================
Files
  index.html  - the page (layout, copy, styles)
  app.js      - built-in WebGL2 3D renderer + scroll/camera choreography
  model.glb   - textured 3D model (loaded when the site is served over http/https)
  model.js    - same model as base64, used automatically when index.html is
                opened directly from disk (file://). Can be deleted once hosted.

Hosting
  Upload the folder as-is to any web host, or into WordPress (e.g. /wp-content/uploads/rd500n/)
  and link or iframe index.html. No build step, no external JS libraries.
  Fonts load from Google Fonts (Archivo, IBM Plex Mono).

Editing
  Camera per section: data-cam="targetX,targetY,targetZ, azimuth°, elevation°, distance, sideShift"
  on each <section> in index.html. Hotspot positions: the HS array in app.js (metres).
  Add ?still to the URL to disable idle motion (useful for screenshots).
