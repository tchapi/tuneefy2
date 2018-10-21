<?php

namespace Deployer;

require 'recipe/common.php';

task('deploy', [
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:shared',
    'deploy:writable',
    'deploy:vendors',
    'deploy:assets',
    'deploy:api_doc',
    'deploy:clear_paths',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
]);

// Configuration
inventory('deploy/servers.yml');

set('bin/npm', function () {
    return (string)run('which npm');
});

set('shared_files', ['app/config/parameters.yml', 'app/config/platforms.yml']);
set('writable_dirs', ['var/cache', 'var/logs']);
set('shared_dirs', ['var/cache', 'var/logs']);

set('ssh_type', 'native');
set('ssh_multiplexing', true);
set('http_user', 'www-data');
set('default_stage', 'production');
set('repository', 'git@github.com:tchapi/tuneefy2.git');
set('clear_paths', [
  './README.md',
  './TODO.todo',
  './structure.sql',
  './.gitignore',
  './.git',
  './deploy',
  './.php_cs',
  './package.json',
  './gulpfile.js',
  './phpunit.xml',
  './deploy.php',
  './composer.*',
]);

// Tasks
desc('Deploy production parameters');
task('deploy:parameters', function () {
    upload('./deploy/parameters.prod.yml', '{{deploy_path}}/shared/app/config/parameters.yml');
    upload('./deploy/platforms.prod.yml', '{{deploy_path}}/shared/app/config/platforms.yml');
});

desc('Install assets');
task('deploy:assets', function() {
  run("cd {{release_path}} && {{bin/npm}} set progress=false && {{bin/npm}} install --production --no-optional && {{bin/npm}} run build-assets");
});

desc('Buidl API documentation');
task('deploy:api_doc', function() {
  run("cd {{release_path}} && {{bin/npm}} run api-documentation");
});

desc('Restart PHP-FPM service');
task('php-fpm:restart', function () {
    // The user must have rights for restart service
    // /etc/sudoers: username ALL=NOPASSWD:/bin/systemctl restart php-fpm.service
    run('sudo systemctl restart php7.1-fpm.service');
});

// Crontab
desc('Add crontab for command');
task('deploy:crontab', function () {
    if (get('stage') === 'production') {
        // Create a string of env_vars for the cron task
        $env_vars = "";
        foreach (get('env') as $key => $value) {
            $env_vars .= $key."=".$value." ";
        }
        set('env_vars', $env_vars);

        cd('/etc/cron.d/');
        run('echo -e \'# Updating tuneefy stats and cleaning expired intents\n*/14 * * * * {{user}} {{env_vars}} {{bin/php}} {{current_path}}/src/tuneefy/cron_runner.php >> {{current_path}}/var/logs/cron-job-{{stage}}.log 2>&1\' | sudo tee tuneefy');
        writeln('<info>Wrote /etc/cron.d/tuneefy file</info>');
    } else {
        writeln('<info>Not installing crontabs for a stage other than production</info>');
    }
});

// Hooks
after('deploy', 'success');
after('deploy:symlink', 'php-fpm:restart');
after('deploy:symlink', 'deploy:crontab');
after('deploy:update_code', 'deploy:parameters');
after('deploy:failed', 'deploy:unlock');
