var gulp = require('gulp');
var uglify = require('gulp-uglify');
var sass = require('gulp-sass');
var pump = require('pump');

let resourcesFolder = 'src/tuneefy/Resources/';
let webFolder = 'web/'

gulp.task('javascript', function () {
  pump([
        gulp.src(resourcesFolder + 'js/**/*.js'),
        uglify(),
        gulp.dest(webFolder + 'js')
    ],
    console.log
    );
});

gulp.task('sass', function () {
  pump([
        gulp.src(resourcesFolder + 'scss/styles.scss'),
        sass({outputStyle: 'compressed'}).on('error', sass.logError),
        gulp.dest(webFolder + 'css')
    ],
    console.log
    );
});

gulp.task('default', ['sass', 'javascript']);
