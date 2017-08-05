var gulp = require('gulp');
var uglify = require('gulp-uglify');
var sass = require('gulp-sass');
var pump = require('pump');

let resourcesFolder = 'src/tuneefy/Resources/';
let webFolder = 'web/'

let log = function (error) {
    if (error) {
        console.log(error);
    }
}

gulp.task('javascript', function () {
  pump([
        gulp.src(resourcesFolder + 'js/**/*.js'),
        uglify(),
        gulp.dest(webFolder + 'js')
    ],
    log
    );
});

gulp.task('twig', function () {
  pump([
        gulp.src(resourcesFolder + 'js/**/*.twig'),
        gulp.dest(webFolder + 'js')
    ],
    log
    );
});

gulp.task('sass', function () {
  pump([
        gulp.src(resourcesFolder + 'scss/styles.scss'),
        sass({outputStyle: 'compressed'}).on('error', sass.logError),
        gulp.dest(webFolder + 'css')
    ],
    log
    );
});

gulp.task('default', ['sass', 'javascript', 'twig']);
