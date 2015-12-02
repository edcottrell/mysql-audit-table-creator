/**
 * Gruntfile to perform various build tasks
 *
 * @author Ed Cottrell <blitzmaster@cereblitz.com>
 * @copyright Copyright (c) 2015 Cereblitz LLC
 * @license MIT License
 * @link http://dev.mysql.com/doc/refman/5.7/en/create-trigger.html
 * @package Cereblitz
 * @version @version@
 */
/*jslint regexp: true, unparam: true */
/*jshint unused:vars */

/*global module, moment, require */

//noinspection JSLint
/**
 * We use moment.js to do a little date manipulation
 * @type {Object|function} moment
 */
var moment = require('moment'); // jshint ignore:line

/**
 @param {Object} grunt
 @param {Object|Function} grunt.config
 @param {Object} grunt.event
 @param {Object} grunt.file
 @param {Function} grunt.file.readJSON
 @param {Function} grunt.initConfig
 @param {Function} grunt.loadNpmTasks
 @param {Function} grunt.registerTask
 @param {Object} grunt.util
 @param {Object} grunt.util._
 @param {Function} grunt.util._.debounce
 */
module.exports = function (grunt) {
    'use strict';

    grunt.file.preserveBOM = true;

    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        copy: {
            main: {
                options: {
                    process: function (content) {
                        return content
                            .replace(/@builddate@/g, moment().format('YYYY-MM-DD'))
                            .replace(/@description@/g, grunt.config.process('<%= pkg.description %>'))
                            .replace(/@version@/g, grunt.config.process('<%= pkg.version %>'))
                            .replace(/@database_version@/g, grunt.config.process('<%= pkg.database_version %>'))
                            .replace(/<!--suppress HtmlUnknownTarget -->\s*/, '');
                    },
                    processContentExclude: ['**/*.{png,gif,jpg,ico,psd,ttf,otf,woff,svg,mwb,wav,mp3}']
                },
                expand: true,
                cwd: 'src/',
                src: [
                    '**/*'
                ],
                dest: 'dist/',
                dot: true
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-copy');

    grunt.registerTask('default', ['copy']);

};