# What is this?
This script *helps* you to migrate bigger projects from hibernate hbm.xml files to annotations.
It is able to migrate most common annotations automatically, but not all, meaning the complicated part will be up to you to do be done manually.
It will just do the monkey work for you.

In our repository, we were able to automate about 90% of the work, but you may find that your codebase is different.
We still encountered a lot of manual work, but if your code already follows hibernate best practices, it should be easier.

The Imports will be addet but possibly not in the order as your IDE expects and a reformatting might be required.

# Who did this?
- Heiko Henning [@GreenRover](https://github.com/GreenRover)
- Marius Schär [@Martyschaer](https://gitlab.com/martyschaer)
- Christine Müller (no github account)

While migrating our own [SBB](https://github.com/SchweizerischeBundesbahnen) codebase form hbm to annotations.
The commit history has been squashed to hide sensitive information. 

# How to use this?
Check out the [wiki](https://github.com/SchweizerischeBundesbahnen/hibernate_hbm2annotation/wiki) for a detailed guide and best practices.

# Setup

Copy config.dist.php to config.php and put the path to your java project into it.

To run you needs to use php >= 7.2

**Important:** after a branch switch, `rm *.json` before running the script. This makes sure the script knows about every file.

# Usage

There are two commands `analyse.php` and `migrate.php`

`migrate.php` provides some cmd options. Please view code of this file to see them all.

## Examples

```bash
$ php migrate.php --printWriteStats --hbmFilter="Betriebspunkt\.hbm"
```

The `--hbmFilter` option accepts PHP-Regex syntax and applies it to the `.hbm.xml`-paths.

When using the `--hbmFilter` option be sure to run at least two files that are next to eachother in the hierarchy at the same time.
The generation of `@AttributeOverride`-annotations might otherwise not work properly.

```bash
$ php migrate.php --printWriteStats --collectUnsupportedAnnoationsFile="C:/devsbb/tmp/annotations_not.txt"
```

To add @Transient annotations to getters Hibernate should ignore
```bash
$ php migrate.php --addTransient
```

The Script will generate some TODOs where it knows it cannot complete the task automatically. Search for them after running the script by:
```bash
$ git diff --name-only | xargs grep -n -A1 "// TODO @HIB" | grep -vP "(:\d*:|--)"
```

## Manual preparatory work

### Move getId / setId and field to implementation class

If you hava any get / set in abstract classes, those needs to be moved, if you use a sequence generator.

https://vladmihalcea.com/how-to-combine-the-hibernate-assigned-generator-with-a-sequence-or-an-identity-column/

Also `public abstract getId()` is not allowed.

