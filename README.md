LpImportReferentiel
==================
This is a Moodle competency framework importer for XML formatted Jean Fruitet's Skills repository (**referentiel**) plugin.

See https://github.com/jfruitet/MoodleReferentielDocumentation/
to browse and download various competency frameworks. Not all metadata is retained when the data are imported - but as much as possible is.


Contact Jean FRUITET <jean.fruitet@free.fr> to have this plugin customised to your needs.

##Installation

Go to https://github.com/jfruitet/moodle-tool_lpimportreferentiel, master branch.

* Download the zip archive to the **./moodle/admin/tool/** directory.
* Unzip the archive
* Rename to **lpimportreferentiel**
* Logon as admin
* Go to ***Site administration> Notification***

Then install the plugin as usual.

##Usage

Download an XML file for the **Skills repository** plugin, then

* Go to ***Site administration>  Competencies>  Import a XML from referentiel plugin***
* Select a XML file
* Set up the scale 
* Click **Import**

## Nota Bene

* Importation fails if a Competency framework with **the same shortname** exists on the server (**This Competency Framework yet exists...** message).
* If a scale is included in the XML file, a new scale is created on the server and given to the newly imported Competency framework, except if a similar scale yet exists on the server (with the **same list of values**).
* The Skills repository taxonomy (**referentiel / domain / competency / item**) is not imported. So you have to configure the taxonomy of the newly imported Competency framework.
* When the importation of a XML fails, you get a **Persistent data** message. Setup the scale configuration before trying a new importation.

That's all folks!