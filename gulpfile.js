/**
 * @file
 * Gulpfile.js.
 */

// Load .env file.
require('dotenv').config({
  debug: process.env.DEBUG,
  path: '.env'
});

// Initialize dependencies.
const { src, dest, watch, series, parallel } = require('gulp');
const bs = require('browser-sync').create();
const rnm = require('gulp-rename');
const bb = require('gulp-babel');
const ug = require('gulp-uglify');
const std = require('gulp-strip-debug');

// Set the working directory using command line options.
(() => {
  const options = parseArgs(process.argv);

  if (options['d']) {
    process.chdir(options.d);
  }
})();

/**
 * Parse a list of arguments returning --options.
 *
 * @param {*} argList The arguments list.
 */
function parseArgs(argList) {
  let arg = {}, a, opt, thisOpt, curOpt;
  for (a = 0; a < argList.length; a++) {
    thisOpt = argList[a].trim();
    opt = thisOpt.replace(/^\-+/, '');

    if (opt === thisOpt) {
      if (curOpt) {
        arg[curOpt] = opt;
      }
      curOpt = null;
    }
    else {
      curOpt = opt;
      arg[curOpt] = true;
    }

  }

  return arg;
}

/**
 * Build JS.
 *
 * @returns {*}
 */
function js() {
  return src([
    'js/src/**/*.js'
  ])
    .pipe(bb())
    .pipe(rnm(path => {
      path.basename = path.basename.replace(/\.es\d/, '');
    }))
    .pipe(dest('js/dist'))
    .pipe(ug())
    .pipe(std())
    .pipe(rnm({ suffix: ".min"}))
    .pipe(dest('js/dist'))
    .pipe(bs.stream());
}

/**
 * File watcher routines.
 */
function watcher() {
  watch(
    ['js/src/*.js', 'js/src/**/*.js'],
    { ignoreInitial: false },
    series(js)
  );
}

/**
 * Init browser-sync layer.
 *
 * @param {Function} done Task execution signal.
 */
function serve(done) {
  bs.init({
    proxy: {
      target: resolveProxy(),
      proxyReq: [
        proxyReq => {
          // Disable Drupal page cache.
          proxyReq.setHeader('Cache-Control', 'no-cache, must-revalidate, max-age=0');
          proxyReq.setHeader('Age', '0');
        }
      ]
    },
    notify: false,
    open: false
  });
  done();
}

/**
 * Resolve proxy URI.
 *
 * This function returns the proxy URI using env variables set at project
 * root level.
 *
 * @returns {string} The proxy URI.
 */
function resolveProxy() {
  if (process.env.BS_PROXY) {
    return process.env.BS_PROXY;
  }

  return 'localhost';
}

// Export commands.
exports.serve = parallel(serve, watcher);
exports.watch = watcher;
exports.default = series(js);
