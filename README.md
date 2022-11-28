# instagram-to-wordpress
A WP CLI importer that imports instagram posts from a downloaded archive to WordPress

1. Download your archive from Instagram
2. Upload the plugin to the WordPress `plugins` folder
3. Make sure WP-CLI is installed.
4. Unzip the `content/posts_1.json` file from the archive into the plugin folder
5. Unzip the `media/posts` folder into a folder named `export/media/posts`
6. Run `wp instagram-import media ./posts_1.json`
