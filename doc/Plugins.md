# Plugins

<!-- toc -->

- [Intro](#intro)
- [Install a plugin](#install-a-plugin)
- [Overview for plugin creators](#overview-for-plugin-creators)
  * [General plugins](#general-plugins)
  * [Service plugins](#service-plugins)
- [Create a plugin](#create-a-plugin)

<!-- tocstop -->

_The following is valid for Neucore 1.42.0 and [neucore-plugin](https://github.com/tkhamez/neucore-plugin)
0.10.0 and above._


## Intro

Plugins add functionality to Neucore. They are most commonly used to create external service accounts
and/or link them to a Neucore user account (like Mumble or Discord). They can also have their own user 
interface.

A plugin can be added multiple times to Neucore with different configuration data (via GUI, it only has to 
be installed once). For example, the [Neucore Discord Plugin](https://github.com/tkhamez/neucore-discord-plugin) 
is added once for every Discord server that should be available to users.


## Install a plugin

The following steps are the same for all plugins. See the respective plugin documentation for further steps.

- Set the `NEUCORE_PLUGINS_INSTALL_DIR` environment variable (e.g. `/home/user/neucore-plugins`).
- Copy the plugin into that directory within its own subdirectory (so that the plugin.yml file is e.g.
  at `/home/user/neucore-plugins/discord/plugin.yml` - do _not_ edit this file!).
- If the plugin contains frontend files (see the respective plugin documentation), make them available
  below `[Neucore installation directory]/web/plugin/{name}`, e.g. by creating a symlink or by mounting the
  directory in the Docker container. See the documentation of the plugin for the name of the {name} directory.
- In Neucore, go to _Administration → Plugins_ and add a new plugin.
- Configure the plugin, at the very least choose the plugin from the dropdown list. Remember to save your changes.


## Overview for plugin creators

For each plugin created in Neucore there is one distinct URL `/plugin/{plugin_id}/{name}`.
The {name} part can be anything and is passed to the method that implements the request. This method will also 
get information about the logged-in user.

All plugins have access to a couple objects from Neucore, e.g. to parse YAML files, get various data like 
group members or make ESI requests with tokens from any character that is available on Neucore.

### General plugins

They can have their own frontend, add items to the navigation menu that point to their own URL and
implement console commands via Neucore (`backend/bin/console plugin {plugin_id} [args] [--opts]`).

See this [example plugin](https://github.com/tkhamez/neucore-example-plugin) for a simple demo.

#### ESI Limits

Both the [ESI error limit](https://developers.eveonline.com/docs/services/esi/best-practices/#error-limit)
and the [ESI rate limit](https://developers.eveonline.com/docs/services/esi/rate-limiting/) are 
reduced to 85% of the actual ESI limits.

The time returned after reaching the permissible rate limit is only a suggestion for a 
minimum delay. The current implementation does not know when tokens will be returned.

### Service plugins

They are available to users from the "Services" menu. They provide configuration data to customise the 
user interface and implement a couple of methods to create and update external service accounts via Neucore.


## Create a plugin

- Create a new PHP application with composer and install the neucore-plugin package:
  ```shell script
  composer init
  composer require tkhamez/neucore-plugin
  ```
- Copy `vendor/tkhamez/neucore-plugin/plugin.yml` to `plugin.yml` in the root directory of the new plugin
  and adjust values.
- Create a new PHP class that implements `Neucore\Plugin\ServiceInterface` or `Neucore\Plugin\GeneralInterface`,
  depending on what kind of plugin (general or service) you want to create. It is also possible to implement both
  in the same class. Not all methods need to be implemented, most can throw an exception instead.
- If you have a frontend, place all frontend files in a dedicated directory so that they can be deployed below
  `web/plugin/{name}` in the document root of the Neucore installation. Mention the name of the {name} directory
  in your documentation, it must be unique among all installed Neucore plugins with a frontend.

Neucore automatically loads all classes from the namespace that is configured with the `psr4_prefix` and 
`psr4_path` values from the `plugin.yml` file.

You can also use all classes and libraries provided by the `neucore-plugin` package and by the `FactoryInterface`
object that is provided by Neucore in the plugin class constructor. However, note that libraries can be updated 
with each Neucore release.

Besides that, **do not use** any class from Neucore or any library that Neucore provides. Those can change or
be removed without notice. This also applies to the frontend API. Also, do not access the Neucore database 
directly.
