# LPGallery for CMSimple_XH

LPGallery shows a folder of JPEG photos as a thumbnail grid on a CMSimple_XH page. Clicking a thumbnail shows the large photo with a watermark, its title and description, metadata and comments. Visitors' comments are protected by a simple sum.

## Requirements

- CMSimple_XH 1.8 (1.7 not tested)
- PHP with the GD extension (JPEG support). Tested on PHP 8.3; PHP 8.5 is not yet tested.
- A web server that can write to `userfiles/gallery`

## Installation

1. Unzip `LPGallery.zip` so that the folder `LPGallery` is in `plugins/`.
2. Create `userfiles/gallery/` and set the permissions in `help/help.htm`, section "How permissions work".
3. Create a page and add the page tag written with HTML entities:

   `&#123;&#123;&#123;LPGallery('userfiles/gallery/test')&#125;&#125;&#125;`

4. Open the page once. The folder, thumbnails and `.json` files are created automatically.
5. Copy JPEG photos into the gallery folder and reload the page.

`test` in the page tag is only an example. Replace it with any gallery name, for example `holiday2026` or `cats`. Each name gets its own folder under `userfiles/gallery/`, and the name must not contain `..`.

Full instructions, the `.json` file format and the settings are in `help/help.htm`, which is linked from the Plugins menu under Help.

## Files

- `index.php`: the gallery pages (thumbnail grid, large photo, comment form)
- `stream.php`: serves the large photo with the watermark
- `includes/engine.php`: folders, thumbnails, `.json` files and comments
- `admin.php`: the Plugins menu entries (Settings and Help)
- `config/config.php`: the settings
- `config/metaconfig.php`: field types for the settings page
- `help/help.htm`: installation, permissions and file format
- `LICENSE`, `license.txt`: the GNU General Public License version 3
- `README.md`: this file

## Credits and license

Copyright (C) 2026 Lambert Peeters. Licensed under the GNU General Public License version 3 (GPL-3.0); see `LICENSE` and `license.txt`.

Inspired by Martin Svarrer's Gallery plugin (v0.9) for CMSimple.
