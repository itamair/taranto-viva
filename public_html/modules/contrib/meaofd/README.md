[![Pablo Urrea Badge](https://pablourrea.github.io/sites/default/files/badges/pablourrea.svg)](https://pablourrea.github.io)


# Mismatched entity and/or field definitions

## Introduction

The [Mismatched entity and/or field definitions](https://www.drupal.org/project/meaofd/) module has as its main purpose to solve a common problem in Drupal website development that appears under the same title on the *Status report* (*/admin/reports/status*) page on the website itself.


## What is the *Mismatched entity and/or field definitions* error?

The *Mismatched entity and/or field definitions* error occurs when there is a discrepancy between the expected structure of entities or fields in Drupal's configuration and the actual state of the database. This typically happens after changes to entity or field definitions, either during module updates, schema changes, or configuration imports between different environments. These inconsistencies can cause unexpected behavior, such as broken field displays, issues with saving or editing entities, and even prevent entities from being properly rendered.


## How is it used the Mismatched entity and/or field definitions module?
The module provides a report page that allows you to correct all the errors that may occur in each type of entity through the menu `Reports` > `Mismatched entity and/or field definitions` or directly in the path `/admin/reports/mismatched-entity-and-or-field-definitions`.
To fix the errors of the entities that present them, simply follow the `Fix` and `Confirm` button of each entity present in the report, then wait till mismatched entity and/or field definitions are fixed.

The module also provides the *Drush* command `meaofd:fix <entity_type_id>` to correct the error on the machine, for example, if the entity affected by this error is “*Paragraph*”, the command to execute to correct the entity is `drush meaofd:fix paragraph`. It is also possible to call the fix method of the `meaofd.fixer` service by passing it the ID of the entity type as a parameter. For example:
```
\Drupal::service("meaofd.fixer")->fix("node");
```
This is very useful to fix this error between environments automatically with a `hook_update_N()` function.


## Requirements

This module requires no modules outside of Drupal core.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see [Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## Current Active Maintainer

- Pablo Urrea - [enchufe](https://www.drupal.org/u/enchufe)


## Bug Reports & New Features

Please report bugs to the [Drupal.org issue queue](https://www.drupal.org/project/issues/meaofd).

