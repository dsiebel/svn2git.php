## svn2git
Subversion to Git migration tool.

Uses git-svn to clone a Subversion repository including all it's tags to Git.
Optionally pushes everything to a remote repository.

**Table of Contents**

- [Getting Started](#getting-started)
  - [Install Dependencies](#install-dependencies)
  - [Get help](#get-help)
  - [Get subversion authors mapping](#get-subversion-authors-mapping)
  - [Migrate the repository](#migrate-the-repository)

### Getting Started

#### Install Dependencies
```composer install```

#### Get help
```bash
$ bin/svn2git
```

```
svn2git - the Subversion to Git migration tool. version 1.0.1

Usage:
  [options] command [arguments]

Options:
  --help           -h Display this help message.
  --quiet          -q Do not output any message.
  --verbose        -v|vv|vvv Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug.
  --version        -V Display this application version.
  --ansi              Force ANSI output.
  --no-ansi           Disable ANSI output.
  --no-interaction -n Do not ask any interactive question.

Available commands:
  fetch-svn-authors   Command line tool to fetch author names from an SVN repository.
  help                Displays help for a command
  list                Lists commands
  migrate             Command line tool to migrate a Subversion repository to Git.
```

### Get subversion authors mapping

**Usage**
```
  bin/svn2git fetch-svn-authors [--output="..."] source
```
**Arguments**
```
  source                Subversion repository to fetch author names from.
```

**Options**
```
  --output              Output file. (default: "./authors.txt")
  --help (-h)           Display this help message.
  --quiet (-q)          Do not output any message.
  --verbose (-v|vv|vvv) Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug.
  --version (-V)        Display this application version.
  --ansi                Force ANSI output.
  --no-ansi             Disable ANSI output.
  --no-interaction (-n) Do not ask any interactive question.
```

**Example**
```bash
$ bin/svn2git fetch-svn-authors svn://example.com/svnrepo --output authors-tranform.txt
```

Edit the output file if you want to make adjustments to the layout. Default layout is:
```
username = username <username>
```
Capability to inject the layout might be added in the future.


### Migrate the repository

**Usage**
```bash
  bin/svn2git migrate [-A|--authors-file="..."] [--remote="..."] source
```

**Arguments**
```
  source                Subversion repository to migrate.
```

**Options**
```
  --authors-file (-A)   Path to Subversion authors mapping.
  --remote              URL of Git remote repository to push to.
  --help (-h)           Display this help message.
  --quiet (-q)          Do not output any message.
  --verbose (-v|vv|vvv) Increase the verbosity of messages: 1 for normal output, 2 for more verbose output and 3 for debug.
  --version (-V)        Display this application version.
  --ansi                Force ANSI output.
  --no-ansi             Disable ANSI output.
  --no-interaction (-n) Do not ask any interactive question.
```

**Example**
```bash
$ bin/svn2git migrate svn://example.com/svnrepo -A authors-transform.txt --remote=git@github.com:user/remoterepo.git
```

To update the master or any added branch / tag just execute the migrate command again.
This might show some warnings and errors because of already existing branches and tags. You can ignore those.

A dedicated update command might be added in the future.
