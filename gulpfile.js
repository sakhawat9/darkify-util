// Load plugins
const gulp = require("gulp");
const clean = require("gulp-clean");
const rename = require("gulp-rename");
const zip = require("gulp-zip");
const cleanCSS = require("gulp-clean-css");
const uglify = require("gulp-uglify");
const notify = require("gulp-notify");
const fs = require("fs");

// Version of record. Unlike darkify-pro there is no package.json to read it
// from — the plugin header in darkify-util.php is the only place the version
// is declared, so the zip name is derived from it. Keeping one source avoids
// the classic "header says 1.1, zip says 1.0" mismatch.
const pluginVersion = (function () {
  const header = fs.readFileSync("./darkify-util.php", "utf8");
  const match = header.match(/^\s*\*?\s*Version:\s*(.+)$/im);
  return match ? match[1].trim() : "0.0.0";
})();

// Paths
const paths = {
  css: {
    // The front-end stylesheet loaded on every page.
    src: ["css/*.css", "!css/*.min.css"],
    dest: "css/",
  },
  assets_css: {
    // Preview / demo / hero frame styles, loaded only where a shortcode runs.
    src: ["assets/css/*.css", "!assets/css/*.min.css"],
    dest: "assets/css/",
  },
  js: {
    // The negation matters: without it the glob picks up the .min.js files this
    // task just wrote and minifies them again into .min.min.js on every run.
    src: ["assets/js/**/*.js", "!assets/js/**/*.min.js"],
    dest: "assets/js/",
  },
};

// Error handler
const onError = function (err) {
  notify.onError({
    title: "Gulp",
    subtitle: "Failure!",
    message: "Error: <%= error.message %>",
    sound: "Basso",
  })(err);
  this.emit("end");
};

// Generate the .pot file.
//
// darkify-pro shells out to bin/i18n.js because its React admin panel needs the
// JS strings remapped onto the built bundle. This plugin has no such panel, so
// `wp i18n make-pot` on its own covers both the PHP and the block's src/*.js.
//
// wp-cli is a developer tool, not a build dependency — if it is missing the
// task warns and lets the rest of the build finish rather than blocking a zip.
gulp.task("makepot", function (done) {
  const { execFileSync } = require("node:child_process");

  if (!fs.existsSync("./languages")) {
    fs.mkdirSync("./languages");
  }

  try {
    execFileSync(
      "wp",
      [
        "i18n",
        "make-pot",
        ".",
        "languages/darkify-util.pot",
        "--slug=darkify-util",
        "--domain=darkify-util",
        "--exclude=node_modules,build,blocks/*/build",
      ],
      { stdio: "inherit" },
    );
  } catch (error) {
    console.warn(
      "makepot: skipped — `wp i18n make-pot` failed or wp-cli is not " +
        "installed. Translations are unchanged.",
    );
  }

  done();
});

// Clean zip and build directories
gulp.task("clean-zip", function () {
  return gulp
    .src("./*.zip", {
      read: false,
      allowEmpty: true,
    })
    .pipe(clean());
});

gulp.task("clean-build", function () {
  return gulp
    .src("./build", {
      read: false,
      allowEmpty: true,
    })
    .pipe(clean());
});

// Copy files to build directory.
//
// The exclusions mirror .distignore, which is what a `wp dist-archive` or a
// plain folder deploy already honours — keep the two in step when either
// changes. blocks/*/build IS shipped: it is the compiled block, and nothing on
// the server runs wp-scripts.
gulp.task("copy", function () {
  return gulp
    .src([
      "./**/*.*",
      "!./build/**",
      "!./node_modules/**",
      "!./blocks/**/node_modules/**",
      "!./blocks/*/src/**",
      "!./blocks/*/package.json",
      "!./blocks/*/package-lock.json",
      "!./blocks/*/build/*.map",
      "!./**/*.map",
      "!./**/*.zip",
      "!./*.js",
      "!./*.json",
      "!.git",
      "!.github",
      "!.claude",
      "!.vscode",
      "!.gitignore",
      "!.distignore",
      "!.DS_Store",
      "!./**/.DS_Store",
      "!yarn-error.log",
      "!./*.lock",
    ])
    .pipe(gulp.dest("build/darkify-util/"));
});

// Create a zip file
gulp.task("make-zip", function () {
  return gulp
    .src("./build/**/*.*")
    .pipe(zip(`darkify-util-v${pluginVersion}.zip`))
    .pipe(gulp.dest("./"));
});

// Clean only minified CSS files
gulp.task("cleanMinifiedCSS", function () {
  return gulp
    .src([`${paths.css.dest}/*.min.css`, `${paths.assets_css.dest}/*.min.css`], {
      read: false,
      allowEmpty: true,
    })
    .pipe(clean());
});

// Clean only minified JavaScript files
gulp.task("cleanMinifiedJs", function () {
  return gulp
    .src(`${paths.js.dest}/**/*.min.js`, { read: false, allowEmpty: true })
    .pipe(clean());
});

// Minify CSS
gulp.task("minify-css", function () {
  return gulp
    .src(paths.css.src)
    .pipe(cleanCSS({ level: { 1: { all: false }, 2: { all: false } } }))
    .pipe(rename({ suffix: ".min" }))
    .pipe(gulp.dest(paths.css.dest));
});

gulp.task("minify-assets-css", function () {
  return gulp
    .src(paths.assets_css.src)
    .pipe(cleanCSS({ level: { 1: { all: false }, 2: { all: false } } }))
    .pipe(rename({ suffix: ".min" }))
    .pipe(gulp.dest(paths.assets_css.dest));
});

// Minify JS
gulp.task("minify-js", function () {
  return gulp
    .src(paths.js.src)
    .pipe(uglify().on("error", onError))
    .pipe(rename({ suffix: ".min" }))
    .pipe(gulp.dest(paths.js.dest));
});

// Watch for changes
gulp.task("watch", function () {
  gulp.watch(paths.css.src, gulp.series("cleanMinifiedCSS", "minify-css"));
  gulp.watch(
    paths.assets_css.src,
    gulp.series("cleanMinifiedCSS", "minify-assets-css"),
  );
  gulp.watch(paths.js.src, gulp.series("cleanMinifiedJs", "minify-js"));
});

// Export tasks
exports.default = gulp.series(
  "clean-zip",
  "clean-build",
  "cleanMinifiedCSS",
  "minify-css",
  "minify-assets-css",
  "cleanMinifiedJs",
  "minify-js",
  "makepot",
  "copy",
  "make-zip",
);
exports.minifyCss = gulp.series("cleanMinifiedCSS", "minify-css", "minify-assets-css");
exports.minifyJs = gulp.series("cleanMinifiedJs", "minify-js");
exports.watch = gulp.series("watch");
