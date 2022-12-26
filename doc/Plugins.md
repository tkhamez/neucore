# Plugins

## Service Registration Plugins

For an example see e.g. [Neucore Discord Plugin](https://github.com/tkhamez/neucore-discord-plugin).

### Create a plugin

- Create a new PHP application with composer and install
  [tkhamez/neucore-plugin](https://github.com/tkhamez/neucore-plugin):
  ```shell script
  composer init
  composer require tkhamez/neucore-plugin
  ```
- Create a new PHP class that implements `Neucore\Plugin\ServiceInterface`.

Neucore automatically loads all classes from the namespace that is configured with the
"PSR-4 Prefix" configuration option and from the `tkhamez/neucore-plugin` package, the `Neucore\Plugin` namespace.

Besides that, **do not use** any class from Neucore or any library that Neucore provides. Those can change or
be removed without notice.

Also note that libraries from objects provided by the `ObjectProvider` can be updated with a new Neucore version.

### Install a plugin

- Copy the plugin to the server where Neucore is installed.
- In Neucore, go to Administration -> Services and add a new service.
- Configure the service, at the very least set "PHP Class", "PSR-4 Prefix" and "PSR-4 Path".
