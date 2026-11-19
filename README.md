# LPGallery for CMSimple_XH

A lightweight, folder-based JPEG gallery plugin for **CMSimple_XH** and **PHP 8.5+**. Based on Martin Svarrer's classic Gallery plugin (v0.9) for CMSimple, refactored and modernized for CMSimple_XH and PHP 8.5.

## Download & Documentation
* **Plugin Page & Download:** [Download LPGallery](https://planit.run.place/downloads/LPGallery/LPGallery.zip)
* **Full Setup & Permissions Guide:** See `help/help.htm` inside the plugin package.

## Features
* **Automatic Folder & Thumbnail Creation:** Add `{{{LPGallery('userfiles/gallery/test')}}}` to a CMSimple_XH page. The gallery folder, `thumb/` subfolder, `tn_*.jpg` thumbnails, and `.txt` metadata files are generated automatically.
* **Classic `.txt` Sidecar Format:** Preserves the plain-text file structure per photo (`Title;Extra1;Extra2;...`, description lines, and `DD-MM-YYYY HH:MM;Name;Comment;` visitor comments) with a built-in math CAPTCHA.
* **On-the-Fly Watermarking:** Scales large image views dynamically with optional UTF-8 TrueType (including `©`) or GD bitmap watermarks, configurable font size, color, alignment, and margin.
* **Responsive & Template-Native:** Uses a responsive CSS Grid layout and inherits your active CMSimple_XH template's stylesheet automatically.
* **PHP 8.5+ & CMSimple_XH 1.7 / 1.8+ Ready:** Modernized with clean PHP 8.5+ syntax and strict path security.

## Quick Installation
1. Download `LPGallery.zip` from [planit.run.place/downloads/LPGallery/LPGallery.zip](https://planit.run.place/downloads/LPGallery/LPGallery.zip) and extract the `LPGallery` folder into your CMSimple_XH `plugins/` directory (`plugins/LPGallery/`).
2. Create `userfiles/gallery/` once on your server with write permissions for the web server (recommended: `2775` with the web server group, e.g. `www-data`; see `help/help.htm` for exact commands and POSIX ACL notes).
3. Ensure `plugins/LPGallery/config/` (`2775`) and `plugins/LPGallery/config/config.php` (`664`) are writable so you can save settings under **Plugins > LPGallery > Settings**.
4. Insert `{{{LPGallery('userfiles/gallery/test')}}}` (replace test with your gallery name) on a page, open the page once to generate the folder, upload your `.jpg`/`.jpeg` photos via FTP/SFTP, and reload.

## Credits & License
* **Based on:** Martin Svarrer's classic Gallery plugin (v0.9) for CMSimple.
* **Author:** Lambert Peeters
* **License:** [GNU General Public License v3.0 (GPL-3.0)](LICENSE)
* **Development Note:** Created and modernized with the assistance of AI (Google Gemini & Anthropic Claude).
