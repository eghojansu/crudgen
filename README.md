# Crud Generator

> No documentation yet

This script was created for build web application structure that use [eghojansu/moe](http://github.com/eghojansu/moe "A Compact PHP Framework")

## Usage

- Download this repo as zip [eghojansu/crudgen](https://github.com/eghojansu/crudgen/archive/master.zip)
- Extract to any folder you want
- If you want use [eghojansu/moe](https://github.com/eghojansu/moe), you just need to edit moegen/config.ini
  (edit in database section, of course other configuration can be overriden)
- Then, CD to that folder via terminal, make sure crudgen can be executed
- hit
	`./crudgen [/path/to/configuration_file.ini] [/path/to/project]`
  if you not pass /path/to/project you must define main.target in configuration_file
- done

This tool was designed to be flexible to build crud application with any template.
You need to make your own Token Generator that extended from crudgen/adapter/AbstractToken and file structure (dot zip).
Then you need to define namespace lookup path in config.

## Apoligize
Please forgive my bad english. :D