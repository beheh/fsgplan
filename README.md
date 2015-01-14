# fsgplan

fsgplan is Facebook application to work in conjunction with the web output of the school lesson planning gp-Untis 2010-2012.

Users on Facebook can subscribe to specific courses and be notified when changes occur. It originally became obsolete when the planning software switched to an interactive web interface.

## Functionality

Every quarter of an hour a cronjob would call fsgapi/fsgapi-update.php which would call "fsgparse-update" and "fsgplan-update".

The fsgparse-script would call the web page with changes and parse it if it differed from the last run, extracting the changes, filtering, normalizing and cleaning the data.

Afterwards fsgplan-update-script would check if the fsgparse-script had run. It would then send notifications to all users on Facebook who were effected by one or more course changes.

When users would open the Facebook-application they would be presented with a week-view with all their changes as color-coded boxes per day.

The system would run without lots of administrative intervention for weeks, notifying the author

## Filesystem

The folders `api` and `fsgplan` were originally webfacing, offering an JSON- and the user-friendly-HTML interaction whereas `fsgapi` was outside of the webroot and called by a cronjob.

## Database

The file `fsgplan.sql` contains the original sql-scheme.

## Developer

The fsgplan system was developed and maintained by Benedict Etzel from 2010-2012. All the code is licensed under the ISC license, apart from the external libraries included in `fsgplan/fsgapi/system/libs`.
