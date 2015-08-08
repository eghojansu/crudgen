# Crud Generator

> No documentation yet

This script was created for build web application structure that use [eghojansu/moe](http://github.com/eghojansu/moe "A Compact PHP Framework")

## Usage

- Download this repo as zip [eghojansu/crudgen](https://github.com/eghojansu/crudgen/archive/master.zip)
- Extract to any folder you want
- If you want use [eghojansu/moe](https://github.com/eghojansu/moe), you just need to edit moegen/config.ini
  (edit in database section)
- Then, CD to that folder via terminal, make sure moegen can be executed
- hit
	`./crudgen /path/to/project [/path/to/configuration_file.ini]`
- done

This tool was designed to be flexible to build crud application with any template.
You need to make your own Token Generator that extended from crudgen/adapter/AbstractToken and file structure (dot zip).
Then you need to define namespace lookup path in config.

## Apoligize
Please forgive my bad english. :D