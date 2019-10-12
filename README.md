# WARNING: This repository is no longer maintained :warning:

> This repository will not be updated. The repository will be kept available in read-only mode.

# Import Pages From XML

This ProcessWire module allows you to import and parse XML files (using xpath) to create or update pages.

![Screencast](https://github.com/justonestep/processwire-importpagesxml/blob/master/screencast.gif)

## Settings

After successfull installation go to `Setup > Import Pages From XML` to start using the XML Importer.

This module does not support all available field types. Nevertheless, I've refrained from restricting the supported field types because many of them should work by default.

Tested and working: 

* image including tag and description support
* select
* text
* textarea
* integer

The following field types will be ignored:

* FieldsetTabOpen
* FieldsetOpen
* FieldsetClose

Not working:

* file
* repeater

## Xpath Mappings

If you want to take advantage of references between fields in your xpath mapping then make sure the fields you're relating to are placed before the ones which need the relations.
You can access and use any values/fields that you placed earlier in your file.
Use `$field_<fieldname>` to match the desired value.

**Example**

```xml
<?xml version="1.0" encoding="UTF-8"?>

<songs>
    <song track="2">
        <title contact_id="1">Some song title</title>
    </song>
    <song track="7">
        <title contact_id="2">Just another song title</title>
    </song>
    <contact id="1" name="Sesmallbos" mail="info@test.org"/>
    <contact id="2" name="Sebigbos" mail="info@exam.ple"/>
</songs>
```

* context: `//song`
* field order: title, track, contact_id, contact_name, contact_mail
* `contact_id` must be placed before `contact_name` and `contact_mail`
* first get contact_id : `title/@contact_id`
* then use that value as relation : `//contact[@id="$field_artist_id"]/@name` as well as `//contact[@id="$field_artist_id"]/@mail`

## Further readings

* [Runs XPath query on XML data](http://php.net/manual/de/simplexmlelement.xpath.php)
* [XML and XPath](http://www.w3schools.com/xml/xml_xpath.asp)
