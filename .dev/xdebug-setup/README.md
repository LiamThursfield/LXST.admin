# Xdebug Setup

## Prerequisites
- The project is set up and run locally via Docker/Laravel Sail
- Using PHPStorm for IDE

## Setup

### File Changes

`.env` should have the following variables set:
```
SAIL_XDEBUG_MODE=develop,debug
SAIL_XDEBUG_CONFIG="client_host=host.docker.internal idekey=docker"
```

Note: These are in .env.example, so may be set in your `.env` file already.

### IDE Setup

#### Set up the CLI Interpreter
- Go to Settings -> PHP -> CLI Interpreter
    - Select the `...` option
    - Create a new CLI Interpreter `From Docker...`
    - Select `Docker Compose`
    - Set `Server` to `Docker`
    - Set `Service` to `laravel.test`

#### Set up the Servers
- Go to Settings -> PHP -> Server
    - Select the +` option
    - Set `Name` to `Docker`
    - Set `Host` to `0.0.0.0`
    - Set `Port` to `80`
    - Set `Debugger` to `Xdebug`
    - Enable `Use path mappings`
    - Set `Absolute path on the server` to `/var/www/html`

- Go to Settings -> PHP -> Server
    - Select the +` option
    - Set `Name` to `localhost`
    - Set `Host` to `localhost`
    - Set `Port` to `80`
    - Set `Debugger` to `Xdebug`
    - Enable `Use path mappings`
    - Set `Absolute path on the server` to `/var/www/html`

#### Set up the Debugger
- Go to Settings -> PHP -> Debug
    - Set `Xdebug port` to `9003`
