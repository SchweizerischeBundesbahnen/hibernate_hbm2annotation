# What is is

This script may helps you to migrate bigger projects from hibernate hbm.xml files to annotations.
It is able to migrate the most common annotations automaticly. But not all.
The complicated part will be up to you to do be done manualy.
It will just do the monkey work for you.

The Imports will be addet but possibly not in the order as your ide expect and a reformating might be required.

# Setup

Copy config.dist.php to config.php and put the path to your java project into.

To run you needs to use php >= 7.2

**Important:** after a branch switch, `rm *.json` before running the script.

# Usage

There are 2 commands analyse.php and migrate.php

migrate.php provides some cmd options. Please view code of this file to see them.

## Example

```{php}
php migrate.php --printWriteStats --hbmFilter="Betriebspunkt\.hbm"
```

```{php}
php migrate.php --printWriteStats --collectUnsupportedAnnoationsFile="C:/devsbb/tmp/annotations_not.txt"
```

To add @Transient annotations to getters Hibernate should ignore
```{php}
php migrate.php --addTransient
```


## Manual preparatory work

### Move getId / setId and field to implementation class

If you hava any get / set in abstract classes, those needs to be moved, if you use a sequence generator.

https://vladmihalcea.com/how-to-combine-the-hibernate-assigned-generator-with-a-sequence-or-an-identity-column/

Also   public abstract getId()    is not allowed.

## Manual cleanup work

### `//TODO @HIBERNATE`
The script will generate `//TODO @HIBERNATE...` comments in the places where persistence annotations *should* go, but the script is unable to generate them.

