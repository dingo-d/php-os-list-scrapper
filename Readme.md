# Repository scraper

This repository contains the simple CLI scrapper written in PHP in order to fetch some interesting information about GitHub repositories.

It contains 2 main commands:

```bash
fetch
repo-vulnerabilities
```

The first one will fetch all the repositories for a user/organization, and the second one will display a list of known vulnerabilities.

## Requirements

1. PHP > 7.4
2. [Composer](https://getcomposer.org/)

The CLI commands are written in PHP, so you need to have PHP installed on your system. Besides that, you'll need to have a [GitHub token](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/creating-a-personal-access-token) to avoid rate limits imposed by GitHub.

Once you have that you're all set.

## Installation and usage

To install the package run

```bash
composer require infinum/php-os-list-scraper
```

Then from your terminal you can run

```bash
composer repos:fetch -- {gh-token} {user-name} {tag-name} {number-of-items} {cursor-id} --fields={field-names} 
```

or

```bash
composer repos:vulnerabilities -- {gh-token} {user-name} {tag-name} {number-of-items} {cursor-id} --full={true|false}
```

Token and user/organization name are **required** fields. The tag-name is the topic you want to fetch. If not set, the scrapper will pick up all the topics in the selected user/organization. The number-of-items is set to 10 by default, maximum is 100. If you want to check out other repositories (if you have more than 100), you'll need to provide the cursor-id. It will be displayed in the list so that you can pass it when making other query. This is because we're using GitHub API v4 using GraphQL. More information on the pagination can be found [here](https://graphql.org/learn/pagination/#pagination-and-edges).
Cursor and number of items are optional parameters.

Example command to list all open source repos for Infinum's organization:

```bash
composer repos:fetch -- $GHTOKEN infinum open-source 100
```

Here I've exposed my token inside the shell, and I'm referencing it using `$GHTOKEN` variable.

In addition to those five arguments, you can also pass `--fields` parameter to the first command. Here you can specify which fields you want to show. For instance

```bash
composer repos:fetch -- $GHTOKEN infinum open-source 50 --fields="Name,Description,License,URL,Star Count,Fork count,Open Issues,Open PRs"
```

The optional `--full` parameter for the vulnerability repost will expand the description for the vulnerabilities your libraries might have.

