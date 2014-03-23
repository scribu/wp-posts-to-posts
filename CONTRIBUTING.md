This guide is meant for developers wanting to work on the plugin code.

### Setup

Make a fork and clone it:

```
git clone --recursive git@github.com:{YOUR GITHUB USERNAME}/wp-posts-to-posts.git posts-to-posts
```

You can now work on the PHP and CSS files. Please follow the [WordPress Coding Standards](http://make.wordpress.org/core/handbook/coding-standards/).

### Unit Tests

If you want to add a new feature, please consider adding a new test for it as well.

The following instructions assume a UNIX-like environment (OS X, Linux, etc.).

Step 1: Install and configure the official WordPress testing suite:

```bash
./bin/install-wp-tests
```

Note that all data in the test DB will be _deleted_ once you run the tests.

Step 2: Install PHPUnit via [Composer](https://getcomposer.org):

```bash
php composer.phar install --dev
```

Step 3: Run the tests:

```bash
./vendor/bin/phpunit
```
