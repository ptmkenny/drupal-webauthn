/**
 * @file
 * Development tasks.
 */
const { dest, src } = require("gulp");
const rename = require("gulp-rename");
const minify = require("gulp-minify");
const babel = require("gulp-babel");
const eol = require("gulp-eol");
const os = require("os");

function compile() {
  return src("js/*.es6.js", { sourcemaps: true })
    .pipe(babel())
    .pipe(
      rename(path => {
        path.basename = path.basename.replace(".es6", "");
      })
    )
    .pipe(eol(os.EOL, true))
    .pipe(dest("js/"))
    .pipe(
      minify({
        ext: {
          min: ".min.js"
        },
        ignoreFiles: ["-min.js"]
      })
    )
    .pipe(eol(os.EOL, true))
    .pipe(dest("js/"));
}

exports.default = compile;
