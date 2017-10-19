# README

*Similar structure as: Membership 2, Popup, Custom Sidebars, CoursePress*

The **only** development branch for Events+ is `master`. This branch ultimately is responsible for creating the production branches that are finally published.


**Remember:** `master` is the ONLY branch that should be edited and forked!

-----

# DEVELOPMENT

As mentioned above: Only directly edit the branch `master`. Other branches should be only updated via grunt tasks (see section "Automation" below).

Important: Do not let your IDE change the **source order** of the code. Fixing up formatting is fine, but moving code blocks around is not! It will confuse grunt and produce problems.

-----

# AUTOMATION

See notes below on how to correctly set up and use grunt. 

Many tasks as well as basic quality control are done via grunt. Below is a list of supported tasks.

**Important**: Before making a pull-request to the master branch always run the task `grunt` - this ensures that all .php, .js and .css files are validated and existing unit tests pass. If an problems are reported then fix those problems before submitting the pull request.

### Grunt Task Runner  

**ALWAYS** use Grunt to build the production branches. Use the following commands:  

Category | Command | Action
---------| ------- | ------
**Build** | `grunt` | Run all default tasks: js, css. **Run this task before submitting a pull-request**.
Build | `grunt build` | Runs all default tasks + lang, builds production version.


### Set up grunt

#### 1. npm

First install node.js from: <http://nodejs.org/>  

```
#!bash 
# Test it:
$ npm -v

# Install it system wide (optional but recommended):
$ npm install -g npm
```

#### 2. grunt

Install grunt by running this command in command line:

```
#!bash 
# Install grunt:
$ npm install -g grunt-cli
```

#### 3. Setup project

In command line switch to the `events-and-bookings` plugin folder. Run this command to set up grunt for the Events+ plugin:

```
#!bash 
# Install automation tools for M2:
$ cd <path-to-wordpress>/wp-content/plugins/events-and-bookings
$ npm install

# Test it:
$ grunt hello
```


# RELEASE

### 1. Build the release version

1.) Switch to `development` branch.

2.) Make sure the version number in **main plugin file** is correct and that the version in file `pacakge.json` matches the plugin version. (in package.json you have x.y.z format, so "1.2.3.4" becomes "1.2.34" here)

3.) Then run `grunt build` This will create a .zip archive of the release files.

4.) Only in `development` branch: There is a folder called `release/` which contains the release files as .zip archive.


