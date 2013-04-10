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

### Testing

The plugin comes with a few unit tests.

1. Install [Composer](https://getcomposer.org).
2. Run `composer install --dev`.
3. Run `vendor/bin/phpunit`.
