# Import Pages From XML

This ProcessWire module allows you to import and parse XML files (using xpath) to create or update pages.

![Screencast](https://github.com/justonestep/processwire-importpagesxml/blob/master/screencast.gif)

## Settings

After successfull installation go to `Setup > Import Pages From XML` page to start using the XML Importer.

## Xpath Mappings

If you need a xpath mapping to be related to another field make sure that that field is placed before that one which needs the relation.
You can access and use any value / field which is ordered before.
Use `$field_<fieldname>` to match the desired value.

**Example**

```xml
<?xml version="1.0" encoding="UTF-8"?>

<songs>
    <song dateplayed="2011-07-24 19:40:26" track="2">
        <title artist_id="1">I left my heart on Europa</title>
    </song>
    <song dateplayed="2011-07-24 19:27:42" track="7">
        <title artist_id="2">Oh Ganymede</title>
    </song>
    <song dateplayed="2011-07-24 19:23:50" track="12">
        <title artist_id="3">Kallichore</title>
    </song>
    <contact id="1" name="Ship of Nomads" mail="info@test.org"/>
    <contact id="2" name="Beefachanga" mail="info@exam.ple"/>
    <contact id="3" name="Jewitt K. Sheppard" mail="info@some.com"/>
</songs>
```

* context: `\\song`
* fields: title, track, artist_id, artist_name, artist_mail
* **artist_id** must be placed before artist_name and artist_mail to be able to use that value
* `//contact[@id="$field_artist_id"]/@name` `//contact[@id="$field_artist_id"]/@mail`
