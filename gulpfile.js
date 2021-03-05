const gulp = require('gulp');
const uglify = require('gulp-uglify');
const sass = require('gulp-sass');
const concat = require('gulp-concat');
const pump = require('pump');

const resourcesFolder = 'src/tuneefy/Resources/';
const webFolder = 'web/'

const log = (error) => {
  if (error) {
    console.log(error);
  }
}

gulp.task('javascript', function (done) {
  pump([
    gulp.src(resourcesFolder + 'js/**/*.js'),
    uglify(),
    gulp.dest(webFolder + 'js')
  ],
    log,
    done
  );
});

gulp.task('twig', function (done) {
  pump([
    gulp.src(resourcesFolder + 'js/**/*.twig'),
    gulp.dest(webFolder + 'js')
  ],
    log,
    done
  );
});

gulp.task('sass', function (done) {
  pump([
    gulp.src(resourcesFolder + 'scss/styles.scss'),
    sass({ outputStyle: 'compressed' }).on('error', sass.logError),
    gulp.dest(webFolder + 'css')
  ],
    log,
    done
  );
});

gulp.task('embed', function (done) {
  pump([
    gulp.src(resourcesFolder + 'scss/partials/embed.scss'),
    sass({ outputStyle: 'compressed' }).on('error', sass.logError),
    gulp.dest(webFolder + 'css')
  ],
    log,
    done
  );
});

gulp.task('widget', function (done) {
  pump([
    gulp.src([resourcesFolder + 'scss/partials/reset.scss', resourcesFolder + 'widget/widget.scss']),
    concat('widget.scss'),
    sass({ outputStyle: 'compressed' }).on('error', sass.logError),
    gulp.dest(webFolder + 'css')
  ],
    log
  );
  pump([
    gulp.src(resourcesFolder + 'widget/widget-overlay.scss'),
    sass({ outputStyle: 'compressed' }).on('error', sass.logError),
    gulp.dest(webFolder + 'css')
  ],
    log
  );
  pump([
    gulp.src(resourcesFolder + 'widget/widget.js'),
    uglify(),
    gulp.dest(webFolder + 'js')
  ],
    log,
    done
  );
});

gulp.task('default', gulp.parallel('widget', 'embed', 'sass', 'javascript', 'twig'));
