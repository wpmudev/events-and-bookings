/*global require*/

/**
 * When grunt command does not execute try these steps:
 *
 * - delete folder 'node_modules' and run command in console:
 *   $ npm install
 *
 * - Run test-command in console, to find syntax errors in script:
 *   $ grunt hello
 */

module.exports = function( grunt ) {
    
    require( 'time-grunt' )(grunt);
    
    require( 'load-grunt-tasks' )(grunt);
    
    var buildTime = new Date().toISOString();
    
    var conf = {
        
        js_folder: 'js/',
        
        css_folder: 'css/',
        
        translation: {
			ignore_files: [
				'node_modules/.*',
				'(^.php)',      // Ignore non-php files.
				'releases/.*'   // Temp release files.
			],
			pot_dir: 'languages/', // With trailing slash.
			textdomain: 'eab',
		},
        
        plugin_branches: {
			include_files: [
				'**',
				'!**/css/src/**',
				'!**/css/sass/**',
				'!**/js/src/**',
				'!**/js/vendor/**',
				'!**/img/src/**',
				'!**/node_modules/**',
				'!**/tests/**',
				'!**/releases/*.zip',
				'!releases/*.zip',
				'!**/release/**',
				'!**/Gruntfile.js',
				'!**/package.json',
				'!**/build/**',
                '!**/bin/**',
                '!**/src/**',
				'!app/assets/css/src/**',
				'!app/assets/js/src/**',
				'!app/assets/js/vendor/**',
				'!app/assets/img/src/**',
				'!node_modules/**',
				'!.sass-cache/**',
				'!releases/**',
				'!Gruntfile.js',
				'!package.json',
				'!phpunit.xml.dist',
				'!README.md',
				'!readme.txt',
				'!build/**',
				'!tests/**',
				'!.git/**',
				'!.git',
				'!**/.svn/**',
				'!.log'
			]
		},
        
        plugin_dir: 'events-and-bookings/',
		plugin_file: 'events-and-bookings.php'
        
    };
    
    grunt.initConfig( {
        
        pkg: grunt.file.readJSON( 'package.json' ),
        
        uglify: {
			all: {
				files: [{
					expand: true,
					src: ['*.js', '!*.min.js'],
					cwd: conf.js_folder,
					dest: conf.js_folder,
					ext: '.min.js',
					extDot: 'last'
				}],
				options: {
					banner: '/*! <%= pkg.title %> - v<%= pkg.version %>\n' +
						' * <%= pkg.homepage %>\n' +
						' * Copyright (c) <%= grunt.template.today("yyyy") %>;' +
						' * Licensed GPLv2+' +
						' */\n',
					mangle: {
						except: ['jQuery']
					}
				}
			}
		},
        
        autoprefixer: {
			options: {
				browsers: ['last 2 version', 'ie 8', 'ie 9'],
				diff: false
			},
			single_file: {
				files: [{
					expand: true,
					src: ['*.css', '!*.min.css'],
					cwd: conf.css_folder,
					dest: conf.css_folder,
					ext: '.css',
					extDot: 'last'
				}]
			}
		},
        
        compass: {
			options: {
			},
			server: {
				options: {
					debugInfo: true
				}
			}
		},
        
        cssmin: {
			options: {
				banner: '/*! <%= pkg.title %> - v<%= pkg.version %>\n' +
					' * <%= pkg.homepage %>\n' +
					' * Copyright (c) <%= grunt.template.today("yyyy") %>;' +
					' * Licensed GPLv2+' +
					' */\n'
			},
			minify: {
				expand: true,
				src: ['*.css', '!*.min.css'],
				cwd: conf.css_folder,
				dest: conf.css_folder,
				ext: '.min.css',
				extDot: 'last'
			}
		},
        
        clean: {
			temp: {
				src: [
					'**/*.tmp',
					'**/.afpDeleted*',
					'**/.DS_Store'
				],
				dot: true,
				filter: 'isFile'
			}
        },
        
        copy: {
            files: {
                src: conf.plugin_branches.include_files,
                dest: 'releases/<%= pkg.name %>-<%= pkg.version %>/'
            }
		},
        
        compress: {
            files: {
                options: {
                    mode: 'zip',
                    archive: './releases/<%= pkg.name %>-<%= pkg.version %>.zip'
                },
                expand: true,
                cwd: 'releases/<%= pkg.name %>-<%= pkg.version %>/',
                src: [ '**/*' ],
                dest: conf.plugin_dir
            }
		},
        
        makepot: {
			target: {
				options: {
					cwd: '',
					domainPath: conf.translation.pot_dir,
					exclude: conf.translation.ignore_files,
					mainFile: conf.plugin_file,
					potFilename: conf.translation.textdomain + '.pot',
					potHeaders: {
						poedit: true, // Includes common Poedit headers.
						'x-poedit-keywordslist': true // Include a list of all possible gettext functions.
					},
					type: 'wp-plugin' // wp-plugin or wp-theme
				}
			}
		},
        
    } );
    
    grunt.registerTask( 'hello', 'Test if grunt is working', function() {
		grunt.log.subhead( 'Hi there :)' );
		grunt.log.writeln( 'Looks like grunt is installed!' );
	});
    
    grunt.registerTask( 'build', 'Run all tasks.', function(target) {
		var build = [], i, branch;

		// Run the default tasks (js/css/php validation).
		grunt.task.run( 'default' );

		// Generate all translation files (same for pro and free).
		grunt.task.run( 'makepot' );
        
        grunt.task.run( 'clean' );
        
        grunt.task.run( 'copy' );
		grunt.task.run( 'compress' );
	});

	// Development tasks.
	grunt.registerTask( 'default', ['clean:temp', 'uglify', 'autoprefixer', 'cssmin'] );

	grunt.task.run( 'clear' );
	grunt.util.linefeed = '\n';
    
}