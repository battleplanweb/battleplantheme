# Claude Code — Project Notes

## JS Minification

Node.js is **not** in the system PATH. Use Adobe Dreamweaver's bundled Node:

**Node:** `C:/Program Files/Adobe/Adobe Dreamweaver 2021/node/node.exe`

**UglifyJS** (self-contained, no npm needed) is stored at:
`C:/Users/info/AppData/Local/Temp/terser_install/uglify_pkg/package/tools/node`

If that temp folder is gone, re-download:
```
https://registry.npmjs.org/uglify-js/-/uglify-js-3.19.3.tgz
```
Extract and use `tools/node` as the require path.

**Minification options to use:**
```js
const UglifyJS = require('...path above...');
const result = UglifyJS.minify({ 'filename.js': code }, {
  mangle: true,
  compress: { drop_console: false, dead_code: true, unused: false },
  output: { comments: false }
});
```

**To find files needing minification:** compare mtimes in `js/` — re-minify if the `.js` is newer than its `.min.js`, or no `.min.js` exists.
