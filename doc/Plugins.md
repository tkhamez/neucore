# Plugins

## Service Registration Plugins

- Create a new PHP application with composer and install [tkhamez/neucore-plugin](https://github.com/tkhamez/neucore-plugin):
  ```shell script
  composer init
  composer require tkhamez/neucore-plugin
  ```
- Create a new class and implement `Neucore\Plugin\ServiceInterface`.
- In Neucore, go to Administration -> Services and add a new service.
- Configure the service, at the very least set "PHP Class", "PSR-4 Prefix" and "PSR-4 Path".
