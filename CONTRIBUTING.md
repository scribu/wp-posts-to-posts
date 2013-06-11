This guide is meant for developers wanting to work on the plugin code.

### Setup

Make a fork and clone it:

```
git clone --recurse-submodules git@github.com:{YOUR GITHUB USERNAME}/wp-posts-to-posts.git posts-to-posts
```

You can now work on the PHP and CSS files. Please follow the [WordPress Coding Standards](http://make.wordpress.org/core/handbook/coding-standards/).

### JavaScript

Don't modify `admin/box.js` directly. Instead:

- [Install node.js](https://github.com/joyent/node/wiki/Installing-Node.js-via-package-manager).

- Install [CoffeeScript](http://coffeescript.org):

```
npm install -g coffee-script
```

and edit the `admin/box.coffee` file. To compile it, run:

```
coffee -c admin
```

### Unit Tests

If you want to add a new feature, please consider adding a new test for it as well.

The following instructions assume a UNIX-like environment (OS X, Linux, etc.).

Step 1: Set un an environemt variable that defines the path to the test suite:

```bash
export WP_TESTS_DIR=/tmp/wordpress-tests/
```

This step will be needed each time you want to run the tests, so you might want to put it in your `.bashrc` file, to be executed automatically.

Step 2: Install and configure the official WordPress testing suite:

```bash
./bin/install-wp-tests
```

Note that all data in the test DB will be _deleted_ once you run the tests.

Step 4: Install PHPUnit via [Composer](https://getcomposer.org):

```bash
php composer.phar install --dev
```

Step 5: Run the tests:

```bash
./vendor/bin/phpunit
```
