# fusion-lib

**fusion-lib is a pragmatic PHP + JavaScript utility toolbox -- boring by design.**

It is built for classic, server-rendered web applications and developers who
prefer explicit code, manual integration, and long-term maintainability over
frameworks, tooling, and trends.

fusion-lib is intentionally boring by design.

It avoids clever abstractions, hidden magic, build steps, and framework lifecycles.
Every file can be read, understood, debugged, and modified directly.
The goal is not novelty — the goal is durability.

---
## Project Status

fusion-lib is under active development.

The library is already used in real projects, but it is **not yet feature-complete**
and may contain known or unknown defects. Documentation, CSS contracts, and
component boundaries are still being refined.

If you adopt fusion-lib today:
- expect occasional breaking changes
- expect missing polish
- expect boring, readable code instead of abstractions

This is intentional.

---
## Usage Overview

fusion-lib is **not installed**.  
It is **included**.

You copy the files you need into your project and load them manually.

## Why fusion-lib exists

fusion-lib exists because many real-world applications:

- Render HTML on the server
- Must live for many years
- Run in constrained or legacy environments
- Are maintained by small teams

In these contexts, complexity is a liability.

fusion-lib provides simple, explicit tools that solve common problems
without introducing frameworks, build pipelines, or hidden behavior.

It is boring by design — so it can be trusted over time.


---

## JavaScript Usage

Each JavaScript file exposes **one intentional global symbol**.

Example:

```html
<script src="js/mapFunctions.js"></script>
<script src="js/myBackend.js"></script>
<script src="js/justDialogs.js"></script>

<script>
    mapFunctions('container');

    const backend = new myBackend();
    backend.callDirect('api.php', { action: 'ping' }, console.log);

    const dialogs = justDialogs('en');
    dialogs.myAlert('Hello world');
</script>
```

Design rules:
- No build step
- No bundler
- No module loader
- No hidden dependencies

---

## PHP Usage

PHP utilities are plain classes.

You may include them manually or use the provided ClassLoader.

### Using ClassLoader

```php
require 'autoload/ClassLoader.php';

ClassLoader::load('my-project', [
    'lib',
    'app',
    'modules'
]);
```

Classes are discovered, cached, and loaded explicitly.

---

## Project Structure (recommended)

```
fusion-lib/
├─ js/
│  ├─ mapFunctions.js
│  ├─ myBackend.js
│  ├─ justDialogs.js
│  └─ toolTip.js
├─ php/
│  ├─ Calendar.php
│  ├─ MoneyValidator.php
│  └─ Utils.php
├─ autoload/
│  └─ ClassLoader.php
├─ README.md
└─ LICENSE
```

You are free to reorganize — fusion-lib does not enforce structure.

---

## Design Principles (Short)

fusion-lib follows a few strict ideas:

- Explicit is better than automatic
- Control is better than abstraction
- Stability is better than fashion
- The user is responsible for integration
- Backward compatibility matters

fusion-lib provides tools, not policy.

---

## Who this is NOT for

fusion-lib is not for developers looking for:

- Frameworks
- SPA architectures
- npm or Composer workflows
- Dependency injection
- Automatic magic

fusion-lib is for developers who want to know exactly what their code does.

---

## License

MIT
