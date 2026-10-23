# team-blue/php-json-immutable-objects

Immutable PHP objects with a JSON schema.

Provides `ImmutableRecordLogic` and `JsonSchemaAwareRecordLogic` traits to build immutable value records that convert to/from typed JSON, including support for polymorphism, encryption of sensitive properties, metadata extraction via attributes/docblocks, and a Symfony bundle (`JsonImmutableObjectsBundle`). Works together with [`team-blue/php-util`](https://github.com/teamdotblue/php-util) and [`team-blue/php-value-objects`](https://github.com/teamdotblue/php-value-objects).

## Installation

```bash
composer require team-blue/php-json-immutable-objects
```

## License

MIT — see [LICENSE](LICENSE).

This repository is a public mirror; development happens internally on the team.blue GitLab.
