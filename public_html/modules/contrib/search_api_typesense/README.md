# Typesense Search API

This module provides a Typesense backend for the [Search API module](https://www.drupal.org/project/search_api).
Typesense is a fast, typo-tolerant search engine for building delightful search experiences. It's written in C++ and
it's open-source.

## Quick Start

### 1. Make sure you have a Typesense server running

You have two options:
- [Typesense Cloud](https://typesense.org/docs/guide/install-typesense.html#option-1-typesense-cloud)
- [Local Machine / Self-Hosting](https://typesense.org/docs/guide/install-typesense.html#option-2-local-machine-self-hosting)

#### DDEV

If you are using [DDEV](https://ddev.com/) to manage your local development environment, you can use the
[ddev-typesense](https://github.com/kevinquillen/ddev-typesense) addon to have a Typesense Docker container running on
your local machine.

### 2. Install and enable the module

Install as you would normally install a contributed Drupal module. For further information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

### 3. Create a new Search API Typesense server

Open the Search Api setting page at `/admin/config/search/search-api/add-server` (see the
[Search API documentation](https://www.drupal.org/docs/8/modules/search-api/getting-started/adding-a-server) for more
details) and select the "Search API Typesense" as **Backend** option.

Then, you must fill in the server credentials at the bottom of the same settings page. If you are using the DDEV addon,
as described in the first step above, you can use this configuration:

* Admin API key: `ddev`
* Host: `YOUR_DDEV_HOSTNAME` (e.g. `my-project.ddev.site`)
* Port: `8109`
* Protocol: `https`

If you are using Typesense Cloud, you can find the API key and the host in the Typesense dashboard.

### 4. Create a new index and add fields

Go to `/admin/config/search/search-api/add-index` and create a new index by selecting the server configured at step 4.

Click on `Save and add fields`.

In the `Fields` tab, be sure to select the appropriate Typesense data type for each field. The Typesense data types are
the one that start with `Typesense:`:

* Typesense: string
* Typesense: string[]
* Typesense: int32
* Typesense: int32[]
* Typesense: int64
* Typesense: int64[]
* Typesense: float
* Typesense: float[]
* Typesense: bool
* Typesense: bool[]

The array version of the data types is used for multi-value fields (like tags, categories, etc.).

Select the fields you want to index and save. At this point, the Typesense server is NOT ready to receive the data yet.

Click on the `Schema` tab to configure specific options for the collection and the fields. This form shows all and only
the fields that have a Typesense data type.

Configure the fields as needed, then click on `Save`. This will create the collection on the Typesense server and your
index is ready to be used.

### 5. Configure the processors

Configure, if needed, the processors at `/admin/config/search/search-api/index/{index_name}/processors`.

### 6. Index content from the UI or cli.

As for Search API standard, you can index content from the UI (from `/admin/config/search/search-api/index/{index_name}`)
or using the Drush command `search-api:index`.

## Typesense documentation

You can find a clear and valuable documentation on the [Typesense API Reference](https://typesense.org/docs/latest/api/).

## Contributing to the module

This project is configured to work with [DDEV](https://ddev.com/) out of the box. If you are not using DDEV, you have
to install and run Typesense on your local machine or server.

On DDEV, you can run the following command to start the stack:

```bash
ddev start
ddev poser
ddev symlink-project
```

Now, you can use the `ddev describe` command to see the available services and ports.

`typesensedasboard`, available at port `8111`, is a [web-based dashboard](https://github.com/bfritscher/typesense-dashboard)
for Typesense that allows you to manage your Typesense collections, documents, and settings easily. It is a great tool
for development and debugging.
